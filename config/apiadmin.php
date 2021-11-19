<?php
// +---------------------------------------------------------------------------------
// | ApiAdmin [ JUST FOR API ]
// +---------------------------------------------------------------------------------
// | Copyright (c) 2017~2020 https://www.apiadmin.org/ All rights reserved.
// +---------------------------------------------------------------------------------
// | Licensed ( https://gitee.com/apiadmin/ApiAdmin/blob/master/LICENSE.txt )
// +---------------------------------------------------------------------------------
// | Author: zhaoxiang <zhaoxiang051405@gmail.com>
// +---------------------------------------------------------------------------------

return [
    'APP_VERSION'           => '5.0',
    'APP_NAME'              => 'ApiAdmin',

    //鉴权相关
    'USER_ADMINISTRATOR'    => [1],



    //安全秘钥
    'AUTH_KEY'              => 'a9c0bcfb-373f-fc96-9e26-2efc5c3526c2',

    //后台登录状态维持时间[目前只有登录和解锁会重置登录时间]
    'ONLINE_TIME'           => 86400,
    //AccessToken失效时间
//    'ACCESS_TOKEN_TIME_OUT' => 86400,
    'ACCESS_TOKEN_TIME_OUT' => 1186400, //测试修改 为长期不失效
    'COMPANY_NAME'          => 'ApiAdmin开发维护团队',

    //跨域配置
    'CROSS_DOMAIN'          => [
        'Access-Control-Allow-Origin'      => '*',
        'Access-Control-Allow-Methods'     => 'POST,PUT,GET,DELETE,OPTIONS',
        'Access-Control-Allow-Headers'     => 'Version, Access-Token, User-Token, Api-Auth, User-Agent, Keep-Alive, Origin, No-Cache, X-Requested-With, If-Modified-Since, Pragma, Last-Modified, Cache-Control, Expires, Content-Type, X-E4M-With,X-AUTH-TOKEN,x-auth-token',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Expose-Headers'=>'X-AUTH-TOKEN,x-auth-token'
    ],

    //后台列表默认一页显示数量
    'ADMIN_LIST_DEFAULT'    => 20,
];
