<?php

use Endroid\QrCode\QrCode;
class ImageQrcode
{

    public function create_qrcode($arr){
        $url = $arr;
//        $level = 'L';$size = 10;$margin = 2;
//
//        try{
//            $QRcode =  new QrCode();
//
//
//        }catch (Exception $e){
//            return '';
//        }
//
////        $upload_dir = ENV::get('root_path') .  'public/temQRCode/';
//
////        $name       = uniqid() . '.png';
////        if (!is_dir($upload_dir)) {
////            mkdir($upload_dir, 0755, true);
////        }
////        $filedir = $upload_dir . $name;
//        $QRcode->png($url,false,$level,$size,$margin);
//
////        $imgInfo = request()->domain() . '/temQRCode/' . $name;
////            $imageString = base64_encode(ob_get_contents());
////        ob_end_clean(); //清除缓冲区的内容，并将缓冲区关闭，但不会输出内容
////        return "data:image/jpg;base64,".$imageString;
//        ob_start();
//
//        $img = ob_get_contents();//获取缓冲区内容
//
//        ob_end_clean();//清除缓冲区内容
//        var_dump($img);die;
//        $imgInfo = 'data:png;base64,' . chunk_split(base64_encode($img));//转base64
//
//        return $imgInfo;
        $qrCode = new QrCode($url);
        return $qrCode->writeDataUri();
    }


    //生成二维码图片
    public function createQrcodeImage($url,$id){
        $qrCode = new QrCode($url);

        try{
            $qrCode->setSize(300);
            $qrCode->setWriterByName('png');
            $qrCode->setMargin(10);
            $qrCode->setEncoding('UTF-8');

            $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
            $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);
            $qrCode->setLogoSize(150, 200);
            $qrCode->setRoundBlockSize(true);
            $qrCode->setValidateResult(false);
            $qrCode->setWriterOptions(['exclude_xml_declaration' => true]);


            $today_time = date("Ymd", time());

            $path_file = root_path().'public/storage/cardQr/'.$today_time.'/';
            if (!file_exists($path_file)) {
                mkdir($path_file, 0755, true);
            }

            $name = date("YmdHim", time()).$id.".png";

            $qecodeName = $path_file.$name;
            $qrCode->writeFile($qecodeName);

        }catch (Exception $e){
            return '';
        }


        return config('app.api_url').'storage/cardQr/'.$today_time.'/'.$name;

    }

}