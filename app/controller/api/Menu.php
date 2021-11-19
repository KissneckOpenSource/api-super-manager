<?php
declare (strict_types = 1);

namespace app\controller\api;

use think\Request;
use app\services\MenuService;
use think\facade\Db;

class Menu extends Base
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $params = $this->request->param();
        $list = MenuService::getInstance()->index($params);
        return $this->buildSuccess($list);
    }

    /**
     * 显示创建资源表单页.
     *
     * @return \think\Response
     */
    public function create()
    {
        //
    }

    /**
     * 保存新建的资源
     *
     * @param  \think\Request  $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        $params = $this->request->param();
        $params['u_id'] = $this->uid;
        $id = MenuService::getInstance()->add($params);
        return $this->buildSuccess(['id'=>$id]);
    }

    /**
     * 显示指定的资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function read($id)
    {
        //
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request  $request
     * @param  int  $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        $params = $this->request->param();
        MenuService::getInstance()->edit($params);
        return $this->buildSuccess([]);
    }

    /**
     * 删除指定资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function delete($id)
    {
        $params = $this->request->param();
        MenuService::getInstance()->delete($params);
        return $this->buildSuccess([]);
    }

    /**
     * 获取按钮权限
     */
    public function getInfo(){
        $params = $this->request->param();
        $params['u_id']= $this->uid;
        $data = MenuService::getInstance()->getInfo($params);
        return $this->buildSuccess($data);
    }

    /**
     * 获取路由权限
     */
    public function getRoutes(){
        $params = $this->request->param();
        $params['u_id']= $this->uid;
        $data = MenuService::getInstance()->getRoutes($params);
        return $this->buildSuccess($data);
    }
}
