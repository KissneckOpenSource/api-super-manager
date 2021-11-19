<?php
declare (strict_types = 1);

namespace app\services;

use think\facade\Db;
use app\util\Tools;
use phpDocumentor\Reflection\Types\Self_;
use think\db\exception\DbException;

class DepartmentService extends BaseServices
{

    /**
     * 获取部门结构
     */
    public function index($params){
        if (isset($params['id']) && $params['id']!=null && $params['id']!=''){
            $list = Db::name('y_department')->where('id',$params['id'])->select()->toArray();
        }else{
            $all_list = Db::name('y_department')->where('delete_time is null')
                ->order('sort','DESC')->order('create_time','DESC')->select()->toArray();
            //处理层级数据
            $list = Tools::recursionTree($all_list,'id','pid');
            if (isset($params['department_name']) && $params['department_name']!=null && $params['department_name']!=''){
                $list = nameSearchTree($list,$params['department_name'],0,'department_name',1);//条件筛选
                for ($i=0;$i<10;$i++){
                    $list = delEmpty($list);//清除空值
                }
            }
            if (isset($params['status']) && $params['status']!=null && $params['status']!=''){
                $list = nameSearchTree($list,$params['status'],0,'status',2);
                for ($i=0;$i<10;$i++){
                    $list = delEmpty($list);
                }
            }
        }
        $list = array_filter($list);//去掉重复值
        $list = delKey(array_values($list));//数据转为数组(不含对象)
        return $list;
    }

    /**
     * 获取部门列表
     */
    public function list($params){
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 15;
        $condition = [];
        if (isset($params['department_name']) && $params['department_name'] != '' && $params['department_name'] != null)
            $condition[] = ['d.department_name','like','%'.$params['department_name'].'%'];
        if (isset($params['status']) && $params['status'] != '' && $params['status'] != null)
            $condition[] = ['d.status','=',$params['status']];
        $mod = Db::name('y_department')->alias('d')
            ->leftJoin('y_user u','u.id=d.assgin')
            ->where('d.id not in (select pid from y_department )')
            ->where('d.delete_time is null')->where($condition)
            ->order('d.sort','DESC')->order('d.create_time','DESC')
            ->field('d.*,u.user_name assgin_name');
        $count = $mod->count();
        $list = $mod->limit($limit*($page-1),$limit)->select()->toArray();
        //获取部门结构
        if ($list){
            $all_list = Db::name('y_department')->field('id,pid,department_name')->select()->toArray();
            foreach ($list as $key=>$val){
                $new_val = $val;
                $new_val['department_name'] = '';
                $list[$key]['depart_archit'] = $this->getArchit($new_val,$all_list);
            }
        }
        return ['pageArr'=>['page'=>$page,'limit'=>$limit,'count'=>$count],'list'=>$list];

    }

    /**
     * 递归获取部门结构
     */
    protected function getArchit($data,$all_list){
        $depart_archit = $data['department_name'];
        if ($data['pid'] !== 0){
            $k = array_search($data['pid'],array_column($all_list,'id'));
            if ($depart_archit){
                $depart_archit = $all_list[$k]['department_name']."=>".$depart_archit;
            }else{
                $depart_archit = $all_list[$k]['department_name'];
            }

            $new = $all_list[$k];
            $new['department_name'] = $depart_archit;
            $depart_archit = $this->getArchit($new,$all_list);
        }
        return $depart_archit;
    }

    /**
     * 新增部门
     */
    public function add($params){
        $add_data = combinaData('y_department',$params,1);
        $add_data['a_id'] = $params['uid'];
        try {
            $id = Db::name('y_department')->insertGetId($add_data);
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'添加失败:'.$e->getMessage()]);
        }
        return $id;
    }

    /**
     * 编辑部门
     */
    public function edit($params){
        $update_data = combinaData('y_department',$params,0);
        try {
            $update_data['update_time'] = date('Y-m-d H:i:s');
            Db::name('y_department')->where('id',$params['id'])->update($update_data);
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'修改失败:'.$e->getMessage()]);
        }
        return true;
    }
    /**
     * 删除部门
     */
    public function delete($params){
        $res = Db::name('y_user')->where('department_id',$params['id'])->find();
        if ($res)
            return $this->throwBusinessException([-1,'选中部门下存在用户，无法删除']);
        try {
            Db::name('y_department')->where('id',$params['id'])->delete();
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'修改失败:'.$e->getMessage()]);
        }
        return true;
    }

}
