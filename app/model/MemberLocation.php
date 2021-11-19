<?php


namespace app\model;


use think\model\concern\SoftDelete;

class MemberLocation extends Base
{
    use SoftDelete;
    protected $table = 'y_member_location';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;

}