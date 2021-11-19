<?php
namespace Wxcheck;

require_once root_path().'/vendor/autoload.php';

use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Ocr\V20181119\Models\GeneralAccurateOCRRequest;
use TencentCloud\Ocr\V20181119\Models\GeneralBasicOCRRequest;
use TencentCloud\Ocr\V20181119\Models\GeneralFastOCRRequest;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Common\Credential;
use TencentCloud\Ocr\V20181119\OcrClient;

class ocr_demo
{

    private $appid;
    private $secret_id;
    private $secret_key;
    /**
     * 构造函数
     *
     * @param string $appid  sdkappid
     */
    public function __construct($appid,$secret_id,$secret_key)
    {
        $this->appid = $appid;
        $this->secret_id = $secret_id;
        $this->secret_key = $secret_key;

    }


    //图片转文字接口
    /**
     * @param $data  数组 必须由ImageBase64 或者ImageUrl其中一个字段
     * @return false|string
     */
    public function create_credential($data){
        try{
            if(!isset($data['ImageBase64']) && !isset($data['ImageUrl'])){
                return ['code'=>2,'data'=>new \stdClass(),'msg'=>'请提交数据或者网址！'];
            }

            if(isset($data['ImageBase64']) && isset($data['ImageUrl'])){
                return ['code'=>2,'data'=>new \stdClass(),'msg'=>'请提交数据或者网址其中一项就可以！'];
            }



            $cred = new Credential($this->secret_id, $this->secret_key);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("ocr.tencentcloudapi.com");

            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new OcrClient($cred,"ap-shanghai",$clientProfile);

            $req = new GeneralBasicOCRRequest();

            if(isset($data['ImageBase64'])){
                $params = [ 'ImageBase64'=>$data['ImageBase64']];
            }else{
                $params = [ 'ImageUrl'=>$data['ImageUrl']];
            }

            $params_json = json_encode($params,JSON_UNESCAPED_UNICODE);

            $req->fromJsonString($params_json);



            $resp = $client->GeneralBasicOCR($req);

        }catch (\Exception $e){
            return ['code'=>2,'data'=>new \stdClass(),'msg'=>$e->getMessage()];
        }


        $res_arr = json_decode($resp->toJsonString(),true);

        $re_str_arr = [];
        if(is_array($res_arr['TextDetections'])){
            $re_str_arr = array_column($res_arr['TextDetections'],'DetectedText');
        }
        return ['code'=>1,'data'=>$re_str_arr,'msg'=>'图像解析'];
    }
}