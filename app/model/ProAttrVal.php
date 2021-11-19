<?php


namespace app\model;


use think\Model;
use think\model\concern\SoftDelete;

class ProAttrVal extends Model
{

    use SoftDelete;
    protected $table = 'y_pro_attr_val';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;
}