<?php
namespace weappay\lib;
/**
* 2015-06-29 修复签名问题
**/
//use app\api\exception\weappay\lib\WxPayConfig;
//use app\api\exception\weappay\lib\WxPayDataBase;
//use app\api\exception\weappay\lib\WxPayException;

/**
 * 
 * 接口调用结果类
 * @author widyhu
 *
 */
class WxPayResults extends WxPayDataBase
{
	/**
	 * 
	 * 检测签名
	 */
	public function CheckSign($paysecret='')
	{

	    //fix异常
		if(!$this->IsSignSet()){
			throw new WxPayException("签名错误-1！");
		}
		
		$sign = $this->MakeSign($paysecret);
		if($this->GetSign() == $sign){
			return true;
		}

		throw new WxPayException("签名错误-2！");
	}
	
	/**
	 * 
	 * 使用数组初始化
	 * @param array $array
	 */
	public function FromArray($array)
	{
		$this->values = $array;
	}
	
	/**
	 * 
	 * 使用数组初始化对象
	 * @param array $array
	 * @param 是否检测签名 $noCheckSign
	 */
	public static function InitFromArray($array, $noCheckSign = false)
	{
		$obj = new self();
		$obj->FromArray($array);
		if($noCheckSign == false){
			$obj->CheckSign(1);
		}
        return $obj;
	}
	
	/**
	 * 
	 * 设置参数
	 * @param string $key
	 * @param string $value
	 */
	public function SetData($key, $value)
	{
		$this->values[$key] = $value;
	}
	
    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
	public static function Init($xml,$paysecret=0)
	{	
		$obj = new self();
		$obj->FromXml($xml);
		//fix bug 2015-06-29
		if($obj->values['return_code'] != 'SUCCESS'){
			 return $obj->GetValues();
		}
		//$obj->CheckSign($paysecret); // 微信小程序支付使用的是这个方法来验证签名
        return $obj->GetValues();
	}
}
