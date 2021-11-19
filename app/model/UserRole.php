<?php


namespace app\model;


class UserRole extends Base
{
    protected $table = 'y_admin_role';

    //获取用户角色id 带缓存
    public static function getUserRoleIds($uid,$clear_cache=false){
        $key = 'user_role_'.$uid;
        if($clear_cache){

            self::clearCache($key);
            $result = false;
        }else{
            $result = self::getNameCache($key);
            if(!$result){
                $result = false;
            }
        }

        if($result === false){
            $result = self::where('uid',$uid)->column('role_id');
            if($result){
                self::setTagCache('user_role',$key,$result);
            }
        }

        return $result;
    }

}