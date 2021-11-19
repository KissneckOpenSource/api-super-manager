<?php
declare(strict_types=1);

namespace app\services;

use app\model\User;
use think\db\exception\DbException;
use think\facade\Db;
use think\facade\Cache;

class UserServices extends BaseServices
{

    /**
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 用户下拉框列表
     */
    public function getUserList($params){
        $condition = [];
        $condition[] = ['status','=',1];
        if (isset($params['role_id']) && $params['role_id'] != '' && $params['role_id'] != null)
            $condition[] = ['role_id','=',$params['role_id']];
        if (isset($params['department_id']) && $params['department_id'] != '' && $params['department_id'] != null)
            $condition[] = ['department_id','=',$params['department_id']];
        if (Cache::has('user_lsit_cache')){
            $list = Cache::get('user_list_cache');
        }else{
            $obj = User::new()->field('id,account,user_name')->where($condition)->where('delete_time is null')->select();
            $list = [];
            if ($obj){
                $list = $obj->toArray();
            }
            Cache::set('user_list_cache',$list);
        }
        return $list;
    }

    /**
     * 获取用户列表
     */
    public function index($params){
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 15;
        $condition = [];
        if(isset($params['department_id']) && $params['department_id'] != '' && $params['department_id'] != null){
            $in_id = $this->getDeparts(explode(',',$params['department_id']));
            $in_id = array_unique($in_id);
            $condition[] = ['u.department_id','in',$in_id];
        }

        if (isset($params['search_content']) && $params['search_content'] != null && $params['search_content'] != ''){
            if (isset($params['search_type'])){
                if ($params['search_type'] == 1)
                    $condition[] = ['u.user_name','like','%'.$params['search_content'].'%'];
                if ($params['search_type'] == 2)
                    $condition[] = ['u.job_number','like','%'.$params['search_content'].'%'];
                if ($params['search_type'] == 3)
                    $condition[] = ['u.mobile','like','%'.$params['search_content'].'%'];
            }
        }
        if (isset($params['id']) && $params['id'] != null && $params['id'] != '')
            $condition[] = ['u.id','=',$params['id']];
        if (isset($params['sex']) && $params['sex'] != null && $params['sex'] != '')
            $condition[] = ['u.sex','=',$params['sex']];
        if (isset($params['status']) && $params['status'] != null && $params['status'] != '')
            $condition[] = ['u.status','=',$params['status']];
        if (isset($params['age']) && $params['age'] != null && $params['age'] != ''){
            $age_arr = explode('-',$params['age']);
            $end_date = date('Y-m-d',strtotime('-'.$age_arr[0].'year'));
            $start_date = date('Y-m-d',strtotime('-'.$age_arr[1].'year'));
            $condition[] = ['u.age','>',$start_date];
            $condition[] = ['u.age','<=',$end_date];
        }

        $mod = Db::name('y_user')->alias('u')
            ->leftJoin('y_department d','u.department_id=d.id')
            ->leftJoin('y_role r','r.id=u.role_id')
            ->where($condition)->where('u.delete_time is null')->order('u.create_time','DESC')
            ->field('u.*,d.department_name,r.role_name,TIMESTAMPDIFF(YEAR,u.age,CURDATE()) real_age');
        $count = $mod->count();
        $list = $mod->limit($limit*($page-1),$limit)->select()->toArray();
        return ['pageArr'=>['page'=>$page,'limit'=>$limit,'count'=>$count],'list'=>$list];
    }

    /**
     * 新增用户
     */
    public function add($params){
        //判断用户账号是否存在
        if (Db::name('y_user')->where('account',$params['account'])->find())
            return $this->throwBusinessException([-1,'账号已经存在']);
        $add_data = combinaData('y_user',$params,1);
//        $add_data['a_id'] = $params['uid'];
        //生成用户代码
        $user_code = $this->checkCode($add_data['department_id'].$add_data['role_id'],date('Ymd').create_rand_stting(5));
        $add_data['job_number'] = $user_code;
        try {
            $id = Db::name('y_user')->insertGetId($add_data);
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'添加失败:'.$e->getMessage()]);
        }
        //清空用户列表缓存
        Cache::delete('user_list_cache');
        return $id;
    }
    /**
     * 编辑用户
     */
    public function edit($params){
        $updata_data = combinaData('y_user',$params,0);
        unset($updata_data['id']);
        try {
            if (isset($params['ids']) && $params['ids'] != '' && $params['ids'] != null){
                $updata_data['update_time'] = date('Y-m-d H:i:s');
                Db::name('y_user')->whereIn('id',explode(',',$params['ids']))->update($updata_data);
            }else{
                Db::name('y_user')->where('id',$params['id'])->update($updata_data);
            }

        }catch (DbException $e){
            return $this->throwBusinessException([-1,'添加失败:'.$e->getMessage()]);
        }
        //清空用户列表缓存
        Cache::delete('user_list_cache');
        Cache::delete('user_poser_'.$params['id']);//清理用户权限缓存
        return true;
    }

    /**
     * 判断用户代码是否存在
     */
    private function checkCode($code,$rand_code){
        $temp = $code.$rand_code;
        $res = Db::name('y_user')->where('job_number',$code.$rand_code)->find();
        if ($res){
            $rand_code = date('Ymd').create_rand_stting(5);
            $temp = $this->checkCode($code,$rand_code);
        }
        return $temp;
    }

    /**
     * 通过id递归获取各级部门
     */
    private function getDeparts($in_id){
        $pre_arr = Db::name('y_department')->whereIn('pid',$in_id)->select()->toArray();
        if ($pre_arr){
            $pre_id = array_column($pre_arr,'id');
//            print_r($pre_id);die;
            $temp = $this->getDeparts($pre_id);
            $in_id = array_merge($in_id,$pre_id,$temp);
        }
        return $in_id;
    }


}