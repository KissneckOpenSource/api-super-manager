<?php


namespace app\model;


use think\model\concern\SoftDelete;

class AdminSaasGroup extends Base
{
    use SoftDelete;
    protected $table = 'admin_saas_group';
    protected $deleteTime = 'delete_time';
}