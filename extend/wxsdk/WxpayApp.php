<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2020 http://www.kissneck.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: 可待科技-磊子 <sxfyxl@126.com>
// +----------------------------------------------------------------------
//支付管理
namespace wxsdk;


class WxpayApp
{
//    public function __construct(App $app)
//    {
//        parent::__construct($app);
//        $extend_path    = root_path();
//        $extend_path =  str_replace("\\","/",$extend_path);
//        require_once $extend_path.'extend'.DIRECTORY_SEPARATOR.'wxsdk'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'WxPay.Api.php'; //引入SDK文件
//        require_once $extend_path.'extend'.DIRECTORY_SEPARATOR.'wxsdk'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'WxPay.JsApiPay.php'; //引入SDK文件
//        require_once $extend_path.'extend'.DIRECTORY_SEPARATOR.'wxsdk'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'WxPay.Data.php'; //引入SDK文件
//    }

    public function __construct(){
        $extend_path    = root_path();
        $extend_path =  str_replace("\\","/",$extend_path);
        //引入SDK文件
        require_once $extend_path.'extend'.DIRECTORY_SEPARATOR.'wxsdk'.DIRECTORY_SEPARATOR.'lib'.
            DIRECTORY_SEPARATOR.'WxPay.Api.php';

        //引入SDK文件
        require_once $extend_path.'extend'.DIRECTORY_SEPARATOR.'wxsdk'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.
            'WxPay.JsApiPay.php';

        //引入SDK文件
        require_once $extend_path.'extend'.DIRECTORY_SEPARATOR.'wxsdk'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.
            'WxPay.Data.php';
    }

    private function errorLog($msg,$ret)
    {
        $rootPath = root_path();
        file_put_contents($rootPath . 'runtime/minipay.log', "[" . date('Y-m-d H:i:s') .
            "] ".$msg."," .json_encode($ret).PHP_EOL, FILE_APPEND);

    }
    /**
     * 保存新建的资源
     * @param  \think\Request  $request
     * @return \think\Response
     */
    public function save($data)
    {

        if(!isset($data['paykey'])){
            return json_encode(['code' => -1, "info"=>[], "data"=>[],
                'msg' => '缺少paykey。'],JSON_UNESCAPED_UNICODE);
        }

        if(!isset($data['profit_sharing'])){
            return json_encode(['code' => -1, "info"=>[], "data"=>[],
                'msg' => '缺少profit_sharing。'],JSON_UNESCAPED_UNICODE);
        }


        if(!isset($data['attach_par'])){
            return json_encode(['code' => -1, "info"=>[], "data"=>[],
                'msg' => '缺少attach_par。'],JSON_UNESCAPED_UNICODE);
        }


        if(!isset($data['contract'])){
            return json_encode(['code' => -1, "info"=>[], "data"=>[],
                'msg' => '缺少paykey。'],JSON_UNESCAPED_UNICODE);
        }

        if(!isset($data['mchid'])){
            return json_encode(['code' => -1, "info"=>[], "data"=>[],
                'msg' => '缺少mchid。'],JSON_UNESCAPED_UNICODE);
        }

        if(!isset($data['paysecret'])){
            return json_encode(['code' => -1, "info"=>[], "data"=>[],
                'msg' => '缺少paysecret。'],JSON_UNESCAPED_UNICODE);
        }

        $paykey         = $data['paykey'];

        $profit_sharing = $data['profit_sharing'];
        $attach_par     = $data['attach_par'];
        $contract       = $data['contract'];


		if(empty($data["paybody"])){
		    $data["paybody"] = "不老泉";
		}
	    //②、统一下单

        $input = new \WxPayUnifiedOrder();
	    $input->SetBody($data["paybody"]);
	    if(empty($attach_par)){
            $input->SetAttach($data["ordernums"]);
        }else{
            $input->SetAttach($attach_par);
        }
	    $input->SetOut_trade_no($data["ordernums"]);
	    $input->SetTotal_fee($data["total"]);
	    $input->SetTime_start(date("YmdHis"));
	    $input->SetTime_expire(date("YmdHis", time() + 600));
	    $input->SetNotify_url($data["notify_url"]);
	    $input->SetTrade_type($data["trade_type"]);
	    $input->SetProfit($profit_sharing);

        $this->errorLog("微信支付回调:",$data);

	    //微信支付类型
	    if($data["trade_type"] == "JSAPI") {
		    $input->SetOpenid($data["openid"]);
	    }

	    //服务商模式
//	    $wx_sappid  = config("wx_sappid");
//	    $wx_sappkey = config("wx_sappkey");
	    if($data["is_service"] == 1){
//		    $input->SetMch_id($wx_sappid);         //服务商商户号
//		    $input->SetSubmch_id($data["mchid"]); //入驻商家商户号
//		    //服务商端配置
//		    $config = [
//			    "GetAppId"       => $data["appid"],
//			    "GetMerchantId"  => $wx_sappid,
//			    "GetKey"         => $wx_sappkey,
//			    "GetSignType"    => "MD5",
//			    "GetAppSecret"   => $data["paysecret"],
//		    ];
	    }else{
	    	//普通商户配置
		    $config = [
		        "GetAppId"       => $data["appid"],
		        "GetMerchantId"  => $data['mchid'],
		        "GetKey"         => $paykey,
		        "GetSignType"    => "MD5",
		        "GetAppSecret"   => $data["paysecret"],
		    ];
	    }

        $order = \WxPayApi::unifiedOrder(json_encode($config),$input);


        if(isset($data["istest"]) &&  $data["istest"] == 1){

		    $data = array(
		    	"return_data" => $data,
			    "return_info" => $order
		    );
            return json_encode($data,JSON_UNESCAPED_UNICODE);
	    }

        if($order['return_msg'] =="OK" && $order['result_code']=="SUCCESS" && $order['return_code']=="SUCCESS") {
			//微信包里面的,我用的是下面自己封装的
			$tools = new \JsApiPay();

	        $jsApiParameters = $tools->GetJsApiParameters(json_encode($config),$order);
	        if($data["trade_type"] == "APP") {
	        	$jsApiParameters_Arg = json_decode($jsApiParameters,true);
                //判断是否使用代扣
	        	if(is_null($contract)){
                    $resdata = [
                        'appid'     => $jsApiParameters_Arg['appId'],
                        'partnerid' => $data['mchid'],
                        'prepayid'  => $order['prepay_id'],
                        'package'   => "Sign=WXPay",
                        'noncestr'  => $jsApiParameters_Arg['nonceStr'],
                        'timestamp' => $jsApiParameters_Arg['timeStamp']
                    ];
                }else{
                    $resdata = [
                        'appid'               => $jsApiParameters_Arg['appId'],
                        'partnerid'           => $data['mchid'],
                        'prepayid'            => $order['prepay_id'],
                        'package'             => "Sign=WXPay",
                        'noncestr'            => $jsApiParameters_Arg['nonceStr'],
                        'timestamp'           => $jsApiParameters_Arg['timeStamp'],
                        'contract_mchid'      => $contract['contract_mchid'],   //签约商户号，必须与mch_id一致
                        'contract_appid'      => $contract['contract_appid'],     //签约公众号，必须与appid一致
                        'plan_id'             => $contract['plan_id'],            //协议模板id
                        'contract_code'       => $contract['contract_code'],        //商户侧的签约协议号，由商户生成，只能是数字、大小写字母的描述。
                        'contract_notify_url' => $contract['contract_notify_url'], //签约信息回调通知的url，通知url必须为外网可访问的url，不能携带参数。
                    ];
                }

		        $resdata['sign'] = self::makeSign($resdata,$paykey);

		        $jsApiParameters = json_encode($resdata);
	        }
	        return $jsApiParameters;
        }

        return json_encode(['code' =>-1, "info"=>$order, "data"=>$data, 'msg' => '支付参数出现错误。'],JSON_UNESCAPED_UNICODE);

    }

	//扫描微信付款码支付
	public function scanWxPay()
	{

		$data           = input();
		$paykey         = empty($data['paykey']) ? config('wx_mchkey') : $data['paykey'];
		$extend_path    = Env::get('extend_path');
		require_once $extend_path.'/wxsdk/lib/WxPay.Api.php'; //引入SDK文件
		require_once $extend_path.'/wxsdk/lib/WxPay.JsApiPay.php'; //引入SDK文件
		if(empty($data["paybody"])){ $data["paybody"] = "店掌宝"; }
		//②、统一下单
		$input = new \WxPayMicroPay();
		$input->SetAuth_code($data['authcode']);
		$input->SetBody($data["paybody"]);
		$input->SetAttach($data["ordernums"]);
		$input->SetOut_trade_no($data["ordernums"]);
		$input->SetTotal_fee($data["total"]);


		//微信支付类型
		if($data["trade_type"] == "JSAPI") {
			$input->SetOpenid($data["openid"]);
		}

		//服务商模式
		//$wx_sappid  = config("wx_sappid");
		//$wx_sappkey = config("wx_sappkey");
		if($data["is_service"] == 1){

		}else{
			//普通商户配置
			$config = [
				"GetAppId"       => $data["appid"],
				"GetMerchantId"  => empty($data['mchid']) ? config('wx_mchid') : $data['mchid'],
				"GetKey"         => $paykey,
				"GetSignType"    => "MD5",
				"GetAppSecret"   => $data["paysecret"],
			];
		}

		$order = \WxPayApi::micropay(json_encode($config),$input);

		if(@$data["istest"] == 1){

			$data = array(
				"return_data" => $data,
				"return_info" => $order
			);
			echo json_encode($data);
			exit;
		}

		if($order['return_msg'] =="OK" && $order['result_code']=="SUCCESS" && $order['return_code']=="SUCCESS") {
			//微信包里面的,我用的是下面自己封装的
			unset($order['return_msg'],$order['result_code'],$order['return_code']);
			echo json_encode(['code' => 1, "data"=>$order,'msg' => '支付成功。']);
		}else{
//			appid: "wx6b8e8854140331e6"
//			err_code: "USERPAYING"
//			err_code_des: "需要用户输入支付密码"
//			mch_id: "1494949702"
//			nonce_str: "SWbs7dNFdeXxLPLD"
//			result_code: "FAIL"
//			return_code: "SUCCESS"
//			return_msg: "OK"
//			sign: "EB4037880FEA76185CD496B929508F64"

			echo json_encode(['code' => 2, "data"=>$order,'msg' => '支付参数出现错误。']);
		}

	}
	//扫描微信商户单号退款-用于店掌宝
	public function refundWxPay()
	{
		$data           = input();
		$paykey         = empty($data['paykey']) ? config('wx_mchkey') : $data['paykey'];

		//②、退款参数
		$input = new \WxPayRefund();
		$input->SetOut_trade_no($data["order_num"]);  //商户订单
		$input->SetOut_refund_no($data["refund_num"]); //退款订单
		$input->SetRefund_fee($data["refund_price"]);  //退款金额
		$input->SetTotal_fee($data["total_price"]);    //支付金额

		//普通商户配置

		$config = [
			"GetAppId"      => $data["appid"],
			"GetMerchantId" => empty($data['mchid']) ? config('wx_mchid') : $data['mchid'],
			"GetKey"        => $paykey,
			"GetSignType"    => "MD5",
			"GetAppSecret"  => $data["paysecret"],
			"SslcertPath"   => $data["sslcert_path"],
			"SslkeyPath"    => $data["sslkey_path"]
		];

		$isrefund = $this->refundWxQuery($data["order_num"],$config);
		if($isrefund['return_msg'] == "OK" && $isrefund['result_code'] == "SUCCESS" && $isrefund['return_code'] == "SUCCESS") {
			//微信包里面的,我用的是下面自己封装的
			echo json_encode(['code' => 2, "data"=>"",'msg' => '该订单已退款,请勿重复退款！']);
			exit;
		}

		$refund = \WxPayApi::refund(json_encode($config),$input);

		if($refund['return_msg'] =="OK" && $refund['result_code']=="SUCCESS" && $refund['return_code']=="SUCCESS") {
			//微信包里面的,我用的是下面自己封装的
			unset($refund['return_msg'],$refund['result_code'],$refund['return_code']);
			echo json_encode(['code' => 1, "data"=>$refund,'msg' => '退款成功。']);
		}else{
			echo json_encode(['code' => 2, "data"=>$refund,'msg' => '退款参数出现错误。']);
		}
	}
	//订单查询功能
	public function orderWxQuery()
	{
		$par = input();
		$paykey         = empty($par['paykey']) ? config('wx_mchkey') : $par['paykey'];
		//②、退款查询参数
		$input = new \WxPayOrderQuery();
		$input->SetOut_trade_no($par["order_num"]);  //商户订单

		//普通商户配置

		$config = [
			"GetAppId"      => $par["appid"],
			"GetMerchantId" => empty($par['mchid']) ? config('wx_mchid') : $par['mchid'],
			"GetKey"        => $paykey,
			"GetSignType"    => "MD5",
			"GetAppSecret"  => $par["paysecret"]
		];
		//unset($config['SslcertPath'],$config['SslkeyPath']);
		$order = \WxPayApi::orderQuery(json_encode($config),$input);
		if($order['return_msg'] =="OK" && $order['result_code']=="SUCCESS" && $order['return_code']=="SUCCESS") {
			//微信包里面的,我用的是下面自己封装的
			unset($order['return_msg'],$order['result_code'],$order['return_code']);
			echo json_encode(['code' => 1, "data"=>$order,'msg' => '查询订单成功。']);
		}else{
			echo json_encode(['code' => 2, "data"=>$order,'msg' => '查询订单失败。']);
		}
	}
	//撤销订单功能
	public function cancelWxOrder()
	{
		$par = input();
		$paykey         = empty($par['paykey']) ? config('wx_mchkey') : $par['paykey'];
		//②、退款查询参数
		$input = new \WxPayReverse();
		$input->SetOut_trade_no($par["order_num"]);  //商户订单

		//普通商户配置
		$config = [
			"GetAppId"      => $par["appid"],
			"GetMerchantId" => empty($par['mchid']) ? config('wx_mchid') : $par['mchid'],
			"GetKey"        => $paykey,
			"GetSignType"    => "MD5",
			"GetAppSecret"  => $par["paysecret"],
			"SslcertPath"   => $par["sslcert_path"],
			"SslkeyPath"    => $par["sslkey_path"]
		];

		//unset($config['SslcertPath'],$config['SslkeyPath']);
		$order = \WxPayApi::reverse(json_encode($config),$input);
		if($order['return_msg'] =="OK" && $order['result_code']=="SUCCESS" && $order['return_code']=="SUCCESS") {
			//微信包里面的,我用的是下面自己封装的
			unset($order['return_msg'],$order['result_code'],$order['return_code']);
			echo json_encode(['code' => 1, "data"=>$order,'msg' => '订单已取消。']);
		}else{
			echo json_encode(['code' => 2, "data"=>$order,'msg' => '订单取消失败。']);
		}
	}

	//退款查询功能
	public function refundWxQuery($ordernum,$config)
	{
		//②、退款查询参数
		$input = new \WxPayRefundQuery();
		$input->SetOut_trade_no($ordernum);  //商户订单
		//unset($config['SslcertPath'],$config['SslkeyPath']);
		$order = \WxPayApi::refundQuery(json_encode($config),$input);
		return $order;
	}

    //退款查询功能
    public function refundWxQuery2()
    {
        $par = input();
        $config = [
            "GetAppId"      => $par["appid"],
            "GetMerchantId" => empty($par['mchid']) ? config('wx_mchid') : $par['mchid'],
            "GetKey"        => $par["paykey"],
            "GetSignType"   => "MD5",
            "GetAppSecret"  => $par["paysecret"],
            "SslcertPath"   => $par["sslcert_path"],
            "SslkeyPath"    => $par["sslkey_path"]
        ];

        //②、退款查询参数
        $input = new \WxPayRefundQuery();
        $input->SetOut_trade_no($par['ordernum']);  //商户订单
        //unset($config['SslcertPath'],$config['SslkeyPath']);
        $order = \WxPayApi::refundQuery(json_encode($config),$input);
        echo json_encode(['code' => 1, "data"=>$order,'msg' => '']);
    }


	//微信转账到零钱功能
    public function enCashMent($par)
    {
//        $par        = input();
        $ordernum   = substr(date('YmdHis'),2).rand(10000, 99999);  //订单号
        $paykey     = empty($par['paykey']) ? config('wx_mchkey') : $par['paykey'];
        $mchid      = empty($par['mchid']) ? config('wx_mchid') : $par['mchid'];
        $sslcert    = empty($par['sslcert_path']) ? config('wx_sslcert') : $par['sslcert_path'];
        $sslkey     = empty($par['sslkey_path']) ? config('wx_sslkey') : $par['sslkey_path'];
        $trade_no   = empty($par['ordernums']) ? $ordernum : $par['ordernums'];
        $desc       = empty($par['desc']) ? "太极泉水提现到微信零钱" : $par['desc'];
        $total      = empty($par['total']) ? 1 : $par['total'];
        if( empty($mchid) && empty($paykey) && empty($sslcert) && empty($sslkey) ){
            $total = 1;
        }

        if(!empty($sslcert) && !empty($sslkey)){
            $sslcert = $this->downFile($sslcert,$par['ordernums']);
            $sslkey  = $this->downFile($sslkey,$par['ordernums']);
        }

        $appid      = $par['appid'];
        $paysecret  = $par['paysecret'];
        $openid     = $par['openid'];
        if(empty($openid)){
            echo json_encode(['code' => 2, "data"=>"",'msg' => '提现用户OpenId参数为空']); exit;
        }

        //②、付款到零钱参数
        $input = new \WxPayTransfersOrder();
        $input->SetOut_trade_no($trade_no);
        $input->SetTotal_fee($total*100);
        $input->SetOpenid($openid);
        $input->SetCheck_name("NO_CHECK");
        $input->SetDesc($desc);

        $config = [
            "GetAppId"       => $appid,
            "GetMerchantId"  => $mchid,
            "GetKey"         => $paykey,
            "GetAppSecret"   => $paysecret,
            "SslcertPath"    => $sslcert,
            "SslkeyPath"     => $sslkey
        ];

        $return = \WxPayApi::wxTransfers(json_encode($config),$input);

        if( $return['return_code'] == "SUCCESS" && $return['result_code'] == "SUCCESS" ) {
            //微信包里面的,我用的是下面自己封装的
            unset($return['return_msg'],$return['result_code'],$return['return_code']);
//            echo json_encode(['code' => 1, "data"=>$return,'msg' => '提现成功。']);
            return json_encode(['code' => 1, "data"=>$return,'msg' => '提现成功。'],JSON_UNESCAPED_UNICODE);
        }

//            echo json_encode(['code' => 2, "data"=>$return,'msg' => '提现出现错误。']);
        return json_encode(['code' => 2, "data"=>$return,'msg' => '提现出现错误。'],JSON_UNESCAPED_UNICODE);

//        exit;
    }


    //商户单号退款接口
    public function refundOutWxPay($par)
    {
//        $par            = input();
        $paykey          = empty($par['paykey']) ? config('wx_mchkey') : $par['paykey'];
        $mchid           = empty($par['mchid']) ? config('wx_mchid') : $par['mchid'];
        $sslcert         = empty($par['sslcert_path']) ? config('wx_sslcert') : $par['sslcert_path'];
        $sslkey          = empty($par['sslkey_path']) ? config('wx_sslkey') : $par['sslkey_path'];
        $refund_price    = empty($par['refund_price']) ? "" : $par['refund_price']; //退款金额
        $total_price     = empty($par['total_price']) ? "" : $par['total_price'];   //支付金额

        $refund_num      = empty($par['refund_num']) ? "" : $par['refund_num'];  //退款单号
        $order_num       = empty($par['order_num']) ? "" : $par['order_num']; //商户订单

        $appid      = $par['appid'];
        $paysecret  = $par['paysecret'];


        //②、退款参数
        $input = new \WxPayRefund();
        $input->SetOut_trade_no($order_num);  //商户订单
        $input->SetOut_refund_no($refund_num); //退款订单
        $input->SetRefund_fee($refund_price);  //退款金额
        $input->SetTotal_fee($total_price);    //支付金额

        if(!empty($sslcert) && !empty($sslkey)){
            $sslcert = $this->downFile($sslcert,$order_num);
            $sslkey  = $this->downFile($sslkey,$order_num);
        }

        //普通商户配置
        $config = [
            "GetAppId"      => $appid,
            "GetMerchantId" => $mchid,
            "GetKey"        => $paykey,
            "GetSignType"   => "MD5",
            "GetAppSecret"  => $paysecret,
            "SslcertPath"   => $sslcert,
            "SslkeyPath"    => $sslkey
        ];

        $isrefund = $this->refundWxQuery($order_num,$config);
        if($isrefund['return_msg'] == "OK" && $isrefund['result_code'] == "SUCCESS" && $isrefund['return_code'] == "SUCCESS") {
            //微信包里面的,我用的是下面自己封装的
            $this->errorLog("微信退款记录:",$isrefund);
            return  json_encode(['code' => 2, "data"=>"",'msg' => '该订单已退款,请勿重复退款！'],JSON_UNESCAPED_UNICODE);

        }

        $refund = \WxPayApi::refund(json_encode($config),$input);
        $this->errorLog("微信退款参数1:",$config);
        $this->errorLog("微信退款传递参数:",$par);
        $this->errorLog("微信退款参数2:",$input);
        $this->errorLog("微信退款参数3:",$refund);
        if($refund['return_msg'] =="OK" && $refund['result_code']=="SUCCESS" && $refund['return_code']=="SUCCESS") {
            //微信包里面的,我用的是下面自己封装的
            unset($refund['return_msg'],$refund['result_code'],$refund['return_code']);
            return json_encode(['code' => 1, "data"=>$refund,'msg' => '退款成功。'],JSON_UNESCAPED_UNICODE);
        }else{
            return json_encode(['code' => 2, "data"=>$refund,'msg' => '退款参数出现错误。'],JSON_UNESCAPED_UNICODE);
        }
    }

    //添加分账接收方
    public function addProfitReceiver()
    {
        $par               = input();
        $paykey            = isset($par['paykey']) ? $par['paykey'] : "";
        $mchid             = isset($par['mchid']) ? $par['mchid']: "";
        $type              = isset($par['type']) ? $par['type'] : "";
        $account           = isset($par['account']) ? $par['account'] : "";
        $name              = isset($par['name']) ? $par['name'] : "";
        $relation_type     = isset($par['relation_type']) ? $par['relation_type'] : "";
        $custom_relation   = isset($par['custom_relation']) ? $par['custom_relation'] : "";
        $appid             = isset($par['appid']) ? $par['appid'] : "";
        $paysecret         = isset($par['paysecret']) ? $par['paysecret'] : "";

        //②、分账接收参数
        $input = new \WxPayProfit();
        //$input->SetMch_id($mchid);         //服务商商户号
        //$input->SetSubmch_id($data["mchid"]); //入驻商家商户号

        if($type == 1){
            $mch_type = "MERCHANT_ID";
        }else{
            $mch_type = "PERSONAL_OPENID";
        }

        //relation_type本字段值为枚举：
        //SERVICE_PROVIDER：服务商
        //STORE：门店
        //STAFF：员工
        //STORE_OWNER：店主
        //PARTNER：合作伙伴
        //HEADQUARTER：总部
        //BRAND：品牌方
        //DISTRIBUTOR：分销商
        //USER：用户
        //SUPPLIER：供应商
        //CUSTOM：自定义

        $receiver = [
            "type"          => $mch_type,      //分账接收方类型,MERCHANT_ID：商户ID , PERSONAL_OPENID：个人openid
            "account"       => $account,       //分账接收方帐号类型是MERCHANT_ID时，是商户ID,类型是PERSONAL_OPENID时，是个人openid
            "name"          => $name,          //类型是MERCHANT_ID时，是商户全称,分账接收方类型是PERSONAL_OPENID时，是个人姓名
            "relation_type" => $relation_type, //与分账方的关系类型子商户与接收方的关系。
        ];

        if($relation_type == "CUSTOM"){
            $receiver['custom_relation'] = $custom_relation;
        }

        $input->SetReceiver(json_encode($receiver)); //分账接收方对象

        //普通商户配置
        $config = [
            "GetAppId"      => $appid,
            "GetMerchantId" => $mchid,
            "GetKey"        => $paykey,
            "GetSignType"   => "HMAC-SHA256",
            "GetAppSecret"  => $paysecret,
        ];

        $r = \WxPayApi::addReceiverProfit(json_encode($config),$input);
        if( $r['result_code']=="SUCCESS" && $r['return_code']=="SUCCESS" ) {
            //微信包里面的,我用的是下面自己封装的
            unset($r['result_code'],$r['return_code']);
            echo json_encode(['code' => 1, "data"=>$r,'msg' => '添加分账接收方成功。']);
        }else{
            echo json_encode(['code' => 2, "data"=>$r,'msg' => '添加分账接收方失败。']);
        }
    }

    //发起分账
    public function profitSharing()
    {
        $par               = input();
        $appid             = isset($par['appid']) ? $par['appid'] : "";
        $paysecret         = isset($par['paysecret']) ? $par['paysecret'] : "";
        $paykey            = isset($par['paykey']) ? $par['paykey'] : "";
        $mchid             = isset($par['mchid']) ? $par['mchid']: "";
        $transaction_id    = isset($par['transaction_id']) ? $par['transaction_id'] : "";
        $out_order_no      = isset($par['out_order_no']) ? $par['out_order_no'] : "";
        $profit_type       = isset($par['profit_type']) ? $par['profit_type'] : 1;
        $receivers         = isset($par['receivers']) ? $par['receivers'] : "{}";
        $sslcert           = isset($par['sslcert_path']) ? $par['sslcert_path'] : "";
        $sslkey            = isset($par['sslkey_path']) ? $par['sslkey_path'] : "";

        //下载证书
        if(!empty($sslcert) && !empty($sslkey)){
            $sslcert = $this->downFile($sslcert,$mchid);
            $sslkey  = $this->downFile($sslkey,$mchid);
        }

        //②、分账接收参数
        $input = new \WxPayProfit();
        $input->SetTransaction_id($transaction_id); //支付订单号
        $input->SetOutOrderNo($out_order_no); //设置商户分账单号
        $input->SetReceivers($receivers); //分账接收方对象

        //普通商户配置
        $config = [
            "GetAppId"       => $appid,
            "GetMerchantId"  => $mchid,
            "GetKey"         => $paykey,
            "GetSignType"    => "HMAC-SHA256",
            "profit_type"    => $profit_type,
            "GetAppSecret"   => $paysecret,
            "SslcertPath"    => $sslcert,
            "SslkeyPath"     => $sslkey
        ];

        $r = \WxPayApi::profitSharing(json_encode($config),$input);

        if( $r['result_code']=="SUCCESS" && $r['return_code']=="SUCCESS" ) {
            //微信包里面的,我用的是下面自己封装的
            unset($r['result_code'],$r['return_code']);
            echo json_encode(['code' => 1, "data"=>$r,'msg' => '分账成功。']);
        }else{
            echo json_encode(['code' => 2, "data"=>$r,'msg' => '分账失败。']);
        }
    }

    //分账完结-直接解冻分账金额
    public function profitSharingFinish()
    {
        $par               = input();
        $appid             = isset($par['appid']) ? $par['appid'] : "";
        $paysecret         = isset($par['paysecret']) ? $par['paysecret'] : "";
        $paykey            = isset($par['paykey']) ? $par['paykey'] : "";
        $mchid             = isset($par['mchid']) ? $par['mchid']: "";
        $transaction_id    = isset($par['transaction_id']) ? $par['transaction_id'] : "";
        $out_order_no      = isset($par['out_order_no']) ? $par['out_order_no'] : "";
        $description       = isset($par['description']) ? $par['description'] : "";
        $sslcert           = isset($par['sslcert_path']) ? $par['sslcert_path'] : "";
        $sslkey            = isset($par['sslkey_path']) ? $par['sslkey_path'] : "";

        //下载证书
        if(!empty($sslcert) && !empty($sslkey)){
            $sslcert = $this->downFile($sslcert,$mchid);
            $sslkey  = $this->downFile($sslkey,$mchid);
        }

        //②、分账接收参数
        $input = new \WxPayProfit();
        $input->SetTransaction_id($transaction_id); //支付订单号
        $input->SetOutOrderNo($out_order_no);       //设置商户分账单号
        $input->SetDescription($description);      //分账完结描述

        //普通商户配置
        $config = [
            "GetAppId"       => $appid,
            "GetMerchantId"  => $mchid,
            "GetKey"         => $paykey,
            "GetSignType"    => "HMAC-SHA256",
            "GetAppSecret"   => $paysecret,
            "SslcertPath"    => $sslcert,
            "SslkeyPath"     => $sslkey
        ];

        $r = \WxPayApi::profitFinish(json_encode($config),$input);

        if( $r['result_code']=="SUCCESS" && $r['return_code']=="SUCCESS" ) {
            //微信包里面的,我用的是下面自己封装的
            unset($r['result_code'],$r['return_code']);
            echo json_encode(['code' => 1, "data"=>$r,'msg' => '分账解除成功。']);
        }else{
            echo json_encode(['code' => 2, "data"=>$r,'msg' => '分账解除失败。']);
        }

    }

    //查询分账结果
    public function profitSharingQuery()
    {
        $par               = input();
        $paysecret         = isset($par['paysecret']) ? $par['paysecret'] : "";
        $paykey            = isset($par['paykey']) ? $par['paykey'] : "";
        $mchid             = isset($par['mchid']) ? $par['mchid']: "";
        $transaction_id    = isset($par['transaction_id']) ? $par['transaction_id'] : "";
        $out_order_no      = isset($par['out_order_no']) ? $par['out_order_no'] : "";

        //②、分账接收参数
        $input = new \WxPayProfit();
        $input->SetTransaction_id($transaction_id); //支付订单号
        $input->SetOutOrderNo($out_order_no); //设置商户分账单号

        //普通商户配置
        $config = [
            "GetMerchantId"  => $mchid,
            "GetKey"         => $paykey,
            "GetSignType"    => "HMAC-SHA256",
            "GetAppSecret"   => $paysecret
        ];

        $r = \WxPayApi::profitQuery(json_encode($config),$input);

        if( $r['result_code']=="SUCCESS" && $r['return_code']=="SUCCESS" ) {
            //微信包里面的,我用的是下面自己封装的
            unset($r['result_code'],$r['return_code']);
            echo json_encode(['code' => 1, "data"=>$r,'msg' => '分账查询成功。']);
        }else{
            echo json_encode(['code' => 2, "data"=>$r,'msg' => '分账查询失败。']);
        }

    }

    //分账回退
    public function profitSharingReturn()
    {
        $par                    = input();
        $appid                  = isset($par['appid']) ? $par['appid'] : "";
        $paysecret              = isset($par['paysecret']) ? $par['paysecret'] : "";
        $paykey                 = isset($par['paykey']) ? $par['paykey'] : "";
        $mchid                  = isset($par['mchid']) ? $par['mchid']: "";
        $out_return_no          = isset($par['out_return_no']) ? $par['out_return_no'] : "";
        $out_order_no           = isset($par['out_order_no']) ? $par['out_order_no'] : "";
        $return_account_type    = isset($par['return_account_type']) ? $par['return_account_type'] : "MERCHANT_ID";
        $return_account         = isset($par['return_account']) ? $par['return_account'] : "";
        $return_amount          = isset($par['return_amount']) ? $par['return_amount'] : "";
        $description            = isset($par['description']) ? $par['description'] : "";
        $sslcert                = isset($par['sslcert_path']) ? $par['sslcert_path'] : "";
        $sslkey                 = isset($par['sslkey_path']) ? $par['sslkey_path'] : "";

        //下载证书
        if(!empty($sslcert) && !empty($sslkey)){
            $sslcert = $this->downFile($sslcert,$mchid);
            $sslkey  = $this->downFile($sslkey,$mchid);
        }

        //②、分账接收参数
        $input = new \WxPayProfit();
        $input->SetOutReturnNo($out_return_no); //支付订单号
        $input->SetOutOrderNo($out_order_no); //设置商户分账单号
        $input->SetReturnAmount($return_amount); //分账接收方对象
        $input->SetDescription($description); //分账接收方对象
        $input->SetReturnAccountType($return_account_type); //分账接收方对象
        $input->SetReturnAccount($return_account); //分账接收方对象

        //普通商户配置
        $config = [
            "GetAppId"       => $appid,
            "GetMerchantId"  => $mchid,
            "GetKey"         => $paykey,
            "GetSignType"    => "HMAC-SHA256",
            "GetAppSecret"   => $paysecret,
            "SslcertPath"    => $sslcert,
            "SslkeyPath"     => $sslkey
        ];

        $r = \WxPayApi::profitReturn(json_encode($config),$input);

        if( $r['result_code']=="SUCCESS" && $r['return_code']=="SUCCESS" ) {
            //微信包里面的,我用的是下面自己封装的
            unset($r['result_code'],$r['return_code']);
            echo json_encode(['code' => 1, "data"=>$r,'msg' => '分账成功。']);
        }else{
            echo json_encode(['code' => 2, "data"=>$r,'msg' => '分账失败。']);
        }

    }

    //查询分账回退
    public function profitSharingReturnQuery()
    {
        $par               = input();
        $appid             = isset($par['appid']) ? $par['appid'] : "";
        $paysecret         = isset($par['paysecret']) ? $par['paysecret'] : "";
        $paykey            = isset($par['paykey']) ? $par['paykey'] : "";
        $mchid             = isset($par['mchid']) ? $par['mchid']: "";
        $out_return_no    = isset($par['out_return_no']) ? $par['out_return_no'] : "";
        $out_order_no      = isset($par['out_order_no']) ? $par['out_order_no'] : "";

        //②、分账接收参数
        $input = new \WxPayProfit();
        $input->SetOutReturnNo($out_return_no); //支付订单号
        $input->SetOutOrderNo($out_order_no); //设置商户分账单号

        //普通商户配置
        $config = [
            "GetAppId"       => $appid,
            "GetMerchantId"  => $mchid,
            "GetKey"         => $paykey,
            "GetSignType"    => "HMAC-SHA256",
            "GetAppSecret"   => $paysecret
        ];

        $r = \WxPayApi::profitReturnQuery(json_encode($config),$input);

        if( $r['result_code']=="SUCCESS" && $r['return_code']=="SUCCESS" ) {
            //微信包里面的,我用的是下面自己封装的
            unset($r['result_code'],$r['return_code']);
            echo json_encode(['code' => 1, "data"=>$r,'msg' => '分账成功。']);
        }else{
            echo json_encode(['code' => 2, "data"=>$r,'msg' => '分账失败。']);
        }

    }


    //支付委托代扣服务接口-支付中签约
    public function contractorderPay(Request $request)
    {
        $data                     = $request->param();
        $paykey                   = empty($data['paykey']) ? config('wx_mchkey') : $data['paykey'];
        $profit_sharing           = empty($data['profit_sharing']) ? "N" : $data['profit_sharing'];
        $attach_par               = isset($data['attach_par']) ? $data['attach_par'] : "";
        $contract_mchid           = isset($data['contract_mchid']) ? $data['contract_mchid'] : "";
        $contract_appid           = isset($data['contract_appid']) ? $data['contract_appid'] : "";
        $plan_id                  = isset($data['plan_id']) ? $data['plan_id'] : "";
        $contract_code            = isset($data['contract_code']) ? $data['contract_code'] : "";
        $contract_notify_url      = isset($data['contract_notify_url']) ? $data['contract_notify_url'] : "";
        $request_serial           = isset($data['request_serial']) ? $data['request_serial'] : "";
        $contract_display_account = isset($data['contract_display_account']) ? $data['contract_display_account'] : "";
        $extend_path    = Env::get('extend_path');
        require_once $extend_path.'/wxsdk/lib/WxPay.Api.php'; //引入SDK文件
        require_once $extend_path.'/wxsdk/lib/WxPay.JsApiPay.php'; //引入SDK文件
        if(empty($data["paybody"])){ $data["paybody"] = "店掌宝"; }
        //②、统一下单
        $input = new \WxPayContract();
        $input->SetBody($data["paybody"]);
        if(empty($attach_par)){
            $input->SetAttach($data["ordernums"]);
        }else{
            $input->SetAttach($attach_par);
        }
        $input->SetOut_trade_no($data["ordernums"]);
        $input->SetTotal_fee($data["total"]);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetNotify_url($data["notify_url"]);
        $input->SetTrade_type($data["trade_type"]);
        $input->SetProfit($profit_sharing);
        $input->SetContract_mchid($contract_mchid);
        $input->SetContract_appid($contract_appid);
        $input->SetPlan_id($plan_id);
        $input->SetContract_code($contract_code);
        $input->SetContract_notify_url($contract_notify_url);
        $input->SetRequest_serial($request_serial);
        $input->SetContract_display_account($contract_display_account);

        $this->errorLog("微信支付回调:",$data);

        //微信支付类型
        if($data["trade_type"] == "JSAPI") {
            $input->SetOpenid($data["openid"]);
        }

        //服务商模式
        $wx_sappid  = config("wx_sappid");
        $wx_sappkey = config("wx_sappkey");
        if($data["is_service"] == 1){
            $input->SetMch_id($wx_sappid);         //服务商商户号
            $input->SetSubmch_id($data["mchid"]); //入驻商家商户号
            //服务商端配置
            $config = [
                "GetAppId"       => $data["appid"],
                "GetMerchantId"  => $wx_sappid,
                "GetKey"         => $wx_sappkey,
                "GetSignType"    => "MD5",
                "GetAppSecret"   => $data["paysecret"],
            ];
        }else{
            //普通商户配置
            $config = [
                "GetAppId"       => $data["appid"],
                "GetMerchantId"  => empty($data['mchid']) ? config('wx_mchid') : $data['mchid'],
                "GetKey"         => $paykey,
                "GetSignType"    => "MD5",
                "GetAppSecret"   => $data["paysecret"],
            ];
        }

        $order = \WxPayApi::contractorderPay(json_encode($config),$input);

//        $this->errorLog("微信自动续费下单返回数据:",json_encode($order,JSON_UNESCAPED_UNICODE));


        if(@$data["istest"] == 1){

            $data = array(
                "return_data" => $data,
                "return_info" => $order
            );
            echo json_encode($data);
            //echo json_encode($order);
            exit;
        }
        $this->errorLog("微信签约支付返回信息:",$order);
        if($order['return_msg'] =="OK" && $order['result_code']=="SUCCESS" && $order['return_code']=="SUCCESS") {
            //微信包里面的,我用的是下面自己封装的
            $tools = new \JsApiPay();
            $jsApiParameters = $tools->GetJsApiParameters(json_encode($config),$order);
            if($data["trade_type"] == "APP") {
                $jsApiParameters_Arg = json_decode($jsApiParameters,true);
                $resdata = [
                    'appid'               => $jsApiParameters_Arg['appId'],
                    'partnerid'           => empty($data['mchid']) ? config('wx_mchid') : $data['mchid'],
                    'prepayid'            => $order['prepay_id'],
                    'package'             => "Sign=WXPay",
                    'noncestr'            => $jsApiParameters_Arg['nonceStr'],
                    'timestamp'           => $jsApiParameters_Arg['timeStamp']
                ];
                $resdata['sign'] = self::makeSign($resdata,$paykey);
                $jsApiParameters = json_encode($resdata);
            }
            echo $jsApiParameters;
        }else{
            echo json_encode(['code' => 2, "info"=>$order, "data"=>$data, 'msg' => '支付参数出现错误。']);
        }

    }


    #=============================================#
    #============== 以下为一些内置方法 ==============#
    #=============================================#

	//制作字符串
	public function getNonceStr($length = 32)
	{
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}

	//制作签名，需要支付密钥
	public function makeSign($data, $paykey)
	{
		//获取微信支付秘钥
		$key = $paykey;
		// 去空
		$data = array_filter($data);
		//签名步骤一：按字典序排序参数
		ksort($data);
		$string_a = http_build_query($data);
		$string_a = urldecode($string_a);
		//签名步骤二：在string后加入KEY
		//$config=$this->config;
		$string_sign_temp = $string_a . "&key=" . $key;
		//签名步骤三：MD5加密
		$sign = md5($string_sign_temp);
		// 签名步骤四：所有字符转为大写
		$result = strtoupper($sign);
		return $result;
	}

	//获得用户IP
	public function getip()
	{
		static $ip = '';
		$ip = $_SERVER['REMOTE_ADDR'];
		if (isset($_SERVER['HTTP_CDN_SRC_IP'])) {
			$ip = $_SERVER['HTTP_CDN_SRC_IP'];
		} elseif (isset($_SERVER['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) and preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
			foreach ($matches[0] as $xip) {
				if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
					$ip = $xip;
					break;
				}
			}
		}
		return $ip;
	}

	/**
	 * 将一个数组转换为 XML 结构的字符串
	 * @param array $arr 要转换的数组
	 * @param int $level 节点层级, 1 为 Root.
	 * @return string XML 结构的字符串
	 */
	public function array2xml($arr, $level = 1)
	{
		$s = $level == 1 ? "<xml>" : '';
		foreach ($arr as $tagname => $value) {
			if (is_numeric($tagname)) {
				$tagname = $value['TagName'];
				unset($value['TagName']);
			}
			if (!is_array($value)) {
				$s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
			} else {
				$s .= "<{$tagname}>" . $this->array2xml($value, $level + 1) . "</{$tagname}>";
			}
		}
		$s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
		return $level == 1 ? $s . "</xml>" : $s;
	}

	/**
	 * 将xml转为array
	 * @param  string   $xml xml字符串
	 * @return array    转换得到的数组
	 */
	public function xml2array($xml)
	{
		//禁止引用外部xml实体
		libxml_disable_entity_loader(false);
		$result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
		return $result;
	}

	//用于支付
	public function curl_post_ssl($url, $xmldata, $second = 30, $aHeader = array())
	{
		$ch = curl_init();
		//超时时间
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		if (count($aHeader) >= 1) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
		}

		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xmldata);
		$data = curl_exec($ch);
		if ($data) {
			curl_close($ch);
			return $data;
		} else {
			$error = curl_errno($ch);
			echo "call faild, errorCode:$error\n";
			curl_close($ch);
			return false;
		}
	}

	//用户退款
	public  function postXmlCurl($xml, $url, $useCert = false, $second = 60, $oid = 0, $source)
	{
		$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);

		//如果有配置代理这里就设置代理
		if (
			WxPayConfig::CURL_PROXY_HOST != "0.0.0.0"
			&& WxPayConfig::CURL_PROXY_PORT != 0
		) {
			curl_setopt($ch, CURLOPT_PROXY, WxPayConfig::CURL_PROXY_HOST);
			curl_setopt($ch, CURLOPT_PROXYPORT, WxPayConfig::CURL_PROXY_PORT);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		// curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
		// curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);//严格校验
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); //严格校验
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		if ($useCert == true && $source == 2) {
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
			curl_setopt($ch, CURLOPT_SSLCERT, PayConfig::get_wx_config_info('sslcert_path', $oid));
			curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
			curl_setopt($ch, CURLOPT_SSLKEY, PayConfig::get_wx_config_info('sslkey_path', $oid));
		}elseif($useCert == true && $source == 5){

			curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
			curl_setopt($ch, CURLOPT_SSLCERT, PayConfig::get_wx_config_info('wxGzhsslcert', $oid));
			curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
			curl_setopt($ch, CURLOPT_SSLKEY, PayConfig::get_wx_config_info('wxGzhsslkey', $oid));
		}

		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//运行curl
		$data = curl_exec($ch);
		//返回结果
		if ($data) {
			curl_close($ch);
			return $data;
		} else {
			$error = curl_errno($ch);
			echo "call faild, errorCode:$error\n";
			curl_close($ch);
			return false;
		}
	}

	//数组转xml
	function arrayToXml($arr)
	{
		$xml = "<root>";
		foreach ($arr as $key => $val) {
			if (is_array($val)) {
				$xml .= "<" . $key . ">" . arrayToXml($val) . "</" . $key . ">";
			} else {
				$xml .= "<" . $key . ">" . $val . "</" . $key . ">";
			}
		}
		$xml .= "</root>";
		return $xml;
	}

    public function downFile($url,$filename)
    {
        $upload_dir = Env::get("root_path");
        $path       = $upload_dir."public/uploads/user_cert/"; //图片存储地址，根据自身项目情况，进行更改
        if (!file_exists($path)) {
            mkdir($path, 0755, true);  //检测该文件夹是否存在，不存在进行创建
        }
        $arr        = parse_url($url); //将得到的http或https 开头的图片进行拆分
        $fileName   = $filename.'_'.basename($arr['path']); //取到名称
        $file       = file_get_contents($url); //获取图片资源
        file_put_contents($path.$fileName,$file); //将图片进行本地保存
        return $path.$fileName;
    }

}
