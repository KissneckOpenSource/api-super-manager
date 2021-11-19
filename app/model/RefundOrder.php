<?php


namespace app\model;


use think\model\concern\SoftDelete;

class RefundOrder extends Base
{

    use SoftDelete;

    protected $table = 'y_order_refund';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;
}