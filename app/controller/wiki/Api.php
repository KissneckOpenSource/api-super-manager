<?php
declare (strict_types=1);
/**
 * @since   2019-08-11
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\controller\wiki;

use app\model\AdminApp;
use app\model\AdminFields;
use app\model\AdminGroup;
use app\model\AdminList;
use app\model\AdminSaasFields;
use app\model\AdminSaasGroup;
use app\model\AdminSaasList;
use app\util\DataType;
use app\util\ReturnCode;
use app\util\Tools;
use think\Response;
use think\facade\Db;

class Api extends Base {

    public function errorCode(): Response {
        $codeArr = ReturnCode::getConstants();
        $codeArr = array_flip($codeArr);
        $result = [];
        $errorInfo = [
            ReturnCode::SUCCESS              => '请求成功',
            ReturnCode::INVALID              => '非法操作',
            ReturnCode::DB_SAVE_ERROR        => '数据存储失败',
            ReturnCode::DB_READ_ERROR        => '数据读取失败',
            ReturnCode::CACHE_SAVE_ERROR     => '缓存存储失败',
            ReturnCode::CACHE_READ_ERROR     => '缓存读取失败',
            ReturnCode::FILE_SAVE_ERROR      => '文件读取失败',
            ReturnCode::LOGIN_ERROR          => '登录失败',
            ReturnCode::NOT_EXISTS           => '不存在',
            ReturnCode::JSON_PARSE_FAIL      => 'JSON数据格式错误',
            ReturnCode::TYPE_ERROR           => '类型错误',
            ReturnCode::NUMBER_MATCH_ERROR   => '数字匹配失败',
            ReturnCode::EMPTY_PARAMS         => '丢失必要数据',
            ReturnCode::DATA_EXISTS          => '数据已经存在',
            ReturnCode::AUTH_ERROR           => '权限认证失败',
            ReturnCode::OTHER_LOGIN          => '别的终端登录',
            ReturnCode::VERSION_INVALID      => 'API版本非法',
            ReturnCode::CURL_ERROR           => 'CURL操作异常',
            ReturnCode::RECORD_NOT_FOUND     => '记录未找到',
            ReturnCode::DELETE_FAILED        => '删除失败',
            ReturnCode::ADD_FAILED           => '添加记录失败',
            ReturnCode::UPDATE_FAILED        => '更新记录失败',
            ReturnCode::PARAM_INVALID        => '数据类型非法',
            ReturnCode::ACCESS_TOKEN_TIMEOUT => '身份令牌过期',
            ReturnCode::SESSION_TIMEOUT      => 'SESSION过期',
            ReturnCode::UNKNOWN              => '未知错误',
            ReturnCode::EXCEPTION            => '系统异常',
        ];

        foreach ($errorInfo as $key => $value) {
            $result[] = [
                'en_code' => $codeArr[$key],
                'code'    => $key,
                'chinese' => $value,
            ];
        }

        return $this->buildSuccess([
            'data' => $result,
            'co'   => config('apiadmin.APP_NAME') . ' ' . config('apiadmin.APP_VERSION')
        ]);
    }

    public function login(): Response {
        $appId = $this->request->post('username');
        $appSecret = $this->request->post('password');

        $appInfo = (new AdminApp())->where('app_id', $appId)->where('app_secret', $appSecret)->find();
        if (!empty($appInfo)) {
            if ($appInfo->app_status) {
                //保存用户信息和登录凭证
                $appInfo = $appInfo->toArray();
                $appInfo['app_hash'] = $appInfo['app_group'];
                $apiAuth = md5(uniqid() . time());
                cache('WikiLogin:' . $apiAuth, $appInfo, config('apiadmin.ONLINE_TIME'));
                cache('WikiLogin:' . $appInfo['id'], $apiAuth, config('apiadmin.ONLINE_TIME'));
                $appInfo['apiAuth'] = $apiAuth;

                return $this->buildSuccess($appInfo, '登录成功');
            } else {
                return $this->buildFailed(ReturnCode::LOGIN_ERROR, '当前应用已被封禁，请联系管理员');
            }
        } else {
            return $this->buildFailed(ReturnCode::LOGIN_ERROR, 'AppId或AppSecret错误');
        }
    }

    public function groupList(): Response {
        //todo 在这里可以拿到用户的信息，为什么还要查询全部group 这样对于指定用户查看指定应用会浪费很大的性能。代码注释
//        $groupInfo = (new AdminGroup())->select();
//        $apiInfo = (new AdminList())->select();

        //todo 在这里可以拿到用户的信息，为什么还要查询全部group 这样对于指定用户查看指定应用会浪费很大的性能。
        //代码优化思想 根据获取用户的app_hash来查询对应的admin_list 和admin_group
        $u_app_hash = $this->appInfo['app_hash'];
        $params = $this->request->param();
        //查询用户获取的所有分组
        $condition = [];
        if(isset($params['app_group_id']) && $params['app_group_id']!=null && $params['app_group_id'] != '')
            $condition[] = ['app_group_hash','=',$params['app_group_id']];
        if(isset($params['interface_name']) && $params['interface_name']!=null && $params['interface_name'] != '')
            $condition[] = ['info','like','%'.$params['interface_name'].'%'];
        if(isset($params['api_class']) && $params['api_class']!=null && $params['api_class'] != '')
            $condition[] = ['api_class','like','%'.$params['api_class'].'%'];
        
        $groupInfo = (new AdminGroup())->where('app_hash','in',$u_app_hash)->select();

        if(!$groupInfo){
            return $this->buildFailed(ReturnCode::INVALID,'用户没有设置接口分组！');
        }

        $groupInfo_arr = $groupInfo->toArray();

        $groupInfo_arr = array_column($groupInfo_arr,'hash');

        $apiInfo = (new AdminList())->where('group_hash','in',$groupInfo_arr)->where($condition)->select();
        printLog(Db::getLastSql(),[],'ceshi1');

        $listInfo = [];
        if ($this->appInfo['app_id'] === -1) {

            $_apiInfo = [];
            foreach ($apiInfo as $aVal) {
                $_apiInfo[$aVal['group_hash']][] = $aVal;
            }
            foreach ($groupInfo as $gVal) {
                if (isset($_apiInfo[$gVal['hash']])) {
                    $gVal['api_info'] = $_apiInfo[$gVal['hash']];
                    $listInfo[] = $gVal;
                }
            }
        } else {
            //将查询的二维对象转换成二维数组
            $apiInfo = Tools::buildArrFromObj($apiInfo, 'hash');

            //将查询的二维对象转换成二维数组
            $groupInfo = Tools::buildArrFromObj($groupInfo, 'hash');
            //获取用户需要显示的api
            $app_api_show = json_decode($this->appInfo['app_api_show'], true);
            foreach ($app_api_show as $key => $item) {

                if(isset($groupInfo[$key])){
                    $_listInfo = $groupInfo[$key];
                    foreach ($item as $apiItem) {
                        if(isset($apiInfo[$apiItem])){
                            $_listInfo['api_info'][] = $apiInfo[$apiItem];
                        }

                    }
                    if (isset($_listInfo['api_info'])) {
                        $listInfo[] = $_listInfo;
                    }
                }

            }
        }

        return $this->buildSuccess([
            'data' => $listInfo,
            'appinfo' =>$this->appInfo,
            'groupInfo' =>$groupInfo,
            "apiinfo"=>$apiInfo,
            'co'   => config('apiadmin.APP_NAME') . ' ' . config('apiadmin.APP_VERSION')
        ]);
    }

    public function detail(): Response {
        $hash = $this->request->get('hash');
        if (!$hash) {
            return $this->buildFailed(ReturnCode::NOT_EXISTS, '缺少必要参数');
        }

        $apiList = (new AdminList())->whereIn('hash', $hash)->find();
        if (!$apiList) {
            return $this->buildFailed(ReturnCode::NOT_EXISTS, '接口hash非法');
        }
        $request = (new AdminFields())->where('hash', $hash)->where('type', 0)->select();
        $response = (new AdminFields())->where('hash', $hash)->where('type', 1)->select();
        $dataType = array(
            DataType::TYPE_INTEGER => 'Integer',
            DataType::TYPE_STRING  => 'String',
            DataType::TYPE_BOOLEAN => 'Boolean',
            DataType::TYPE_ENUM    => 'Enum',
            DataType::TYPE_FLOAT   => 'Float',
            DataType::TYPE_FILE    => 'File',
            DataType::TYPE_ARRAY   => 'Array',
            DataType::TYPE_OBJECT  => 'Object',
            DataType::TYPE_MOBILE  => 'Mobile'
        );

        $groupInfo = (new AdminGroup())->where('hash', $apiList['group_hash'])->find();
        $groupInfo->hot = $groupInfo->hot + 1;
        $groupInfo->save();

        //查询应用域名
        $app_info = Db::name('admin_app')->where('id',$apiList['app_group_id'])->find();
        $app_url = '';
        if ($app_info)
            $app_url = $app_info['app_url'];
        $route_index = '';
        $end_index = '';
        if ($apiList['hash_type'] === 1) {
//            $url = $this->request->domain() . '/api/' . $apiList['api_class'];
            $route_arr = explode('/',$apiList['api_class']);
            $end_route = end($route_arr);
            if ($end_route == ':id'){
                $route_index = ':id';
                array_pop($route_arr);
                $url = $app_url. 'api/' . implode('/',$route_arr).'/';
            } elseif ($end_route == 'edit' && $apiList['method'] == 2){
                $route_index = ':id';
                $end_index = '/edit';
                array_pop($route_arr);
                array_pop($route_arr);
                $url = $app_url. 'api/' . implode('/',$route_arr).'/';
            }else{
                $url = $app_url. 'api/' . $apiList['api_class'];
            }

        } else {
//            $url = $this->request->domain() . '/api/' . $hash;
            $url = $app_url . '/api/' . $hash;
        }


        $api_calss_arr = explode('/',$apiList['api_class']);

        $code_php = root_path().'app/controller/api/'.ucwords($api_calss_arr[0]).'.php';

        $code_data = file_get_contents($code_php);
//        $code_data = '';

        return $this->buildSuccess([
            'request'  => $request,
            'response' => $response,
            'dataType' => $dataType,
            'apiList'  => $apiList,
            'url'      => $url,
            'route_index' => $route_index,
            'end_index' => $end_index,
            'co'       => config('apiadmin.APP_NAME') . ' ' . config('apiadmin.APP_VERSION'),
            'code_php'=>$code_data
        ]);
    }

    //saas文档接口
    public function saasGroupList(): Response {

        $groupInfo = AdminSaasGroup::select();

        $groupInfo_arr = $groupInfo->toArray();

        $groupInfo_arr = array_column($groupInfo_arr,'hash');

        $apiInfo = (new AdminSaasList())->where('group_hash','in',$groupInfo_arr)->select();


        $listInfo = [];


        $_apiInfo = [];
        foreach ($apiInfo as $aVal) {
            $_apiInfo[$aVal['group_hash']][] = $aVal;
        }
        foreach ($groupInfo as $gVal) {
            if (isset($_apiInfo[$gVal['hash']])) {
                $gVal['api_info'] = $_apiInfo[$gVal['hash']];
            }
            $listInfo[] = $gVal;
        }


        return $this->buildSuccess([
            'data' => $listInfo,
//            'appinfo' =>$this->appInfo,
            'groupInfo' =>$groupInfo,
            "apiinfo"=>$apiInfo,
            'co'   =>  'kissneck SAAS 接口文档 ' . config('apiadmin.APP_VERSION')
        ]);
    }

    //saas文档接口
    public function saasDetail(): Response {
        $hash = $this->request->get('hash');
        if (!$hash) {
            return $this->buildFailed(ReturnCode::NOT_EXISTS, '缺少必要参数');
        }

        $apiList = (new AdminSaasList())->whereIn('hash', $hash)->find();
        if (!$apiList) {
            return $this->buildFailed(ReturnCode::NOT_EXISTS, '接口hash非法');
        }


        $request = [];
        $response = [];
        $re_data = (new AdminSaasFields())->where('hash', $hash)->select();
        if($re_data){
            foreach ($re_data as $v){
                if($v->type == 0){
                    $request[] =  $v;
                }else{
                    $response[] = $v;
                }
            }
        }
        $dataType = array(
            DataType::TYPE_INTEGER => 'Integer',
            DataType::TYPE_STRING  => 'String',
            DataType::TYPE_BOOLEAN => 'Boolean',
            DataType::TYPE_ENUM    => 'Enum',
            DataType::TYPE_FLOAT   => 'Float',
            DataType::TYPE_FILE    => 'File',
            DataType::TYPE_ARRAY   => 'Array',
            DataType::TYPE_OBJECT  => 'Object',
            DataType::TYPE_MOBILE  => 'Mobile'
        );

        $groupInfo = (new AdminSaasGroup())->where('hash', $apiList['group_hash'])->find();
        $groupInfo->hot = $groupInfo->hot + 1;
        $groupInfo->save();

        if ($apiList['hash_type'] === 1) {
            $url = $this->request->domain() . '/api/' . $apiList['api_class'];
        } else {
            $url = $this->request->domain() . '/api/' . $hash;
        }


        $api_calss_arr = explode('/',$apiList['api_class']);

        $code_data = '';

        return $this->buildSuccess([
            'request'  => $request,
            'response' => $response,
            'dataType' => $dataType,
            'apiList'  => $apiList,
            'url'      => $url,
            'co'       => 'kissneck SAAS 接口文档 ' . config('apiadmin.APP_VERSION'),
            'code_php'=>$code_data
        ]);
    }

    public function logout(): Response {
        $ApiAuth = $this->request->header('ApiAuth');
        cache('WikiLogin:' . $ApiAuth, null);
        cache('WikiLogin:' . $this->appInfo['id'], null);

        $oldAdmin = cache('Login:' . $ApiAuth);
        if ($oldAdmin) {
            $oldAdmin = json_decode($oldAdmin, true);
            cache('Login:' . $ApiAuth, null);
            cache('Login:' . $oldAdmin['id'], null);
        }

        return $this->buildSuccess([], '登出成功');
    }

    /**
     * 获取文档菜单,树状
     */
    public function getDocMenu(){
        $params = $this->request->param();
        $u_app_hash = $this->appInfo['app_hash'];

        //获取权限内所有接口组
        $app_group = Db::name('admin_app_group')->whereIn('hash',$u_app_hash)->select()->toArray();
        //获取接口组下的应用
        $app_arr = Db::name('admin_app')->whereIn('app_group',$u_app_hash)->select()->toArray();
        //获取接口列表
        $inter_arr = Db::name('admin_list')->whereIn('app_group_hash',$u_app_hash)->select()->toArray();

        /**
         * 组装树状数据
         */
        $app_list_arr = [];
        foreach ($inter_arr as $key=>$val){
            foreach ($app_arr as $k=>$v){
                if ($val['app_group_id'] == $v['id']){
                    if (!isset($app_list_arr[$v['id']]))
                        $app_list_arr[$v['id']] = ['id'=>$v['id'],'name'=>$v['app_name'],'app_group'=>$v['app_group']];
                    $app_list_arr[$v['id']]['children'][] = ['id'=>$val['id'],'name'=>$val['info']];
                }
            }
        }
        $app_list_arr = array_values($app_list_arr);

        $list = [];
        foreach ($app_list_arr as $key=>$val){
            foreach ($app_group as $k=>$v){
                if ($val['app_group'] == $v['hash']){
                    if (!isset($list[$v['id']]))
                        $list[$v['id']] = ['id'=>$v['id'],'name'=>$v['name']];
                    $list[$v['id']]['children'][] = $val;
                }
            }
        }
        $list = array_values($list);
        return $this->buildSuccess($list);
    }

    /**
     * 获取接口文档
     */
    public function getDetail(){
        $params = $this->request->param();
        $inter_info = Db::name('admin_list')->where('id',$params['id'])->find();
        return $this->buildSuccess($inter_info);
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
        $doc_content = '# '.$inter_info['info'].' #'.PHP_EOL.'---'.PHP_EOL.PHP_EOL
            .'### 简要描述： ###'.PHP_EOL.PHP_EOL.'- '.$inter_info['info'].PHP_EOL.PHP_EOL
            .'### 请求URL： ###'.PHP_EOL.PHP_EOL.'- `http://xx.com/api/'.$inter_info['api_class'].'`'.PHP_EOL.PHP_EOL
            .'### 请求方式： ###'.PHP_EOL.PHP_EOL.'- '.$curl_type[$inter_info['method']].PHP_EOL.PHP_EOL
            .'### 参数： ###'.PHP_EOL.PHP_EOL.' |参数名|必选|类型|说明|'.PHP_EOL.'|:----    |:---|:----- |-----   |'.PHP_EOL;
        $request_string = '';
        foreach ($request_data as $key=>$val){
            $request_string .= '|'.$val['field_name'].'|'.$is_no[$val['is_must']].'|'.$dataType[$val['data_type']].'|'.$val['info'].'|'.PHP_EOL;
        }
        $return_str = json_encode([]);
        if ($inter_info['return_str'])
            $return_str = json_encode(json_decode($inter_info['return_str'],true),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        $doc_content .= $request_string.'>### 返回示例 ###'.PHP_EOL.PHP_EOL.'```'.PHP_EOL.$return_str.PHP_EOL.'```'.PHP_EOL.PHP_EOL
            .'### 返回参数说明 ###'.PHP_EOL.PHP_EOL.'|参数名|类型|说明|'.PHP_EOL.'|:-----  |:-----|-----  |'.PHP_EOL;
        $response_content = '';
        foreach ($response_data as $key => $val){
            $response_content .= '|'.$val['field_name'].'|'.$dataType[$val['data_type']].'|'.$val['info'].'|'.PHP_EOL;
        }
        $doc_content .= $response_content.'>### 备注 ### '.PHP_EOL.PHP_EOL.'- 更多返回错误代码请看首页的错误代码描述';

        return $this->buildSuccess([$doc_content]);
    }
}
