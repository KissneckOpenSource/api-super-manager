<?php
namespace Message;

use app\controller\api\Base;
use think\App;
use think\facade\Cache;
use think\facade\Db;


class Message extends Base
{


    //查询消息未读数量
    public function unread($uid){
        if(!$uid){
           throw new \Exception('缺少用户ID',10006);
        }

        //查询系统消息的缓存是否存在，如果存在返回缓存数据，如果没有缓存，则查询

        $unread_num = Cache::get('message_'.$uid);

        if(!$unread_num){
            $sql = 'SELECT count(aa.id) as num '.
                'FROM '.
                '( SELECT ym.id FROM y_message ym '.
                'LEFT JOIN y_message_log yml ON ym.id = yml.mid '.
                'AND yml.uid = '.$uid.' '.
                'LEFT JOIN y_message_del_log ymdl on ym.id = ymdl.mid  '.
                'AND ymdl.uid = '.$uid.' '.
                'WHERE ym.delete_time IS NULL '.
                'AND ( ym.type = 1 OR locate( "'.$uid.'", touid ) > 0 ) '.
                'AND yml.id IS NULL '.
                'AND ymdl.id IS NULL '.
                'GROUP BY ym.id '.
                ') aa';
            $re = Db::query($sql);

            if(!$re){
                $re_unread = 0;
            }else{
                $re_unread = $re[0]['num'];
            }

            //获取当前时间到当天的结束时间的秒数
            $over_timestamp = strtotime(date('Y-m-d '.'23:59:59'))-time();

            if($over_timestamp <= 0 ){
                $over_timestamp = 5;
            }

            $re_cache = Cache::tag('message_tag')->set('message_'.$uid,$re_unread,$over_timestamp);

            if(!$re_cache){
                throw new \Exception('计算未读数量失败，请重试！',10006);
            }

            return $re_unread;
        }

        return $unread_num;

    }

    //修改消息为已读
    //$data[] 消息ID的一维数组

    public function read_message($data){

    }

    //获取消息列表
    //data['type'] 读取消息的类型 1进入列表就是读取 2点击读取
    //data['uid'] 当前用户
    //data['create_time'] 当前用户注册时间
    //data['page'] 当前查询开始的条数
    //data['page_size'] 每页显示数量
    //data['user_type'] 用户当前身份
    public function message_list($data){
        if(!$data['uid']){
            throw new Exception('缺少用户ID',10006);
        }

        if(!$data['create_time']){
            throw new Exception('缺少用户注册时间',10006);
        }

        if(!is_numeric($data['page'])){

            throw new Exception('缺少查询开始条数',10006);
        }

        if(!$data['page_size']){
            throw new Exception('缺少每页显示条数',10006);
        }

        $sql = 'SELECT ym.id,ym.m_type as type,ym.content,ifnull(yso.order_num,"") as order_num,'.
            'ym.title,'.
            'DATE_FORMAT(ym.create_time,"%Y-%m-%d %H:%i") as create_time,ym.obj_id,';

        if($data['type'] == 1){
            $sql .= '1 as is_read '.
                'FROM y_message ym ';
        }else{
            $sql .= 'if(yml.id is null,0,1) as is_read  '.
                'FROM y_message ym '.
                'LEFT JOIN y_message_log yml ON ym.id = yml.mid '.
                'AND yml.uid = '.$data['uid'].' ';
        }

        $sql .= 'LEFT JOIN y_message_del_log ymdl on ym.id = ymdl.mid '.
            'AND ymdl.uid = '.$data['uid'].' '.
            'LEFT JOIN y_store_order yso on ym.obj_id=yso.id and ym.m_type in (2,3,4,5,6,7) '.
            'WHERE ym.delete_time IS NULL '.
            'AND ( ym.type = 1 OR locate( "'.$data['uid'].'", touid ) > 0 ) '.
            'AND ymdl.id IS NULL '.
            'and ym.create_time > "'.$data['create_time'].'" ';


        if($data['user_type'] == 1){
            //当前用户类型是普通用户
            $sql .= 'and ym.user_type in (1,3) ';
        }else{
            //当前用户类型是医生
            $sql .= 'and ym.user_type in (1,2) ';
        }
        $sql .= 'ORDER BY ym.id desc limit '.$data['page'].','.$data['page_size'];

        $re = Db::query($sql);

        if($re){
            $this->clena_cache_message($data['uid']);

            if($data['type'] == 1){
                //查询的未读数据的id
                $sql = 'SELECT ym.id '.
                    'FROM y_message ym '.
                    'LEFT JOIN y_message_log yml ON ym.id = yml.mid AND yml.uid = '.$data['uid'].' '.
                    'AND ( ym.type = 1 OR locate( "'.$data['uid'].'", touid ) > 0 ) '.
                    'AND yml.id IS NULL '.
                    'and ym.create_time > "'.$data['create_time'].'" ';

                $re_unread = Db::query($sql);

                if($re_unread){
                    $re_unread_id = array_column($re_unread,'id');

                    //插入用户已读消息
                    $ins_mes_read = [];
                    foreach ($re_unread_id as $v){
                        $ins_mes_read[] = ['uid'=>$data['uid'],'mid'=>$v];
                    }

                    if($ins_mes_read){
                        $re_ins_num = Db::name('y_message_log')->insertAll($ins_mes_read);
                        if($re_ins_num != count($ins_mes_read)){
                            Log::record('用户'.$data['uid'].'进入消息列表，修改全部未读消息为已读消息插入数据失败！');
                        }
                    }
                }
            }


        }

        return $re;


    }

    //向用户推送消息(并且向数据库中插入消息)
    //data是一个数组 为了方便后台批量向用户发送消息处理

    //$data['type']  消息类型
    //$data['obj_id'] 发送的对象  不是系统消息 必须有该字段
    //$data['touid'] 发送用户的id,多个使用  type =2必须
    //$data['content'] 消息内容
    //$data['m_type'] 消息显示类型 1系统 2付款订单 3医生接单 4投诉订单 5投诉订单反馈 6投诉订单退款提醒  7投诉反馈提醒
    //$data['user_type']  发送消息的用户类型

    //$is_check_order 是否需要检查订单 在支付回调等确定订单一定存在的 可以不用检查
    public function create_message($data,$is_check_order=1){
        $error = [];

        foreach ($data as $v){
            $ins = [];

            if(!isset($v['type']) || empty($v['type'])){
                throw new \Exception('缺少插入对应type！',10006);
            }

            $ins['type'] = $v['type'];
            $ins['m_type'] = $v['m_type'];
            $ins['content'] = $v['content'];
            $ins['user_type'] = $v['user_type'];
            $ins['order_id'] = $v['order_id'];
            if($v['type'] == 1){
                $ins['touid'] = '';
            }
            else{

                if(!isset($v['touid']) || empty($v['touid'])){
                    throw new \Exception('缺少插入对应touid！',10006);
                }
                $ins['touid'] = $v['touid'];
            }

            if(!isset($v['content']) || empty($v['content'])){
                throw new \Exception('缺少插入对应数据内容！',10006);
            }

            if(!isset($v['m_type']) || empty($v['m_type'])){
                throw new \Exception('缺少插入对应数据m_type！',10006);
            }

            //1系统 2付款订单 3医生接单 4投诉订单 5投诉订单反馈 6投诉订单退款提醒  7投诉反馈提醒
            switch ($v['m_type']){
                case 1:
                    $title = '系统';
                    break;
                case 2:
                    $title = '付款订单';
                    break;
                case 3:
                    $title = '会员订单付费成功';
                    break;
                case 4:
                    $title = '投诉反馈';
                    break;
                case 5:
                    $title= '投诉订单反馈';
                    break;
                case 6:
                    $title = '订单取消';
                    break;
                case 7:
                    $title = '评价回复提醒';
                    break;
            }

            if(in_array($v['m_type'],[2,3,4,5,6,7])){
                if(!isset($v['obj_id']) || empty($v['obj_id'])){
                    throw new \Exception('缺少插入对应数据ID！',10006);
                }
                $ins['obj_id'] = $v['obj_id'];
                if($is_check_order == 1){
                    $re_store_order = Db::name('y_store_order')
                        ->where('id',$v['obj_id'])
                        ->field('id')
                        ->find();
                    if(!$re_store_order){
                        throw new \Exception('插入数据ID,查询对应数据查询错误！',10006);
                    }
                }
            }

            $ins['title'] = $title;

            if($ins){
                //插入数据  并为对应的用户记录一条未读消息
                $re_message = Db::name('y_message')->insertGetId($ins);

                if(!$re_message){
                    //记录错误  返回错误
                    $error[] = $ins;
                    continue;
                }

                if($v['type'] == 1){
                    //删除所有的消息缓存信息，全部重取一次.因为系统如果发送的是全部用户
                    // 暂时无法向每个用户增加1操作，所以选择删除全部标签缓存

                    Cache::tag('message_tag')->clear();


                }else{

                    $to_uid_arr = explode(',',$v['touid']);

                    foreach ($to_uid_arr as $v_to_uid){
                        //获取当前时间到当天的结束时间的秒数
                        $over_timestamp = strtotime(date('Y-m-d '.'23:59:59'))-time();

                        if($over_timestamp <= 0 ){
                            $over_timestamp = 5;
                        }

                        //向指定用户更新未读消息数量
                        $unread_num = Cache::get('message_'.$v_to_uid);

                        if($unread_num){
                            $re_cache = Cache::tag('message_tag')->set('message_'.$v_to_uid,$unread_num+1,$over_timestamp);
                        }else{
                            $re_cache = Cache::tag('message_tag')->set('message_'.$v_to_uid,1,$over_timestamp);
                        }

                        if(!$re_cache){

                            throw new \Exception('插入数据成功，记录未读数量失败！',10006);
                        }
                    }


                }


            }
        }

        return $error;
    }


    //清0本地未读消息缓存
    public function clena_cache_message($uid){
        //获取当前时间到当天的结束时间的秒数
        $over_timestamp = strtotime(date('Y-m-d '.'23:59:59'))-time();

        if($over_timestamp <= 0 ){
            $over_timestamp = 5;
        }
        $re_cache = Cache::tag('message_tag')->set('message_'.$uid,0,$over_timestamp);

        if(!$re_cache){
            throw new \Exception('清零消息数量失败，请重试！',10006);
        }
    }

    //删除指定的消息
    //$data['id']  消息ID 可以是id或是id逗号拼接
    //$data['uid']  用户ID
    public function del_message($data){
        //确认消息是否存在
        $sql = 'SELECT ym.id,ymdl.id as id_del '.
                'FROM y_message ym '.
                'LEFT JOIN y_message_del_log ymdl on ym.id=ymdl.mid	and ymdl.uid='.$data['uid'].' '.
                'WHERE '.
                '( ym.type = 1 OR ( ym.type = 2 AND locate( "'.$data['uid'].'", ym.touid ) > 0 ) ) '.
                'AND ym.id in ('.$data['id'].') '.
                'ORDER BY ym.id desc,id_del desc';

        $re = Db::query($sql);

        if(!$re){
            throw new \Exception('删除消息查询错误！',10006);
        }


        $ins = [];
        $re_ids = [];
        foreach ($re as $v){
            if(!$v['id_del']){
                $re_ids[] = $v['id'];
                $ins[] = ['uid'=>$data['uid'],'mid'=>$v['id']];
            }else{
                //用户选择的删除信息 有已删除的
                Log::record('删除用户指定的信息 消息ID【'.$v['id'].'】 已被删除！');
            }

        }



        $re = Db::name('y_message_del_log')->insertAll($ins);

        return ['ins'=>$re,'del_id'=>$re_ids];
    }
}