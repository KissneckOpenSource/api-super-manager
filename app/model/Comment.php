<?php


namespace app\model;


use think\Model;
use think\model\concern\SoftDelete;

class Comment extends Model
{
    use SoftDelete;
    protected $table = 'y_comment';
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = null;
}