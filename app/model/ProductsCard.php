<?php


namespace app\model;


use think\Model;
use think\model\concern\SoftDelete;

class ProductsCard extends Model
{
    use SoftDelete;

    protected $table = 'y_products_card';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;
}