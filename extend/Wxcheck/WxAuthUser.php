<?php


namespace Wxcheck;


use think\Exception;
use think\facade\Log;
class WxAuthUser
{

    //微信小程序授权方法
    public function wx_auth_xcx($wx_code,$iv,$encryptedData,$iv2,$encryptedData2)
    {
        $authorizer_appid =  config('app.wx_xcx_appid');

        $authorizer_appsecret = config('app.wx_xcx_secret');

        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='. $authorizer_appid.'&secret='.$authorizer_appsecret.'&js_code='. $wx_code.'&grant_type=authorization_code';

        $html = file_get_contents($url);

        Log::write("微信access：".print_r($html,true));
        printLog("微信授权信息获取1：",["data"=>$html,"code"=>$wx_code,"iv2"=>$iv2,"encryptedData2"=>$encryptedData2],"wxAuth");
        $obj = json_decode($html);

        if(isset($obj->errcode)){
            if($obj->errcode == 40163){
                throw new Exception('获取微信code,已失效。请重新授权！'.$obj->errcode,11186);

            }else{
                throw new Exception('授权失败，请重新授权！'.$obj->errcode,11186);

            }

        }


        $arrlist['openid'] = $obj->openid;
        $arrlist['session_key'] = $obj->session_key;
        $pc = new WXBizDataCrypt($authorizer_appid, $arrlist['session_key']);
        if((isset($encryptedData) && !empty($encryptedData)) && (isset($iv) && !empty($iv))){


            $iv 		   = str_replace(' ', '+', $iv);
            $encryptedData = urldecode($encryptedData);
            $encryptedData = str_replace(' ', '+', $encryptedData);

            /**
             * 解密用户敏感数据
             * @param encryptedData 明文,加密数据
             * @param iv            加密算法的初始向量
             * @param code          用户允许登录后，回调内容会带上 code（有效期五分钟），开发者需要将 code 发送到开发者服务器后台，使用code 换取 session_key api，将 code 换成 openid 和 session_key
             * @return
             */


            $errCode = $pc->decryptData($encryptedData, $iv, $data);
            Log::write("微信获取信息：".json_encode($errCode,JSON_UNESCAPED_UNICODE));
            if ($errCode != 0) {
                $errCode = $pc->decryptData($encryptedData, $iv, $data);
                if ($errCode != 0) {
                    $errCode = $pc->decryptData($encryptedData, $iv, $data);
                    if($errCode){

                        throw new Exception('授权失败，请重新授权！'.$errCode,11186);
                    }

                }

            }
            //判断获取信息是否成功
            $d = json_decode($data, true);
            Log::write("微信获取信息：".$data);
        }
        else{
            $d = [];
            Log::write("选择不获取用户信息：");
        }

        if((isset($encryptedData2) && !empty($encryptedData2)) && (isset($iv2) && !empty($iv2))){

            $iv2 = str_replace(' ', '+', $iv2);
            $encryptedData2 = urldecode($encryptedData2);
            $encryptedData2 = str_replace(' ', '+', $encryptedData2);

            $errCode2 = $pc->decryptData($encryptedData2, $iv2, $data2);
            printLog("微信授权信息获取2：",["data"=>$errCode2,"session_key"=>$arrlist['session_key']],"wxAuth");
            printLog("微信授权信息获取3：",["data"=>$data2],"wxAuth");
            if ($errCode2 != 0) {
                $errCode2 = $pc->decryptData($encryptedData2, $iv2, $data2);
                if ($errCode2 != 0) {
                    $errCode2 = $pc->decryptData($encryptedData2, $iv2, $data2);
                    if($errCode2){

                        throw new Exception('授权获取绑定手机号码失败，请重新授权！'.$errCode2,11186);

                    }

                }

            }
            Log::write("微信手机获取信息：".$data2);
            $d2 = json_decode($data2, true);
        }
        else{
            $d2 = [];
            Log::write("不获取微信手机信息：");
        }



        return [$d,$d2];

    }


    //微信通过wxcode获取用户openid、unionid
    public function wxCodeDecode($wx_code){

        $authorizer_appid =  config('app.wx_xcx_appid');

        $authorizer_appsecret = config('app.wx_xcx_secret');

        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.
            $authorizer_appid.'&secret='.$authorizer_appsecret.'&js_code='.
            $wx_code.'&grant_type=authorization_code';

        $html = file_get_contents($url);
        printLog("微信授权打印-1：",[$html],"wxauth");
        Log::write("微信access：".print_r($html,true));

        $obj = json_decode($html);

        if(isset($obj->errcode)){
            if($obj->errcode == 40163){
                throw new Exception('获取微信code,已失效。请重新授权！',11186);

            }else{
                throw new Exception('授权失败，请重新授权！',11186);
            }
        }
        $arrlist['openid'] = $obj->openid;
        $arrlist['session_key'] = $obj->session_key;
        if(isset($obj->unionid)){
            $arrlist['unionid'] = $obj->unionid;
        }

        return $arrlist;
    }

    //解析微信密钥信息
    //$type 解析的类型
    //$wx_code_data  第一次微信code获取的数据
    public function wxDataEncode($type,$wx_code_data,$iv,$encryptedData){
        $authorizer_appid =  config('app.wx_xcx_appid');
        $pc = new WXBizDataCrypt($authorizer_appid, $wx_code_data['session_key']);
        if($type == 1){
            //解析用户信息
            $errCode = $pc->decryptData($encryptedData, $iv, $data);
            Log::write("微信获取信息：".json_encode($errCode,JSON_UNESCAPED_UNICODE));
            if ($errCode != 0) {
                $errCode = $pc->decryptData($encryptedData, $iv, $data);
                if ($errCode != 0) {
                    $errCode = $pc->decryptData($encryptedData, $iv, $data);
                    if($errCode){

                        throw new Exception('授权失败，请重新授权！',11186);
                    }
                }
            }
            //判断获取信息是否成功
            $d = json_decode($data, true);

            $d['openId'] = $wx_code_data['openid'];


            if(isset($wx_code_data['unionid'])){
                $d['unionId'] = $wx_code_data['unionid'];
            }


            Log::write("微信获取信息：".$data);
        }else{
            //解析用户手机号码
//            $pc = new WXBizDataCrypt($authorizer_appid, $wx_code_data['session_key']);
            $errCode2 = $pc->decryptData($encryptedData, $iv, $data2);
            Log::write("微信手机获取信息：".print_r($errCode2,true));
            if ($errCode2 != 0) {
                $errCode2 = $pc->decryptData($encryptedData, $iv, $data2);
                if ($errCode2 != 0) {
                    $errCode2 = $pc->decryptData($encryptedData, $iv, $data2);
                    if($errCode2){
                        throw new Exception('授权获取绑定手机号码失败，请重新授权！',11186);
                    }

                }

            }
            Log::write("微信手机获取信息：".$data2);
            $d = json_decode($data2, true);
        }

        return $d;
    }


    //微信小程序 同一主题 其中一个小程序跳转到另外一个小程序 前端无提示获取跳转用户的信息包括openid,unionid
    public function wx_code2_session($wx_code){
        if(empty($wx_code)){
            throw new Exception('缺少微信code！',11186);
        }
        $authorizer_appid =  config('app.wx_xcx_appid');

        $authorizer_appsecret = config('app.wx_xcx_secret');


        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$authorizer_appid.
            '&secret='.$authorizer_appsecret.'&js_code='.$wx_code.'&grant_type=authorization_code';

        $html = file_get_contents($url);

        Log::write("微信access：".print_r($html,true));

        $obj = json_decode($html);

        if(isset($obj->errcode)){
            if($obj->errcode == -1){
                throw new Exception('系统繁忙，请重试！'.$obj->errcode.$obj->errmsg,11186);

            }elseif($obj->errcode == 40029){
                throw new Exception('微信code无效，请重试！'.$obj->errcode.$obj->errmsg,11186);
            }elseif($obj->errcode == 45011){
                throw new Exception('请求次数太频繁，请稍后重试！'.$obj->errcode.$obj->errmsg,11186);
            }
            else{
                throw new Exception('授权失败，请重新授权！'.$obj->errcode.$obj->errmsg,11186);
            }
        }
        $arrlist['unionid'] = '';
        $arrlist['openid'] = $obj->openid;
        if(isset($obj->unionid)){
            $arrlist['unionid'] = $obj->unionid;
        }



        return $arrlist;
    }
}