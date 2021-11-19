<?php
declare (strict_types=1);
/**
 * 接口管理
 * @since   2018-02-11
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\controller\admin;

use app\model\AdminApp;
use app\model\AdminFields;
use app\model\AdminGroup;
use app\model\AdminList;
use app\util\ReturnCode;
use think\Exception;
use think\facade\Db;
use think\facade\Env;
use think\Response;
use think\captcha\facade\Captcha;
use app\util\DataType;

class InterfaceList extends Base {

    /**
     * 获取接口列表
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
        $group_hash = $this->request->get('group_hash','');
        $app_group_id = $this->request->get('app_group_id','');

        $obj = new AdminList();
        if (strlen($status)) {
            $obj = $obj->where('status', $status);
        }
        if ($type) {
            switch ($type) {
                case 1:
                    $obj = $obj->where('hash', $keywords);
                    break;
                case 2:
                    $obj = $obj->whereLike('info', "%{$keywords}%");
                    break;
                case 3:
                    $obj = $obj->whereLike('api_class', "%{$keywords}%");
                    break;
            }
        }
        if($group_hash){
            $obj = $obj->where('group_hash', $group_hash);
        }
        if($app_group_id){
            $obj = $obj->where('app_group_id', $app_group_id);
        }
        if($user['id']!=1){
            $obj = $obj->where('app_group_hash',"in", $user['app_hash']);
        }

        $listObj = $obj->order('id', 'DESC')->paginate(['page' => $start, 'list_rows' => $limit])->toArray();
        foreach ($listObj['data'] as &$op){
            $op['group_name'] = AdminGroup::where("hash",$op['group_hash'])->value("name");
            $op['app_name'] = AdminApp::where("id",$op['app_group_id'])->value("app_name");
        }
        return $this->buildSuccess([
            'list'  => $listObj['data'],
            'count' => $listObj['total']
        ]);
    }

    /**
     * 获取接口唯一标识
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function getHash(): Response {
        $res['hash'] = uniqid();

        return $this->buildSuccess($res);
    }

    //新增接口 获取选择应用的全部资源路由
    public function get_routes() :Response{
        $param = $this->request->param();
        if(!isset($param['group_hash']) || $param['group_hash'] == ''){
            return $this->buildFailed(ReturnCode::INVALID,'缺少group_hash！');
        }

        $re = AdminList::where('group_hash',$param['group_hash'])
            ->field('api_class,method')
            ->select()
            ->toArray();
        $re_data = [];
        if($re){
            foreach ($re as $v){
                $temp_arr = explode('/',$v['api_class']);
                $v['routes'] = ucwords(strtolower($temp_arr[0]));

                if(!isset($re_data[$v['routes']])){
                    $re_data[$v['routes']]['routes'] = $v['routes'];
                }

                $re_data[$v['routes']]['method'][] =$v['method'];
            }
        }

        $re_data = array_values($re_data);
        return $this->buildSuccess($re_data,'对应接口分组的全部资源路由名称');

    }

    /**
     * 新增接口
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function add(): Response {
        $postData = $this->request->post();
        \think\facade\Log::write("接受请求参数".print_r($postData,true));
        if (!preg_match("/^[A-Za-z0-9_\/:]+$/", $postData['api_class'])) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR, '真实类名只允许填写字母，数字和/');
        }

        if(!isset($postData['orgin_id'])){
            $postData['orgin_id'] = order_num();
        }

        if(!isset($postData['hash']) || !$postData['hash']){
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR, '生成hash失败，请检查是否存在网络！');
        }

        if(!isset($postData['router_type'])){
            $postData['router_type'] = 0;
        }


        //检查对应的接口是否在对应的应用里面已经创建，如果创建。不允许重复创建！
        $where = [];

        if($postData['router_type'] == 1){
            $temp_arr = explode('/',$postData['api_class']);
            if(count($temp_arr) > 1){
                return $this->buildFailed(ReturnCode::INVALID,'资源路由只用填写控制器名称！');
            }
            $where[] = 'router_type = 1';
        }
        $where[] = 'api_class = "'.$postData['api_class'].'"';
        $where[] = 'app_group_hash = "'.$postData['app_group_hash'].'"';

        $where_str = implode(' and ',$where);
        $re_admin_list = AdminList::whereRaw($where_str)->field('id')->find();
        if($re_admin_list){
            return $this->buildFailed(ReturnCode::INVALID,'接口分组已创建你设置的接口');
        }

        if(!isset($postData['api_type'])){
            $postData['api_type'] = 1;
        }

        // 启动事务

        Db::startTrans();
        try {
            $list_hash = [];
            if($postData['router_type'] == 1){

                $hash_arr[] =  $postData['hash'];
                $hash_arr[] =  uniqid();
                $hash_arr[] =  uniqid();
                $hash_arr[] =  uniqid();
                $hash_arr[] =  uniqid();
                $hash_arr[] =  uniqid();
                $hash_arr[] =  uniqid();

                $orgin_id_arr[] =$postData['orgin_id'];
                $orgin_id_arr[] = order_num();
                $orgin_id_arr[] = order_num();
                $orgin_id_arr[] = order_num();
                $orgin_id_arr[] = order_num();
                $orgin_id_arr[] = order_num();
                $orgin_id_arr[] = order_num();
                //根据用户设置的控制器名称生产对应的资源路由
                $postData['api_class'] = ucwords(strtolower($postData['api_class']));
                $where[] = 'router_type = 1';

                $api_class_router = [
                    [$postData['api_class'],1],
                    [$postData['api_class'],2],
                    [$postData['api_class'].'/:id',3],
                    [$postData['api_class'].'/:id',4],

                    [$postData['api_class'].'/:id',2],
                    [$postData['api_class'].'/:id/edit',2],
                    [$postData['api_class'].'/create',2]
                ];
                for ($i=0;$i<count($api_class_router);$i++){
                    $list_hash[] = $hash_arr[$i];
                    $postData_arr[] = [
                        'api_class'=>$api_class_router[$i][0],
                        'hash'=>$hash_arr[$i],
                        'access_token'=>$postData['access_token'],
                        'status'=>1,
                        'method'=>$api_class_router[$i][1],
                        'info'=> '资源路由'.$postData['info'],
                        'des'=>'资源路由'.$postData['des'],
                        'is_test'=>$postData['is_test'],
                        'return_str'=>null,
                        'group_hash'=>$postData['group_hash'],
                        'hash_type'=>$postData['hash_type'],
                        'app_group_id'=>$postData['app_group_id'],
                        'app_group_hash'=>$postData['app_group_hash'],
                        'api_type'=>$postData['api_type'],
                        'create_flag'=>0,
                        'orgin_id'=>$orgin_id_arr[$i],
                        'router_type'=>$postData['router_type'],
                    ];
                }
                $admin_list_obj = new AdminList();
                $re_save = $admin_list_obj->saveAll($postData_arr);
                $re_save_arr = $re_save->toArray();
                if(count($re_save_arr) != count($postData_arr)){
                    throw new Exception('创建资源路由失败！');
                }
            }else{
                $list_hash[] = $postData['hash'];
                $res = AdminList::create($postData);
                if ($res === false) {
                    throw new Exception('创建接口失败',-1);
                }
            }
            //增加功能  当用户创建新的接口 直接吧当前接口添加到对应的组里面,不需要在取应用接入-应用管理里面增加
            //1获取到对应的app
            if(isset($postData['app_orgin_id'])){
                $appInfo = Db::name('admin_app')
                    ->where('orgin_id',$postData['app_orgin_id'])
                    ->find();
            }else{
                $appInfo = Db::name('admin_app')
                    ->where('id',$postData['app_group_id'])
                    ->find();
                $postData['app_orgin_id'] = $appInfo['orgin_id'];
            }

            if(!$appInfo){
                throw new Exception('查询接口选择应用错误,请检查对应的应用是否创建!',-1);
            }

            $update_data = [];

            $temp_api_show = [];

            //向对应的应用里面增加对应接口
            if($appInfo['app_api_show']){
                $temp_api_show = json_decode($appInfo['app_api_show'],true);
            }

            foreach ($list_hash as $v){
                $temp_api_show[$postData['group_hash']][] = $v;
            }

            $update_data['app_api_show'] = json_encode($temp_api_show,JSON_UNESCAPED_UNICODE);

            $temp_app_api = [];
            if($appInfo['app_api']){
                $temp_app_api = explode(',',$appInfo['app_api']);

            }

            foreach ($list_hash as $v){
                $temp_app_api[] = $v;
            }

            $update_data['app_api'] = implode(',',$temp_app_api);

            $re_up_admin_app = Db::name('admin_app')
                ->where('id',$appInfo['id'])
                ->update($update_data);

            if(!$re_up_admin_app){
                throw new Exception('同步更新接口到应用中失败!',-1);
            }


            cache('AccessToken:Easy:' . $appInfo['app_secret'], null);
            if ($oldWiki = cache('WikiLogin:' . $postData['id'])) {
                cache('WikiLogin:' . $oldWiki, null);
            }

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
        }



        //并且把接口添加到对应的应用
        $msg = '操作完成';
        if(config('app.api_url') == config('app.sys_api_url')){
            $app_url = $appInfo['app_url'];
            if($app_url){  //顺带刷新对应app的数据
                $app_url_a = $app_url;
                try{
                    refresh_app($app_url."/Admin/InterfaceList/add",$postData,$app_url_a);
                }catch (Exception $e){
                    $msg = $e->getMessage();
                }

            }else{
                $msg = '没有找到推送项目url';
            }

        }

        return $this->buildSuccess([],$msg);
    }

    /**
     * 接口状态编辑
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function changeStatus(): Response {
        $hash = $this->request->get('hash');
        $status = $this->request->get('status');
        $res = AdminList::update([
            'status' => $status
        ], [
            'hash' => $hash
        ]);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }
        cache('ApiInfo:' . $hash, null);

        return $this->buildSuccess();
    }

    /**
     * 编辑接口
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function edit(): Response {
        $postData = $this->request->post();
        if (!preg_match("/^[A-Za-z0-9_\/]+$/", $postData['api_class'])) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR, '真实类名只允许填写字母，数字和/');
        }

        if(!isset($postData['orgin_id'])){
            $re_admin_list = Db::name('admin_list')
                ->where('id',$postData['id'])
                ->field('id,orgin_id')
                ->find();
            if(!$re_admin_list){
                return $this->buildFailed(ReturnCode::INVALID, '查询编辑的接口不存在！');
            }
            $postData['orgin_id'] = $re_admin_list['orgin_id'];
        }else{
            $re_admin_list = Db::name('admin_list')->where('orgin_id',$postData['orgin_id'])->field('id,orgin_id')->find();
            $postData['id'] = $re_admin_list['id'];
        }


        $res = AdminList::update($postData);
        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }
        cache('ApiInfo:' . $postData['hash'], null);
        $msg = '操作完成';
        if(config('app.api_url') == config('app.sys_api_url')){
            $app_url = AdminApp::where("id",$postData['app_group_id'])->value("app_url");
            if($app_url){  //顺带刷新对应app的数据
                $app_url_a = $app_url;
                try{
                    refresh_app($app_url."/Admin/InterfaceList/edit",$postData,$app_url_a);
                }catch (Exception $e){
                    $msg = $e->getMessage();
                }

            }else{
                $msg = '没有找到推送项目url';
            }

        }



        return $this->buildSuccess([],$msg);
    }

    /**
     * 删除接口
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

        $hashRule = (new AdminApp())->whereLike('app_api', "%$hash%")->select();
        if ($hashRule) {
            $oldInfo = (new AdminList())->where('hash', $hash)->find();
            foreach ($hashRule as $rule) {
                $appApiArr = explode(',', $rule->app_api);
                $appApiIndex = array_search($hash, $appApiArr);
                array_splice($appApiArr, $appApiIndex, 1);
                $rule->app_api = implode(',', $appApiArr);

                $appApiShowArrOld = json_decode($rule->app_api_show, true);
                $appApiShowArr = $appApiShowArrOld[$oldInfo->group_hash];

                $appApiShowIndex = array_search($hash, $appApiShowArr);
                array_splice($appApiShowArr, $appApiShowIndex, 1);
                $appApiShowArrOld[$oldInfo->groupHash] = $appApiShowArr;
                $rule->app_api_show = json_encode($appApiShowArrOld);

                $rule->save();
            }
        }

        AdminList::destroy(['hash' => $hash]);
        AdminFields::destroy(['hash' => $hash]);

        cache('ApiInfo:' . $hash, null);

        $msg = '操作完成';

        if(config('app.api_url') == config('app.sys_api_url')){
            $app_url = AdminApp::where("id",$oldInfo->app_group_id)->value("app_url");
            if($app_url){  //顺带刷新对应app的数据
                $app_url_a = $app_url;
                try{
                    refresh_app($app_url."/Admin/InterfaceList/del?hash=".$hash,[],$app_url_a);
                }catch (Exception $e){
                    $msg = $e->getMessage();
                }

            }else{
                $msg = '没有找到推送项目url';
            }
        }



        return $this->buildSuccess([],$msg);
    }

    /**
     * 刷新接口路由
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function refresh(): Response {

        $app_group_hash = $this->request->get('app_group_hash');

        if(!$app_group_hash){
            return $this->buildFailed(ReturnCode::INVALID,'请选择刷新的应用分组！');
        }

        $re_admin_app = AdminApp::where('app_group',$app_group_hash)->field('app_url')->find();

        if(!$re_admin_app){
            return $this->buildFailed(ReturnCode::INVALID,'选择刷新的应用分组,对应的应用查询错误！');
        }

        if(config('app.api_url') != config('app.sys_api_url')){
            //执行目标服务器的刷新路由
            //todo 刷新的路由 应该是刷新每个项目副本的api路由文件 但是现在是刷星全部路由文件
            $rootPath = root_path();
            $apiRoutePath = $rootPath . 'route/apiRoute.php';
            $tplPath = $rootPath . 'install/apiRoute.tpl';
            $methodArr = ['*', 'POST', 'GET','PUT','DELETE'];

            $tplOriginStr = file_get_contents($tplPath);

            $sql = "SELECT *,".

                    "IF (router_type = 1,SUBSTR(api_class,1,".
                        "IF (INSTR(api_class, '/') = 0,".
                        "IF (INSTR(api_class, '/') = 0,LENGTH(api_class),INSTR(api_class, '/')),".
                    "IF (INSTR(api_class, '/') = 0,LENGTH(api_class),INSTR(api_class, '/')) - 1)),api_class) api_class_bak ".
                    "FROM admin_list where app_group_hash in ('".$app_group_hash."') GROUP BY api_class_bak order by router_type ASC";

            $listInfo = Db::query($sql);

            $tplStr = [];
            $rule_str = '\')->middleware([app\middleware\ApiAuth::class, app\middleware\ApiPermission::class, app\middleware\RequestFilter::class, app\middleware\ApiLog::class]);';
            foreach ($listInfo as $value) {
                if ($value['hash_type'] === 1) {
                    if ($value['router_type'] == 0){
                        array_push(
                            $tplStr,
                            'Route::rule(\'' . addslashes($value['api_class']) . '\',\'api.' .
                            addslashes($value['api_class']) . '\', \'' . $methodArr[$value['method']] . $rule_str);
                    }else{
                        /**
                         * 资源路由
                         * 3,PUT  4,DELETE
                         */
                        array_push($tplStr,
                            'Route::resource(\'' . addslashes($value['api_class_bak']) . '\',\'api.'
                            . addslashes($value['api_class_bak']) . $rule_str);
                    }
                }else {
                    array_push($tplStr, 'Route::rule(\'' . addslashes($value['hash']) . '\',\'api.' . addslashes($value['api_class']) . '\', \'' . $methodArr[$value['method']] . '\')->middleware([app\middleware\ApiAuth::class, app\middleware\ApiPermission::class, app\middleware\RequestFilter::class, app\middleware\ApiLog::class]);');
                }
            }
            $tplOriginStr = str_replace(['{$API_RULE}'], [implode(PHP_EOL . '    ', $tplStr)], $tplOriginStr);

            file_put_contents($apiRoutePath, $tplOriginStr);
        }

        $msg = '操作完成';
        if(config('app.api_url') == config('app.sys_api_url')){
            $app_url = $re_admin_app->app_url;
            if($app_url){  //顺带刷新对应app的数据
                $app_url_a = $app_url;
                try{

                    refresh_app($app_url."admin/InterfaceList/refresh?app_group_hash=".$app_group_hash,[],$app_url_a);
                }catch (Exception $e){
                    $msg = $e->getMessage();
                }

            }else{
                $msg = '没有找到推送项目url';
            }

        }


        return $this->buildSuccess();
    }

    /**
     * 创建方法
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function createFunc() {

        $param = $this->request->param();

        //type 操作类型  1增加api文件夹下面的方法  2增加wapi下面的文件夹
        if(isset($param['type']) && !empty($param['type'])){
            if(!in_array($param['type'],[1,2])){
                return $this->buildFailed(ReturnCode::PARAM_INVALID,'请检查参数！');
            }
            $type = $param['type'];
        }else{
            $type = 1;
        }

        if(!isset($param['api_class']) || empty($param['api_class'])){
            return $this->buildFailed(ReturnCode::PARAM_INVALID,'请检查参数api_class！');
        }
        if(!isset($param['method']) || empty($param['method']) || (int)$param['method'] <= 0 ){
            return $this->buildFailed(ReturnCode::PARAM_INVALID,'请检查参数method！');
        }


        $listInfo = Db::name('admin_list')->alias('al')->leftJoin('admin_app aa','aa.id=al.app_group_id')
            ->where('al.status',1)
            ->where('al.api_class',$param['api_class'])->where('al.method',$param['method'])
            ->field('al.api_class,al.info,al.id,al.create_flag')
            ->field('aa.app_url')
            ->find();

        if(!$listInfo){
            return $this->buildFailed(ReturnCode::PARAM_INVALID,'请检查选择创建的接口是否已经新增！');
        }

        $rootPath = root_path();

        $tplStr = [];

        $func_info = explode('/',$listInfo['api_class']);
        if($type == 1){
            $apiRoutePath = $rootPath . 'app/controller/api/' . ucwords($func_info[0]).'.php';
        }else{
            $apiRoutePath = $rootPath . 'app/controller/wapi/' . ucwords($func_info[0]).'.php';
        }
        Db::startTrans();
        try{
            if($listInfo['create_flag'] === 0){
                $re_up = Db::name('admin_list')->where('id',$listInfo['id'])->update(['create_flag'=>1]);
                if(!$re_up){
                    throw new Exception('创建文件失败',ReturnCode::INVALID);
                }
            }else{
                //已创建代码则不创建
                return $this->buildFailed(ReturnCode::INVALID,'代码已创建');
            }
            //业务平台不创建文件
            if(config('app.api_url') !== config('app.sys_api_url')){
                if(!file_exists($apiRoutePath)){
                    if($type == 1){
                        //不存在文件创建文件
                        $tplPath = $rootPath . 'install/apiFunc.tpl';
                        $tplOriginStr = file_get_contents($tplPath);

                        $tpl_param = ['api',ucwords($func_info[0]),$listInfo['info'],lcfirst($func_info[1])];


                    }else{
                        //先创建wapi的基本base
                        $apiBasePath = $rootPath . 'app/controller/wapi/Basw.php';
                        if(!file_exists($apiBasePath)){
                            $tpBaselPath = $rootPath . 'install/wapiBase.tpl';
                            $tplBaseOriginStr = file_get_contents($tpBaselPath);
                            $re_base_file = file_put_contents($apiBasePath,$tplBaseOriginStr);
                        }
                        $tplPath = $rootPath . 'install/apiFunc.tpl';
                        $tplOriginStr = file_get_contents($tplPath);
                        //创建wapi类文件参数
                        $tpl_param = ['wapi',ucwords($func_info[0]),$listInfo['info'],lcfirst($func_info[1])];
                    }

                    $tplOriginStr = str_replace(['{$APP_NAME}','{$CLASS_NAME}','{$CLASS_INFO}','{$FUNC_NAME}'],
                        $tpl_param, $tplOriginStr);

                    $re_file = file_put_contents($apiRoutePath,$tplOriginStr);

                    if($re_file === false){
                        throw new Exception('创建文件失败',ReturnCode::INVALID);
                    }
                }
                else{
                    $tplOriginStr = "\t/**".PHP_EOL.
                        "\t *".$listInfo['info'].PHP_EOL.
                        "\t */".PHP_EOL.
                        "\tpublic function ".ucwords($func_info[1])."(){".PHP_EOL.
                        "\t\t".'$this->request->paraam();'. PHP_EOL.
                        "\t\t//返回错误". PHP_EOL.
                        "\t\t".'//return $this->buildFailed(ReturnCode::INVALID,"错误说明");'. PHP_EOL.
                        "\t\t//返回成功". PHP_EOL.
                        "\t\t".'//return $this->buildSuccess([], "成功说明");'. PHP_EOL.
                        PHP_EOL.PHP_EOL.

                        "\t}".PHP_EOL;

                    $this->insertCode($apiRoutePath,$tplOriginStr);


                }
            }


            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            return $this->buildFailed(ReturnCode::INVALID,'创建文件失败！');
        }

        $msg = '创建完成！';
//        if(config('app.verify_host') != $listInfo['app_url']){
        //随便向远程服务器发送创建文件的请求
        try{
            $app_url_a = $listInfo['app_url'];
            refresh_app($listInfo['app_url']."/Admin/InterfaceList/createFunc",$param,$app_url_a);
        }catch (Exception $e){
            $msg .= "【发执行请求错误".$e->getMessage()."】";
        }
//        }
        //file_put_contents($apiRoutePath, $tplOriginStr);
        printLog("创建文件1：",$func_info,"createFile");
        printLog("创建文件2：",[$rootPath.'app/controller/api/' . ucwords($func_info[0]).'.php'],"createFile");

        return $this->buildSuccess([],$msg);
    }

    //获取应用分组
    public function group(): Response {
        $user = $this->userInfo;
        if($user['id']==1){
            $group = AdminApp::where("app_status",1)
                ->field("id,app_name,app_group,app_url")
                ->select()->toArray();
        }else{
            $group = AdminApp::where("app_status",1)
                ->field("id,app_name,app_group,app_url")
                ->where("app_group","in",$user['app_hash'])
                ->select()
                ->toArray();
        }

        return $this->buildSuccess($group);
    }

    /****
     **PHP在文件指定行数后面插入内容
     **参数$file.原始文件名
     **参数$line,在第几行插入内容
     **参数$txt,要插入的内容
     **
     * @param $file
     * @param $line
     * @param $txt
     * @return bool
     */
    private function insertCode($file,$txt){
        if(!$fileContent = @file($file)){
            $data['msg']='htaccess:文件不存在';
            $data['status']='error';
            throw new \Exception('请检查文件是否创建！');

        }
        $lines = count($fileContent);
        $line = $lines-2;
        printLog("创建文件3：",[$lines],"createFile");

        $fileContent[$line].=$txt;

        $newContent = implode('',$fileContent);
        if(!file_put_contents($file,$newContent)){

            $data['msg']='htaccess:无法写入数据';
            $data['status']='error';
            throw new \Exception($data['msg']);

        }
        printLog("创建文件4：",[$newContent],"createFile");

        return true;
    }


    /**
     * 生成文档
     */
    public function createDoc(){
        $curl_type = [
          0 => '不限',
          1 => 'POST',
          2 => 'GET',
          3 => 'PUT',
          4 => 'DELETE'
        ];
        $is_no = [
            0 => '否',
            1 => '是'
        ];
        $dataType = [
            1 => 'INTEGER',
            2 => 'STRING',
            3 => 'ARRAY',
            4 => 'FLOAT',
            5 => 'BOOLEAN',
            6 => 'FILE',
            7 => 'ENUM',
            8 => 'MOBILE',
            9 => 'OBJECT'
        ];

        $params = $this->request->param();
        $inter_info = Db::name('admin_list')->where('id',$params['id'])->find();
        $request_data = Db::name('admin_fields')->where('hash',$inter_info['hash'])->where('type',0)
            ->field('field_name,is_must,info,data_type')->select()->toArray();
        $response_data = Db::name('admin_fields')->where('hash',$inter_info['hash'])->where('type',1)
            ->field('field_name,show_name,data_type,info')->select()->toArray();
        $inter_info['request_data'] = $request_data;
        $inter_info['response_data'] = $response_data;

        //组装md文件数据
        $doc_content = '>**简要描述：**'.PHP_EOL.PHP_EOL.'- '.$inter_info['info'].PHP_EOL.PHP_EOL
        .'>**请求URL：**'.PHP_EOL.PHP_EOL.'- `http://xx.com/api/'.$inter_info['api_class'].'`'.PHP_EOL.PHP_EOL
        .'>**请求方式：**'.PHP_EOL.PHP_EOL.'- '.$curl_type[$inter_info['method']].PHP_EOL.PHP_EOL
        .'>**参数：**'.PHP_EOL.PHP_EOL.' |参数名|必选|类型|说明|'.PHP_EOL.'|:----    |:---|:----- |-----   |'.PHP_EOL;
        $request_string = '';
        foreach ($request_data as $key=>$val){
            $request_string .= '|'.$val['field_name'].'|'.$is_no[$val['is_must']].'|'.$dataType[$val['data_type']].'|'.$val['info'].'|'.PHP_EOL;
        }
        $return_str = json_encode([]);
        if ($inter_info['return_str'])
            $return_str = json_encode(json_decode($inter_info['return_str'],true),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        $doc_content .= $request_string.'>**返回示例**'.PHP_EOL.PHP_EOL.'```'.PHP_EOL.$return_str.PHP_EOL.'```'.PHP_EOL.PHP_EOL
        .'>**返回参数说明**'.PHP_EOL.PHP_EOL.'|参数名|类型|说明|'.PHP_EOL.'|:-----  |:-----|-----  |'.PHP_EOL;
        $response_content = '';
        foreach ($response_data as $key => $val){
            $request_string .= '|'.$val['field_name'].'|'.$dataType[$val['data_type']].'|'.$val['info'].'|'.PHP_EOL;
        }
        $doc_content .= $response_content.'>**备注** '.PHP_EOL.PHP_EOL.'- 更多返回错误代码请看首页的错误代码描述';
        //组装文档地址
        $rootPath = root_path();
        $docPath = $rootPath . 'public/interfaceDoc/'.$inter_info['info'].'_'.$inter_info['hash'].'.md';
        $re_file = file_put_contents($docPath,$doc_content);
        if(!$re_file)
            return $this->buildFailed(-1,'保存失败');
        Db::name('admin_list')->where('id',$params['id'])->update(['md_path'=>$docPath]);

        return $this->buildSuccess([$docPath]);
    }
}
