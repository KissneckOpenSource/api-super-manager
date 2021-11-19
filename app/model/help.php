<?php


namespace app\model;


use think\facade\Cache;
use think\model\concern\SoftDelete;

class help extends Base
{
    use SoftDelete;
    protected $table = 'y_help';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = null;


    //获取全部设置协议
    public static function get_help(){
        $cache_help = Cache::get('cache_help');

        if(!$cache_help){
//            $cache_help = self::where('delete_time',null)->field('name,description,type')->select()->toArray();
            $cache_help = self::field('name,description,type')->select()->toArray();
            if($cache_help){
                $cache_arr = [];
                foreach ($cache_help as $v){

                    $cache_arr[$v['type']] = ['name'=>$v['name'],'des'=>$v['description']];
                }
                Cache::set('cache_help',$cache_arr,60*60*24);
                $cache_help = $cache_arr;
            }
        }

        return $cache_help;
    }
}