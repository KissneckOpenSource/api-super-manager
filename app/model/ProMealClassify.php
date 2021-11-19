<?php


namespace app\model;


use think\model\concern\SoftDelete;

class ProMealClassify extends Base
{
    use SoftDelete;

    protected $table = 'y_pro_meal_classify';

    protected $deleteTime = 'delete_time';

    protected $defaultSoftDelete = null;
}