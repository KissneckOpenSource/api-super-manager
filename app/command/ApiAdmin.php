<?php
declare (strict_types=1);

namespace app\command;

use app\model\ProAttrKey;
use app\model\ProAttrVal;
use app\util\ReturnCode;
use Carbon\Carbon;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Exception;
use think\facade\Db;


class ApiAdmin extends Command {
    protected function configure() {
        // 指令配置
        $this->setName('apiadmin:test')
            ->setDescription('ApiAdmin默认命令行脚本，主要用于内部测试和研究');
    }

    protected function execute(Input $input, Output $output): void {

        var_dump( dirname(__DIR__,2) );
        var_dump( dirname(__DIR__) );
        root_path();
        exit();
    }


    function generateRandomString($length = 10, $type = 1)
    {
        if ($type == 1) {

            $characters = '0123456789';
        } else {

            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }




    function getCombinationToString($val)
    {
        // 保存上一个的值
        static $res = array();
        if(empty($res))
        {
            $res = $val;
        }else{
            // 临时数组保存结合的结果
            $list = array();
            foreach ($res as $k => $v) {
                foreach ($val as $key => $value) {
                    $list[$k.'_'.$key] = $v.'_'.$value;
                }
            }
            $res = $list;
        }
        return $res;
    }
}
