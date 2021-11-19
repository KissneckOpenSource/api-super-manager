<?php
declare (strict_types=1);

namespace app\services;

use app\Exception\BusinessException;

class BaseServices
{
    protected static $instance = [];

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        if ((static::$instance[static::class] ?? []) instanceof static) {
            return static::$instance[static::class];
        }
        static::$instance[static::class] = new static();
        return static::$instance[static::class];
    }

    /**
     * @param array $response
     * @param null $info
     * @throws BusinessException
     */
    public function throwBusinessException(array $response, $info = null)
    {
        if (!is_null($info)) $response[1] = $info;
        throw new BusinessException($response);
    }

    /**
     * 日志记录
     *
     * @param $modelObj object 模型对象
     * @param $type     int 模块类型
     * @param $optionType int 操作 0:添加 1:修改 2:删除
     */
    public function eventLog($modelObj, $type, $optionType)
    {
        $modelObj->logType    = $type;
        $modelObj->optionType = $optionType;
        event('log', $modelObj);
    }

    // 产品的日志记录单独处理
    public function eventProduct($modelObj, $skuId, $field = 1, $type = 5)
    {
        $modelObj->logType = $type;
        $modelObj->field = $field;
        $modelObj->skuId = $skuId;
        event('ProductLog', $modelObj);
    }
}