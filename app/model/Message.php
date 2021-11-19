<?php


namespace app\model;


use think\model\concern\SoftDelete;

class Message extends Base
{
    use SoftDelete;

    protected $table = 'y_message';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;
}