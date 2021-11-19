<?php
// 应用公共文件
use app\util\ReturnCode;
use Firebase\JWT\JWT;
use think\Exception;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Log;
use think\facade\Db;

/**
 * 2019-10-25
 * 签发jwt
 * $user 用户登录的信息的数组
 * $arr 需要绑定的参数
 */
function handlerUserLogin($arr, $exp,$is_type = 0)
{
    /**
     *  sub: 该JWT所面向的用户
     * iss: 该JWT的签发者
     * iat(issued at): 在什么时候签发的token 时间戳
     * exp(expires): token什么时候过期 时间戳
     * jti：JWT ID为web token提供唯一标识
     */

//    $token = \uuid\UuidHelper::generate()->string;

    Log::write('JWT记录：创建TOEKN arr='.json_encode($arr,JSON_UNESCAPED_UNICODE));

    $token = \UuidHelper::generate()->string;


    $jwt = [
        "jti" => $token,
        "iss" => "domain",
        "iat" => time(),
        "exp" => $exp,
        "jt"=>0,
    ];

    if($is_type == 1){
        $jwt['jt'] = 1;     //该字段表示 是否为登录 1登录 0非登录兑换
        $jwt['flag'] = time();

    }

    Log::write('JWT记录：创建TOEKN jwt='.json_encode($jwt,JSON_UNESCAPED_UNICODE));

    $jwt = array_merge($jwt, $arr);

//    Log::write('JWT记录：创建TOEKN 合并后的jwt='.json_encode($jwt,JSON_UNESCAPED_UNICODE));

    \think\facade\Log::write('加密的数据JWT：'.print_r($jwt,true));
    $jwt_key = config('app.JWT_KEY');
    return \Firebase\JWT\JWT::encode($jwt, $jwt_key);
}

/**
 * 修改 该方法为 检查token 和超时处理token的兑换和处理返回需要用户登录
 * 检测是否有存在的token缓存，如果存在则删除
 * @param $uid 用户ID
 * @param $power    用户类型
 * @param $token    token
 * @param int $type 1验证 2设置
 * @payload 解析的用户token缓存信息
 * 返回数组中
 *  //m=1 表示在其他设备登录 m=2请登录 m=3 创建token失败 m=4 创建长期token缓存失败
 */
function cache_token($uid, $token, $type = 1, $exp = null,$payload=[])
{
    //短期token
    $name = $uid . '_X_AUTH_TOKEN';

    if ($type == 1) {
////        Log::write('JWT验证');
//        if (Cache::has($name)) {
//
////            Log::write('JWT存在缓存');
//            $c_token = Cache::get($name);
////            Log::write('JWT获取的缓存TOKEN='.$c_token);
////
////            Log::write('JWT获取的验证TOKEN='.$token);
//
//            if ($c_token == $token) {
//                $c_time = time();
//                if(($c_time > $payload['iat']+config('app.MIN_TIME')) && ($c_time <$payload['exp'])){
//
////                    Log::write('JWT验证短期时间已过期，但是缓存没有过期，需要兑换返回新的TOKEN');
//
//                    $exp = time() + (config('app.MAX_TIME'));
//
//                    unset($payload['jti'],$payload['iss'],$payload['iat'],$payload['exp'],$payload['jt']);
//
//                    $new_token = handlerUserLogin($payload,$exp);
//
//                    //设置短时间过期的token保存，已应对并发问题
//                    $re_set = Cache::set($token,$new_token,20);
//
//                    if(!$re_set){
//                        throw new \think\Exception('账号已失效', 10006);
//                    }
//
//                    //header(config('app.AUTH_HEADER') . ':' . $new_token);
//
//                    //重新设置用户对应的新的token
//                    $re_token = cache_token($payload['uid'],$new_token,2,$exp);
//
//                    if(!$re_token['s']){
//                        throw new \think\Exception('账号已失效', 10006);
//                    }
//
//                    return ['s' => true, 't' => $new_token,'r'=>11];
//                }
//
//                return ['s' => true, 't' => '','r'=>1];
//
//            }
//            else{
////                Log::write('JWT获取的验证  缓存TOKEN与验证token不一致');
//                $key = config('app.JWT_KEY');
//                //解析保存在缓存中的token数据 是否为登录信息，如果是登录信息，则不允许兑换token
//                $payload_cache = (array)JWT::decode($c_token, $key, config('app.ALG'));
////                Log::write('当前缓存toekn：'.json_encode($payload_cache,JSON_UNESCAPED_UNICODE));
////                Log::write('当前的验证toekn：'.json_encode($payload,JSON_UNESCAPED_UNICODE));
//                if($payload_cache['jt'] == 1 || $payload_cache['flag'] > $payload['flag']){
//                    return ['s' => false, 't' => '', 'm' => 1];
//                }
//
//                //获取对应的token的长期时间是否过期，如果过期了返回前端需要登录，否在为对应的用户兑换新的token
//                if(!isset($payload['exp'])){
//                    Log::record('验证token，token不一致，获取token的长时间已过期！');
//                    return ['s' => false, 't' => '', 'm' => 2,'r'=>2];
//                }
//
//                if($payload['exp'] < time()){
//                    return ['s' => false, 't' => '', 'm' => 2,'r'=>2];
//                }else{
//                    //为用户兑换新的token
//                    return ['s' => true, 't' => $c_token,'r'=>11];
//                }
//
//            }
//        }
//        else {
////            Log::write('JWT获取的验证  缓存的TOKEN不存在');
//            //检查当前用户的token
//            if(!isset($payload['exp'])){
////                Log::write('验证token，token不一致，获取token的长时间已过期！');
//
//                return ['s' => false, 't' => '', 'm' => 2,'r'=>2,'token'=>''];
//            }
//
//            if($payload['exp'] < time()){
////                Log::write('请求的TOKEN已超过当前token的最大过期时间');
//
//                return ['s' => false, 't' => '', 'm' => 2,'r'=>2,'token'=>''];
//            }else{
//                //为用户兑换新的token
//                $exp = time() + (config('app.MAX_TIME'));
//
//                unset($payload['jti'],$payload['iss'],$payload['iat'],$payload['exp'],$payload['jt']);
//
//                $new_token = handlerUserLogin($payload,$exp);
//
//                //设置短时间过期的token保存，已应对并发问题
//                $re_set = Cache::set($token,$new_token,20);
//
//                if(!$re_set){
//                    throw new \think\Exception('账号已失效', 10006);
//                }
//
//
//                //重新设置用户对应的新的token
//                $re_token = cache_token($payload['uid'],$new_token,2,$exp);
//
//                if(!$re_token['s']){
//                    throw new \think\Exception('账号已失效', 10006);
//                }
//
//                return ['s' => true, 't' => $new_token,'r'=>11];
//            }
//
//
//
//        }
        if(!isset($payload['exp'])){
//                Log::write('验证token，token不一致，获取token的长时间已过期！');

            return ['s' => false, 't' => '', 'm' => 2,'r'=>2,'token'=>''];
        }

        if($payload['exp'] < time()){
//                Log::write('请求的TOKEN已超过当前token的最大过期时间');

            return ['s' => false, 't' => '', 'm' => 2,'r'=>2,'token'=>''];
        }else{
            //为用户兑换新的token
            $exp = time() + (config('app.MAX_TIME'));

            unset($payload['jti'],$payload['iss'],$payload['iat'],$payload['exp'],$payload['jt']);

            $new_token = handlerUserLogin($payload,$exp);

            //设置短时间过期的token保存，已应对并发问题
            $re_set = Cache::set($token,$new_token,20);

            if(!$re_set){
                throw new \think\Exception('账号已失效', 10006);
            }


            //重新设置用户对应的新的token
//            $re_token = cache_token($payload['uid'],$new_token,2,$exp);
            $re_token = cache_token($uid,$new_token,2,$exp);

            if(!$re_token['s']){
                throw new \think\Exception('账号已失效', 10006);
            }

            return ['s' => true, 't' => $new_token,'r'=>11];
        }

    }
    elseif ($type == 2) {

        //根据设置生成token 并且设置文件缓存时间

        if ($exp) {
            $re = Cache::set($name, $token, $exp);
        } else {
            $re = Cache::set($name, $token, 3600);
        }

        if (!$re) {
            return ['s' => false, 't' => '', 'm' => 3];
        }
        return ['s' => true, 't' => $token];

    }
//    elseif($type == 21){
//        if ($exp) {
//            $re = Cache::set($name, $token, $exp);
//        } else {
//            $re = Cache::set($name, $token, 3600);
//        }
//
//        if (!$re) {
//            return ['s' => false, 't' => '', 'm' => 4];
//        }
//        return ['s' => true, 't' => $token];
//    }
    else {
        $re = Cache::delete($name);

        return ['s' => true, 't' => '',];
    }

}

//生成订单唯一编号
function order_num(){
        //毫秒方式，发现连续调用会出现重复
////    list($s1, $s2) = explode(' ', microtime());
////    $order_num = date('YmdHis').$s2;
////    return $order_num;


//    //    list($s1, $s2) = explode(' ', microtime());
//    list($s1, $s2) = explode(' ', microtime());
////    $order_num =date('YmdHis').$s2.substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
//    $order_num =date('YmdHi').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
//    return $order_num;


//    $date_time = date('YmdHi');
//
//    $characters = '0123456789';
//
//    $randomString = '';
//    for ($i = 0; $i < 8; $i++) {
//        $randomString .= $characters[rand(0, strlen($characters) - 1)];
//    }
//    return $date_time.$randomString;

    $order_num = date('YmdHi', time()) . substr(microtime(), 2, 6) . sprintf('%03d', rand(0, 999));

    return $order_num;

}
//将表情进行转义  用于存储的时候
function emoji_encode($str){
    $strEncode = '';

    $length = mb_strlen($str,'utf-8');

    for ($i=0; $i < $length; $i++) {
        $_tmpStr = mb_substr($str,$i,1,'utf-8');
        if(strlen($_tmpStr) >= 4){
            $strEncode .= '[[EMOJI:'.rawurlencode($_tmpStr).']]';
        }else{
            $strEncode .= $_tmpStr;
        }
    }

    return $strEncode;
}

//将表情进行反转义  用于读取的时候
function emoji_decode($str){
    $strDecode = preg_replace_callback('|\[\[EMOJI:(.*?)\]\]|', function($matches){
        return rawurldecode($matches[1]);
    }, $str);
    return $strDecode;
}


/**
 * 计算sql limit的查询
 * @param $current_page  获取的页数
 * @param $limit    获取的每页显示的条数
 * @return int
 */
function limit_arr($current_page,$limit){
    $page_start = ($current_page-1)*$limit;

    return (int)$page_start;
}



//// Curl--GET请求
//function curl_get($url)
//{
//    $ch = curl_init();
//    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//    curl_setopt($ch, CURLOPT_HEADER, false);
//    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//    curl_setopt($ch, CURLOPT_URL, $url);
//    curl_setopt($ch, CURLOPT_REFERER, $url);
//    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//    $result = curl_exec($ch);
//    curl_close($ch);
//    return $result;
//}
//
//// Curl--POST请求
//function curl_post($url, $curlPost)
//{
//    $ch = curl_init();
//    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//    curl_setopt($ch, CURLOPT_HEADER, false);
//    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//    curl_setopt($ch, CURLOPT_URL, $url);
//    curl_setopt($ch, CURLOPT_REFERER, $url);
//    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//    curl_setopt($ch, CURLOPT_POST, 1);
//    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
//    $result = curl_exec($ch);
//    curl_close($ch);
//    return $result;
//}

//数组去重
function assoc_unique($arr, $key) {
    $tmp_arr = array();
    foreach ($arr as $k => $v) {
        if (in_array($v[$key], $tmp_arr)) {
            //搜索$v[$key]是否在$tmp_arr数组中存在，若存在返回true
            unset($arr[$k]);
        } else {
            $tmp_arr[] = $v[$key];
        }
    }
    sort($arr); //sort函数对数组进行排序
    return $arr;
}




//获取中文字符串首字母
function getChineseFirstLetter($str)
{
    $str= iconv("UTF-8","gb2312", $str);//编码转换
    if (preg_match("/^[\x7f-\xff]/", $str))
    {
        $fchar=ord($str{0});
        if($fchar>=ord("A") and $fchar<=ord("z") )return strtoupper($str{0});
        $a = $str;
        $val=ord($a{0})*256+ord($a{1})-65536;
        if($val>=-20319 and $val<=-20284)return "A";
        if($val>=-20283 and $val<=-19776)return "B";
        if($val>=-19775 and $val<=-19219)return "C";
        if($val>=-19218 and $val<=-18711)return "D";
        if($val>=-18710 and $val<=-18527)return "E";
        if($val>=-18526 and $val<=-18240)return "F";
        if($val>=-18239 and $val<=-17923)return "G";
        if($val>=-17922 and $val<=-17418)return "H";
        if($val>=-17417 and $val<=-16475)return "J";
        if($val>=-16474 and $val<=-16213)return "K";
        if($val>=-16212 and $val<=-15641)return "L";
        if($val>=-15640 and $val<=-15166)return "M";
        if($val>=-15165 and $val<=-14923)return "N";
        if($val>=-14922 and $val<=-14915)return "O";
        if($val>=-14914 and $val<=-14631)return "P";
        if($val>=-14630 and $val<=-14150)return "Q";
        if($val>=-14149 and $val<=-14091)return "R";
        if($val>=-14090 and $val<=-13319)return "S";
        if($val>=-13318 and $val<=-12839)return "T";
        if($val>=-12838 and $val<=-12557)return "W";
        if($val>=-12556 and $val<=-11848)return "X";
        if($val>=-11847 and $val<=-11056)return "Y";
        if($val>=-11055 and $val<=-10247)return "Z";
    }
    else
    {
        return null;
    }
}



/**
 * @author injection(injection.mail@gmail.com)
 * @var date1日期1
 * @var date2 日期2
 * @var tags 年月日之间的分隔符标记,默认为'-'
 * @return 相差的月份数量
 * @example:
$date1 = "2003-08-11";
$date2 = "2008-11-06";
$monthNum = getMonthNum( $date1 , $date2 );
echo $monthNum;
 */
function getMonthNum( $date1, $date2, $tags='-' ){

    $date1 = explode($tags,$date1);

    $date2 = explode($tags,$date2);

    return abs($date1[0] - $date2[0]) * 12 + abs($date1[1] - $date2[1]);

}


//检查字符串拼接ID是否符合开头不能是0、逗号开头，不能是0、逗号结尾，中间不能有小于等于0的数字
function check_id_str(string $data,$is_change='int')
{
    $data_arr = explode(',',$data);

    if(!$data_arr){
        throw new Exception('数据请使用英文逗号拼接！',11186);
    }

    $current_arr = current($data_arr);
    if(empty($current_arr) || (int)$current_arr <= 0 ){
        throw new Exception('数据不符合规范！',11186);
    }

    $end_arr = end($data_arr);
    if(empty($end_arr) || (int)$end_arr <= 0 ){
        throw new Exception('数据不符合规范！',11186);
    }

    foreach ($data_arr as &$v){
        if((int)$v <= 0 ){
            throw new Exception('数据不符合规范！',11186);
        }
        switch ($is_change){
            case 'int':
                $v = (int)$v;
            case  'string':
                $v = (string)$v;
        }
    }

    return $data_arr;
}


//隐藏手机号中间数字
function hidePhoneNum($mobile)
{


    $mobile = substr_replace($mobile, '****', 3, 4);
    return $mobile;
}


//生日转换为年龄
function birthday_show($birthday){
//    $age = strtotime($birthday);
//    if($age === false){
//        return false;
//    }
//    list($y1,$m1,$d1) = explode("-",date("Y-m-d",$age));
//    $now = strtotime("now");
//    list($y2,$m2,$d2) = explode("-",date("Y-m-d",$now));
//    if(!$y1) $y1 = 0;
////    var_dump($y2,$y1);die;
//    $age = $y2 - $y1;
//    if((int)($m2.$d2) < (int)($m1.$d1)){
//        $age -= 1;
//
//    }
//    return $age;
    $carbon = new \Carbon\Carbon($birthday);

    return $carbon->age;
}

/**
 * 判断手机号格式
 * @param $mobile 手机号
 * @return boolean
 */
function is_mobile($mobile = ''){
    if (preg_match("/^1[3456789]\d{9}$/", $mobile)) {
        return true;
    }else{
        return false;
    }
}


/**
 * 添加日志
 * @param $action_name 行为名称
 * $uid  用户id
 * $type 类型  1后台管理员  2用户
 * $data 用户提交的数据
 * @return boolean
 */
function journal_add($data){

    $data['data'] = json_encode($data['data']);

    if($data['type'] == 1){  //管理员

        $nickname = db::name("y_admin")->where("id",$data['uid'])->value("account");

        $data['nickname'] = $nickname;


    }elseif($data['type'] ==2){  //用户

        $nickname = db::name("y_member")->where("id",$data['uid'])->value("nickname");

        $data['nickname'] = $nickname;

    }

    $id = db::name("y_admin_user_action")->insertGetId($data);

    return $id;
}

/**
 * 判断密码格式
 * @param $password 密码
 * @return boolean
 */
function is_password($password = '',$min=6,$max=16){
    if (!ctype_alnum($password) || strlen($password) < $min || strlen($password) > $max){
        return false;
    }else{
        return true;
    }
}

/**
 * excel 导出数据
 * @param array $arr 导出数据
 * @param array $column_arr v
 * @param string $title 标题
 * @param string $upload_dir v
 * @throws \think\Exception
 */
function excel_action( array $arr,array $column_arr,string $title,string $upload_dir) :void
{
    try{
        $obj_excel = new \PHPExcel();

        $obj_excel->getActiveSheet()->calculateColumnWidths();

        $zm = ['A1','B1','C1','D1','E1','F1','G1','H1','I1','J1',
            'K1','L1','M1','N1','O1','P1','Q1','R1','S1','T1','U1','V1','W1','X1','Y1','Z1'];

        foreach ($column_arr as $k=>$v){
            $obj_excel->getActiveSheet()->SetCellValue($zm[$k], $v);
        }

        $cell_counter = 2;

        foreach ($arr as $key => $value) {
            $c_num = 0;
            foreach ($value as $k_key=>$v_value){
                $tem_num = substr($zm[$c_num], -1);
                $tem_arr = explode($tem_num,$zm[$c_num]);
                $column_zm = $tem_arr[0];
                $obj_excel->getActiveSheet()->SetCellValue($column_zm . $cell_counter, $v_value);
                $c_num++;
            }
            $cell_counter++;
        }

        foreach ($column_arr as $k=>$v){
            //获取的最后的数字
            $tem_num = substr($zm[$k], -1);
            $tem_arr = explode($tem_num,$zm[$k]);
            $column_zm = $tem_arr[0];
            $obj_excel->getActiveSheet()->getColumnDimension($column_zm)->setAutoSize(true);
        }

        $obj_excel->getActiveSheet()->setTitle($title);

        $objWriter = \PHPExcel_IOFactory::createWriter($obj_excel, 'Excel2007');

        $objWriter->save($upload_dir);

    }
    catch (\Exception $e){
        throw new \think\Exception($e->getMessage(), 10006);
    }

}

//检查是否为json格式
function is_json($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

//写入指定文件
/**
 * @param $path public后面的地址
 * @param $str 记录的内容
 */
function writeFileLog($path,$str){
    $path = root_path().'runtime/a4.log';
//    if (!file_exists($path)) {
//        mkdir($path, 0755, true);
//    }
    file_put_contents($path,$str.PHP_EOL,FILE_APPEND);
}

#=================================================================================================
#===========================================saas公共方法===========================================
#=================================================================================================
/**
 * 发送验证码
 * @param $mobile  接收短信的手机号
 * @param $type    saas 发送类型1：验证码，2：通知
 * @param null $content 短信内容
 * @param null $tpl      短信模板，如需使用其它模板，需申请
 * @param null $tag 短信签名，如需使用其它标签，需申请
 * @return mixed
 */
function sendMobile($mobile, $type, $content=null, $tpl=null, $tag=null)
{
    $param['saastoken'] = config('app.saas_token');
    $param['access_key_id'] = config('app.accessKey_id');
    $param['access_key_secret'] = config('app.access_key_secret');
    $param['sms_type'] = $type;
    if (!empty($tag)){
        $param['sms_tag'] = $tag;
    }else{
        $param['sms_tag'] = config('app.sms_tag');
    }
    $param['sms_phone'] = $mobile;
    if ($content){
        $param['sms_param'] = json_encode($content, true);
    }
    if ($tpl){
        $param['sms_tpl'] = $tpl;
    }
    $url = config('app.saas_api').'api/v1/sms';
    $data = curl_post($url,$param);

    if(!$data){
        throw new Exception('发送短信失败，请重试',11186);
    }

    $data = json_decode($data,true);

    if($data['code'] != 1){
        throw new Exception($data['msg'],11186);
    }


    $data['data'] = base64_decode($data['data']);

    return $data;
}


//pay_money 单位分
//$attach_par 自定义字段
function wx_mini_pay($re_store_order,$attach_par){
    //接入微信支付
    $params = array(
        'saastoken'   => config("app.saas_token"), //Saas平台Token，需申请
        'appid'       => config("app.wx_xcx_appid"),       //小程序AppId
        'paysecret'   => config("app.wx_xcx_secret"),   //App密钥
        'mchid'       => config("app.wx_mic"),       //普通商户号
        'paykey'      => config("app.wx_paykey"),      //商户支付密钥
        'total'       => $re_store_order['pay_money'],       //商品价格
        'ordernums'   => $re_store_order['order_num'],       //商品订单号
        'openid'      => $re_store_order['xcx_wxopenid'],      //用户微信OpenId
        'is_service'  => 0,            //是否为服务商模式，是填写：1
//        'notify_url'  => config('app.api_url').'wx_notify',   //微信支付回调地址
        'notify_url'  => $re_store_order['wx_notify'],   //微信支付回调地址
        'paybody'     => $re_store_order['name'],     //支付商品内容
        'istest'      => 0,            //是否为测试模式 1：是，0：否
        'trade_type'  => "JSAPI"       //支付类型//JSAPI APP
    );

    if($attach_par > 0){
        $params['attach_par'] = $attach_par;
    }

    $apiUrl  = config('app.saas_api')."v1/wxpayapp";
    $res     = curl_post($apiUrl, $params);
    if(!$res){
        throw new \think\Exception('获取支付响应失败，请重试！',11186);
    }
    //处理返回结果数据
    $return_data = json_decode($res,true);


    return $return_data;
}
//打印日志
function printLog($msg,$ret,$file_name = "test")
{
    $rootPath = root_path();
    file_put_contents($rootPath . 'runtime/'.$file_name.'.log', "[" . date('Y-m-d H:i:s') . "] ".$msg."," .json_encode($ret).PHP_EOL, FILE_APPEND);

}

//微信退款 saas方法
/**
 * $origin_price 源订单支付金额
 * @param $origin_price
 * @param $refund_price 退款金额,单位：分，
 * @param $refund_num 退款单号
 * @param $order_num 退款订单号
 * @return mixed
 * @throws \think\Exception
 */
function wxrefundout($origin_price,$refund_price,$refund_num,$order_num){

    $sslcert = config('app.wx_sslcert_path');
    $sslkey = config('app.wx_sslkey_path');

    if(!empty($sslcert) || !empty($sslkey)){
        $sslcert = config('app.api_url').$sslcert;
        $sslkey  = config('app.api_url').$sslkey;
    }
    $param['saastoken'] = config('app.saas_token');
    $param['appid']     = config("app.wx_xcx_appid");  //小程序或者微信APP应用AppId
    $param['paysecret'] = config("app.wx_xcx_secret");//小程序或者微信应用密钥
    $param['mchid']     = config("app.wx_mic"); //商家商户号
    $param['paykey']    = config("app.wx_paykey");//商户支付密钥

    //原支付金额，单位：分
    $param['total_price'] = $origin_price;
    //退款金额,单位：分，
    $param['refund_price'] = $refund_price;
    //原商户支付订单号
    $param['order_num'] = $order_num;
    //退款单号
    $param['refund_num'] = $refund_num;
    //cert.pem 证书必须web地址，不能中文命名
    $param['sslcert_path'] = $sslcert;
    $param['sslkey_path']  = $sslkey;

    $url  = config('app.saas_api').'v1/wxrefundout';
    $data = curl_post($url, $param);
    if(!$data){
        throw new \think\Exception('获取支付响应失败，请重试！',11186);
    }
    $result = json_decode($data,true);
    if($result['code'] != 1){
        throw new \think\Exception($result['msg'],11186);
    }
    printLog("Saas退款返回",$result,"refund");
    return $result;
}


//处理saas返回信息
function return_saas($data){

    if(!$data || $data['code'] !== 200){
        throw new \think\Exception('请求失败！',10006);
    }

    if(!$data['result']){
        throw new \think\Exception('请求返回错误！',10006);
    }
    $result = json_decode($data['result'],true);

    if($result['code'] != 1){
        throw new \think\Exception($result['msg'],10006);
    }

    return $result['data'];
}


/**
 * Curl 携带参数请求请求
 * @author 可待科技 <sxfyxl@126.com>
 */
function makeRequest($url, $params = array(),$header=[] ,$expire = 0, $extend = array(), $hostIp = '')
{
    if (empty($url)) {
        return array('code' => '100');
    }

    $_curl = curl_init();
    $_header = array(
        'Accept-Language: zh-CN',
        'Connection: Keep-Alive',
        'Cache-Control: no-cache'

    );
    if($header){
        $_header = array_merge($_header,$header);
    }
    // 方便直接访问要设置host的地址
    if (!empty($hostIp)) {
        $urlInfo = parse_url($url);
        if (empty($urlInfo['host'])) {
            $urlInfo['host'] = substr(DOMAIN, 7, -1);
            $url = "http://{$hostIp}{$url}";
        } else {
            $url = str_replace($urlInfo['host'], $hostIp, $url);
        }
        $_header[] = "Host: {$urlInfo['host']}";
    }

    // 只要第二个参数传了值之后，就是POST的
    if (!empty($params)) {
        curl_setopt($_curl, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($_curl, CURLOPT_POST, true);
    }

    if (substr($url, 0, 8) == 'https://') {
        curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($_curl, CURLOPT_SSL_VERIFYHOST, false);
    }
    curl_setopt($_curl, CURLOPT_URL, $url);
    curl_setopt($_curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($_curl, CURLOPT_USERAGENT, 'API PHP CURL');
    curl_setopt($_curl, CURLOPT_HTTPHEADER, $_header);

    if ($expire > 0) {
        curl_setopt($_curl, CURLOPT_TIMEOUT, $expire); // 处理超时时间
        curl_setopt($_curl, CURLOPT_CONNECTTIMEOUT, $expire); // 建立连接超时时间
    }else{
        curl_setopt($_curl, CURLOPT_TIMEOUT, 30); // 处理超时时间
        curl_setopt($_curl, CURLOPT_CONNECTTIMEOUT, 10); // 建立连接超时时间
    }

    // 额外的配置
    if (!empty($extend)) {
        curl_setopt_array($_curl, $extend);
    }

    $result['result'] = curl_exec($_curl);
    $result['code'] = curl_getinfo($_curl, CURLINFO_HTTP_CODE);
    $result['info'] = curl_getinfo($_curl);
    if ($result['result'] === false) {
        $result['result'] = curl_error($_curl);
        $result['code'] = -curl_errno($_curl);
    }

    curl_close($_curl);

    return $result;
}


/**
 * 微信提现接口
 * @param $data
 * @return array
 */
function wxencashment($data)
{
    $sslcert = config('app.wx_sslcert_path');
    $sslkey = config('app.wx_sslkey_path');

    if(!empty($sslcert) || !empty($sslkey)){
        $sslcert = config('app.api_url').$sslcert;
        $sslkey  = config('app.api_url').$sslkey;
    }

    $params = array(
        'saastoken'        => config('app.saas_token'),
        'appid'            => config('app.wx_xcx_appid'),
        'paysecret'        => config('app.wx_xcx_secret'),
        'mchid'            => config('app.wx_mic'),
        'paykey'           => config('app.wx_paykey'),
        'total'            => $data['total'],
        'ordernums'        => $data['ordernums'],
        'openid'           => $data['openid'],
        'sslcert_path'     => $sslcert,
        'sslkey_path'      => $sslkey,
    );
    $apiUrl  = config('app.saas_api')."v1/wxencashment";
    file_put_contents(root_path().'/runtime/bbbb.log','请求数据'.json_encode($params,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);
    Log::write('支付 saas请求参数：'.json_encode($params,JSON_UNESCAPED_UNICODE));
    return makeRequest($apiUrl,$params);
}

/**
 * JD物流查询
 * @param $no
 * @return array
 */
function JDExpressQuery($no)
{
    $params = array(
        'deptNo'        => "EBU4418046675974",
        'newWBType'     => 1,
        'saastoken'     => config('app.saas_token'),
        'no'            => $no,
    );
    $apiUrl  = config('app.saas_api')."getB2BSWbMainAllTrack";
    return makeRequest($apiUrl,$params);
}



//京东发货方法
/*
 *
 * */
function send_jd_order($order_num,$total_sum,$address_info)
{
    if(config("app.is_jd_send")){
        return 1;
    }
    $data = [
        'saastoken'            => config('app.saas_token'),
        'orderNo'              => $order_num,
        'deptNo'               => "EBU4418046675974",
        'expressItemQty'       => $total_sum,  //寄托物总箱数；单位个（非冷链专车，必填）
        'remark'               => '',       //订单备注
        'senderName'           => '何再生',    //寄件人姓名；最大长度 50（无简称时，必填）
        'senderMobile'         => '13983374077',
        'senderProvinceName'   => "重庆",  //寄件人省
        'senderCityName'       => "巴南区",  //寄件人市
        'senderCountyName'     => "南彭街道",    //寄件人区/县
        'senderAddress'        => "重庆市巴南区南彭远志达物流园",   //寄件人街道
        'receiverAddress'      => $address_info['a'],   //收件人街道；
        'receiverName'         => $address_info['n'],
        'receiverMobile'       => $address_info['t'],
        'receiverProvinceName' => $address_info['p'],
        'receiverCityName'     => $address_info['c'],
        'receiverCountyName'   => $address_info['d'],
        'grossVolume'          => "0.4",     //总体积；
        'grossWeight'          => "21",     //总重量
        'createUser'           => "阿依达桶装水",
        'senderCompany'        => "重庆阿依达太极泉水股份有限公司",
        'expressItemName'      => "桶装水",   //寄托物品名称
    ];

    $apiUrl  = config('app.saas_api')."createWbOrder";
    $res = makeRequest($apiUrl,$data);
    $res = json_decode($res['result'], true);
    $res = $res['jingdong_eclp_co_createWbOrder_responce']['CoCreateLwbResult_result'];

    if($res['resultCode'] !== 1){
        throw new \think\Exception($res["resultMsg"],11186);
    }

    $ins_water_order = [
        'shipnum'  => $res['lwbNo'],
        'logistic' => "京东物流",
        'shiptime' => date('Y-m-d H:i:s')
    ];
    $re_up_water_order = Db::name('y_water_order')->where("order_num",$order_num)->update($ins_water_order);
    if(!$re_up_water_order){
        return 2;
    }
    return 1;
}




/**刷新对应app的数据
 * $url  链接地址
 * $data 数据
*/
function refresh_app($url,$data=[],$app_url=''){

    $app_url = rtrim($app_url,'/');

    $app_api_url =  rtrim(config('app.api_url'),'/');
    Log::write('URL=='.print_r($app_url,true));
    Log::write('URL==='.print_r($app_api_url,true));
//    $url_arr = [
//        'https://mumu.api.kissneck.com.cn',
//        'https://dfds.api.fengxl.com',
//        'https://sxz.api.kissneck.cn',
//        'https://mlzg.api.kissneck.cn'
//    ];



        if($app_url != $app_api_url){
            if(!$app_url){
                throw new \think\Exception('缺少登录URL！',ReturnCode::INVALID);
            }

            $login_url = preg_replace('/\s+/', '', $app_url).'/admin/Login/index';
            Log::write('获取登录URL'.print_r($login_url,true));
            //先登录获取api_auth
            $re_login = makeRequest($login_url,['password'=>123123,'username'=>'root']);
            Log::write('获取登录返回数据'.print_r($re_login,true));
            if($re_login['code'] != 200){
                throw new \think\Exception('发送远程登录请求失败，请检查！',ReturnCode::INVALID);
            }

            $result_login = json_decode($re_login['result']);

//        Log::write('获取解析登录返回数据'.print_r($result_login,true));
            if((json_last_error() !== JSON_ERROR_NONE)){
                throw new \think\Exception('登录接收到的返回数据不是json格式！',ReturnCode::INVALID);
            }

            if((!isset($result_login->code) || $result_login->code !=1 )&& $re_login['code'] == 200){
                throw new \think\Exception('登录接收到的返回数据为操作失败！',ReturnCode::INVALID);
            }


//        Log::write('发送对外请求的url'.$url);
            Log::write('发送对外请求的参数'.print_r($data,true));

            $re = makeRequest(preg_replace('/\s+/', '', $url),$data,['Api-Auth: '.$result_login->data->apiAuth]);
            Log::write('获取返回数据'.print_r($re,true));
            if($re['code'] != 200){
                throw new \think\Exception('发送远程请求失败，请检查！',ReturnCode::INVALID);
            }

            $result = json_decode($re['result']);

            if((json_last_error() !== JSON_ERROR_NONE)){
                throw new \think\Exception('接收到的返回数据不是json格式！',ReturnCode::INVALID);
            }

            if($result->code != 1 && $re['code'] == 200){
                throw new \think\Exception('接收到的返回数据为操作失败！',ReturnCode::INVALID);
            }
        }
}


/**
 * 发送命令行指令
 * @param $type 指令类型
 * @param $project_path 切换的文件路径
 * @param string $warehouse_path 仓库路径 ssh地址
 * @return array
 * @throws Exception
 */
function send_exec($type,$project_path,$warehouse_path = '',$git_msg = '创建项目'){
    $out = [];
    $msg = '';
    switch ($type){
        case 1 :
            //克隆仓库
//            $instruction = 'git clone '.$warehouse_path.' && git checkout dev';
            $instruction = 'git clone '.$warehouse_path;
            $msg = '克隆仓库';
            break;
        case 2 :
            //拉取仓库代码
            $msg = '拉取仓库代码';
            $instruction = 'git pull';
            break;
        case 3 :
            $instruction = 'git add -A && git commit "'.$git_msg.'"';
            //推送仓库代码
            $msg = '推送仓库代码';
            break;
        case 4 :
            //git config --global user.email "test@163.com" && git config --global user.name "test"  && cat config
            //第一次推送项目
            $instruction = 'git add --all :/ && git commit -m "'.$git_msg.'" && git push ';
            //推送仓库代码
            $msg = '推送仓库代码';
            break;
        default :
            throw new Exception('未知指令！',ReturnCode::INVALID);
    }

    $shell = "cd {$project_path}/ && ".$instruction." 2>&1";
    Log::write('执行指令数据'.$shell);
    try{
        $re_exec = exec($shell,$out);
        Log::write('git执行返回命令行数据'.print_r($out,true));
        Log::write('git执行结果'.print_r($re_exec,true));
        if($type == 1){
            $git_filename_arr = explode("/",$warehouse_path);
            if(count($git_filename_arr) <= 1){
                throw new Exception('创建副本文件夹失败',ReturnCode::INVALID);
            }

            $git_filename = end($git_filename_arr);

            $git_name_arr = explode('.git',$git_filename);
            $project_path = $project_path.'/'.$git_name_arr[0];
            $instruction_c = 'git checkout dev';
            $shell_c = "cd {$project_path}/ && ".$instruction_c." 2>&1";
            $re_exec = exec($shell_c,$out_c);
            $msg = '切换分支到dev';
        }
    }catch (Exception $e){
        throw new Exception('执行【'.$msg.'】命令行错误',ReturnCode::INVALID);
    }

    return $out;
}


/**
 * 拷贝本项目到git创建的副本文件里面
 * @param $orgin_dir 源项目根目录绝对地址
 * @param $target_dir 副本项目地址
 * @param int $mode  文件夹的权限
 * @param array $witer_file app文件需要写入的文件
 * @param string $p_file_name 父级文件夹的名称
 * @return bool|string
 * @throws Exception
 */
function copy_files($orgin_dir,$target_dir,$mode = 0775,$witer_file=[],$p_file_name=''){
    //排除的文件或者文件夹名字
    $exclude = ['.','..','.git','.env','myProject','runtime','vendor','.DS_Store','.idea'];

//    echo "111".$target_dir.PHP_EOL;

    if(!is_dir($target_dir)){
        //判断有没有目录，没有则创建
        try{
            mkdir($target_dir,$mode,true);
        }catch (Exception $e){
            throw new Exception('创建副本文件夹失败！',ReturnCode::INVALID);
        }

    }
    $re_copy = '';

    $temp = scandir($orgin_dir);

    if(is_array($temp)){
        foreach ($temp as $k=>$v){
            if(in_array($v,$exclude)){
                continue;
            }
            if($p_file_name == 'api'){
                if(!in_array($v,$witer_file)){
                    continue;
                }
                $orgin_url = $orgin_dir.DIRECTORY_SEPARATOR.$v;
//            echo "222".$orgin_url.PHP_EOL;
                $target_url = $target_dir.DIRECTORY_SEPARATOR.$v;
//            echo "333".$target_url.PHP_EOL;
            }else{
                $orgin_url = $orgin_dir.DIRECTORY_SEPARATOR.$v;
//            echo "222".$orgin_url.PHP_EOL;
                $target_url = $target_dir.DIRECTORY_SEPARATOR.$v;
//            echo "333".$target_url.PHP_EOL;
            }

            if(is_dir($orgin_url)){

                copy_files($orgin_url,$target_url,0775,$witer_file,$v);
            }else{
                $re_copy = copy($orgin_url,$target_url);
                if(!$re_copy){
                    throw new Exception('克隆文件失败！',ReturnCode::INVALID);
                }
            }
        }
    }

    return $re_copy;


}


//快速读取文件指定的段落
//$orgin_file_path  源文件的地址
//$fun_name写入方法的名称
function read_file_section($orgin_file_path,$fun_name){
    $file_arr = [];

    $file = fopen($orgin_file_path, "r") or exit("读取的文件不存在，请检查选择的接口访问是否存在!");


    $regex_str = '/public(\s)*function(\s)*'.$fun_name.'(\s)*/';

    //是否开始写入
    $flag_start = 0;

    $flag_write = 0;

    while(!feof($file))
    {

        $file_str = fgets($file);

        if($flag_start == 0){
            $isMatched = preg_match($regex_str, $file_str, $matches);
            if($isMatched > 0){
                //标记为开始写
                $flag_start = 1;
            }
        }

        if($flag_start == 1){
            $isMatched = 0;
            if($flag_write > 0){
                $isMatched = preg_match($regex_str, $file_str, $matches);
            }

            if($isMatched <= 0){
                $file_arr[] =$file_str;
            }
        }
    }
    fclose($file);


}

/**
 * 树状数据搜索
 * type 匹配类型  1，模糊匹配 2，完全匹配
 * field 需要匹配的字段名
 * name 需要匹配的字段值
 * data 树状数据
k 下标（递归使用）
 */
function nameSearchTree($data,$name,$k = 0,$field = 'department_name',$type = 1){
    $list = [];

    foreach ($data as $key=>$val){
        if ($type == 1){
            $bool = strpos($val[$field],$name) !== false;
        }else{
            $bool = $val[$field] == $name;
        }
        $val['type'] = 0;
        if ($bool){
            $val['type'] = 1;//表示能匹配到搜索条件
        }
        $list[$k] = $val;
        if (isset($val['children'])){
            unset($list[$k]['children']);
            $temp = nameSearchTree($val['children'],$name,$k,$field,$type);
            if (!empty($temp)){
                $list[$k]['children'] = $temp;
            }
        }else{
            if ($bool){
                $val['type'] = 1;
                $list[$k] = $val;
            }else{
                unset($list[$k]);
            }
        }
        $k++;
    }
    return $list;
}


//删除空值
function delEmpty($data){
    $list = [];
    foreach ($data as $key=>$val){
        $list[$key] = $val;
        if ((!isset($val['children']) || empty($val['children'])) && (isset($val['type'])&&$val['type']==0)){
            unset($list[$key]);
        }
        if (isset($val['children']) && !empty($val['children'])){
            $temp = delEmpty($val['children']);
            $list[$key]['children'] = $temp;
        }
    }
    return $list;
}

//清空数组键值
function delKey($list){
    $new_lsit = [];
    foreach ($list as $key=>$val){
        $k = $val;
        if (isset($val['children'])){
            unset($k['children']);
            $data = array_values($val['children']);
            $temp = delKey($data);
            if($temp)
                $k['children'] = $temp;
            $new_lsit[] = $k;
        }else{
            $new_lsit[] = $val;
        }
    }
    return $new_lsit;
}

/**
 * 组装新增、修改数据
 * @type 0 修改  1新增
 */
function combinaData($tabel,$data=[],$type = 0){

    $new_data = [];
    if (isset($data['id']) && $type != 0){
        $info = Db::name($tabel)->where('id',$data['id'])->find();
        if (empty($info)){
            return $new_data;
        }
        unset($data['id']);
        foreach ($data as $key=>$value){
            if (isset($info[$key]) && $value != $info[$key]){
                $new_data[$key] = $value;
            }
        }
    }else{
        $sql = 'select column_name,column_comment,data_type from information_schema.columns 
            where table_name="'.$tabel.'" and table_schema="'.config('database.connections.mysql.database').'"';
        $info = Db::query($sql);
        foreach ($info as $key=>$val){
            if (isset($data[$val['column_name']])){
                $new_data[$val['column_name']] = $data[$val['column_name']];
            }
        }
    }
    return $new_data;
}

/**
 * 生成随机字符串
 */
function create_rand_stting($len, $special=false)
{
    $chars = array(
        "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
        "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
        "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
        "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
        "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
        "3", "4", "5", "6", "7", "8", "9"
    );

    if ($special) {
        $chars = array_merge($chars, array(
            "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
            "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
            "}", "<", ">", "~", "+", "=", ",", "."
        ));
    }

    $charsLen = count($chars) - 1;
    shuffle($chars);                            //打乱数组顺序
    $str = '';
    for ($i = 0; $i < $len; $i++) {
        $str .= $chars[mt_rand(0, $charsLen)];    //随机取出一位
    }
    return $str;
}







