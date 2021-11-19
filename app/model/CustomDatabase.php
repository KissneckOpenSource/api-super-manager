<?php


namespace app\model;


use think\model\concern\SoftDelete;

class CustomDatabase extends Base
{
    use SoftDelete;
    protected $table='admin_custom_database';
    protected $deleteTime = 'delete_time';
}