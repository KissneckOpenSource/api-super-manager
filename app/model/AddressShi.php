<?php


namespace app\model;


use think\facade\Cache;
use think\facade\Db;
use think\model\concern\SoftDelete;

class AddressShi extends Base
{
    use SoftDelete;
    protected $table = 'y_address_shi';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;

    public static function open_shi(){
        $shi_list = Cache::get('open_shi');
        if(!$shi_list){
           $re = Db::name('y_address_shi')->alias('yas')
               ->leftJoin('y_address_sheng ys','yas.pcode=ys.code')
               ->fieldRaw('concat(ys.name,yas.name) as addres')
               ->where('yas.is_on',1)
               ->where('yas.delete_time',null)
               ->where('ys.delete_time',null)
               ->select()
               ->toArray();
           if($re){
               $re = array_column($re,'addres');
               Cache::set('open_shi',$re,60*60*24);
           }

            $shi_list = $re;
        }

        return $shi_list;
    }
}