<?php


namespace app\model;


use think\model\concern\SoftDelete;

class MemberInvoice extends Base
{
    use SoftDelete;
    protected $table = 'y_member_invoice';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = null;


}