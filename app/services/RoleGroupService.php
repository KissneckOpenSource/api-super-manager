<?php
declare (strict_types = 1);

namespace app\services;

use think\facade\Db;
use think\db\exception\DbException;

class RoleGroupService extends BaseServices
{
    /**
     * 获取角色组列表
     */
    public function index($params){
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 15;
        $condition = [];
        if (isset($params['id']) && $params['id'] != '' && $params['id'] != null)
            $condition[] = ['id','=',$params['id']];
        if (isset($params['group_name']) && $params['group_name'] != '' && $params['group_name'] != null)
            $condition[] = ['group_name','like','%'.$params['group_name'].'%'];
        if (isset($params['status']) && $params['status'] != '' && $params['status'] != null)
            $condition[] = ['status','=',$params['status']];

        $mod = Db::name('y_role_group')->where($condition)->where('delete_time is null')->order('create_time','DESC');
        $count = $mod->count();
        $list = $mod->limit($limit*($page-1),$limit)->select()->toArray();

        return ['pageArr'=>['page'=>$page,'limit'=>$limit,'count'=>$count],'list'=>$list];
    }
    /**
     * 新增角色组
     */
    public function add($params){
        if (Db::name('y_role_group')->where('group_name',$params['group_name'])->find())
            return $this->throwBusinessException([-1,'角色组名称已存在']);
        $add_data = combinaData('y_role_group',$params,1);
        $add_data['a_id'] = $params['uid'];
        try {
            $id = Db::name('y_role_group')->insertGetId($add_data);
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'添加失败:'.$e->getMessage()]);
        }
        return $id;
    }
    /**
     * 编辑角色组
     */
    public function edit($params){
        $updata_data = combinaData('y_role_group',$params,0);
        try {
            $updata_data['update_time'] = date('Y-m-d H:i:s');
            Db::name('y_role_group')->where('id',$params['id'])->update($updata_data);
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'添加失败:'.$e->getMessage()]);
        }
        return true;
    }
    /**
     * 删除角色组
     */
    public function delete($params){
        $res = Db::name('y_role')->where('group_id',$params['id'])->find();
        if ($res)
            return $this->throwBusinessException([-1,'选中角色组下存在角色，无法删除']);
        try {
            Db::name('y_role_group')->where('id',$params['id'])->delete();
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'修改失败:'.$e->getMessage()]);
        }
        return true;
    }
}
