<?php


namespace app\model;


use think\Model;
use think\model\concern\SoftDelete;

class ProductsData extends Model
{
    //use SoftDelete;

    protected $table = 'y_products_data';

    //protected $deleteTime = 'delete_time';

    //protected $defaultSoftDelete = "0000-00-00 00:00:00";

}