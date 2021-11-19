<?php


namespace app\model;


use think\facade\Cache;
use think\Model;
use think\model\concern\SoftDelete;

class Feedback extends Model
{
    use SoftDelete;
    protected $table = 'y_feedback_type';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;

    //获取反馈类型
    public static function feedback_all(){
        $feedback_list = Cache::get('feedback_type');

        if(!$feedback_list){
            $re = self::where('is_show',1)->order('sort desc')->field('id,title')->select();
            if($re){
                $re_arr = $re->toArray();
                Cache::set('feedback_type',$re_arr,60*60);
                $feedback_list = $re_arr;
            }
        }

        return $feedback_list;
    }


}