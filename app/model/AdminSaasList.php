<?php


namespace app\model;


use think\model\concern\SoftDelete;

class AdminSaasList extends Base
{
    use SoftDelete;
    protected $table = 'admin_saas_list';
    protected $deleteTime = 'delete_time';
}