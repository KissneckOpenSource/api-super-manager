<?php


namespace Wxcheck;


use think\Exception;
use think\facade\Log;

class WXHongBao
{
    private $mch_id = "";               //商户ID写死
    private $wxappid = "";          //微信公众号，写死
    private $client_ip = ""; //"127.0.0.1"; //调用红包接口的主机的IP,服务端IP,写死，即脚本文件所在的IP
    private $apikey = "";    //pay的秘钥值
    private $total_num = 1;                   //发放人数。固定值1，不可修改
//    private $nick_name = "微信公众号红包";           //红包商户名称
    private $nick_name;           //红包商户名称
//    private $send_name = "微信公众号红包";          //红包派发者名称
    private $send_name;          //红包派发者名称
    private $wishing = "欢迎再次参与";      //
    private $act_name = "";     //活动名称
    private $remark = "";
    private $nonce_str = "";
    private $mch_billno = "";   //订单号
    private $re_openid = "";    //接收方的openID
    private $total_amount = 1 ;   //红包金额，单位 分
    private $min_value = 1;   //最小金额
    private $max_value = 1;   //根据接口要求，上述3值必须一致
    private $sign = "";     //签名在send时生成
    private $amt_type;     //分裂红包参数，在sendgroup中进行定义，是常量 ALL_RAND

    //证书，在构造函数中定义，注意！
    private $apiclient_cert; //= getcwd()."/apiclient_cert.pem";
    private $apiclient_key;// = getcwd()."/apiclient_key.pem";
    private $apiclient_ca;// = getcwd()."/apiclient_key.pem";

    //分享参数
    private $isShare = false; //有用？似乎是无用参数，全部都不是必选和互相依赖的参数
    private $share_content = "";
    private $share_url ="";
    private $share_imgurl = "";

    private $wxhb_inited;

    private $api_hb_group = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendgroupredpack";//裂变红包
    private $api_hb_single = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";

    private $error = "ok"; //init



    /**
     * WXHongBao::__construct()
     * 步骤
     * new(openid,amount)
     * setnickname
     * setsend_name
     * setwishing
     * setact_name
     * setremark
     * send()
     * @return void
     */
    function __construct($wx_paykey,$mch_id,$wxappid,$client_ip,$nick_name,$send_name,$order_num){
        //好像没有什么需要构造函数做的 引入需要的文件
        $this->wxhb_inited = false;
        $this->apiclient_cert = getcwd() . "/apiclient_cert.pem";
        $this->apiclient_key = getcwd() . "/apiclient_key.pem";
        //$this->apiclient_ca = getcwd() . "/zzz1/rootca.pem";
        //商户号
        $this->mch_id = $mch_id;

        //微信appid
        $this->wxappid = $wxappid;

        //发送的服务器IP
        $this->client_ip = $client_ip;

        //商家密钥
        $this->apikey = $wx_paykey;

        //发送红包商家名称
        $this->nick_name = $nick_name;

        //发送红包用户
        $this->send_name = $send_name;

        //订单号
        $this->mch_billno = $order_num;


    }

    public function err(){
        return $this->error;
    }

    public function error(){
        return $this->err();
    }
    /**
     * WXHongBao::newhb()
     * 构造新红包
     * @param mixed $toOpenId
     * @param mixed $amount 金额分
     * @return void
     */
    public function newhb($toOpenId,$amount){

        if(!is_numeric($amount)){
            $this->error = "金额参数错误";
            throw new Exception($this->error,11186);

//        }elseif($amount<100){
        }elseif($amount<100){
            $this->error = "金额不能小于1元";
            throw new Exception($this->error,11186);

        }elseif($amount>20000){
            $this->error = "金额不能大于200元";
            throw new Exception($this->error,11186);

        }

        $this->gen_nonce_str();//构造随机字串
//        $this->gen_mch_billno();//构造订单号
        $this->setOpenId($toOpenId);
        $this->setAmount($amount);
        $this->wxhb_inited = true; //标记微信红包已经初始化完毕可以发送

        //每次new 都要将分享的内容给清空掉，否则会出现残余被引用
        $this->share_content= "";
        $this->share_imgurl = "";
        $this->share_url = "";
    }

    /**
     * WXHongBao::sendGroup()
     * 发送裂变红包,参数为裂变数量
     * @param integer $num 3-20
     * @return
     */
    public function sendGroup($num=3){
        $this->amt_type = "ALL_RAND";//$amt; 固定值。发送裂变红包组文档指定参数，随机
        return $this->send($this->api_hb_group,$num);
    }

    public function getApiSingle(){
        return $this->api_hb_single;
    }

    public function getApiGroup(){
        return $this->api_hb_group;
    }

    public function setNickName($nick){
        $this->nick_name = $nick;
    }

    public function setSendName($name){
        $this->send_name = $name;
    }

    public function setWishing($wishing){
        $this->wishing = $wishing;
    }

    public function setActName($act){
        $this->act_name = $act;
    }

    public function setRemark($remark){
        $this->remark = $remark;
    }

    public function setOpenId($openid){
        $this->re_openid = $openid;
    }

    /**
     * WXHongBao::setAmount()
     * 设置红包金额
     * 文档有两处冲突描述
     * 一处指金额 >=1 (分钱)
     * 另一处指金额 >=100 < 20000 [1-200元]
     * 有待测试验证！
     * @param mixed $price 单位 分
     * @return void
     */
    public function setAmount($price){
        $this->total_amount = (int)$price;
        $this->min_value = (int)$price;
        $this->max_value = (int)$price;
    }

    //以下方法，为设置分裂红包时使用
    public function setHBminmax($min,$max){
        $this->min_value = $min;
        $this->max_value = $max;
    }

    public function setShare($img="",$url="",$content=""){
        //https://mmbiz.qlogo.cn/mmbiz/MS1jaDO92Ep4qNo9eV0rnItptyBrzUhJqT8oxSsCofdxibnNWMJiabaqgLPkDaEJmia6fqTXAXulKBa9NLfxYMwYA/0?wx_fmt=png
        //http://mp.weixin.qq.com/s?__biz=MzA5Njg4NTk3MA==&mid=206257621&idx=1&sn=56241da30e384e40771065051e4aa6a8#rd
        $this->share_content = $content;
        $this->share_imgurl = $img;
        $this->share_url = $url;
    }

    //查询红包状态
    public function query_hongbao($data){
        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/gethbinfo';

        //生成签名的数据
        $param = [];

        $param["bill_type"]='MCHT'; //MCHT:通过商户订单号获取红包信息。

        $param["appid"]=config('app.wx_gzh_appid'); //微信分配的公众账号ID

        $param["mch_id"]=config('app.wx_mic'); //MCHT:通过商户订单号获取红包信息。

        $param["nonce_str"]=$this->nonce_str;  //随机字符串

        $param["mch_billno"]=$data['mch_billno'];  //红包订单号

        ksort($param); //按照键名排序...艹，上面排了我好久
        $params = $this->gen_Sign($param); //生成签名
//
//         = $this->sign;

//        $xml = $this->genXMLParam(2,$param);
        $xml = $this->arrayToXml($params);

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_TIMEOUT,10);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);


        curl_setopt($ch,CURLOPT_SSLCERT,$this->apiclient_cert);

        curl_setopt($ch,CURLOPT_SSLKEY,$this->apiclient_key);


        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);
//            $rsxml = simplexml_load_string($data);
            $xmlstring = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);

            $rsxml = json_decode(json_encode($xmlstring));

            Log::write('红包返回33'.$data);
            if($rsxml->return_code == 'SUCCESS' && isset($rsxml->status) && !empty($rsxml->status)){
                if($rsxml->status == 'FAILED'){
                    $this->error = json_encode($rsxml->return_msg);
//                    Log::write('红包返回1'.$this->error);
                    Log::write('查询红包返回错误记录：'.$this->error);
                    throw new Exception($this->error,11186);
                }

//                $sss = $rsxml->status;

                return $rsxml->status;
            }else{

                //调用查询接口 ，检查红包是否正确

                $this->error = json_encode($rsxml->return_msg);
                Log::write('查询红包请求错误记录：'.$this->error);
                throw new Exception($this->error,11186);

            }
        }

        $this->error = curl_errno($ch);

        curl_close($ch);
        Log::write('红包返回2'.$this->error);
        throw new Exception($this->error,11186);


    }


    /**
     * WXHongBao::send()
     * 发出红包
     * 构造签名
     * 注意第二参数，单发时不要改动！
     * @return boolean $success
     */
    public function send(){

        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";
        $total_num = 1;

        if(!$this->wxhb_inited) {
            $this->error .= "(红包未准备好)";
            Log::write($this->error);
            throw new Exception($this->error,11186);

        }

        $this->total_num = $total_num;

        //生成签名的数据
        unset($param);
        //其实应该用key重排一次 right?
        $param["act_name"]=$this->act_name;//

        if($this->total_num==1){
            //这些是裂变红包用不上的参数，会导致签名错误
            $param["client_ip"]=$this->client_ip;   //调用接口的机器Ip地址
            $param["max_value"]=$this->max_value;
            $param["min_value"]=$this->min_value;
            $param["nick_name"]=$this->nick_name;
        }

        //如果 红包金额大于200或者小于1元时必传场景id
        if($this->total_amount < 100 || $this->total_amount > 20000 ){
            $param["scene_id"] = 'PRODUCT_1';  //发放红包使用场景，红包金额大于200或者小于1元时必传
        }

        $param["mch_billno"] = $this->mch_billno;  //商户订单号
        $param["mch_id"]=$this->mch_id;     //商户号
        $param["nonce_str"]=$this->nonce_str;  //随机字符串
        $param["re_openid"]=$this->re_openid;   //接受红包的用户openid
        $param["remark"]=$this->remark;    //备注信息
        $param["send_name"]=$this->send_name;   //商户名称
        $param["total_amount"]=$this->total_amount;     //付款金额 单分
        $param["total_num"]=$this->total_num;    //红包发放总人数
        $param["wishing"]=$this->wishing;   //红包祝福语
        $param["wxappid"]=$this->wxappid;   //微信分配的公众账号ID


        //裂变红包 用不到就注释掉
        if($this->share_content) $param["share_content"] = $this->share_content;
        if($this->share_imgurl) $param["share_imgurl"] = $this->share_imgurl;
        if($this->share_url) $param["share_url"] = $this->share_url;
        if($this->amt_type) $param["amt_type"] = $this->amt_type; //


        ksort($param); //按照键名排序...艹，上面排了我好久

        $params = $this->gen_Sign($param); //生成签名

        //构造提交的数据
//        $xml = $this->genXMLParam();
        $xml = $this->arrayToXml($params);

        Log::write('红包返回-1 '.$xml);



        //debug
//        file_put_contents("hbxml.debug",$xml);

        //提交xml,curl
        //$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_TIMEOUT,10);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);

        //curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT,$this->apiclient_cert);
        //curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY,$this->apiclient_key);

        //curl_setopt($ch,CURLOPT_CAINFO,$this->appclient_ca);

        /*
        if( count($aHeader) >= 1 ){
          curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }
        */
        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
        $data = curl_exec($ch);
        //die(print_r($data));
        if($data){
            curl_close($ch);
//            $rsxml = simplexml_load_string($data);
            $xmlstring = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);

            $rsxml = json_decode(json_encode($xmlstring));
            Log::write('红包返回33'.$data);
            if($rsxml->return_code == 'SUCCESS' && $rsxml->result_code == 'SUCCESS'){
                return true;
            }else{

                try{
                    $re_gethbinfo = $this->query_hongbao($param);

                    if($re_gethbinfo == 'SENDING'){
                        //发放中
                        //重复调用5次获取最新的红包状态  返回前端
                        $query_hongbao_i = 5;
                        $tem_status = 0;    //返回的状态  1正常  2错误  3红包发送中，请等待
                        while ($query_hongbao_i > 0){
                            $re_gethbinfo = $this->query_hongbao($param);
                            if($re_gethbinfo == 'SENDING'){
                                //发放中
                                //重复调用5次获取最新的红包状态  返回前端
                                $query_hongbao_i--;
                            }elseif($re_gethbinfo == 'SENT'){
                                //已发放待领取
                                $query_hongbao_i = 0;
                                return true;

                            }elseif($re_gethbinfo == 'RECEIVED'){
                                //已领取
                                $query_hongbao_i = 0;
                                Log::write('红包已领取');
                                throw new Exception('红包已领取',11186);
                            }elseif($re_gethbinfo == 'RFUND_ING'){
                                //退款中
                                $query_hongbao_i = 0;
                                $tem_status = 2;
                                Log::write('红包已被退款');
                                throw new Exception('红包已被退款',11186);
                            }elseif($re_gethbinfo == 'REFUND'){
                                //已退款
                                $query_hongbao_i = 0;
                                $tem_status = 2;
                                Log::write('红包已退款');
                                throw new Exception('红包已退款',11186);
                            }
                        }

                        throw new Exception('红包正在发送中，请稍后在公众号中领取',11186);
                    }
                    elseif($re_gethbinfo == 'SENT'){
                        //已发放待领取
                        return true;

                    }
                    elseif($re_gethbinfo == 'RECEIVED'){
                        //已领取
                        Log::write('红包已领取');
                        throw new Exception('红包已领取',11186);

                    }
                    elseif($re_gethbinfo == 'RFUND_ING'){
                        //退款中
                        Log::write('红包已被退款');
                        throw new Exception('红包已被退款',11186);

                    }
                    elseif($re_gethbinfo == 'REFUND'){
                        //已退款
                        Log::write('红包已退款');
                        throw new Exception('红包已退款',11186);
                    }

                }catch (\Exception $e){
                    //调用查询接口 ，检查红包是否正确
                    $this->error = json_encode($rsxml->return_msg);
                    Log::write('红包返回1'.$this->error);
//                    throw new Exception($this->error,11186);
                    return json(['code'=>-1,'data'=>[],'msg'=>$this->error]);
                }



//                return false;
            }
        }
        else{
            $this->error = curl_errno($ch);

            curl_close($ch);
            Log::write('红包返回2'.$this->error);
//            throw new Exception($this->error,11186);
            return json(['code'=>-1,'data'=>[],'msg'=>$this->error]);

        }

    }

    private function gen_nonce_str($length=32){
//        $this->nonce_str = strtoupper(md5(mt_rand().time())); //确保不重复而已
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for($i=0;$i<$length;$i++) {
            $str.=substr($chars, mt_rand(0,strlen($chars)-1),1);
        }
        $this->nonce_str = $str;
    }

    private function gen_Sign($param){
//        unset($param);
//        //其实应该用key重排一次 right?
//        $param["act_name"]=$this->act_name;//
//
//        if($this->total_num==1){
//            //这些是裂变红包用不上的参数，会导致签名错误
//            $param["client_ip"]=$this->client_ip;   //调用接口的机器Ip地址
//            $param["max_value"]=$this->max_value;
//            $param["min_value"]=$this->min_value;
//            $param["nick_name"]=$this->nick_name;
//        }
//
//        //如果 红包金额大于200或者小于1元时必传场景id
//        if($this->total_amount < 100 || $this->total_amount > 20000 ){
//            $param["scene_id"] = 'PRODUCT_2';  //发放红包使用场景，红包金额大于200或者小于1元时必传
//        }
//
//        $param["mch_billno"] = $this->mch_billno;  //商户订单号
//        $param["mch_id"]=$this->mch_id;     //商户号
//        $param["nonce_str"]=$this->nonce_str;  //随机字符串
//        $param["re_openid"]=$this->re_openid;   //接受红包的用户openid
//        $param["remark"]=$this->remark;    //备注信息
//        $param["send_name"]=$this->send_name;   //商户名称
//        $param["total_amount"]=$this->total_amount;     //付款金额 单分
//        $param["total_num"]=$this->total_num;    //红包发放总人数
//        $param["wishing"]=$this->wishing;   //红包祝福语
//        $param["wxappid"]=$this->wxappid;   //微信分配的公众账号ID
//
//
//        //裂变红包 用不到就注释掉
//        if($this->share_content) $param["share_content"] = $this->share_content;
//        if($this->share_imgurl) $param["share_imgurl"] = $this->share_imgurl;
//        if($this->share_url) $param["share_url"] = $this->share_url;
//        if($this->amt_type) $param["amt_type"] = $this->amt_type; //

//        ksort($param); //按照键名排序...艹，上面排了我好久

        //$sign_raw = http_build_query($param)."&key=".$this->apikey;
        $sign_raw = "";
        foreach($param as $k => $v){
            $sign_raw .= $k."=".$v."&";
        }
        $sign_raw .= "key=".$this->apikey;
        //可以用下面方法查看
        // file_put_contents("11sign.txt",$sign_raw);//debug
        $this->sign = strtoupper(md5($sign_raw));

        $param['sign'] =$this->sign;

        return $param;

    }

    /**
     * WXHongBao::genXMLParam()
     * 生成post的参数xml数据包
     * 注意生成之前各项值要生成，尤其是Sign
     * @return $xml
     */
//    public function genXMLParam($type = 1,$param=[]){
//
//        if($type === 1){
//                    $xml = "<xml>
//                 <act_name><![CDATA[".$this->act_name."]]></act_name>
//                 <client_ip><![CDATA[".$this->client_ip."]]></client_ip>
//                 <max_value>".$this->max_value."</max_value>
//                  <mch_billno>".$this->mch_billno."</mch_billno>
//                  <mch_id>".$this->mch_id."</mch_id>
//                  <min_value>".$this->min_value."</min_value>
//                  <nick_name><![CDATA[".$this->nick_name."]]></nick_name>
//                  <nonce_str>".$this->nonce_str."</nonce_str>
//                  <re_openid>".$this->re_openid."</re_openid>
//                  <remark><![CDATA[".$this->remark."]]></remark>
//                   <send_name><![CDATA[".$this->send_name."]]></send_name>
//                   <total_amount>".$this->total_amount."</total_amount>
//                   <total_num>".$this->total_num."</total_num>
//                   <wishing><![CDATA[".$this->wishing."]]></wishing>
//                   <wxappid>".$this->wxappid."</wxappid>
//                   <sign>".$this->sign."</sign>
//                </xml>
//                   ";
//        }
//        else{
//            $xml = "<xml>
//            <sign><![CDATA[".$param["sign"]."]]></sign>
//            <mch_billno><![CDATA[".$param["mch_billno"]."]]></mch_billno>
//            <mch_id><![CDATA[".$param["mch_id"]."]]></mch_id>
//            <appid><![CDATA[".$param["appid"]."]]></appid>
//            <bill_type><![CDATA[".$param["bill_type"]."]]></bill_type>
//            <nonce_str><![CDATA[".$param["nonce_str"]."]]></nonce_str>
//            </xml>
//            ";
//        }
//
//
//
//
//        return $xml;
//    }

    function arrayToXml($arr) {
//        ksort($param); //按照键名排序...艹，上面排了我好久
        $xml = "<xml>";
        foreach ($arr as $key=>$val) {
            if (is_numeric($val)) {
                $xml.="<".$key.">".$val."</".$key.">";
            } else {
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

    /**
     * WXHongBao::gen_mch_billno()
     * 商户订单号（每个订单号必须唯一）
    组成： mch_id+yyyymmdd+10位一天内不能重复的数字。
    接口根据商户订单号支持重入， 如出现超时可再调用。
     * @return void
     */
    private function gen_mch_billno(){
        //生成一个长度10，的阿拉伯数字随机字符串
        $rnd_num = array('0','1','2','3','4','5','6','7','8','9');
        $rndstr = "";
        while(strlen($rndstr)<10){
            $rndstr .= $rnd_num[array_rand($rnd_num)];
        }

        $this->mch_billno = $this->mch_id.date("Ymd").$rndstr;
    }
}
/**
 *1.上边是红包类，需要用的时候直接引入红包类。
 *2.//实例化红包类
 *  $wxhongbao=new \WXHongBao();
 *3. //需要发放的openid 金额 openid 根据微信提供的接口获取，金额根据自己需求
 * $wxhongbao->newhb($user_openid, $pay_money*100);
 *$wxhongbao->setActName("根据自己需求设置");
 * $wxhongbao->setWishing("根据自己需求设置");
 *$wxhongbao->setRemark("根据自己需求设置");
 *参数设置之后发放红包
 *$wxhongbao->send();
 **/
