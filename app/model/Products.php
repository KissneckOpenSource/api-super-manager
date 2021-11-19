<?php


namespace app\model;


use think\Model;
use think\model\concern\SoftDelete;

class Products extends Model
{
    use SoftDelete;

    protected $table = 'y_products';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;



}