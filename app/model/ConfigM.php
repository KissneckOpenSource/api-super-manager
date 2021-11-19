<?php


namespace app\model;


use think\facade\Cache;
use think\model\concern\SoftDelete;

class ConfigM extends Base
{
    use SoftDelete;
    protected $table = 'y_config';
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = null;

    //获取全部设置信息
    public static function get_all_config(){

        $cache_config = Cache::get('cache_config');

        if(!$cache_config){
//            $re_config = self::where('delete_time',null)->field('config_key,value')->select();
            $re_config = self::field('config_key,value')->select();
            if($re_config){
                $cache_arr = [];
                foreach ($re_config as $v){
                    $cache_arr[$v->config_key] = $v->value;
                }

                Cache::set('cache_config',$cache_arr,60*60*24);
                $cache_config = $cache_arr;
            }
        }

        return $cache_config;
    }
}