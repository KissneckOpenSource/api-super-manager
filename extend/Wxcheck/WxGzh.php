<?php
namespace Wxcheck;

use app\BaseController;
//use think\Controller;
use think\App;
use think\Db;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Log;
use think\facade\View;

define("TOKEN", "wxsaas");
define("APP_ID", "wx413594f7fc15b9bc");
define("APP_SCR", "29e309954cd9ccab0363b0d1d4c21df3");

class WxGzh extends BaseController
{
    private $apiData;
//    private $miniAppid = "wxd453e758f8c3715b";
    private $miniAppid;

    private $APP_ID;

    private $APP_SCR;

    public function __construct() {
//        parent::__construct($app);
//        $this->userInfo = '';
        //这部分初始化用户信息可以参考admin模块下的Base去自行处理
        $this->APP_ID = config('app.wx_gzh_appid');
        $this->APP_SCR = config('app.wx_gzh_secret');
        $this->miniAppid = config('app.wx_xcx_appid');
    }
//    public function initialize()
//    {
//        $this->APP_ID = config('app.wx_gzh_appid');
//        $this->APP_SCR = config('app.wx_gzh_secret');
//        $this->miniAppid = config('app.wx_xcx_appid');
//    }

    //获取公共号用户信息
    private function getOpenidInfo($openid = "")
    {
        $access_token = $this->getWxtoken();
        $send_url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".
            $access_token."&openid=".$openid."&lang=zh_CN";
        $re = curl_get($send_url);
        return json_decode($re,true);
    }


    private function errorLog($msg,$ret)
    {
        $rootPath = root_path();

        file_put_contents($rootPath . 'runtime/gzh.log', "[" . date('Y-m-d H:i:s') . "] ".$msg."," .
            json_encode($ret).PHP_EOL, FILE_APPEND);

    }

    public function decodeUnicode($str)
    {
        return preg_replace_callback('/\\\\u([0-9a-f]{4})/i',
            function($matches) { return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE"); },
        $str);
    }

    public function index()
    {
        $wechatObj = new wechatCallbackapiTest1();
        //$wechatObj->valid();
        $postdata = file_get_contents("php://input");
        if (empty($postdata)){
            echo ""; exit;
        }
        //将发挥过来的XML进行解析
        $this->messsageType($postdata);

    }

    /*  微信公众号服务端-事件类型
        1 关注/取消关注事件
        2 扫描带参数二维码事件
        3 上报地理位置事件
        4 自定义菜单事件
        5 点击菜单拉取消息时的事件推送
        6 点击菜单跳转链接时的事件推送
    */
    public function messsageEvent($postObj)
    {
        $event_content = $postObj["Event"];
        if($event_content == "subscribe"){
            $rel = $this->getOpenidInfo($postObj["FromUserName"]);
            $this->errorLog("微信公众号关注通知",$rel);
            $text_params = array(
                'touser'       => $postObj["FromUserName"],
                'msgtype'      => "text",
                'text' => array(
                    "content"          => "感谢您关注店掌宝！",
                )
            );

            $mini_params = array(
                'touser'       => $postObj["FromUserName"],
                'msgtype'      => "miniprogrampage",
                'miniprogrampage' => array(
                    "title"          => "点击体验店掌宝",
                    "appid"          => $this->miniAppid,
                    "pagepath"       => "pages/tabBar/index/index",
                    "thumb_media_id" => "sLF7cqyWJFpQLoXAgfyyh_Q2w2Yj0Lxd4ABk5w4TFmQ",
                )
            );

            $this->sendMessage($text_params);
            $this->sendMessage($mini_params);
        }
        else if($event_content == "unsubscribe"){
            $rel = $this->getOpenidInfo($postObj["FromUserName"]);
            $this->errorLog("微信公众号取消关注通知",$rel);

        }

    }

    /*  微信公众号服务端-消息类型
        现支持回复文本、图片、图文、语音、视频、音乐
    */
    public function messsageType($postdata)
    {
        libxml_disable_entity_loader(true);
        $postArg = json_decode(json_encode(simplexml_load_string($postdata, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $msg_type = $postArg["MsgType"];
        $this->errorLog("微信公众号服务端3",$postArg);
        $this->errorLog("微信公众号服务端4",$msg_type);

        //用户发送的消息类型判断
        switch ($msg_type)
        {
            case "text":    //文本消息
                return array('type'=>'text','msg'=>'文本','obj'=>$postArg);
                break;
            case "image":   //图片消息
                return array('type'=>'image','msg'=>'图片','obj'=>$postArg);
                break;

            case "voice":   //语音消息
                return array('type'=>'voice','msg'=>'语音','obj'=>$postArg);
                break;
            case "video":   //视频消息
                return array('type'=>'video','msg'=>'视频','obj'=>$postArg);
                break;
            case "location"://位置消息
                return array('type'=>'location','msg'=>'位置','obj'=>$postArg);
                break;
            case "link":    //链接消息
                return array('type'=>'link','msg'=>'链接','obj'=>$postArg);
                break;
            case "event":    //事件消息
                $this->messsageEvent($postArg);
                break;
            default:
                return array('type'=>'unknow msg type','msg'=>'未知','obj'=>$postArg);
                break;
        }

    }

    //发送消息接口
    public function sendMessage($params)
    {
        $access_token = $this->getWxtoken();
        $data_string = $this->decodeUnicode(json_encode($params));
        $send_url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=".$access_token;
        $re = curl_post($send_url,$data_string);
        $this->errorLog("微信公众号消息结果反馈",$re);
        return $re;
    }

    //获取微信公众号Token
    public function getWxtoken()
    {

        $gzhtoken = Cache::get('wx_gzhtoken');


        if($gzhtoken){
            $token = $this->getupdateAccessToken($gzhtoken);
        }
        else{
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->APP_ID.
                "&secret=".$this->APP_SCR;
            $token = $this->curl_info($url);
            if(!isset($token["access_token"])){
                throw new \Exception('token fail',11186);

            }
            $current_time = time();
            $new_ex_time = $current_time + 7000;

            $cache_dta = [
                'token'        => $token["access_token"],
                'create_time'  => $current_time,
                'expires_time' => $new_ex_time,
            ];
            Cache::set('wx_gzhtoken',$cache_dta,$token["expires_in"]);
            $token = $token["access_token"];
        }

        return $token;

    }

    /*
     **** template 模板消息
     **/
    //获取模板消息列表
    public  function getTemplateList()
    {
        $access_token = $this->getWxtoken();
        $list = curl_get("https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token=".$access_token);
        return $list;
    }

    //发送模板消息

    /**
     * @param $par  模板数据数组  ['']
     * @param $template_id 模板ID
     * @param $open_id 接收用户公众号openid
     * @param $url 跳转地址
     * @return bool|string
     */
    public function sendTemplateMessage($par,$template_id,$open_id,$url='') :void
    {
        if(!$open_id){
            //用户未关注公众号
            throw new \Exception('请关注公众号！',11187);
        }

//        $data = [
//            "touser" => $open_id, //对方的openid，前一步获取
////            "template_id" => "PkPXqOVGp8aqD64N_8oXGUlsv4OpRzf0rSRmDFhCPkM", //模板id
//            "template_id" => $template_id, //模板id
//            "miniprogram" => [
//                "appid" => "", //跳转小程序appid
////                "pagepath" => "pages/index/index"
//                "pagepath" => ""
//            ],
//            //跳转小程序页面
//            "data" => [
//                "first" => [
////                    "value" => "商家体验申请", //自定义参数
//                    "value" => $par['first'], //自定义参数
//                    "color" => '#173177'//自定义颜色
//                ],
//                "keyword1" => [
//                    "value" => $par["keyword1"], //申请名称
//                    "color" => '#173177'//自定义颜色
//                ],
//
//                "keyword2" => [
//                    "value" => $par["keyword2"], //申请人
//                    "color" => '#173177'//自定义颜色
//                ],
//                "keyword3" => [
//                    "value" => $par["keyword3"], //申请类型
//                    "color" => '#173177'//自定义颜色
//                ],
//                "keyword4" => [
//                    "value" => date("Y-m-d H:i:s",time()), //申请时间
//                    "color" => '#173177'//自定义颜色
//                ],
//                "remark"   => [
//                    "value" => $par["remark"], //自定义参数
//                    "color" => '#173177'//自定义颜色
//                ],
//            ]
//        ];
        $data = [
            "touser" => $open_id, //对方的openid，前一步获取
            "template_id" => $template_id, //模板id
            "miniprogram" => [
                "appid" => "", //跳转小程序appid
//                "pagepath" => "pages/index/index"
                "pagepath" => ""
            ],
        ];

        //跳转小程序页面


        $tem_data = [];

        if(isset($par['first']) && !empty($par['first'])){
            $tem_data['first'] = [
                "value" => $par['first'], //自定义参数
                "color" => '#173177'];
        }

        if(isset($par['keyword1']) && !empty($par['keyword1'])){
          $tem_data['keyword1'] = [
              "value" => $par["keyword1"], //申请名称
              "color" => '#173177'
          ];     //自定义颜色
        }

        if(isset($par['keyword2']) && !empty($par['keyword2'])){
        $tem_data['keyword2'] = [
            "value" => $par['keyword2'], //自定义参数
            "color" => '#173177'];
        }

        if(isset($par['keyword3']) && !empty($par['keyword3'])){
          $tem_data['keyword3'] = [
              "value" => $par['keyword3'], //自定义参数
              "color" => '#173177'];
        }

        if(isset($par['keyword4']) && !empty($par['keyword4'])){
            $tem_data['keyword4'] = [
                "value" => $par['keyword4'], //自定义参数
                "color" => '#173177'];
        }

        if(isset($par['remark']) && !empty($par['remark'])){
            $tem_data['remark'] = [
                "value" => $par['remark'], //自定义参数
                "color" => '#173177'];
        }

        if($tem_data){
            $data['data'] = $tem_data;
        }

        if($url){
            $data['url'] = $url;
        }

        $access_token = $this->getWxtoken();

        // 发送模板消息接口
        $msg_url="https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token;

        $result = curl_post($msg_url,json_encode($data));

        $result = json_decode($result,true);

        Log::write("access_token::::::".print_r($result,true));

        if(isset($result['errcode']) && $result['errcode'] !=0 && $result['errmsg'] != 'ok'){
            $is_error = 1;
            if($result['errcode'] == 40001 || $result['errcode'] == 400014 ){
                Log::record('出现40001或400014');

                $i = 0;
                while($i <4){
                    $result = $this->rm_access_token($data);
                    Log::write("出现40001或400014的数据返回第".$i."::::::".json_encode($result));
                    if(!isset($result['errcode'])){
                        $is_error = 0;
                        break;
                    }
                    $i++;
                }

            }
            if($is_error){
                throw new \Exception('发送微信模板消息超时！',11186);
            }

        }
//        return true;
    }

    //重新发送模板消息
    private function rm_access_token($data){

        $access_token = $this->getWxtoken();

        // 发送模板消息接口
        $msg_url="https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token;

        $result = curl_post($msg_url,json_encode($data));

        $result = json_decode($result,true);
    }

    //创建自定义菜单
    public function createCustomMenu()
    {
        $data = '{
            "button":[
                {
                    "type":"view",
                    "name":"店掌宝",
                    "url":"https://h5.dylm.kissneck.com/gzh/#/firstMenu/0/"
                },
                {
                    "type":"view",
                    "name":"APP",
                    "url":"https://h5.dylm.kissneck.com/gzh/#/manager/"
                },
                {
                    "type":"miniprogram",
                    "name":"小程序",
                    "url":"https://market.cloud.tencent.com/channel/fresh#/",
                    "appid":"wxd453e758f8c3715b",
                    "pagepath":"pages/tabBar/index/index"
                }
            ]
        }';

        $access_token = $this->getWxtoken();
        $msg_url="https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token;
        return $return = curl_post($msg_url,$data);
    }

    //添加小程序卡片素材
    public function setMiniGuideCard()
    {
        $par = input();
        $miniAppid = isset($par["appid"]) ? $par["appid"] : $this->miniAppid;
        $data = [
            "media_id" => $par["media_id"],
            "type"     => 0,
            "title"    => $par["title"],
            "path"     => $par["path"],
            "appid"    => $miniAppid,
        ];
        $access_token = $this->getWxtoken();
        $data_string = json_encode($data);
        $send_url = "https://api.weixin.qq.com/cgi-bin/guide/setguidecardmaterial?access_token=".$access_token;
        $re = curl_post($send_url,$data_string);
        return $re;
    }


    //添加图片素材
    public function setImageGuideCard()
    {
        $par = input();
        $miniAppid = isset($par["appid"]) ? $par["appid"] : $this->miniAppid;
        $data = [
            "media_id" => $par["media_id"],
            "type"     => 0,
            "title"    => $par["title"],
            "path"     => $par["path"],
            "appid"    => $miniAppid,
        ];
        $access_token = $this->getWxtoken();
        $data_string = json_encode($data);
        $send_url = "https://api.weixin.qq.com/cgi-bin/guide/setguideimagematerial?access_token=".$access_token;
        $re = curl_post($send_url,$data_string);
        return $re;
    }

    //上传临时素材文件
    public function uploadTempMedia()
    {
        $image_name   = input("image_name"); //该文件已在服务器 //
        $access_token = $this->getWxtoken();
        $send_url     = "https://api.weixin.qq.com/cgi-bin/media/upload?access_token=" . $access_token . "&type=image";
        $filename     = realpath($image_name);

        p($filename);

        $re           = $this->httpPost($send_url, $filename);
        p($re);
        return $re;
    }

    public function httpPost($url, $data)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
        $data = array('media' => new \CURLFile($data));//>=5.5

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "TEST");
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    //获取素材列表
    public function getMaterialList()
    {
        $par = input();
        $type = isset($par["type"]) ? $par["type"] : "image";
        $offset = isset($par["offset"]) ? $par["offset"] : 0; //起始页数
        $count  = isset($par["count"]) ? $par["count"] : 10;  //显示数量最大20
        $data = [
            "type"     => $type,
            "offset"   => $offset,
            "count"    => $count,
        ];
        $access_token = $this->getWxtoken();
        $data_string = json_encode($data);
        $send_url = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token=".$access_token;
        $re = curl_post($send_url,$data_string);
        return $re;
    }



    //更新微信token
    public function getupdateAccessToken($gzhtoken)
    {
        $token_expires = $gzhtoken['expires_time'];
        $current_time  = time();
        if ($token_expires <= $current_time) {
            $new_ex_time = $current_time + 7000;

            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->APP_ID.
                "&secret=".$this->APP_SCR;


            $token = $this->curl_info($url);
            if (isset($token["access_token"])) {

                $cache_dta = [
                    'token'        => $token["access_token"],
                    'create_time'  => $current_time,
                    'expires_time' => $new_ex_time,
                ];
                Cache::set('wx_gzhtoken',$cache_dta,$token["expires_in"]);
                return $token["access_token"];
            } else {

                throw new \Exception('解析微信token失败！',11186);
            }

        }

        return $gzhtoken['token'];

    }



    //获取微信信息
    public function getWxOpenId($code,$state)
    {
        $appid = $this->APP_ID;
        $secret = $this->APP_SCR;
//        $code  = input('code');
//        $state = input('state');
        //通过code获得openid
        if (!isset($code)) {
            //触发微信返回code码
            $siteUrl = config('website');
            $baseUrl = urlencode($siteUrl . '/getwxinfo');
            $url = $this->_CreateOauthUrlForCode($baseUrl,$state);
            return redirect($url);
        } else {
            $openid   = $this->getOpenid($code);

            $arg = [
                'open_id'     => $openid,
                'create_time' => time(),
                'appid'       => $appid,
                'type'        => $state,
            ];
            $tableWhere = Db::name('wx_gzhopenid')->where("open_id",$openid)->where("appid",$appid);
            $r = false;
            $find = $tableWhere->find();
            if(!$find){
                $r = Db::name('wx_gzhopenid')->insertGetId($arg);
            }
            $alert = "绑定成功";
            if(!$r){ $alert = "已绑定"; }
            echo "<script> alert('".$alert."'); </script>";
        }
    }

    /**
     *
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return string
     */
    private function _CreateOauthUrlForCode($redirectUrl,$state)
    {
        $urlObj["appid"] = $this->APP_ID;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = $state . "#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    public function getOpenid($code)
    {
        $url = $this->__CreateOauthUrlForOpenid($code);
        //初始化curl
        $ch = curl_init();
        $curlVersion = curl_version();
        $ua = "WXPaySDK/3.0.9 (" . PHP_OS . ") PHP/" . PHP_VERSION . " CURL/" . $curlVersion['version'];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        //运行curl，结果以jason形式返回
        $res = curl_exec($ch);
        curl_close($ch);
        //取出openid
        $data = json_decode($res, true);
        $openid = isset($data['openid']) ? $data['openid'] : "";
        if (!empty($openid)) {
            $this->apiData = $data;
            return $openid;
        }
        throw new \Exception('openid 获取失败',11186);
//        else {
//            return json(["code" => 2, 'data' => $data, "msg" => "openid 获取失败"]);
//        }
    }


    /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return string
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v) {
            if ($k != "sign") {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     *
     * @return string
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = APP_ID;
        $urlObj["secret"] = APP_SCR;
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?" . $bizString;
    }


    private function curl_info($url) {
        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$secret);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        // curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Errno'.curl_error($ch);
        }
        curl_close($ch);
        $arr= json_decode($tmpInfo,true);
        return $arr;
    }

}


//class wechatCallbackapiTest1{
//
//
//
//    public function valid(){
//        $echoStr = $_GET["echostr"];
//
//        //valid signature , option
//        if($this->checkSignature()){
//            echo $echoStr;
//            //$this->responseMsg();
//            exit;
//        }
//    }
//    private function checkSignature(){
//        // you must define TOKEN by yourself
//        if (!defined("TOKEN")) {
//            echo "token"; exit;
//        }
//
//        $signature = $_GET["signature"];
//        $timestamp = $_GET["timestamp"];
//        $nonce = $_GET["nonce"];
//
//        $token = TOKEN;
//        $tmpArr = array($token, $timestamp, $nonce);
//        // use SORT_STRING rule
//        sort($tmpArr, SORT_STRING);
//        $tmpStr = implode( $tmpArr );
//        $tmpStr = sha1( $tmpStr );
//
//        if( $tmpStr == $signature ){
//            return true;
//        }else{
//            return false;
//        }
//    }
//
//    function curl_info($url) {
//        $ch = curl_init();
////        curl_setopt($ch, CURLOPT_URL, "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$secret);
//        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
//        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
//        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
//        // curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        $tmpInfo = curl_exec($ch);
//        if (curl_errno($ch)) {
//            echo 'Errno'.curl_error($ch);
//        }
//        curl_close($ch);
//        $arr= json_decode($tmpInfo,true);
//        return $arr;
//    }
//
//
//    function get_by_curl($url,$post = false){
//        $ch = curl_init();
//        curl_setopt($ch,CURLOPT_URL,$url);
//        curl_setopt($ch, CURLOPT_HEADER, 0);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//        if($post){
//            curl_setopt($ch, CURLOPT_POST, 1);
//            curl_setopt($ch, CURLOPT_POSTFIELDS,$post);
//        }
//        $result = curl_exec($ch);
//        curl_close($ch);
//        return $result;
//    }
//
//    function trimString($value)
//    {
//        $ret = null;
//        if (null != $value)
//        {
//            $ret = $value;
//            if (strlen($ret) == 0)
//            {
//                $ret = null;
//            }
//        }
//        return $ret;
//    }
//    /**
//     * 作用：产生随机字符串，不长于32位
//     */
//    public function createNoncestr( $length = 32 )
//    {
//        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
//        $str ="";
//        for ( $i = 0; $i < $length; $i++ ) {
//            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
//        }
//        return $str;
//    }
//
//
//    function keyrep($key) {
//        //return $key;
//        if ($key == '嗨' || $key == '在吗' || $key == '你好') {
//            $mt = mt_rand(1, 17);
//            $array = array(
//                1 => '自杀中，稍后再说...',
//                2 => '有事找我请大叫！',
//                3 => '我正在裸奔，已奔出服务区',
//                4 => '我现在位置：WC； 姿势：下蹲； 脸部：抽搐； 状态：用力中。。。。',
//                5 => '去吃饭了，如果你是帅哥，请一会联系我，如果你是美女...............就算你是美女，我也要先吃饱肚子啊',
//                6 => '洗澡中~谢绝旁观！！^_^0',
//                7 => '有熊出?]，我去诱捕，尽快回来。',
//                8 => '你好，我是500，请问你是250吗？',
//                9 => '喂！乱码啊，再发',
//                10 => ' 不是我不理你，只是时间难以抗拒！',
//                11 => '你刚才说什么，我没看清楚，请再说一遍！',
//                12 => '发多几次啊~~~发多几次我就回你。',
//                13 => '此人已死，有事烧纸！',
//                14 => '乖，不急哦&hellip;',
//                15 => '你好.我去杀几个人,很快回来.',
//                16 => '本人已成仙?有事请发烟?佛说有烟没火成不了正果?有火没烟成不了仙。',
//                17 => ' 你要和我说话？你真的要和我说话？你确定自己想说吗？你一定非说不可吗？那你说吧，这是自动回复，反正我看不见其实我在~就是不回你拿我怎么着？'
//            );
//            return $array[$mt];
//        }
//        if ($key == '靠' || $key == '啊' || $key == '阿') {
//            $mt = mt_rand(1, 19);
//            $array = array(
//                1 => '人之初?性本善?玩心眼?都滚蛋。',
//                2 => '今后的路?我希望你能自己好好走下去?而我  坐车',
//                3 => '笑话是什么?就是我现在对你说的话。',
//                4 => '人人都说我丑?其实我只是美得不明显。',
//                5 => 'A;猪是怎么死的?B;你还没死我怎么知道',
//                6 => '奥巴马已经干掉和他同姓的两个人?奥特曼你要小心了。 ',
//                7 => '有的人活着?他已经死了?有的人活着?他早该死了。',
//                8 => '"妹妹你坐船头?哥哥我岸上走"据说很傻逼的人看到都是唱出来的。',
//                9 => '我这辈子只有两件事不会?这也不会?那也不会。',
//                10 => ' 过了这个村?没了这个店?那是因为有分店。',
//                11 => '我以为你只是个球?没想到?你真是个球。',
//                12 => '你终于来啦，我找你N年了，去火星干什么了？我现在去冥王星，回头跟你说个事，别走开啊',
//                13 => '你有权保持沉默，你所说的一切都将被作为存盘记录。你可以请代理服务器，如果请不起网络会为你分配一个。',
//                14 => '本人正在被国际刑警组织全球范围内通缉，如果您有此人的消息，请拨打当地报警电话',
//                15 => '洗澡中~谢绝旁观！！^_^0',
//                16 => '嘀，这里是移动秘书， 美眉请再发一次，我就与你联系；姐姐请再发两次，我就与你联系；哥哥、弟弟就不要再发了，因为发了也不和你联系！',
//                17 => ' 其实我在~就是不回你拿我怎么着？',
//                18 => '你刚才说什么，我没看清楚，请再说一遍！',
//                19 => '乖，不急。。。'
//            );
//        }
//        if( $key == "查电话" ){
//
//            return "联系电话：18171267166";
//        }
//
//        if($key == "查地址"){
//
//            return "公司地址：湖北省武汉市珞狮路南国大家装A2-1808";
//
//        }
//
//        if( $key == "查邮箱" ){
//
//            return "公司联系邮箱：kissneck@163.com";
//        }
//
//        if( $key == "人工客服"){
//
//            return "客服QQ号码为：350833692";
//
//        }
//
//        if ($key == '请问') {
//            $mt = mt_rand(1, 5);
//            $array = array(
//                1 => '"我脸油吗"反光？?反正我不清楚',
//                2 => '走，我请你吃饭',
//                3 => '此人已死，有事烧纸！',
//                4 => '喂！什么啊！乱码啊，再发',
//                5 => '笑话是什么？?就是我现在对你说的话。'
//            );
//            return $array[$mt];
//        }
//        return "要不您换个问法？？？";
//    }
//
//    function keylist() {
//        $array = array(
//            1 => '嗨',
//            2 => '你好',
//            3 => '靠',
//            4 => '在吗',
//            5 => '请问'
//        );
//    }
//
//
//
//    //发送红包到公众号
//    public function send_hongbao(){
//
//    }
//}




