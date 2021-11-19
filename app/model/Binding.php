<?php


namespace app\model;


use think\model\concern\SoftDelete;

class Binding extends Base
{
    protected $table = 'y_binding';
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = null;

}