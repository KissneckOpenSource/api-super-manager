<?php

namespace app\controller\{$APP_NAME};

use app\util\ReturnCode;

class {$CLASS_NAME} extends Base
{

    /**
     * 资源路由列表
     * 请求 GET
     */
    public function index(){
        $params = $this->request->param();
        //返回错误
        //return $this->buildFailed(ReturnCode::INVALID,"错误说明");
        //返回成功
        return $this->buildSuccess([], "成功说明");
    }

    /**
    * 资源路由保存
    * 请求 POST
    */
    public function save(){
        $params = $this->request->param();
        return $this->buildSuccess([], "成功说明");
    }

    /**
    * 资源路由更新
    * 请求 PUT
    */
    public function update($id){
        $params = $this->request->param();
        return $this->buildSuccess([], "成功说明");
    }

    /**
    * 资源路由删除
    * 请求 DELETE
    */
    public function delete($id){
        $params = $this->request->param();
        return $this->buildSuccess([], "成功说明");
    }

}