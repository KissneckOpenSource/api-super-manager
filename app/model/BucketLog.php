<?php


namespace app\model;


use think\model\concern\SoftDelete;

class BucketLog extends Base
{
    use SoftDelete;
    protected $table = 'y_bucket_log';
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = null;

}