<?php


namespace app\model;


use think\model\concern\SoftDelete;

class DesignColumn extends Base
{
    use SoftDelete;
    protected $table='admin_design_column';
    protected $deleteTime = 'delete_time';
}