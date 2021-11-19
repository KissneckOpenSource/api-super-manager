<?php

namespace app\controller\{$APP_NAME};

use app\util\ReturnCode;

class {$CLASS_NAME} extends Base
{

    /**
     * {$CLASS_INFO}
     */
    public function {$FUNC_NAME}(){
        $params = $this->request->param();

        //返回错误
        //return $this->buildFailed(ReturnCode::INVALID,"错误说明");
        //返回成功
        //return $this->buildSuccess([], "成功说明");
    }

}