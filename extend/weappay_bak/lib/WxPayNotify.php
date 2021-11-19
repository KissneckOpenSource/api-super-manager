<?php
namespace weappay\lib;

use think\Controller;
use app\api\exception\weappay\lib\WxPayApi;
use app\api\exception\weappay\lib\log;
use app\common\model\Order;
use app\common\model\MemberCoupons as Mcoupon;
use app\common\model\Coupons as Coupon;
use app\common\model\Products as Pro;
use think\Db;


/**
 *
 * 回调基础类
 * @author widyhu
 *
 */

class WxPayNotify extends WxPayNotifyReply
{

    /**
     *
     * 回调入口
     * @param bool $needSign  是否需要签名输出
     */
    final public function Handle($needSign = true)
    {
        $msg = "OK";
        Log::DEBUG("异步反馈:" . json_encode(input()));
        //当返回false的时候，表示notify中调用NotifyCallBack回调失败获取签名校验失败，此时直接回复失败
        $result = WxpayApi::notify(array($this, 'NotifyCallBack'), $msg);
        Log::DEBUG(json_encode($result));
        if($result == false){
            $this->SetReturn_code("FAIL");
            $this->SetReturn_msg($msg);
            $this->ReplyNotify(false);
            return;
        } else {
            //该分支在成功回调到NotifyCallBack方法，处理完成之后流程
            $this->SetReturn_code("SUCCESS");
            $this->SetReturn_msg("OK");
        }
        $this->ReplyNotify($needSign);
    }

    /**
     *
     * 回调方法入口，子类可重写该方法
     * 注意：
     * 1、微信回调超时时间为2s，建议用户使用异步处理流程，确认成功之后立刻回复微信服务器
     * 2、微信服务器在调用失败或者接到回包为非确认包的时候，会发起重试，需确保你的回调是可以重入
     * @param array $data 回调解释出的参数
     * @param string $msg 如果回调处理失败，可以将错误信息输出到该方法
     * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */

    public function NotifyProcess($data, &$msg)
    {

        Log::DEBUG("异步支付反馈2---:" . json_encode($data));

        $result_code = $data['result_code'];
        $return_code = $data['return_code'];

        if( $result_code == 'SUCCESS' && $return_code == 'SUCCESS' ){

            $oid       = $data["attach"];
            $order     = new Order;
            $orderinfo = $order->where('id',$oid)->find();
            Log::DEBUG("异步支付反馈3---:" . $orderinfo);
            if( $orderinfo['status'] == '待付款' ){

                Log::DEBUG("异步支付反馈4---:" . json_encode($data));

                //支付成功更改订单状态
                $arg = [
                    'id'        => $oid,
                    'status'    => 2,
                    'paynumber' => $data['transaction_id'],
                    'paytime'   => time()
                ];

                $r = $order->where('id',$oid)->update($arg);

                if($r) {

                    //添加用户已经使用的优惠劵
                    $couponinfo = Coupon::where('id', $orderinfo['iscoupon'])->find();
                    $couponData = [
                        'coupon_id' => $orderinfo['iscoupon'],
                        'uid' => $orderinfo['uid'],
                        'oid' => $orderinfo['oid'],
                        'product_id' => $couponinfo['product_id'],
                        'coupon_name' => $couponinfo['coupon_name'],
                        'coupon_price' => $couponinfo['coupon_price'],
                        'coupon_left_time' => $couponinfo['coupon_left_time'],
                        'coupon_add_time' => $couponinfo['coupon_add_time'],
                        'coupon_note' => $couponinfo['coupon_note'],
                        'coupon_condition' => $couponinfo['coupon_condition'],
                        'coupon_status' => 2,
                        'coupon_createtime' => time(),
                    ];

                    Mcoupon::create($couponData);

                    //更新商品销量
                    $order_info   = json_decode($orderinfo['order_info'],true);
                    foreach ($order_info as $v){

                        $pid         = $v['id'];
                        $shareuid    = $v['shareuid'];

                        $walletinfo  = Db::name('member_wallet')->where('w_uid',$shareuid)->find();

                        $commission  = Pro::where('id',$pid)->value("commission"); //佣金
                        $ordercount  = Pro::where('id',$pid)->value("ordercount"); //销量

                        $proArg = [
                            'id'          => $pid,
                            'ordercount'  => $ordercount+1
                        ];

                        Pro::where('id',$pid)->update($proArg);


                        //佣金计算
                        $wallet = [
                            'w_uid'      => $shareuid,
                            'w_price'    => $walletinfo['w_price']+$commission
                        ];
                        if($walletinfo){
                            $wallet['updatetime'] = time();
                            Db::name('member_wallet')->where('w_id',$walletinfo['w_id'])->update($wallet);
                        }else{
                            $wallet['updatetime'] = time();
                            $wallet['createtime'] = time();
                            Db::name('member_wallet')->insert($wallet);
                        }

                    }

                }
                Log::DEBUG("异步支付反馈5---:" . $order->id);
            }

        }else{

            if( !array_key_exists("transaction_id", json_decode($data) )){
                $msg = "输入参数不正确";
                Log::DEBUG("错误返回:" . $msg);
                return false;
            }
            Log::DEBUG("支付失败:" . json_encode($data));
        }

        return true;
    }

    /**
     *
     * notify回调方法，该方法中需要赋值需要输出的参数,不可重写
     * @param array $data
     * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */

    final public function NotifyCallBack($data)
    {
        $msg = "OK";
        $result = $this->NotifyProcess($data, $msg);
        if($result == true){
            $this->SetReturn_code("SUCCESS");
            $this->SetReturn_msg("OK");
        } else {
            $this->SetReturn_code("FAIL");
            $this->SetReturn_msg($msg);
        }
        return $result;
    }


    /**
     *
     * 回复通知
     * @param bool $needSign 是否需要签名输出
     */
    final private function ReplyNotify($needSign = true)
    {
        //如果需要签名
        if($needSign == true && $this->GetReturn_code($return_code) == "SUCCESS")
        {
            $this->SetSign();
        }
        WxpayApi::replyNotify($this->ToXml());
    }
}