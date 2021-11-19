<?php


namespace app\model;


use think\model\concern\SoftDelete;

class ProCategory extends Base
{
    use SoftDelete;
    protected $table = 'y_pro_category';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;
}