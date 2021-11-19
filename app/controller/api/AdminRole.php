<?php


namespace app\controller\api;


use app\model\Menu;
use app\util\ReturnCode;
use think\facade\Cache;
use think\facade\Db;

class AdminRole extends Base
{

    //获取角色列表
    public function index(){

        $params = $this->request->param();

        $page_start = limit_arr($params['pages'],$params['limit']);

        $re_arr = Db::name('y_role')->limit($page_start,$params['limit'])
            ->order('sorting')
            ->field('id,name,is_sys,note')
            ->select()->toArray();

        //获取全部角色菜单的数据
        $re_role_menu = Db::name('y_role_menu')
            ->field('role_id,menu_id')
            ->order(['role_id'=>'asc','menu_id'=>'asc'])
            ->select()->toArray();

        foreach ($re_arr as $k=>&$v){
            $v['role_list'] = [];
            foreach ($re_role_menu as $kk=>$vv){
                if($v['id'] == $vv['role_id']){
                    $v['role_list'][] = $vv['menu_id'];
                    unset($re_role_menu[$kk]);
                }
            }
        }


        $re_total_number = Db::name('y_role')->count();

        //TODO  待处理页面按键权限。
//        $button = ['add'=>1,'edit'=>1,'del'=>1,'export'=>0];
//
//        return json(result_create_arr($re_arr,'角色列表',1,'',
//            (int)$params['limit'],(int)$params['pages'],$re_total_number,$button));

        $result = [
            'page_size'=>(int)$params['limit'],     //每页显示的条数
            'current_page'=>(int)$params['pages'],  //当前的页数
            'total_number'=>$re_total_number,  //总条数
            'data' => $re_arr,
        ];


        return $this->buildSuccess($result);


    }

    public function index2(){

        $re_arr = \app\model\Role::order('sorting')
            ->field('id,name,is_sys,note')
            ->select();
        $re_total_number = \app\model\Role::count();

        //TODO  待处理页面按键权限。
        $button = ['add'=>1,'edit'=>1,'del'=>1,'export'=>0];

        return json(result_create_arr($re_arr,'角色列表',1,'',
            (int)$re_total_number,(int)1,$re_total_number,$button));

    }




    //操作角色  包含新增、修改、删除
    //todo 前端只能给到权限的二级 下级权限需要后台处理主动添加
    public function  action_role(){
        $params = $this->request->param();

        if(in_array($params['type'],[1,2])){
            if(!isset($params['name']) || empty($params['name'])){
//                return json(result_create_arr('','缺少name'));
                return $this->buildFailed(ReturnCode::INVALID,'缺少name！');
            }

            if(!isset($params['note']) || empty($params['note'])){
//                return json(result_create_arr('','缺少note'));
                return $this->buildFailed(ReturnCode::INVALID,'缺少note！');
            }

            if(!isset($params['rule_str']) || empty($params['rule_str'])){
//                return json(result_create_arr('','缺少rule_str'));
                return $this->buildFailed(ReturnCode::INVALID,'缺少rule_str！');
            }
        }

        if($params['type'] == 1){

            $re_role = Db::name('y_role')->where('name',$params['name'])
                ->field('name')
                ->find();

            if($re_role){
                if($re_role['name'] == $params['name']){
                    $msg = '角色名称已存在！';
                }

                return json(result_create_arr('',$msg));
            }

            //获取前端传过来的权限
            $re_menu = Db::name('y_menu')
                ->where('id','in',$params['rule_str'])
                ->where('delete_time',null)
                ->field('id,pid')
                ->order('id')
                ->select()
                ->toArray();

            $rule_arr = explode(',',$params['rule_str']);

            if(count($re_menu) != count($rule_arr)){
                return json(result_create_arr('','验证权限选项存在异常！'));
            }


            //根据查询的权限获取对应的二级权限的下级操作权限
            $p_menu = [];   //需要查询二级权限的权限ID
            foreach ($re_menu as $v){
                if($v['pid'] > 0){
                    $p_menu[] = $v['id'];
                }
            }



            if($p_menu){
                $re_menu_front = Db::name('y_menu')
                    ->where('pid','in',$p_menu)
                    ->where('delete_time',null)
                    ->where('front','>',0)
                    ->field('id,pid')
                    ->select()
                    ->toArray();
                if($re_menu_front){
                    $re_menu_new = [];
                    foreach ($re_menu as $k=>$v){
                        $re_menu_new[] = ['id'=>$v['id']];
                        foreach ($re_menu_front as $kk=>$vv){
                            if($v['id'] == $vv['pid']){
                                $re_menu_new[] = ['id'=>$vv['id']];
                                unset($re_menu_front[$kk]);
                            }
                        }

                    }
                }else{
                    $re_menu_new =  $re_menu;
                }
            }



            // 启动事务
            Db::startTrans();
            try {


                $hash_token = \UuidHelper::generate(1)->string;
                $inset_data = [
                    'code'=>$hash_token,
                    'name'=>$params['name'],
                    'is_sys'=>0,
                    'note'=>$params['note'],
                    'a_id'=>$this->uid
                ];

                $re_obj = Db::name('y_role')->insertGetId($inset_data);


//                if(!isset($re_obj->id) || empty($re_obj->id)){
//                    throw new \think\Exception('新增角色失败，请重试！', 10006);
//                }

                if(!$re_obj){
                    throw new \think\Exception('新增角色失败，请重试！', 10006);
                }
                $ins_arr = [];
                //返回前端的菜单ID数组
                $re_role_list = [];
                foreach ($re_menu_new as $v){
                    $re_role_list[] = $v['id'];
                    $ins_arr[] = ['role_id'=>$re_obj,'menu_id'=>$v['id']];
                }

                if(!$ins_arr){
                    throw new \think\Exception('生成权限数据不能为空', 10006);
                }

                $re_ins_all = Db::name('y_role_menu')->insertAll($ins_arr);
                if($re_ins_all != count($re_menu_new)){
                    throw new \think\Exception('增加选择权限，失败，请重试！', 10006);
                }


                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
            }

            return $this->buildSuccess($re_role_list,'新增角色成功');

        }
        else if($params['type'] == 2){
            $update_arr = [];
            //修改
            if(!isset($params['id']) || empty($params['id'])){

                return $this->buildFailed(ReturnCode::INVALID,'请选择修改的角色！');
            }

            $re_role = Db::name('y_role')->where('id',$params['id'])
                ->field('name,note,a_id,is_sys')
                ->find();

            if(!$re_role){
                return $this->buildFailed(ReturnCode::INVALID,'查询选择的角色失败，请确认修改的角色已创建！');
            }

            if($re_role['is_sys'] == 1){
                return $this->buildFailed(ReturnCode::INVALID,'系统内置角色不能修改！');
            }

            if($re_role['name'] != $params['name']){
                //检查角色名称是否重名
                $re_check_name = Db::name('y_role')->where('id','<>',$params['id'])
                    ->where('name',$params['id'])
                    ->field('id')
                    ->find();
                if($re_check_name){
                    //return json(result_create_arr(new \stdClass(),'角色名称重名，请设置其他名字！'));
                    return $this->buildFailed(ReturnCode::INVALID,'角色名称重名，请设置其他名字！');
                }
                $update_arr['name'] = $params['name'];
            }

            $re_role_menu = Db::name('y_role_menu')->alias('yrm')
                ->leftJoin('y_menu ym','yrm.menu_id=ym.id')
                ->where('yrm.role_id',$params['id'])
                ->where('ym.front',0)
                ->field('menu_id')
                ->select()
                ->toArray();

            if($re_role_menu){
                $role_menu_id = array_column($re_role_menu,'menu_id');
                $role_id_str = implode(',',$role_menu_id);
            }
            else{
                $role_id_str ='';
                $role_menu_id = [];
            }




            if($re_role['note'] != $params['note']){
                $update_arr['note'] = $params['note'];
            }



            //返回前端的权限ID数组

            $role_menu_id = explode(',',$params['rule_str']);

            if($role_id_str != $params['rule_str']){

                //检查传入的权限ID是否全部存在，如果存在不一致返回错误
                $re_menu = Db::name('y_menu')
                    ->where('id','in',$params['rule_str'])
                    ->where('delete_time',null)
                    ->field('id,pid')
                    ->select()
                    ->toArray();

                if(count($re_menu) != count($role_menu_id)){
                    //return json(result_create_arr(new \stdClass(),'验证权限选项存在异常！'));
                    return $this->buildFailed(ReturnCode::INVALID,'验证权限选项存在异常！');
                }

                $role_menu_id = [];
                //根据查询的权限获取对应的二级权限的下级操作权限
                $p_menu = [];   //需要查询二级权限的权限ID
                foreach ($re_menu as $v){
                    if($v['pid'] > 0){
                        $p_menu[] = $v['id'];
                    }
                }


                $re_menu_new = [];
                if($p_menu){
                    $re_menu_front = Db::name('y_menu')
                        ->where('pid','in',$p_menu)
                        ->where('delete_time',null)
                        ->where('front','>',0)
                        ->field('id,pid')
                        ->select()
                        ->toArray();
                    if($re_menu_front){

                        foreach ($re_menu as $k=>$v){
                            $re_menu_new[] = ['id'=>$v['id']];
                            foreach ($re_menu_front as $kk=>$vv){
                                if($v['id'] == $vv['pid']){
                                    $re_menu_new[] = ['id'=>$vv['id']];
                                    unset($re_menu_front[$kk]);
                                }
                            }

                        }
                    }else{
                        $re_menu_new =  $re_menu;
                    }
                }


                //todo 只对接收的规则（菜单）ID 做了基本的查询和数量比对
                $update_arr['rule_str'] = $re_menu_new;

            }

            if(!$update_arr){
                //return json(result_create_arr('','请修改权限后再保存！'));
                return $this->buildFailed(ReturnCode::INVALID,'请修改权限后再保存！');
            }

            if($re_role['a_id'] != $this->uid){
                $update_arr['a_id'] = $this->uid;
            }


            // 启动事务
            Db::startTrans();
            try {
                if(isset($update_arr['rule_str']) && !empty($update_arr['rule_str'])){
                    $ins_role_menu = $update_arr['rule_str'];
                    unset($update_arr['rule_str']);
                    $re_del_role_menu = Db::name('y_role_menu')->where('role_id',$params['id'])->delete();
                    if(!$re_del_role_menu){
                        throw new \think\Exception('修改权限失败', 10006);
                    }
                    $ins_arr = [];
                    foreach ($ins_role_menu as $v){
                        $role_menu_id[] = $v['id'];
                        $ins_arr[] = ['role_id'=>$params['id'],'menu_id'=>$v['id']];
                    }

                    if(!$ins_arr){
                        throw new \think\Exception('生成权限数据不能为空', 10006);
                    }
                    $re_ins_all = Db::name('y_role_menu')->insertAll($ins_arr);
                    if($re_ins_all != count($ins_role_menu)){
                        throw new \think\Exception('修改权限失败', 10006);
                    }

                    //修改权限的菜单，需要清除用户菜单缓存数据  根据标签清除
                    Cache::tag('menu_role')->clear();
                }

                if($update_arr){
                    $re_up_role = Db::name('y_role')->where('id',$params['id'])->update($update_arr);
                    if(!$re_up_role){
                        throw new \think\Exception('修改权限失败', 10006);
                    }
                }


                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();

                $msg = $e->getMessage();

//                return json(result_create_arr(new \stdClass(),$msg));
                return $this->buildFailed(ReturnCode::INVALID,$msg);
            }

//            return json(result_create_arr(['menu'=>$role_menu_id],'修改完成！',1));
            return $this->buildSuccess(['menu'=>$role_menu_id],'修改完成！');
        }
        else if($params['type'] == 3){
            //删除指定的权限
            $re_role = Db::name('y_role')->where('id',$params['id'])->field('id,is_sys')->find();
            if(!$re_role){
//                return json(result_create_arr(new \stdClass(),'查询角色失败，请确认角色是否创建！'));
                return $this->buildFailed(ReturnCode::INVALID,'查询角色失败，请确认角色是否创建！！');
            }

            if($re_role['is_sys'] == 1){
                return $this->buildFailed(ReturnCode::INVALID,'系统内置角色不能修改！');
            }

            // 启动事务
            Db::startTrans();
            try {
                $re_del_role=Db::name('y_role')->where('id',$params['id'])->delete();
                if(!$re_del_role){
                    throw new \think\Exception('删除角色失败，请重试', 10006);
                }

                $re_del_role_menu = Db::name('y_role_menu')->where('role_id',$params['id'])->delete();
                if(!$re_del_role_menu){
                    throw new \think\Exception('删除角色失败，请重试', 10006);
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                //return json(result_create_arr(new \stdClass(),'删除角色失败，请重试！'));
                return $this->buildFailed(ReturnCode::INVALID,'删除角色失败，请重试！');
            }

            //return json(result_create_arr(new \stdClass(),'删除角色成功！',1));
            return $this->buildSuccess([],'删除角色成功 ！');
        }


    }

    //获取全部角色方法
    public function get_role(){
        $re = Db::name('y_role')->order('sorting')
            ->field('id,name')
            ->select()->toArray();
//        return json(result_create_arr($re,'角色下拉框数据',1));
        return $this->buildSuccess($re,'角色下拉框数据 ！');
    }

    //修改角色【废弃】
    public function edit_role(){
        $params = $this->request->param();

        try{
            $this->validate($params,'RoleValidate.edit_role',[]);
        }catch (\Exception $e){
            $e->getMessage();
            return json(result_create_arr('',$e->getMessage()));
        }

        $re_role = \app\model\Role::where('id',$params['id'])->field('code,name')->find();

        if(!$re_role){
            return json(result_create_arr(new \stdClass(),'角色查询失败，请确认该角色是否已创建！'));
        }

        $update_data = [];

        if($re_role['code'] != $params['u_name_code']){
            $update_data['code'] = $params['u_name_code'];
        }

        if($re_role['name'] != $params['u_name']){
            $update_data['name'] = $params['u_name'];
        }

        $check_re = \app\model\Role::whereOr($update_data)->field('code,name')->find();

        if($check_re){
            if($check_re['name'] == $update_data['name']){
                $msg = '修改的角色名称以存在，请设置其他角色名称！';
            }else if($check_re['code'] == $update_data['code']){
                $msg = '修改的角色唯一编码已存在，请设置其他角色唯一编码！';
            }
            return json(result_create_arr(new \stdClass(),'修改！'));
        }

    }

    //用户获取左侧菜单
    //获取左侧菜单
    public function get_menu(){
        $uid = $this->uid;


        $params = $this->request->param();

        if($params['m_type'] == 1){
            $key = 'admin_menu_'.$uid;
            //查询缓存是否存在用户的菜单，如果存在，获取缓存
            //$re_menu = Cache::get($key);
           // if(!$re_menu){
                $re_menu = Menu::getMenuTree('admin',$uid);
                if($re_menu){
                    \app\model\Base::setTagCache($key,'menu_role',$re_menu);
                }
           // }
        }else{
            $re_menu = Menu::getMenuTree('admin',null);
        }

        //通过用户的权限来获取可以显示的左侧栏
//        return json(result_create_arr($re_menu,'菜单导航！',1));
        return $this->buildSuccess($re_menu,'菜单导航');
    }


    //删除角色

    //角色授权

}