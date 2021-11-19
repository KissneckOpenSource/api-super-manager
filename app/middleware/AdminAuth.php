<?php
declare (strict_types=1);

namespace app\middleware;


use app\model\AdminAuthGroupAccess;
use app\model\AdminAuthRule;
use app\model\AdminMenu;
use app\model\AdminUser;
use app\util\ReturnCode;
use app\util\RouterTool;
use app\util\Tools;
use think\Response;

class AdminAuth {

    /**
     * ApiAuth鉴权
     * @param \think\facade\Request $request
     * @param \Closure $next
     * @return mixed|\think\response\Json
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function handle($request, \Closure $next): Response {
        $header = config('apiadmin.CROSS_DOMAIN');


        $ApiAuth = $request->header('Api-Auth', '');

        if ($ApiAuth) {
            $userInfo = cache('Login:' . $ApiAuth);

            if ($userInfo) {
                $userInfo = json_decode($userInfo, true);
            }
            if (!$userInfo || !isset($userInfo['id'])) {
                return json([
                    'code' => ReturnCode::AUTH_ERROR,
                    'msg'  => 'ApiAuth不匹配',
                    'data' => []
                ])->header($header);
            } else {
                $request->API_ADMIN_USER_INFO = $userInfo;
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



    /**
     * 获取用户权限数据
     * @param $uid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function getAccess(int $uid): array {
        $isSupper = Tools::isAdministrator($uid);
        if ($isSupper) {
            $access = (new AdminMenu())->select();
            $access = Tools::buildArrFromObj($access);

            return array_values(array_filter(array_column($access, 'url')));
        } else {
            $groups = (new AdminAuthGroupAccess())->where('uid', $uid)->find();
            if (isset($groups) && $groups->group_id) {
                $access = (new AdminAuthRule())->whereIn('group_id', $groups->group_id)->select();
                $access = Tools::buildArrFromObj($access);

                return array_values(array_unique(array_column($access, 'url')));
            } else {
                return [];
            }
        }
    }

    /**
     * 获取当前用户的允许菜单
     * @param int $uid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function getAccessMenuData(int $uid): array {
        $returnData = [];
        $isSupper = Tools::isAdministrator($uid);
        if ($isSupper) {
            $access = (new AdminMenu())->where('router', '<>', '')->select();
            $returnData = Tools::listToTree(Tools::buildArrFromObj($access));
        } else {
            $groups = (new AdminAuthGroupAccess())->where('uid', $uid)->find();
            if (isset($groups) && $groups->group_id) {
                $access = (new AdminAuthRule())->whereIn('group_id', $groups->group_id)->select();
                $access = array_unique(array_column(Tools::buildArrFromObj($access), 'url'));
                array_push($access, "");
                $menus = (new AdminMenu())->whereIn('url', $access)->where('show', 1)->select();
                $returnData = Tools::listToTree(Tools::buildArrFromObj($menus));
                RouterTool::buildVueRouter($returnData);
            }
        }

        return array_values($returnData);
    }

}
