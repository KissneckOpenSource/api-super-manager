<?php
declare (strict_types=1);
/**
 * 应用管理
 * @since   2018-02-11
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\controller\admin;

use app\model\AdminApp;
use app\model\AdminAppGroup;
use app\model\AdminList;
use app\model\AdminGroup;
use app\util\ReturnCode;
use app\util\Strs;
use app\util\Tools;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\Response;

class App extends Base {


    private $file_copy_path;
    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->file_copy_path = root_path().'public';
    }

    /**
     * 获取应用列表
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
        $app_group = $this->request->get('app_group','');

        $obj = new AdminApp();
        if (strlen($status)) {
            $obj = $obj->where('app_status', $status);
        }
        if ($type) {
            switch ($type) {
                case 1:
                    $obj = $obj->where('app_id', $keywords);
                    break;
                case 2:
                    $obj = $obj->whereLike('app_name', "%{$keywords}%");
                    break;
            }
        }
        if($app_group){
            $obj = $obj->where('app_group', $app_group);
        }else{
            if($user['id']!=1){
                $obj = $obj->where('app_group',"in", $user['app_hash']);
            }
        }
        $listObj = $obj->order('app_add_time', 'DESC')->paginate(['page' => $start, 'list_rows' => $limit])->toArray();
        //后续可优化，现在数据不多暂时循环查询

        foreach ($listObj['data'] as &$op){
            $op['group_name'] = AdminAppGroup::where("hash",$op['app_group'])->value("name");
        }
        return $this->buildSuccess([
            'list'  => $listObj['data'],
            'count' => $listObj['total']
        ]);
    }

    /**
     * 获取AppId,AppSecret,接口列表,应用接口权限细节
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function getAppInfo(): Response {
        $user = $this->userInfo;
        $apiArr = (new AdminList())->where("app_group_hash","in",$user['app_hash'])->select();
        foreach ($apiArr as $api) {
            $res['apiList'][$api['group_hash']][] = $api;
        }
        $groupArr = (new AdminGroup())->where("app_hash","in",$user['app_hash'])->select();
        $groupArr = Tools::buildArrFromObj($groupArr);
        $res['groupInfo'] = array_column($groupArr, 'name', 'hash');
        $id = $this->request->get('id', 0);
        if ($id) {
            $appInfo = (new AdminApp())->where('id', $id)->find()->toArray();
            $res['app_detail'] = json_decode($appInfo['app_api_show'], true);
        } else {
            $res['app_id'] = mt_rand(1, 9) . Strs::randString(7, 1);
            $res['app_secret'] = Strs::randString(32);
        }

        return $this->buildSuccess($res);
    }

    /**
     * 刷新APPSecret
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function refreshAppSecret(): Response {
        $data['app_secret'] = Strs::randString(32);

        return $this->buildSuccess($data);
    }

    /**
     * 新增应用
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     * 新增应用 需要为应用创建副本项目文件夹  并且克隆git仓库
     */
    public function add(): Response {

        $postData = $this->request->post();

        //需要拉取的对应控制器的文件方法

        printLog('应用测试参数:'.json_encode($postData),[],'postdata');
        if($postData['app_url']){
            //如果url的地址缺少结束的反斜杠 则返回前端
            $end_str = substr($postData['app_url'],-1);
            if($end_str != '/'){
                return $this->buildFailed(ReturnCode::INVALID,'应用域名请使用 / 结尾!');
            }
        }

        if($postData['app_group'] == 'default'){
            return $this->buildFailed(ReturnCode::INVALID,'请选择应用分组');
        }

        $data = [
            'id'           => $postData['id'],
            'app_id'       => $postData['app_id'],
            'app_secret'   => $postData['app_secret'],
            'app_name'     => $postData['app_name'],
            'app_info'     => $postData['app_info'],
            'app_group'    => $postData['app_group'],
            'app_add_time' => time(),
            'app_api'      => '',
            'app_api_show' => '',
            'app_url' => $postData['app_url'],
        ];

        if(isset($postData['orgin_id'])){
            $data['orgin_id'] = $postData['orgin_id'];
        }else{
            $data['orgin_id'] = order_num();
        }



        if(isset($postData['git_path'])){
            $data['app_git_path'] = $postData['git_path'];
        }else{
            return $this->buildFailed(ReturnCode::INVALID,'请填写git 克隆的仓库SSH地址!');
        }
        $git_out = [];
        try{
            if (isset($postData['app_api']) && $postData['app_api']) {
                $appApi = [];
                if(is_array($postData['app_api'])){
                    $data['app_api_show'] = json_encode($postData['app_api']);
                }else{
                    $data['app_api_show'] = $postData['app_api'];
                    $postData['app_api'] = json_decode($postData['app_api'],true);
                }


                foreach ($postData['app_api'] as $value) {
                    $appApi = array_merge($appApi, $value);
                }
                $data['app_api'] = implode(',', $appApi);

            }

            if(config('app.is_update') == 2){
                $git_filename_arr = explode("/",$postData['git_path']);
                if(count($git_filename_arr) <= 1){
                    throw new Exception('创建副本文件夹失败',ReturnCode::INVALID);
                }

                $git_filename = end($git_filename_arr);

                $git_name_arr = explode('.git',$git_filename);
                $copy_files_path = $this->file_copy_path.'/myProject/'.$postData['app_id'].'/'.$git_name_arr[0];
                $data['copy_path'] = $copy_files_path;
            }

            Db::startTrans();
            try {
                $res = AdminApp::create($data);
                //看看是否有组信息，若有则添加应用组
                if (isset($postData['group_info']) && $postData['group_info']){
                    $group_arr = json_decode($postData['group_info'],true);
                    //判断分组是否已创建
                    if (!Db::name('admin_app_group')->where('id',$group_arr['id'])->find()){
                        //创建分组
                        Db::name('admin_app_group')->insert($group_arr);
                    }
                }else{
                    $group_arr = Db::name('admin_app_group')->where('hash',$postData['app_group'])->find();
                    if ($group_arr)
                        $postData['group_info'] = json_encode($group_arr);
                }
                if ($res === false) {
                    Db::rollBack();
                    throw new Exception('创建应用失败',ReturnCode::DB_SAVE_ERROR);
                }
            }catch (\Exception $e){
                Db::rollBack();
                throw new Exception('创建应用失败:'.$e->getMessage(),ReturnCode::DB_SAVE_ERROR);
            }
            Db::commit();

            //todo 创建应用和调用副本项目创建应用因该分开。创建应用是向应用的仓库提交应用代码，但是开发的服务器还没在部署项目应用
            $app_url_a = $postData['app_url'];

            $out_arr = [];
            Log::write('***  is_update'.config('app.is_update'));
            if(config('app.is_update') == 1){
                if(config('app.api_url') == config('app.sys_api_url')){
                    if($postData['app_url']){  //顺带刷新对应app的数据
                        $postData['id'] = $res['id'];

                        refresh_app($postData['app_url']."/Admin/App/add",$postData,$app_url_a);
                    }
                }
            }
            elseif(config('app.is_update') == 2){
                Log::write('***  git_path'.$postData['git_path']);
                if(isset($postData['git_path'])){
                    $app_url = rtrim($app_url_a,'/');

                    $app_api_url =  rtrim(config('app.api_url'),'/');
                    Log::write('*** app_url'.$app_url);
                    Log::write('***  app_api_url'.$app_api_url);
                    if($app_url != $app_api_url){
                        Log::write('*** 444');
                        //项目名称

                        $copy_files_path1 = $this->file_copy_path.'/myProject';
                        if(!file_exists($copy_files_path1)){
                            mkdir($this->file_copy_path.'/myProject', 0775, true);
                        }

                        $copy_files_path = $copy_files_path1.'/'.$postData['app_id'];
                        //创建副本文件夹
                        if (!file_exists($copy_files_path)) {
                            mkdir($copy_files_path, 0775, true);
                        }

                        //副本文件夹地址
                        $project_path = $copy_files_path;
                        Log::write('克隆项目文件地址'.$project_path);
                        $out_arr = send_exec(1,$project_path,$postData['git_path']);
                        $git_out[] = "克隆仓库".PHP_EOL.implode(PHP_EOL,$out_arr);

                    }
                }

            }

        }catch (Exception $e){
            return $this->buildFailed($e->getCode(),$e->getMessage());
        }
        $msg = '操作成功！';
        try{
            if(config('app.is_update') == 2) {
                if (isset($postData['git_path'])) {
                    //解析git克隆地址获取创建的文件夹
                    $git_filename_arr = explode("/",$postData['git_path']);
                    if(count($git_filename_arr) <= 1){
                        throw new Exception('创建副本文件夹失败',ReturnCode::INVALID);
                    }

                    $git_filename = end($git_filename_arr);

                    $git_name_arr = explode('.git',$git_filename);

                    if(count($git_name_arr) <= 1){
                        throw new Exception('创建副本文件夹失败',ReturnCode::INVALID);
                    }

                    $target_dir = $data['copy_path'];


                    //测试排除文件和选择写入的文件
                    $witer_file = ['Base.php','Miss.php'];

                    //获取用户选择的添加接口 并写入文件
                    $re_admin_list = Db::table('admin_list')
                        ->where('hash','in',$data['app_api'])
                        ->field('api_class')
                        ->select()->toArray();
                    if($re_admin_list){
                        foreach ($re_admin_list as $v){
                            $temp_class_arr = explode('/',$v['api_class']);
                            if(count($temp_class_arr) > 1){
                                $witer_file[] = $temp_class_arr[0].'.php';
                            }
                        }
                    }

                    copy_files(root_path(),$target_dir,0755,$witer_file);

                    $out_arr_2 = send_exec(4,$target_dir);


                    $git_out[] = "推送项目原始代码".PHP_EOL.implode(PHP_EOL,$out_arr_2);
                }
            }
        }catch (Exception $e){
            $msg = $e->getMessage();
        }

        Cache::delete('app_get_all');

        return $this->buildSuccess(['git'=>$git_out],$msg);
    }

    /**
     * 应用状态编辑
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function changeStatus(): Response {
        $id = $this->request->get('id');
        $status = $this->request->get('status');
        $res = AdminApp::update([
            'id'         => $id,
            'app_status' => $status
        ]);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }
        $appInfo = (new AdminApp())->where('id', $id)->find();
        cache('AccessToken:Easy:' . $appInfo['app_secret'], null);
        if ($oldWiki = cache('WikiLogin:' . $id)) {
            cache('WikiLogin:' . $oldWiki, null);
        }
        Cache::delete('app_get_all');
        return $this->buildSuccess();
    }

    /**
     * 编辑应用
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function edit(): Response {
        $postData = $this->request->post();
        $data = [
            'app_secret'   => $postData['app_secret'],
            'app_name'     => $postData['app_name'],
            'app_info'     => $postData['app_info'],
            'app_group'    => $postData['app_group'],
            'app_api'      => '',
            'app_api_show' => '',
            'app_url' => $postData['app_url'],
        ];
        if(isset($postData['orgin_id'])){
            $re_admin_app =  Db::name('admin_app')
                ->where('orgin_id',$postData['orgin_id'])
                ->field('id,orgin_id,app_api,app_git_path,copy_path')
                ->find();
        }else{
            $re_admin_app =  Db::name('admin_app')
                ->where('id',$postData['id'])
                ->field('id,orgin_id,app_api,app_git_path,copy_path')
                ->find();
        }

        if(!$re_admin_app){
            return $this->buildFailed(ReturnCode::INVALID,'查询应用错误，请选择已创建的应用！');
        }

        $postData['id'] = $re_admin_app['id'];

        $postData['orgin_id'] = $re_admin_app['orgin_id'];

        //修改思路  如果更新失败，不向项目服务器推送消息
        if($postData['app_url']){  //顺带刷新对应app的数据
            $app_url_a = $postData['app_url'];
            \think\facade\Log::write('app_url_a='.$app_url_a);
            refresh_app(str_replace('"','', $postData['app_url'])."/Admin/App/edit",$postData,$app_url_a);
        }

        if (isset($postData['app_api']) && $postData['app_api']) {
            $appApi = [];
            if(is_array($postData['app_api'])){
                $data['app_api_show'] = json_encode($postData['app_api']);
            }else{
                $data['app_api_show'] = $postData['app_api'];
                $postData['app_api'] = json_decode($postData['app_api'],true);
            }
            foreach ($postData['app_api'] as $value) {
                $appApi = array_merge($appApi, $value);
            }
            $data['app_api'] = implode(',', $appApi);
        }
        $res = AdminApp::update($data, ['id' => $postData['id']]);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }
        $appInfo = (new AdminApp())->where('id', $postData['id'])->find();
        cache('AccessToken:Easy:' . $appInfo['app_secret'], null);
        if ($oldWiki = cache('WikiLogin:' . $postData['id'])) {
            cache('WikiLogin:' . $oldWiki, null);
        }
        Cache::delete('app_get_all');
        return $this->buildSuccess();

    }

    /**
     * 删除应用
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function del(): Response {
        $id = $this->request->get('id');
        if (!$id) {
            return $this->buildFailed(ReturnCode::EMPTY_PARAMS, '缺少必要参数');
        }
        $appInfo = (new AdminApp())->where('id', $id)->find();
        if($appInfo->app_url){
            //顺带刷新对应app的数据
            $app_url_a = $appInfo->app_url;
            refresh_app($appInfo->app_url."/Admin/App/del?id=".$id,[],$app_url_a);
        }
        cache('AccessToken:Easy:' . $appInfo['app_secret'], null);

        AdminApp::destroy($id);
        if ($oldWiki = cache('WikiLogin:' . $id)) {
            cache('WikiLogin:' . $oldWiki, null);
        }

        //todo 强行删除全部应用的缓存
        Cache::delete('app_get_all');

        return $this->buildSuccess();
    }

    //获取全部应用
    public function getAll(){
        $cache_temp = Cache::get('app_get_all');
        $msg = '完成 ';
        if($cache_temp){
            $re = $cache_temp;
            $msg .= '缓存获取';
        }else{
            $re = AdminApp::where('app_status',1)->field('app_id,app_name')->select()->toArray();
            if($re){
                Cache::set('app_get_all',$re);
            }
            $msg .= '实时获取';
        }

        return $this->buildSuccess($re,$msg);
    }

}
