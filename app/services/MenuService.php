<?php
declare (strict_types = 1);

namespace app\services;

use think\facade\Db;
use app\util\Tools;
use think\db\exception\DbException;
use think\facade\Cache;

class MenuService extends BaseServices
{
    /**
     * 获取菜单列表(树状)
     */
    public function index($params){

        if (isset($params['id']) && $params['id']!=null && $params['id']!=''){
            $list = Db::name('y_menu')->where('id',$params['id'])->select()->toArray();
        }else{
            $all_list = Db::name('y_menu')->where('delete_time is null')
                ->order('sort','DESC')->order('create_time','DESC')->select()->toArray();
            //处理层级数据
            $list = Tools::recursionTree($all_list,'id','pid');
            if (isset($params['menu_name']) && $params['menu_name']!=null && $params['menu_name']!=''){
                $list = nameSearchTree($list,$params['menu_name'],0,'menu_name',1);//条件筛选
                for ($i=0;$i<10;$i++){
                    $list = delEmpty($list);//清除空值
                }
            }
            if (isset($params['status']) && $params['status']!=null && $params['status']!=''){
                $list = nameSearchTree($list,$params['status'],0,'status',2);
                for ($i=0;$i<10;$i++){
                    $list = delEmpty($list);
                }
            }
        }
        $list = array_filter($list);//去掉重复值
        $list = delKey(array_values($list));//数据转为数组(不含对象)
        return $list;
    }

    /**
     * 新增菜单
     */
    public function add($params){
        $add_data = combinaData('y_menu',$params,1);
        try {
            $id = Db::name('y_menu')->insertGetId($add_data);
            //成功为账号加入权限
            $user_info = Db::name('y_user')->where('id',$params['u_id'])->find();
            if ($user_info['user_power']){
                $user_power = json_decode($user_info['user_power'],true);
                array_push($user_power,$id);
                Db::name('y_user')->where('id',$params['u_id'])->update(['user_power'=>json_encode($user_power)]);
            }

        }catch (DbException $e){
            return $this->throwBusinessException([-1,'添加失败:'.$e->getMessage()]);
        }
        Cache::delete('all_power');//清除所有权限缓存
        return $id;
    }

    /**
     * 编辑菜单
     */
    public function edit($params){
        $update_data = combinaData('y_menu',$params,0);
        try {
            $update_data['update_time'] = date('Y-m-d H:i:s');
            Db::name('y_menu')->where('id',$params['id'])->update($update_data);
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'修改失败:'.$e->getMessage()]);
        }
        Cache::delete('all_power');//清除所有权限缓存
        return true;
    }

    /**
     * 删除菜单
     */
    public function delete($params){
        if (isset($params['id']) && $params['id'] != null && $params['id'] != ''){
            $id_arr = $this->getMenus(explode(',',$params['id']));
            try {
                Db::name('y_menu')->whereIn('id',$id_arr)->update(['delete_time'=>date('Y-m-d H:i:s')]);
            }catch (DbException $e){
                $this->throwBusinessException([-1,'删除失败:'.$e->getMessage()]);
            }
        }
        return true;
    }

    /**
     * 获取按钮权限
     */
    public function getInfo($params){
        $user_info = Db::name('y_user')->alias('u')
            ->leftJoin('y_department d','d.id=u.department_id')
            ->leftJoin('y_role r','r.id=u.role_id')
            ->leftJoin('y_user uu','uu.id=d.assgin')
            ->field('u.*,d.department_name,r.role_name,uu.user_name departmen_assgin')
            ->where('u.id',$params['u_id'])->find();

        if ($user_info['user_type'] == 1){
            $button_power = Db::name('y_menu')->where('type',3)
                ->field('id,power_mark')->select()->toArray();
        }else{
            $power_id_arr = [];
            if ($user_info['user_power'])
                $power_id_arr = json_decode($user_info['user_power'],true);
            $button_power = Db::name('y_menu')->whereIn('id',$power_id_arr)->where('type',3)
                ->field('id,power_mark')->select()->toArray();

        }
        $button_mark = array_column($button_power,'power_mark');

        return ['user_info'=>$user_info,'button_mark'=>$button_mark,'roles'=>[$user_info['job_number']]];
    }

    /**
     * 获取路由权限
     */
    public function getRoutes($params){
        $user_info = Db::name('y_user')->where('id',$params['u_id'])->find();

        if ($user_info['user_type'] == 1){
            $menu_power = Db::name('y_menu')->whereIn('type',[1,2])->where('delete_time is null')
                ->order('sort','DESC')->order('create_time','DESC')->select()->toArray();
        }else{
            $power_id_arr = [];
            if ($user_info['user_power'])
                $power_id_arr = json_decode($user_info['user_power'],true);
            $menu_power = Db::name('y_menu')->whereIn('id',$power_id_arr)->whereIn('type',[1,2])->where('delete_time is null')
                ->where('status',1)->order('sort','DESC')->order('create_time','DESC')->select()->toArray();
        }

        $menu_power = $this->recursionTree($menu_power,'id','pid');

        return $menu_power;
    }

    /**
     * 通过id递归获取下级所有菜单
     */
    private function getMenus($in_id){
        $pre_arr = Db::name('y_menu')->whereIn('pid',$in_id)->select()->toArray();
        if ($pre_arr){
            $pre_id = array_column($pre_arr,'id');
            $temp = $this->getMenus($pre_id);
            $in_id = array_merge($in_id,$pre_id,$temp);
        }
        return $in_id;
    }

    public function recursionTree($arr, $key = 'id', $fkey = 'fid', $num = 0)
    {
        $list = [];
        foreach ($arr as $val) {
            if ($val[$fkey] == $num) {
                if ($val['type'] == 1){
                    $tmp = self::recursionTree($arr, $key, $fkey, $val[$key]);
                    if ($tmp) {
                        $val['children'] = $tmp;
                    }
                }
                $list[] = $val;
            }
        }
        return $list;
    }
}
