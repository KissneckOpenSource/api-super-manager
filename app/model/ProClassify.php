<?php


namespace app\model;


use think\Model;

class ProClassify extends Model
{
    protected $table = 'y_pro_classify';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;
}