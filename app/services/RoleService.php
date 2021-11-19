<?php
declare (strict_types = 1);

namespace app\services;

use think\facade\Db;
use think\db\exception\DbException;

class RoleService extends BaseServices
{
    /**
     * 角色列表
     */
    public function index($params){
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 15;
        $condition = [];
        if (isset($params['id']) && $params['id']!=''&&$params['id']!=null)
            $condition[] = ['r.id','=',$params['id']];
        if (isset($params['role_name']) && $params['role_name']!=''&&$params['role_name']!=null)
            $condition[] = ['r.role_name','like','%'.$params['role_name'].'%'];
        if (isset($params['status']) && $params['status']!=''&&$params['status']!=null)
            $condition[] = ['r.status','=',$params['status']];
        $mod = Db::name('y_role')->alias('r')->where($condition)->where('r.delete_time is null')
            ->leftJoin('y_role_group rg','rg.id=r.group_id')
            ->order('r.sort','DESC')->order('r.create_time','DESC')
            ->field('r.*,rg.group_name');
        $count = $mod->count();
        $list = $mod->limit($limit*($page-1),$limit)->select()->toArray();

        return ['pageArr'=>['page'=>$page,'limit'=>$limit,'count'=>$count],'list'=>$list];
    }
    /**
     * 创建角色
     */
    public function add($params){
        if (Db::name('y_role')->where('role_name',$params['role_name'])->find())
            return $this->throwBusinessException([-1,'角色名称已存在']);
        $add_data = combinaData('y_role',$params,1);
        $add_data['a_id'] = $params['uid'];
        try {
            $id = Db::name('y_role')->insertGetId($add_data);
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'添加失败:'.$e->getMessage()]);
        }
        return $id;
    }
    /**
     * 编辑角色
     */
    public function edit($params){
        $updata_data = combinaData('y_role',$params,0);
        try {
            $updata_data['update_time'] = date('Y-m-d H:i:s');
            Db::name('y_role')->where('id',$params['id'])->update($updata_data);
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'添加失败:'.$e->getMessage()]);
        }
        return true;
    }
    /**
     * 批量删除角色
     */
    public function delete($params){
        $res = Db::name('y_user')->whereIn('role_id',explode(',',$params['ids']))->find();
        if ($res)
            return $this->throwBusinessException([-1,'选中角色下存在用户，无法删除']);
        try {
            Db::name('y_role')->whereIn('id',explode(',',$params['ids']))->delete();
        }catch (DbException $e){
            return $this->throwBusinessException([-1,'修改失败:'.$e->getMessage()]);
        }
        return true;
    }
}
