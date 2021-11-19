<?php


namespace app\model;


use think\facade\Cache;
use think\facade\Config;
use think\Model;
use think\model\concern\SoftDelete;

class Banner extends Model
{
    use SoftDelete;
    protected $table = 'y_banner';
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = null;

    public static function get_banner(){
        if(Config::get("app.is_debug") == 1){
            $re_banner = self::where('is_disable',1)->field('type,img,url,title,obj_id,banner_upload_type')->select()->toArray();

            $banner_list = $re_banner;
        }else{
            $banner_list = Cache::get('banner_list');

            if(!$banner_list){
                $re_banner = self::where('is_disable',1)->field('type,img,url,title,obj_id,banner_upload_type')->select()->toArray();

                if($re_banner){
                    Cache::set('banner_list',$re_banner);
                }
                $banner_list = $re_banner;
            }
        }


        return $banner_list;
    }
}