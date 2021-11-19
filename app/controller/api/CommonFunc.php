<?php


namespace app\controller\api;


use app\model\AddressShi;
use app\model\Banner;
use app\model\ConfigM;
use app\util\ReturnCode;
use think\facade\Db;
use think\facade\Log;

class CommonFunc extends Base
{
    //通用上传图片
    //返回 上传图片的url地址
    public function upImage(){

//        $tem = request()->file('files');

//        Log::write(print_r($tem,true));

        //二进制文件 img_file
        $files = request()->file('file');

//        if(!$files){
//            return $this->buildFailed(ReturnCode::INVALID,'请上传图片');
//        }

        Log::write(print_r($files,true));
        try {
            if(!is_array($files)){
                $files =['file'=>$files];
            }
            validate(['image'=>'filesize:202400|fileExt:jpg,jpeg,png,xlsx,xls'])->check($files);
            $savename = [];
            foreach($files as $file) {
                $tem_name = \think\facade\Filesystem::disk('public')->putFile( 'topic', $file);
                $savename[] = strtr($tem_name,'\\','/');
            }
        } catch (\think\exception\ValidateException $e) {

            return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
        }



        if (empty($savename)){
            return $this->buildFailed(ReturnCode::INVALID,'图片上传失败');
        }else{
            $res_data = [];
            foreach ($savename as $v){
                $res_data[] = [
                    'src'=>config('app.api_url') . 'storage/'. $v,
                    'path'=>'/storage/' . $v
                ];
            }

            return $this->buildSuccess($res_data,'图片上传成功！');
        }
    }

    //获取会员金额
    public function getVipFee(){
        $re = ConfigM::get_all_config();

        if(!isset($re['vip_fee']) || empty($re['vip_fee'])){
            return $this->buildFailed(ReturnCode::INVALID,'获取会员费用失败！');
        }

        if(!isset($re['vip_bucket']) || empty($re['vip_bucket'])){
            return $this->buildFailed(ReturnCode::INVALID,'获取会员免桶数量失败！');
        }

        return $this->buildSuccess(['vip_fee'=>bcdiv($re['vip_fee'],100,2),'vip_bucket'=>$re['vip_bucket']],'会员费用');
    }

    //获取banner图片
    public function getBanner(){

        $re = Banner::get_banner();

        return $this->buildSuccess($re,'轮播图片！');
    }

    //获取用户地址
    public function getLocation(){
        $param = $this->request->param();

        if(!isset($param['c']) || empty($param['c'])){
            return $this->buildFailed(ReturnCode::INVALID,'请传递城市名称');
        }
        $area     = isset($param['d']) ? $param['d'] : "";
        if($param['c'] == "重庆市市辖区" || $param['c'] == "重庆市重庆市") {
            $area_data = array("彭水县", "垫江县", "涪陵区", "渝北区", "江北区", "渝中区", "南岸区", "九龙坡区", "大渡口区", "沙坪坝区", "北碚区", "巴南区");
            if (!in_array($area, $area_data)) {
                return $this->buildFailed(ReturnCode::INVALID, '您所在的区县暂未开通配送服务！');
            }
        }
        //查询对应的城市是否开通
        $re = AddressShi::whereRaw('locate("'.$param['c'].'",name) > 0')
            ->where('is_on',1)->field('id')->find();

        if(!$re){
            return $this->buildFailed(ReturnCode::INVALID,'您所在的城市暂未开通配送服务，点击申请开通，我们将尽快开通！');
        }

        return $this->buildSuccess([],'地址在配送区域！');
    }


    //公共订单支付完成 或者 订单未收货的退款通过，归还用户的桶冻结





    //后台创建、编辑、删除轮播


    /**
     * jd快递查询
     * @return \think\Response
     */
    public function expressInquiry()
    {
        $no = $this->request->param('no');
        $res = JDExpressQuery($no);
        $res = json_decode($res['result'], true);
        $res = $res['jingdong_eclp_co_gotoB2BSWbMainAllTrack_responce']['CoCreateLwbResult_result'];
        if(empty($res['b2bLwbTrack'])){
            return $this->buildSuccess();
        }else{
            return $this->buildSuccess($res['b2bLwbTrack']);
        }

    }


    /**
     * 获取太极泉第三方用户信息
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function gettjqUserInfo()
    {
        $uid  = $this->request->param('uid');
        $tkey = $this->request->param('key');

        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);

        $key = config("app.tjq_third_api_key");
        $sign = md5($key.$msectime.$uid);
        $param = [
            'sign'      => $sign,
            'timestamp' => $msectime,
            'userId'    => $uid,
            'key'       => $tkey,
        ];
        $param = http_build_query($param);
        $url = config("app.mm_link")."/userInfo?".$param;
        $res = makeRequest($url);
        //$res = json_decode($res['result'], true);

        print_r($res);
        exit;

        if(!isset($res['code']) || empty($res['code']) || !is_array($res['code'])){
            return $this->buildFailed(ReturnCode::INVALID,"获取信息失败");
        }

        if(!isset($res['code']['errcode']) || $res['code']['errcode'] != 0 )
            return $this->buildFailed(ReturnCode::INVALID,"获取信息失败");


        if(!isset($res['code']['errmsg']) || empty($res['code']['errmsg']) || $res['code']['errmsg'] != "success" )
            return $this->buildFailed(ReturnCode::INVALID,"获取信息失败");

        $data = json_decode($res['data'], true);

        if(empty($data['mobile']))
            return $this->buildFailed(ReturnCode::INVALID,"获取信息失败");


        $member = \app\model\Member::where('id',$this->uid)->find();

        if(empty($member)){
            return $this->buildFailed(ReturnCode::INVALID,"信息绑定失败");
        }
        $member->save([
            'sync_id' => isset($data['id'])?$data['id']:0,
            'nickname' => isset($data['nickname'])?$data['nickname']:null,
            'tel' => isset($data['mobile'])?$data['mobile']:null,
            'wx_unionid' => isset($data['unionid'])?$data['unionid']:null,
        ]);

        return $this->buildSuccess();
    }


}