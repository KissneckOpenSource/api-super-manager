<?php


namespace app\model;


use think\model\concern\SoftDelete;

class DesignDatabase extends Base
{
    use SoftDelete;
    protected $table='admin_design_database';
    protected $deleteTime = 'delete_time';
}