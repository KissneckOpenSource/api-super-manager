<?php
declare (strict_types=1);
/**
 * 接口组维护
 * @since   2018-02-11
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\controller\admin;

use app\model\AdminApp;
use app\model\AdminAppGroup;
use app\model\AdminGroup;
use app\model\AdminList;
use app\util\ReturnCode;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;
use think\Response;

class InterfaceGroup extends Base {

    /**
     * 获取接口组列表
     * @return Response
     * @throws \think\db\exception\DbException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function index(): Response {
        $user = $this->userInfo;
        $limit = $this->request->get('size', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $start = $this->request->get('page', 1);
        $keywords = $this->request->get('keywords', '');
        $type = $this->request->get('type', '');
        $status = $this->request->get('status', '');
        $app_hash = $this->request->get('app_hash', '');

        $obj = new AdminGroup();
        if (strlen($status)) {
            $obj = $obj->where('status', $status);
        }
        if ($type) {
            switch ($type) {
                case 1:
                    $obj = $obj->where('hash', $keywords);
                    break;
                case 2:
                    $obj = $obj->whereLike('name', "%{$keywords}%");
                    break;
            }
        }
        if($app_hash){
            $obj = $obj->where('app_hash', $app_hash);
        }
        if($user['id']!=1){
            $obj = $obj->where('app_hash',"in", $user['app_hash']);
        }
        $listObj = $obj->order('create_time', 'desc')->paginate(['page' => $start, 'list_rows' => $limit])->toArray();
        foreach ($listObj['data'] as &$op){
            $op['group_name'] = AdminAppGroup::where("hash",$op['app_hash'])->value("name");
        }
        return $this->buildSuccess([
            'list'  => $listObj['data'],
            'count' => $listObj['total']
        ]);
    }

    /**
     * 获取全部有效的接口组
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function getAll(): Response {
        $user = $this->userInfo;

        $temp_group = Cache::get('list_getAll'.$user['app_hash']);
        if($temp_group){
            $listInfo = $temp_group;
        }else{
            $listInfo = (new AdminGroup())
                ->where(['status' => 1])
                ->where("app_hash","in",$user['app_hash'])
                ->select()->toArray();
            Cache::tag('list_group_data')->set('list_getAll'.$user['app_hash'],$listInfo,$this->group_data_time);
        }


        return $this->buildSuccess([
            'list' => $listInfo
        ]);
    }

    /**
     * 接口组状态编辑
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function changeStatus(): Response {
        $id = $this->request->get('id');
        $status = $this->request->get('status');
        $res = AdminGroup::update([
            'id'     => $id,
            'status' => $status,
        ]);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }

        return $this->buildSuccess();
    }

    /**
     * 添加接口组
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function add(): Response {
        $postData = $this->request->post();

        if(!isset($postData['hash'])){
            return $this->buildFailed(ReturnCode::INVALID,'对不起，没有hash!');
        }

        $re_admin_app = AdminApp::where('app_group',$postData['app_hash'])
            ->field('app_url,orgin_id')->find();

        if(!$re_admin_app){
            return $this->buildFailed(ReturnCode::INVALID,'对不起，查询对应的app_group没有找到对应的应用');
        }

        $re_admin_group = AdminGroup::where('name',$postData['name'])
            ->where('app_hash',$postData['app_hash'])
            ->field('id')->find();

        if($re_admin_group){
            return $this->buildFailed(ReturnCode::INVALID,'对不起，对应应用的接口分组已创建同名接口分组！');
        }



        $res = AdminGroup::create($postData);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }

        //推送远程
        $msg = '操作完成';
        if(config('app.api_url') == config('app.sys_api_url')){
            $app_url = $re_admin_app['app_url'];
            if($app_url){  //顺带刷新对应app的数据
                $app_url_a = $app_url;
                try{
                    refresh_app($app_url."/Admin/InterfaceGroup/add",$postData,$app_url_a);
                }catch (Exception $e){
                    $msg .= PHP_EOL.'推送错误：'.$e->getMessage();
                }

            }else{
                $msg = PHP_EOL.'推送错误：没有找到推送项目url';
            }
        }
        $res_arr = $res->toArray();
        Cache::tag('list_group_data')->clear();
        return $this->buildSuccess($res_arr,$msg);
    }

    /**
     * 接口组编辑
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function edit(): Response {
        $postData = $this->request->post();

        if(!isset($postData['hash'])){
            return $this->buildFailed(ReturnCode::INVALID,'对不起，没有hash!');
        }

        $re_admin_app = AdminApp::where('app_group',$postData['app_hash'])
            ->field('app_url,orgin_id')->find();

        if(!$re_admin_app){
            return $this->buildFailed(ReturnCode::INVALID,'对不起，查询对应的app_group没有找到对应的应用');
        }

        $re_admin_group = AdminGroup::where('name',$postData['name'])
            ->where('hash','<>',$postData['hash'])
            ->where('app_hash',$postData['app_hash'])
            ->field('id')->find();

        if($re_admin_group){
            return $this->buildFailed(ReturnCode::INVALID,'对不起，对应应用的接口分组已创建同名接口分组！');
        }




        $res = AdminGroup::update($postData);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }

        //推送远程
        $msg = '操作完成';
        if(config('app.api_url') == config('app.sys_api_url')){
            $app_url = $re_admin_app['app_url'];
            if($app_url){  //顺带刷新对应app的数据
                $app_url_a = $app_url;
                try{
                    refresh_app($app_url."/Admin/InterfaceGroup/edit",$postData,$app_url_a);
                }catch (Exception $e){
                    $msg .= PHP_EOL.'推送错误：'.$e->getMessage();
                }

            }else{
                $msg = PHP_EOL.'推送错误：没有找到推送项目url';
            }
        }

        Cache::tag('list_group_data')->clear();
        return $this->buildSuccess([],$msg);
    }

    /**
     * 接口组删除
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function del(): Response {
        $hash = $this->request->get('hash');
        if (!$hash) {
            return $this->buildFailed(ReturnCode::EMPTY_PARAMS, '缺少必要参数');
        }
        if ($hash === 'default') {
            return $this->buildFailed(ReturnCode::INVALID, '系统预留关键数据，禁止删除！');
        }

        AdminList::update(['group_hash' => 'default'], ['group_hash' => $hash]);
        $hashRule = (new AdminApp())->whereLike('app_api_show', "%$hash%")->select();
        if ($hashRule) {
            foreach ($hashRule as $rule) {
                $appApiShowArr = json_decode($rule->app_api_show, true);
                if (!empty($appApiShowArr[$hash])) {
                    if (isset($appApiShowArr['default'])) {
                        $appApiShowArr['default'] = array_merge($appApiShowArr['default'], $appApiShowArr[$hash]);
                    } else {
                        $appApiShowArr['default'] = $appApiShowArr[$hash];
                    }
                }
                unset($appApiShowArr[$hash]);
                $rule->app_api_show = json_encode($appApiShowArr);
                $rule->save();
            }
        }
        Cache::tag('list_group_data')->clear();
        AdminGroup::destroy(['hash' => $hash]);

        return $this->buildSuccess();
    }
}
