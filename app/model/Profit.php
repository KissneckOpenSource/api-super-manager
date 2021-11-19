<?php


namespace app\model;

use think\model\concern\SoftDelete;
use think\model\relation\HasOne;

/**
 * 用户收益明细表
 * Class Profit
 * @package app\model
 */
class Profit extends Base
{
    use SoftDelete;

    protected $table = 'y_profit_apiadmin';

    protected $defaultSoftDelete = null;

    protected $autoWriteTimestamp = true;

    public function member(): HasOne {
        return $this->hasOne('member', 'id', 'uid');
    }

    public function questionnaireType(): HasOne {
        return $this->hasOne('questionnaireType', 'id', 'questionnaire_id');
    }
}
