<?php
declare (strict_types=1);

namespace app\middleware;

use app\model\AdminApp;
use app\model\AdminList;
use app\util\ReturnCode;
use think\facade\Cache;
use think\Model;
use think\Request;

class ApiAuth {

    /**
     * 获取接口基本配置参数，校验接口Hash是否合法，校验APP_ID是否合法等
     * @param Request $request
     * @param \Closure $next
     * @return mixed|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function handle($request, \Closure $next) {
        $header = config('apiadmin.CROSS_DOMAIN');
        $apiHash = substr($request->pathinfo(), 4);
        $temp_method = $request->method();
        switch (strtoupper($temp_method)){
            case 'GET':
                $temp_str = 2;
                break;
            case 'POST':
                $temp_str = 1;
                break;
            case 'PUT':
                $temp_str = 3;
                break;
            case 'DELETE':
                $temp_str = 4;
                break;
            default:
                return json([
                    'code' => -1,
                    'msg'  => 'A请求方式无法识别！',
                    'data' => []
                ])->header($header);
        }
        if ($apiHash) {
            $cached = Cache::has('ApiInfo:' . $apiHash.$temp_method);
            if ($cached) {
                $apiInfo = Cache::get('ApiInfo:' . $apiHash.$temp_method);
            } else {
                $apiInfo = (new AdminList())->where('hash', $apiHash)->where('hash_type', 2)
                    ->whereRaw('method=0 or method ='.$temp_str)->find();
                if ($apiInfo) {
                    $apiInfo = $apiInfo->toArray();
                    Cache::delete('ApiInfo:' . $apiInfo['api_class'].$temp_method);
                    Cache::set('ApiInfo:' . $apiHash.$temp_method, $apiInfo);
                } else {
                    $apiInfo = (new AdminList())
                        ->where('api_class', $apiHash)
                        ->where('hash_type', 1)
                        ->whereRaw('method=0 or method ='.$temp_str)->find();
                    if ($apiInfo) {
                        $apiInfo = $apiInfo->toArray();
                        Cache::delete('ApiInfo:' . $apiInfo['hash'].$temp_method);
                        Cache::set('ApiInfo:' . $apiHash.$temp_method, $apiInfo);
                    } else {
                        return json([
                            'code' => ReturnCode::DB_READ_ERROR,
                            'msg'  => '获取接口配置数据失败',
                            'data' => []
                        ])->header($header);
                    }
                }
            }

            $accessToken = $request->header('Access-Token', '');
            if (!$accessToken) {
                if ($apiInfo['method'] == 2) {
                    $accessToken = $request->get('Access-Token', '');
                }
                if ($apiInfo['method'] == 1) {
                    $accessToken = $request->post('Access-Token', '');
                }
            }
            if (!$accessToken) {
                return json([
                    'code' => ReturnCode::AUTH_ERROR,
                    'msg'  => '缺少必要参数Access-Token',
                    'data' => []
                ])->header($header);
            }
            if ($apiInfo['access_token']) {
                $appInfo = $this->doEasyCheck($accessToken);
            } else {
                $appInfo = $this->doEasyCheck($accessToken);
            }
            if ($appInfo === false) {
                return json([
                    'code' => ReturnCode::ACCESS_TOKEN_TIMEOUT,
                    'msg'  => 'Access-Token已过期',
                    'data' => []
                ])->header($header);
            }

            $request->APP_CONF_DETAIL = $appInfo;
            $request->API_CONF_DETAIL = $apiInfo;

            return $next($request);
        } else {
            return json([
                'code' => ReturnCode::AUTH_ERROR,
                'msg'  => '缺少接口Hash',
                'data' => []
            ])->header($header);
        }
    }

    /**
     * 简易鉴权，更具APP_SECRET获取应用信息
     * @param $accessToken
     * @return array|false|mixed|object|\think\App
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    private function doEasyCheck($accessToken) {

        $appInfo = cache('AccessToken:Easy:' . $accessToken);
        $this->errorLog("token检查",$appInfo);
        if (!$appInfo) {
            $appInfo = (new AdminApp())->where('app_secret', $accessToken)->find();
            $this->errorLog("token检查2",$appInfo);
            if (!$appInfo) {
                return false;
            } else {
                $appInfo = $appInfo->toArray();
                $this->errorLog("token检查3",$appInfo);
                cache('AccessToken:Easy:' . $accessToken, $appInfo);
            }
        }

        return $appInfo;
    }

    private function errorLog($msg,$ret)
    {
        $rootPath = root_path();
        file_put_contents($rootPath . 'runtime/aaa.log', "[" . date('Y-m-d H:i:s') . "] ".$msg."," .json_encode($ret).PHP_EOL, FILE_APPEND);

    }

    /**
     * 复杂鉴权，需要先通过接口获取AccessToken
     * @param $accessToken
     * @return bool|mixed
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    private function doCheck($accessToken) {
        $appInfo = cache('AccessToken:' . $accessToken);
        $this->errorLog("token检查4",$appInfo);
        if (!$appInfo) {
            return false;
        } else {
            return $appInfo;
        }
    }
}
