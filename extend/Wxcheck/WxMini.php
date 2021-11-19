<?php
namespace Wxcheck;

use app\BaseController;
//use think\Controller;
use think\App;
use think\Db;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Log;

//use think\facade\View;

//define("APP_ID", "wxd453e758f8c3715b");
//define("APP_SCR", "61aaed09cf98fba4a8e92e1cdca1b504");

class WxMini  extends BaseController
{

//    private $wxAppid = "wx413594f7fc15b9bc"; //公众号APPID
    private $wxAppid; //公众号APPID

    protected $access_url = "https://api.weixin.qq.com/cgi-bin/token";

    protected $APP_ID;  //小程序app_id

    protected $APP_SCR; //小程序secret


    //公众号openid
    public function __construct($wxAppid,$APP_ID,$APP_SCR)
    {

        $this->wxAppid = $wxAppid;

        $this->APP_ID = $APP_ID;

        $this->APP_SCR = $APP_SCR;

        $this->errorLog('记录小程序配置信息',[$wxAppid,$APP_ID,$APP_SCR]);

    }



    /**
     * @param $tem_id  发送统一模板的模板ID
     * @return array
     * @throws \Exception
     */
    public function testSend($openid,$tem_id)
    {

        $content['first']     = "验证用户身份消息";
        $content['keyword1']     = "--";
        $content['keyword2']     = date('Y-m-d H:i:s');


        try{
            $r = $this->sendTemplateMessage($content,$openid,$tem_id);
        }catch (\Exception $e){

            $this->errorLog($e->getMessage());
            return ['code' => 2, 'data' =>'', 'msg' => $e->getMessage()];
        }


        return ['code' => 1, 'data' =>$r, 'msg' => '公众号已绑定'];
    }


    private function errorLog($msg,$ret=[])
    {
        $rootPath = root_path();
        file_put_contents($rootPath . 'runtime/mini.log', "[" . date('Y-m-d H:i:s') . "] ".$msg.
            "," .json_encode($ret).PHP_EOL, FILE_APPEND);

    }

    /**
     * 下发小程序和公众号统一的服务消息
     * @param $par  该参数时根据设置的模板的信息设置
     * @param $open_id
     * @param $tem_id 公众号和小程序统一模板消息ID
     * @return bool|string  1绑定公众号 2未绑定公众号
     * @throws \Exception  发送检测模板发生异常
     */
    public function sendTemplateMessage($par,$open_id,$tem_id)
    {

        $data = [
            "touser" => $open_id, //当前用户的小程序openid，前一步获取
            "mp_template_msg" => [
                "appid" => $this->wxAppid,

                "template_id" => $tem_id, //模板id
            ]
        ];

        if(isset($par['first']) && !empty($par['first'])){
            $tem_data = [];
            if(is_array($par['first'])){
                if(isset($par['first']['value']) && !empty($par['first']['value'])){
                    $tem_data['value'] = $par['first']['value'];
                }
                if(isset($par['first']['color']) && !empty($par['first']['color'])){
                    $tem_data['color'] = $par['first']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['first'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['mp_template_msg']['data']['first'] = $tem_data;
        }

        if(isset($par['keyword1']) && !empty($par['keyword1'])){
            $tem_data = [];
            if(is_array($par['keyword1'])){
                if(isset($par['keyword1']['value']) && !empty($par['keyword1']['value'])){
                    $tem_data['value'] = $par['first']['value'];
                }
                if(isset($par['keyword1']['color']) && !empty($par['keyword1']['color'])){
                    $tem_data['color'] = $par['keyword1']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['keyword1'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['mp_template_msg']['data']['keyword1'] = $tem_data;
        }

        if(isset($par['keyword2']) && !empty($par['keyword2'])){
            $tem_data = [];
            if(is_array($par['keyword2'])){
                if(isset($par['keyword2']['value']) && !empty($par['keyword2']['value'])){
                    $tem_data['value'] = $par['keyword2']['value'];
                }
                if(isset($par['keyword2']['color']) && !empty($par['keyword2']['color'])){
                    $tem_data['color'] = $par['keyword2']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['keyword2'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['mp_template_msg']['data']['keyword2'] = $tem_data;
        }

        if(isset($par['keyword3']) && !empty($par['keyword3'])){
            $tem_data = [];
            if(is_array($par['keyword3'])){
                if(isset($par['keyword3']['value']) && !empty($par['keyword3']['value'])){
                    $tem_data['value'] = $par['first']['value'];
                }
                if(isset($par['keyword3']['color']) && !empty($par['keyword3']['color'])){
                    $tem_data['color'] = $par['first']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['keyword3'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['mp_template_msg']['data']['keyword3'] = $tem_data;
        }

        if(isset($par['keyword4']) && !empty($par['keyword4'])){
            $tem_data = [];
            if(is_array($par['keyword4'])){
                if(isset($par['keyword4']['value']) && !empty($par['keyword4']['value'])){
                    $tem_data['value'] = $par['keyword4']['value'];
                }
                if(isset($par['keyword4']['color']) && !empty($par['keyword4']['color'])){
                    $tem_data['color'] = $par['keyword4']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['keyword4'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['mp_template_msg']['data']['keyword4'] = $tem_data;
        }

        if(isset($par['remark']) && !empty($par['remark'])){
            $tem_data = [];
            if(is_array($par['remark'])){
                if(isset($par['remark']['value']) && !empty($par['remark']['value'])){
                    $tem_data['value'] = $par['remark']['value'];
                }
                if(isset($par['remark']['color']) && !empty($par['remark']['color'])){
                    $tem_data['color'] = $par['remark']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['remark'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['mp_template_msg']['data']['remark'] = $tem_data;
        }

//
//        if(isset($par['url']) && !empty($par['url'])){
//
//            $data['data']['remark'] = $tem_data;
//        }
//
//        //表单提交场景下，为 submit 事件带上的 formId；支付场景下，为本次支付的 prepay_id
//        if(isset($par['form_id']) && !empty($par['form_id'])){
//            $data['form_id'] = $par['form_id'];
//        }
//
//        //点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
//        if(isset($par['page']) && !empty($par['page'])){
//            $data['page'] = $par['page'];
//        }

        $access_token = $this->getWxtoken();
        $msg_url="https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=".
            $access_token; // 下发小程序和公众号统一的服务消息

        $r = curl_post($msg_url,json_encode($data));

        $data = json_decode($r,true);

        if($data['errcode'] != 0){
            if($data['errcode'] == 43004){


                return 2;
            }else{
                throw new \Exception($data['errmsg'],11186);

            }
        }

        return 1;
    }


    //发送小程序模板消息
    public function sendMiniTemplate($par,$open_id,$tem_id){

        $data = [
            "touser" => $open_id, //当前用户的小程序openid，前一步获取
            "template_id" => $tem_id, //模板id
        ];

        if(isset($par['first']) && !empty($par['first'])){
            $tem_data = [];
            if(is_array($par['first'])){
                if(isset($par['first']['value']) && !empty($par['first']['value'])){
                    $tem_data['value'] = $par['first']['value'];
                }
                if(isset($par['first']['color']) && !empty($par['first']['color'])){
                    $tem_data['color'] = $par['first']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['first'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['data']['first'] = $tem_data;
        }

        if(isset($par['keyword1']) && !empty($par['keyword1'])){
            $tem_data = [];
            if(is_array($par['keyword1'])){
                if(isset($par['keyword1']['value']) && !empty($par['keyword1']['value'])){
                    $tem_data['value'] = $par['first']['value'];
                }
                if(isset($par['keyword1']['color']) && !empty($par['keyword1']['color'])){
                    $tem_data['color'] = $par['keyword1']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['keyword1'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['data']['keyword1'] = $tem_data;
        }

        if(isset($par['keyword2']) && !empty($par['keyword2'])){
            $tem_data = [];
            if(is_array($par['keyword2'])){
                if(isset($par['keyword2']['value']) && !empty($par['keyword2']['value'])){
                    $tem_data['value'] = $par['keyword2']['value'];
                }
                if(isset($par['keyword2']['color']) && !empty($par['keyword2']['color'])){
                    $tem_data['color'] = $par['keyword2']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['keyword2'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['data']['keyword2'] = $tem_data;
        }

        if(isset($par['keyword3']) && !empty($par['keyword3'])){
            $tem_data = [];
            if(is_array($par['keyword3'])){
                if(isset($par['keyword3']['value']) && !empty($par['keyword3']['value'])){
                    $tem_data['value'] = $par['first']['value'];
                }
                if(isset($par['keyword3']['color']) && !empty($par['keyword3']['color'])){
                    $tem_data['color'] = $par['first']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['keyword3'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['data']['keyword3'] = $tem_data;
        }

        if(isset($par['keyword4']) && !empty($par['keyword4'])){
            $tem_data = [];
            if(is_array($par['keyword4'])){
                if(isset($par['keyword4']['value']) && !empty($par['keyword4']['value'])){
                    $tem_data['value'] = $par['keyword4']['value'];
                }
                if(isset($par['keyword4']['color']) && !empty($par['keyword4']['color'])){
                    $tem_data['color'] = $par['keyword4']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['keyword4'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['data']['keyword4'] = $tem_data;
        }

        if(isset($par['remark']) && !empty($par['remark'])){
            $tem_data = [];
            if(is_array($par['remark'])){
                if(isset($par['remark']['value']) && !empty($par['remark']['value'])){
                    $tem_data['value'] = $par['remark']['value'];
                }
                if(isset($par['remark']['color']) && !empty($par['remark']['color'])){
                    $tem_data['color'] = $par['remark']['color'];
                }else{
                    $tem_data['color'] = '#173177';//自定义颜色
                }
            }else{
                $tem_data['value'] = $par['remark'];
                $tem_data['color'] = '#173177';//自定义颜色
            }

            $data['data']['remark'] = $tem_data;
        }

        if(isset($par['url']) && !empty($par['url'])){

            $data['data']['remark'] = $tem_data;
        }

        //表单提交场景下，为 submit 事件带上的 formId；支付场景下，为本次支付的 prepay_id
        if(isset($par['form_id']) && !empty($par['form_id'])){
            $data['form_id'] = $par['form_id'];
        }

        //点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
        if(isset($par['page']) && !empty($par['page'])){
            $data['page'] = $par['page'];
        }

        $access_token = $this->getWxtoken();
        $msg_url="https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=".
            $access_token; // 下发小程序和公众号统一的服务消息
        return curl_post($msg_url,json_encode($data));
    }

    //获取微信小程序Token
    public function getWxtoken()
    {
        $minitoken = Cache::get('minitoken');


        if($minitoken){
            $token = $this->getupdateAccessToken($minitoken);
        }else{
            $url = $this->access_url."?grant_type=client_credential&appid=".$this->APP_ID.
                "&secret=".$this->APP_SCR;
            $token = $this->curl_info($url);
            if(!isset($token["access_token"])){
                throw new \Exception('token fail',11186);
            }

            $tem_gzhtoken = [
                'expires_time'=>time() + ($token["expires_in"]-200),
                'token'=>$token["access_token"],
            ];
            Cache::set('minitoken',$tem_gzhtoken,7000);
            $token = $token["access_token"];
        }

        return $token;

    }


    //更新微信token
    public function getupdateAccessToken($minitoken)
    {
        $token_expires = $minitoken['expires_time'];


        $url = $this->access_url."?grant_type=client_credential&appid=".$this->APP_ID.
            "&secret=".$this->APP_SCR;

        $current_time  = time();
        if ($token_expires <= $current_time) {
            $token = $this->curl_info($url);
            if (isset($token["access_token"])) {

                $tem_gzhtoken = [
                    'expires_time'=>time() + ($token["expires_in"]-200),
                    'token'=>$token["access_token"],
                ];
                Cache::set('minitoken',$tem_gzhtoken,7000);
                return $token["access_token"];
            } else {
                return $token;
            }

        } else {
            return $minitoken['token'];
        }
    }


    /**获取小程序分享二维码
     * @param $scene 参数
     * @param $page 小程序页面
     * @param int $width 图片宽度
     * @return bool|string 返回base64
     * @throws \Exception  获取二维码异常
     */
    public function getUnlimited($scene,$page, $width = 600){

        $access_token = $this->getWxtoken();

        $url="https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=$access_token";

        $data=[
            'scene'=>$scene,
            'page'=>$page,
            'width'=>$width,
            'auto_color'=>false,
        ];

        $data=json_encode($data);

        //拿到二维码
        $result = $this->curl_info_post($url,$data);

        $result=$this->data_uri($result,'image/png');

        return $result;
    }

    //二进制转图片image/png
    public function data_uri($contents, $mime)
    {
        $base64   = base64_encode($contents);
        return ('data:' . $mime . ';base64,' . $base64);
    }

    public function curl_info($url) {
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


    //post 请求
    function curl_info_post($url,$data,$useragent='Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)',$httpheader=[],$timeout=0)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if($useragent){
            curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
        }

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        if($data){
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        if($httpheader){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        }

        $res = curl_exec($ch);
        curl_close($ch);

        return $res;
    }

}





