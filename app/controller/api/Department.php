<?php
declare (strict_types = 1);

namespace app\controller\api;

use think\Request;
use app\services\DepartmentService;

class Department extends Base
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $params = $this->request->param();
        $params['uid'] = $this->uid;
        $list = DepartmentService::getInstance()->index($params);
        return $this->buildSuccess($list);
    }
    /**
     * 部门列表 (非树状结构)
     */
    public function list(){
        $params = $this->request->param();
        $params['uid'] = $this->uid;
        $list = DepartmentService::getInstance()->list($params);
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
        $params['uid'] = $this->uid;
        $id = DepartmentService::getInstance()->add($params);
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
        DepartmentService::getInstance()->edit($params);
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
        DepartmentService::getInstance()->delete($params);
        return $this->buildSuccess([]);
    }
}
