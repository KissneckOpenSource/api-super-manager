<?php
declare (strict_types=1);
/**
 * 错误码统一维护
 * @since   2017/02/28 创建
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\util;

class ReturnCode {

    const SUCCESS = 1;
    const INVALID = -1;
    const DB_SAVE_ERROR = -2;
    const DB_READ_ERROR = -3;
    const CACHE_SAVE_ERROR = -4;
    const CACHE_READ_ERROR = -5;
    const FILE_SAVE_ERROR = -6;
    const LOGIN_ERROR = -7;
    const NOT_EXISTS = -8;
    const JSON_PARSE_FAIL = -9;
    const TYPE_ERROR = -10;
    const NUMBER_MATCH_ERROR = -11;
    const EMPTY_PARAMS = -12;
    const DATA_EXISTS = -13;
    const AUTH_ERROR = -14;

    const OTHER_LOGIN = -16;
    const VERSION_INVALID = -17;

    const CURL_ERROR = -18;

    const RECORD_NOT_FOUND = -19; // 记录未找到
    const DELETE_FAILED = -20; // 删除失败
    const ADD_FAILED = -21; // 添加记录失败
    const UPDATE_FAILED = -22; // 添加记录失败

    const PARAM_INVALID = -995; // 参数无效
    const ACCESS_TOKEN_TIMEOUT = -996;
    const SESSION_TIMEOUT = -997;
    const UNKNOWN = -998;
    const EXCEPTION = -999;

    const NotLogged = -11187;   //用户未关注公众号

    const NotBucket = -11188;   //用户需要增加指标

    const NotOpen = -11189;   //未开通送水服务

    const NOTTEL = -11190;   //用户手机号码已被绑定

    const REQUIRELOGIN = 401;   //用户需要重新登录



    public static function getConstants(): array {
        $oClass = new \ReflectionClass(__CLASS__);

        return $oClass->getConstants();
    }
}
