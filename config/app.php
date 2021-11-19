<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 应用地址
    'app_host'         => env('app.host', ''),
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 默认应用
    'default_app'      => 'index',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 应用映射（自动多应用模式有效）
    'app_map'          => [],
    // 域名绑定（自动多应用模式有效）
    'domain_bind'      => [],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list'    => [],

    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'    => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'   => true,

// +----------------------------------------------------------------------
// | 自定义设置
// |
// |设置规范：设置域名 统一使用/结尾
// |
// |
// +----------------------------------------------------------------------

    //是否运营更新的代码 0不运行 1运行推送更新指令  2运行git操作更新仓库
    'is_update'=>1,
    'is_power'=> 0,// 默认0不开启  1 开启

//=============================================项目相关配置===========================================================
    'is_debug'=>env('myconfig.is_debug',1),



//=============================================JWT域名相关配置===========================================================


//设置token时间
    'MIN_TIME' => 60*60*24,
    //token 过期最长时间
//    'MAX_TIME' => 60*60*1000,
    'MAX_TIME' => 60*60*24*15,

    //设置的header的token键名称
    'AUTH_HEADER'=>'X-AUTH-TOKEN',

    //token 设置的加密方式
    'ALG' => array('HS256'),

    'JWT_KEY'=>'24680KeDaiSoft20201211FBc',

//=============================================项目域名相关配置===========================================================
    //接口系统平台网络地址
    'sys_api_url'=>'https://apism.kissneck.com/',

    'api_url'=>env('myconfig.api_url','https://apism.kissneck.com/'),
    //当前字段是检查是否为本服务的相关请求 不能删除必须设置正确
    'verify_host'=>env('myconfig.verify_host',''),

//=============================================微信公众号相关配置==========================================================
    //显示验证是否关注公众号显示的公众号外部url地址
    'wx_gzh_wurl'=>env('myconfig.wx_gzh_wurl',''),

    //微信公众号APPid
    'wx_gzh_appid'=>env('myconfig.wx_gzh_appid',''),
    //微信公众号密钥
    'wx_gzh_secret'=>env('myconfig.wx_gzh_secret',''),
    //公众号领取红包模板
    'wx_gzh_tem'=>env('myconfig.wx_gzh_tem',''),

//=============================================微信小程序相关配置==========================================================
    //微信小程序APPid
    'wx_xcx_appid'=>env('myconfig.wx_xcx_appid',''),
    //微信小程序密钥
    'wx_xcx_secret'=>env('myconfig.wx_xcx_secret',''),
    //小程序海报扫码跳转小程序地址
    'wx_mini_postImage'=>env('myconfig.wx_mini_postImage',''),



    //小程序检查用户是否已关注公众号检测模板
    'wx_mini_tem'=>env('myconfig.wx_mini_tem',''),
//=============================================微信商户相关配置==========================================================
    //微信支付 微信商户号
    'wx_mic'=>env('myconfig.wx_mic',''),
    //微信支付 App应用商户密钥
    'wx_paykey'=>env('myconfig.wx_paykey',''),
    //微信发送红包 服务器IP
    'client_ip'=>env('myconfig.client_ip',''),


    'wx_sslcert_path'=>env('myconfig.wx_sslcert_path',''),

    'wx_sslkey_path'=>env('myconfig.wx_sslkey_path',''),



];
