<?php


namespace CheckFile;


use think\Exception;

class CheckFile implements CheckFileInterface
{
    /**
     * 检查接口方法在副本文件中是否存在
     * @param string $path 副本文件根目录路径
     * @param int $type 接口类型  0普通  1资源
     * @param int $loc 文件的位置  1api  2wexin
     * @param string $api_class 接口的类名称或类/方法名
     * @return bool
     */
     public function checkFun(string $path, int $type, int $loc,string $api_class): bool
     {
         // TODO: Implement checkFun() method.
         try{
             $this->check_fun($path,$type,$loc,$api_class);
         }catch (Exception $e){
             return false;
         }

         return true;
     }



    /**
     * 写入文件
     * 检查接口方法在副本文件中是否存在
     * @param string $path 需要写入方法的文件地址
     * @param int $type 接口类型  0普通  1资源
     * @param int $loc 文件的位置  1api  2wexin
     * @param string $api_class 接口的类名称或类/方法名
     * @return bool
     */
    public function writeCopyFile(string $path,$type,int $loc,string $api_class) :void{
        $temp_class = explode('/',$api_class);

        $rootPath = dirname(__DIR__,2).DIRECTORY_SEPARATOR;

        $file_loc = '';

        if($loc == 1){
            $file_loc = 'api';
        }else{
            $file_loc = 'wexin';
        }
        $path = $path.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'controller'.DIRECTORY_SEPARATOR.
            $file_loc.DIRECTORY_SEPARATOR.$temp_class[0].'.php';
        var_dump("*********************************************");
        var_dump($rootPath);
        var_dump("*********************************************");
        if(!file_exists($path)){
            if($type == 1){
                if(count($temp_class) != 1){
                    throw new Exception('添加的真实类必须是【控制器名称】');
                }
                //不存在文件创建文件
                $tplPath = $rootPath . 'install/apiFuncResources.tpl';
                $tplOriginStr = file_get_contents($tplPath);
                $tpl_param = [$file_loc,ucwords($temp_class[0]),'',lcfirst($temp_class[1])];
            }
            else{
                if(count($temp_class) != 2){
                    throw new Exception('添加的真实类必须是【控制器名称/方法名称】');
                }
                //不存在文件创建文件
                $tplPath = $rootPath . 'install/apiFunc.tpl';
                $tplOriginStr = file_get_contents($tplPath);
                $tpl_param = ['api',ucwords($temp_class[0]),'',lcfirst($temp_class[1])];
                //先创建wapi的基本base
                $apiBasePath = $rootPath . 'app/controller/wexin/Base.php';
                if(!file_exists($apiBasePath)){
                    $tpBaselPath = $rootPath . 'install/wapiBase.tpl';
                    $tplBaseOriginStr = file_get_contents($tpBaselPath);
                    $re_base_file = file_put_contents($apiBasePath,$tplBaseOriginStr);
                }
            }

            $tplOriginStr = str_replace(['{$APP_NAME}','{$CLASS_NAME}','{$CLASS_INFO}','{$FUNC_NAME}'],
                $tpl_param, $tplOriginStr);

            $re_file = file_put_contents($path,$tplOriginStr);

            if($re_file === false){
                throw new Exception('创建文件失败',ReturnCode::INVALID);
            }
        }
        else{

            if($type == 1){
                throw new Exception('资源路由文件已创建，不能重复创建！');
            }
            else{
                if(count($temp_class) != 2){
                    throw new Exception('添加的真实类必须是【控制器名称/方法名称】');
                }
                $listInfo['info'] = "";
                $tplOriginStr = "\t/**".PHP_EOL.
                    "\t *".$listInfo['info'].PHP_EOL.
                    "\t */".PHP_EOL.
                    "\tpublic function ".ucwords($temp_class[1])."(){".PHP_EOL.
                    "\t\t".'$this->request->paraam();'. PHP_EOL.
                    "\t\t//返回错误". PHP_EOL.
                    "\t\t".'//return $this->buildFailed(ReturnCode::INVALID,"错误说明");'. PHP_EOL.
                    "\t\t//返回成功". PHP_EOL.
                    "\t\t".'return $this->buildSuccess([], "成功说明");'. PHP_EOL.
                    PHP_EOL.PHP_EOL.

                    "\t}".PHP_EOL;

                $this->insertCode($path,$tplOriginStr);
            }
        }
    }

    //方法检查功能
    protected function check_fun($path,$type,$loc,$api_class){
        $temp_class = explode('/',$api_class);
        $temp_file_name = $path.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'controller'.DIRECTORY_SEPARATOR;
        if($loc == 1){
            $temp_file_name = $temp_file_name.
                'api';
        }
        else{
            $temp_file_name = $temp_file_name.
                'wexin';
        }
        $temp_file_name = $temp_file_name.DIRECTORY_SEPARATOR.$temp_class[0];
        $temp_check_name = [];
        if($type == 0){
            //普通路由需要到文件里的方法
            if(count($temp_class) != 2){
                throw new Exception('添加的真实类必须是【控制器名称/方法名称】');
            }
            $temp_check_name[] = $temp_class[1];
        }
        else{
            //检查文件 并检查资源路由的方法是否都存在
            if(count($temp_class) != 1){
                throw new Exception('添加的真实类必须是【控制器名称】');
            }
            $temp_check_name[] = 'index';
            $temp_check_name[] = 'save';
            $temp_check_name[] = 'update';
            $temp_check_name[] = 'delete';
        }
        if(file_exists($temp_file_name)){
            //检查文件存在,并检查方法是否存在
            $re_check_fun = $this->read_file_section($temp_file_name,$temp_check_name);
            if($re_check_fun){
                throw new Exception('添加的真实类已在指定的文件中存在指定的方法，请检查！');
            }
        }

    }



    /**
     * 查询文件内容存在指定的方法
     * @param $orgin_file_path 源文件的地址
     * @param array $fun_name 查询的方法名称
     * @return bool
     */
    protected function read_file_section($orgin_file_path,$fun_name){


        $file = fopen($orgin_file_path, "r");

        if(!$file){
            throw new Exception('读取的文件不存在，请检查选择的接口访问是否存在!');
        }
        $regex_arr = [];
        foreach ($fun_name as $v){
            $regex_arr[] = '/public(\s)*function(\s)*'.$v.'(\s)*/';
        }


        $return_bool = false;

        try{
            while(!feof($file))
            {
                $file_str = fgets($file);
                $isMatched = 0;
                foreach ($regex_arr as $v){
                    $isMatched = preg_match($v, $file_str, $matches);
                    if($isMatched > 0){
                        $return_bool = true;
                        break 2;
                    }
                }


            }
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        } finally {
            fclose($file);
        }



        return $return_bool;

    }


    /****
     **PHP在文件指定行数后面插入内容
     **参数$file.原始文件名
     **参数$line,在第几行插入内容
     **参数$txt,要插入的内容
     **
     * @param $file
     * @param $line
     * @param $txt
     * @return bool
     */
    public function insertCode($file,$txt) :void{
        if(!$fileContent = @file($file)){
            $data['msg']='htaccess:文件不存在';
            $data['status']='error';
            throw new \Exception('请检查文件是否创建！');

        }
        $lines = count($fileContent);
        $line = $lines-2;
        printLog("创建文件3：",[$lines],"createFile");

        $fileContent[$line].=$txt;

        $newContent = implode('',$fileContent);
        if(!file_put_contents($file,$newContent)){

            $data['msg']='htaccess:无法写入数据';
            $data['status']='error';
            throw new \Exception($data['msg']);

        }
        printLog("创建文件4：",[$newContent],"createFile");


    }

    //打印日志
    function printLog($msg,$ret,$file_name = "test")
    {
//        $rootPath = root_path();
        $rootPath = dirname(__DIR__,2);
        file_put_contents($rootPath . 'runtime/'.$file_name.'.log', "[" . date('Y-m-d H:i:s') . "] ".$msg."," .json_encode($ret).PHP_EOL, FILE_APPEND);

    }
}