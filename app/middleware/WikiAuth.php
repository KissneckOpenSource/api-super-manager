<?php
declare (strict_types=1);

namespace app\middleware;

use app\util\ReturnCode;

class WikiAuth {

    /**
     * ApiAuth鉴权
     * @param \think\facade\Request $request
     * @param \Closure $next
     * @return mixed|\think\response\Json
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function handle($request, \Closure $next) {
        $header = config('apiadmin.CROSS_DOMAIN');
        $ApiAuth = $request->header('Api-Auth', '');
        if ($ApiAuth) {
            $userInfo = cache('Login:' . $ApiAuth);
            if (!$userInfo) {
                $userInfo = cache('WikiLogin:' . $ApiAuth);
            } else {
                $userInfo = json_decode($userInfo, true);
                //由于在通过接口管理-接口维护查看接口文档，如果登录的用户的app_id设置了-1就是查看全部，这样是设置错误的
                //todo 需要判断用户是否是root,如果是root才需要显示.存在问题，如果设置了一个其他名称和root有一样的权限
                $userInfo['app_id'] = -1;
//                $userInfo['app_id'] = 1;
            }
            if (!$userInfo || !isset($userInfo['id'])) {
                return json([
                    'code' => ReturnCode::AUTH_ERROR,
                    'msg'  => 'ApiAuth不匹配',
                    'data' => []
                ])->header($header);
            } else {
                $request->API_WIKI_USER_INFO = $userInfo;
            }

            return $next($request);
        } else {
            return json([
                'code' => ReturnCode::AUTH_ERROR,
                'msg'  => '缺少ApiAuth',
                'data' => []
            ])->header($header);
        }
    }
}
