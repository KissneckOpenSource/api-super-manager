<?php
/**
 *
 * @since   2019-04-23
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\model;

use think\facade\Cache;
use think\Model;

class Base extends Model {
//清除指定键名的缓存
    public static function clearCache($key){
        Cache::delete($key);
    }

    //清除指定标签的缓存
    public static function tagClearCache($key){
        Cache::tag($key)->clear();
    }

    //设置指定的标签的缓存数据
    public static function setTagCache($key,$tag,$data){
        Cache::tag($tag)->set($key,$data);
    }

    //设置指定键名的缓存数据
    public static function setNameCache($key,$data){
        Cache::set($key,$data);
    }

    //通过缓存键名获取指定的缓存数据
    public static function getNameCache($tag){
        $re_cache = Cache::get($tag);
        return $re_cache;
    }

}
