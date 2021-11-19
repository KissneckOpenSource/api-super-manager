<?php


namespace app\model;


use think\Model;
use think\model\concern\SoftDelete;

class ProAttrKey extends Model
{
    use SoftDelete;
    protected $table = 'y_pro_attr_key';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;

}