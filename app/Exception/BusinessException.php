<?php
declare (strict_types=1);

namespace app\Exception;

use Exception;
use Throwable;

/**
 * 业务异常抛出处理
 *
 * Class BusinessException
 * @package app\Exceptions
 */
class BusinessException extends Exception
{
    public function __construct(array $codeResponse, Throwable $previous = null)
    {
        list($code, $message) = $codeResponse;
        parent::__construct($message, $code, $previous);
    }
}
