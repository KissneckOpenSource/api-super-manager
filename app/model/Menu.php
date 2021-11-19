<?php


namespace app\model;


use think\facade\Db;
use think\model\concern\SoftDelete;

class Menu extends Base
{
    use SoftDelete;

    protected $table = 'y_menu';

    protected $autoWriteTimestamp = 'datetime';

    protected $deleteTime = 'delete_time';

    //获取左侧菜单菜单树
    //根据指定用户查询对应菜单权限
    /**
     * @param $grouping
     * @param null $uid 如果uid为null则表示师超级管理员，可以查询全部菜单
     * @throws \think\Exception
     */
    public static function getMenuTree($grouping,$uid=null){
        if(empty($uid)){
            $menu_list = self::where('grouping',$grouping)
                ->where('enable',1)
                ->order('sorting')
                ->field('id,pid,title')
                ->select()
                ->toArray();
            $menu_list = self::buildMenuTree($menu_list);
        }else{
            $role_id_arr = UserRole::getUserRoleIds($uid);
            if($role_id_arr){
                $menu_id_by_role = Db::name('y_role_menu')
                    ->where('role_id','in',$role_id_arr)
                    ->column('menu_id');

                $menu_list = self::where(['grouping'=>$grouping,'enable'=>1])
                    ->where('id','in',$menu_id_by_role)
                    ->order('sorting')
//                    ->field('id,pid,title,ext_url,controller,action')
                    ->field('id,pid,title,ext_url')
                    ->select()
                    ->toArray();
            }else{
                return [];
            }
            $menu_list = self::buildMenuTree($menu_list);

            //$menu_check_arr = self::buileMenuCheck($menu_list);


            //对所有的用户权限设置标签为menu_role TODO 暂时不对权限进行验证，要求前端根据返回要求显示左侧栏目
            //self::setTagCache('admin_menu_check'.$uid,'menu_role',$menu_check_arr);
        }

        return $menu_list;
    }

    /**
     * @param $sourceItems 菜单权限源数据
     * @param null $pid 菜单对应的父规则ID
     */
    public static function buildMenuTree($sourceItems,$pid=0)
    {
        //返回的菜单数组
        $result = [];

        if(empty($sourceItems)){
            return null;
        }

        foreach ($sourceItems as $key=>$item){
            if($item['pid'] == $pid){
                $item['children'] = self::buildMenuTree($sourceItems,$item['id']);
                $result[] = $item;
            }
        }
        return $result;
    }

    //根据菜单获取用户检测的一维数组
    public static function buileMenuCheck($data){
        //验证菜单权限数组
        $result = [];
        foreach ($data as $key=>$value){
            if(($value['controller'] && !empty($value['controller'])) &&
                ($value['action'] && !empty($value['action']))){
                $result[] = $value['controller'].'/'.$value['action'];
            }
            if($value['children']){
                foreach ($value['children'] as $key_c=>$value_c){

                    if(($value_c['controller'] && !empty($value_c['controller'])) &&
                        ($value_c['action'] && !empty($value_c['action']))){
                        $result[] = $value_c['controller'].'/'.$value_c['action'];
                    }

                    if($value_c['children']){
                        foreach ($value_c['children'] as $key_cc=>$value_cc){
                            if(($value_cc['controller'] && !empty($value_cc['controller'])) &&
                                ($value_cc['action'] && !empty($value_cc['action']))){
                                $result[] = $value_cc['controller'].'/'.$value_cc['action'];
                            }

                        }
                    }
                }
            }
            unset($data[$key]);
        }
        return $result;
    }


}