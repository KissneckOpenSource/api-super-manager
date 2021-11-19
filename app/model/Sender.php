<?php


namespace app\model;


use think\model\concern\SoftDelete;

class Sender extends Base
{
    use SoftDelete;

    protected $table = 'y_sender';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;
}