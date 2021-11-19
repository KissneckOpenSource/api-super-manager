<?php


namespace CheckFile;


interface CheckFileInterface
{


    /**
     * 检查接口方法在副本文件中是否存在
     * @param string $path 副本文件根目录路径
     * @param int $type 接口类型  0普通  1资源
     * @param int $loc 文件的位置  1api  2wexin
     * @param startin $api_class 接口的类或类和方法【资源路由只有类普通的有类和方法】
     * @return bool
     */
    public function checkFun(string $path,int $type,int $loc,string $api_class) :bool;

    //写入指定副本文件
    public function writeCopyFile(string $path,$type,int $loc,string $api_class) :void;

}