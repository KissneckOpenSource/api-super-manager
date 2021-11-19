<?php


namespace app\model;

use think\model\concern\SoftDelete;

/**
 * 用户表
 * Class Member
 * @package app\model
 */
class Member extends Base
{
    use SoftDelete;

    protected $table = "y_member";

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;
}
