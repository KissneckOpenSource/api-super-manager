<?php


namespace app\model;


use think\model\concern\SoftDelete;

class MemberProfile extends Base
{
    use SoftDelete;
    protected $table = 'y_member_profile';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;
}