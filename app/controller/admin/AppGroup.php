<?php
declare (strict_types=1);
/**
 *
 * @since   2018-02-11
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\controller\admin;

use app\model\AdminApp;
use app\model\AdminAppGroup;
use app\model\AdminUser;
use app\util\ReturnCode;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;
use think\Response;
use think\facade\Log;
use think\facade\Request;

class AppGroup extends Base {

    /**
     * 获取应用组列表
     * @return \think\Response
     * @throws \think\db\exception\DbException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function index(): Response {
        $limit = $this->request->get('size', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $start = $this->request->get('page', 1);
        $keywords = $this->request->get('keywords', '');
        $type = $this->request->get('type', '');
        $status = $this->request->get('status', '');
        $user = $this->userInfo;
        $obj = new AdminAppGroup();
        $obj = $obj->where("hash","in",$user['app_hash']);

        if (strlen($status)) {
            $obj = $obj->where('status', $status);
        }
        if ($type) {
            switch ($type) {
                case 1:
                    if (strlen($keywords)) {
                        $obj = $obj->where('hash', $keywords);
                    }
                    break;
                case 2:
                    $obj = $obj->whereLike('name', "%{$keywords}%");
                    break;
            }
        }
        $listObj = $obj->paginate(['page' => $start, 'list_rows' => $limit])->toArray();

        return $this->buildSuccess([
            'list'  => $listObj['data'],
            'count' => $listObj['total']
        ]);
    }

    /**
     * 获取全部有效的应用组
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function getAll(): Response {
        $user = $this->userInfo;

        $msg = '完成';
        if($user['id']==1){
            $temp_group = Cache::get('group_getAll');
            if($temp_group){
                $listInfo = $temp_group;
                $msg .= ' 缓存';
            }else{
                $listInfo = (new AdminAppGroup())
                    ->where(['status' => 1])
                    ->select()
                    ->toArray();
                Cache::tag('group_data')->set('group_getAll',$listInfo,$this->group_data_time);
                $msg .= ' 实时';
            }

        }else{
            $temp_group = Cache::get('group_getAll'.$user['app_hash']);
            if($temp_group){
                $listInfo = $temp_group;
                $msg .= ' 缓存';
            }else{
                $listInfo = (new AdminAppGroup())->where(['status' => 1])
                    ->where("hash","in",$user['app_hash'])
                    ->select()->toArray();
                Cache::tag('group_data')
                    ->set('group_getAll'.$user['app_hash'],$listInfo,$this->group_data_time);
                $msg .= ' 实时';
            }

        }

        return $this->buildSuccess([
            'list' => $listInfo
        ],$msg);
    }

    /**
     * 应用组状态编辑
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function changeStatus(): Response {
        $id = $this->request->get('id');
        $status = $this->request->get('status');
        $res = AdminAppGroup::update([
            'id'     => $id,
            'status' => $status
        ]);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }
        Cache::tag('group_data')->clear();
        return $this->buildSuccess();
    }

    /**
     * 添加应用组
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function add(): Response {
        $user = $this->userInfo;
        $postData = $this->request->post();

        if(!isset($postDate['orgin_id'])){
            $postDate['orgin_id'] = order_num();
        }
        $res = AdminAppGroup::create($postData);

        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }

        //新增的同时给当前账号叠加权限
        if($user['app_hash']){
            $hash = $user['app_hash'].",".$res->hash;
        }else{
            $hash = $res->hash;
        }
        //更新用户信息
        $user['app_hash'] = $hash;
        $ApiAuth = Request::header('Api-Auth', '');
        Cache::set('Login:'.$ApiAuth,json_encode($user));
        AdminUser::where("id",$user['id'])->update(['app_hash'=>$hash]);
        $msg = '操作成功';
        Cache::tag('group_data')->clear();
        Cache::delete('group_getAll');
        return $this->buildSuccess($res->toArray(),$msg);
    }

    /**
     * 应用组编辑
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function edit(): Response {
        $postData = $this->request->post();
        $res = AdminAppGroup::update($postData);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }
        Cache::tag('group_data')->clear();
        return $this->buildSuccess();
    }

    /**
     * 应用组删除
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function del(): Response {
        $hash = $this->request->get('hash');
        if (!$hash) {
            return $this->buildFailed(ReturnCode::EMPTY_PARAMS, '缺少必要参数');
        }

        $has = (new AdminApp())->where(['app_group' => $hash])->count();
        if ($has) {
            return $this->buildFailed(ReturnCode::EMPTY_PARAMS, '当前分组存在' . $has . '个应用，禁止删除');
        }
        AdminAppGroup::destroy(['hash' => $hash]);


        Cache::tag('group_data')->clear();


        return $this->buildSuccess();
    }
}
