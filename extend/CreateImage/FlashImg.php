<?php
/**
 * todo 如果需要左图片重叠并且有透明看到背景图一定使用 imagecopy
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/27 0027
 * Time: 11:20
 */

namespace CreateImage;
use think\Db;
use think\Exception;
use think\facade\Env;
class FlashImg
{
    private $font;




    function __construct()
    {
        $this->path = root_path();
        $this->font =  $this->path.'public/SHR.ttf';

    }

    /**
     * 等比例缩放图片
     * @param $filename 源图片资源
     * @param $new_filename 保存图片的地址
     * @param $n_w  新图片的宽度
     * @param $n_h  新图片的高度
     * @throws \Exception
     */
    public function copy_size($filename,$new_filename,$n_w,$n_h) :void
    {

        $new=imagecreatetruecolor($n_w, $n_h);

        $size0=getimagesize($filename);

        $width=$size0[0];
        $height=$size0[1];

        $hz_0 = explode("/",$size0['mime']);
        switch ($hz_0[1]){
            case 'jpg':
            case 'jpeg':
                $img = imagecreatefromjpeg($filename);
                break;
            case 'png':
                $img = imagecreatefrompng($filename);
                break;
            default:
                throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片123。');
                break;
        }

        //copy部分图像并调整
        imagecopyresized($new, $img,0, 0,0, 0,$n_w, $n_h, $width, $height);
        //图像输出新图片、另存为
        $new_hz = explode('.',$new_filename);
        switch ($new_hz[1]){
            case 'jpg':
            case 'jpeg':
                imagejpeg($new, $new_filename);
                break;
            case 'png':
                imagepng($new, $new_filename);
                break;
        }
        imagedestroy($new);
        imagedestroy($img);

    }


    /**
     * 创建指定宽高的图片
     * @param $save_path 保存生成图片的位置
     * @param $img_w 生成图片的宽度
     * @param $img_h 生成图片的高度
     * @param $color_arr rgb颜色的编码 数组
     * @throws \Exception
     */
    public function create_image($save_path,$img_w,$img_h,$color_arr)  :void
    {

        try{
            $canvas=imagecreatetruecolor($img_w,$img_h);

            imagefill($canvas,0,0,imagecolorallocate($canvas,$color_arr[0],$color_arr[1],$color_arr[2]));//生成一个背景图片

            // 圆角处理
            $radius	 = 30;

            $lt_corner	 = imagecreatetruecolor($radius, $radius);	// 创建一个正方形的图像
            $bgcolor	= imagecolorallocate($lt_corner, 255, 255, 255);	 // 图像的背景
            $fgcolor	= imagecolorallocate($lt_corner, 0, 0, 0);
            imagefill($lt_corner, 0, 0, $bgcolor);

            imagefilledarc($lt_corner, $radius, $radius, $radius*2, $radius*2, 180, 270, $fgcolor, IMG_ARC_PIE);
            // 将弧角图片的颜色设置为透明
            imagecolortransparent($lt_corner, $fgcolor);




            // lt(左上角)
//            $lt_corner	= get_lt_rounder_corner($radius);
            imagecopymerge($canvas, $lt_corner, 0, 0, 0, 0, $radius, $radius, 100);
            // lb(左下角)
            $lb_corner	= imagerotate($lt_corner, 90, 0);
            imagecopymerge($canvas, $lb_corner, 0, $img_h - $radius, 0, 0, $radius, $radius, 100);
            // rb(右上角)
            $rb_corner	= imagerotate($lt_corner, 180, 0);
            imagecopymerge($canvas, $rb_corner, $img_w - $radius, $img_h - $radius, 0, 0, $radius, $radius, 100);
            // rt(右下角)
            $rt_corner	= imagerotate($lt_corner, 270, 0);
            imagecopymerge($canvas, $rt_corner, $img_w - $radius, 0, 0, 0, $radius, $radius, 100);

            imagejpeg($canvas,$save_path,100);//保存图片
        }catch (\Exception $e){
            throw new \Exception('生成图片失败！。');
        }


    }



    /**
     * 向指定图片的指定位置写入文字
     * @param $text_arr 要写入的文字 数组  每个元素表示要写入的一段文字
     * 示例[[
     *      "t"=>"文字1",
     *      "f"=>13,    //字体大小
     *      "b"=>0(是否加粗 1加 0不加),
     *      "c"=>[],    //是否设置颜色，如果设置颜色必须是 示例[000,000,00]格式
     * ],["t"=>"文字2","f"=>12,"b"=>0]]
     * @param $left_size 每个写入数组元素对应的位置 示例 [100(一个元素的X),100(一个元素的X)]
     * 如果$is_line=0 则该字段，在方法内部计算获取
     * @param $m_top 距离顶部距离
     * @param $interval 每行的间距
     * @param $img_path 写入文字的图片地址
     * @param $is_line 文字是一行还是换行 0不换行  1换行  如果文字内容大于一个元素，则是需要把数组文字拼接成一段文字
     * @throws \Exception
     */
    public function write_text( array $text_arr,array $left_size,int $m_top,
                                int $interval,string $img_path,int $is_line=0) :void
    {
        $distance = 0;
        $img_data = getimagesize($img_path);
        $img_data_width=$img_data[0];
        $img_data_height=$img_data[1];
        $hz = explode("/",$img_data['mime']);
        switch ($hz[1]){
            case 'jpg':
            case 'jpeg':
                $imagecreatefromjpeg = 'imagecreatefromjpeg';
                $imageTypr = 'imagejpeg';
                break;
            case 'png':
                $imagecreatefromjpeg = 'imagecreatefrompng';
                $imageTypr = 'imagepng';
                break;
            default:
                throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片。');
                break;
        }
        $img_obj = $imagecreatefromjpeg($img_path);
        imagesavealpha($img_obj,true);      //这里很重要 意思是不要丢了$sourePic图像的透明色

        if($is_line == 0){
            //获取写入文字的长度
            $fontBox_width_all = 0;
            $fontBox_height_all = 0;
            foreach ($text_arr as $k=>$v){

                $fontBox = imagettfbbox($v['f'], 0, $this->font, $v['t']);//获取文字所需的尺寸大小
                $min_x = min( array($fontBox[0], $fontBox[2], $fontBox[4], $fontBox[6]) );
                $max_x = max( array($fontBox[0], $fontBox[2], $fontBox[4], $fontBox[6]) );
                $min_y = min( array($fontBox[1], $fontBox[3], $fontBox[5], $fontBox[7]) );
                $max_y = max( array($fontBox[1], $fontBox[3], $fontBox[5], $fontBox[7]) );
                $fontBox_width_all  = $fontBox_width_all+( $max_x - $min_x );
                $fontBox_height_all = $fontBox_height_all+( $max_y - $min_y );
            }

            if($left_size){
                $interval_x = $left_size[0];
            }else{
                $interval_x = 90;
            }


            $interval_y = $m_top;



            if($img_data_width < $fontBox_height_all){
                //把文字平分换行写入
                foreach ($text_arr as $k=>$v){
                    $color_arr = [239, 239, 239];
                    if($v['c']){
                        $color_arr = $v['c'];
                    }
                    if($k == 0){
                        $interval_x = $interval_x + 0;

                    }
                    $fontBox = imagettfbbox($v['f'], 0, $this->font, $v['t']);//获取文字所需的尺寸大小
                    $fontBox_width_all  = $fontBox_width_all+( $max_x - $min_x );

                    if($k > 0){
                        $interval_y = $interval_y+10;
                    }
                    //获取文字个数
                    if($v['b'] > 0){

                        for ($i=1;$i<=$v['b'];$i++){
                            //参数 1 图片资源  2字体大小 3旋转角度 4x轴位置 5y轴位置 6颜色 7字体文件 8要渲染的字符串
                            imagettftext($img_obj, $v['f'], 0, $interval_x+$i, $interval_y,
                                imagecolorallocate($img_obj,$color_arr[0],$color_arr[1],$color_arr[2]), $this->font, $v['t']);
                        }
                    }
                    else{
                        //参数 1 图片资源  2字体大小 3旋转角度 4x轴位置 5y轴位置 6颜色 7字体文件 8要渲染的字符串
                        imagettftext($img_obj, $v['f'], 0, $interval_x, $interval_y,
                            imagecolorallocate($img_obj,$color_arr[0],$color_arr[1],$color_arr[2]), $this->font, $v['t']);
                    }


                }

            }
            else if($img_data_width == $fontBox_height_all){
                $interval_x = 0;

                foreach ($text_arr as $k=>$v){
                    $color_arr = [239, 239, 239];
                    if($v['c']){
                        $color_arr = $v['c'];
                    }
                    if($k == 0){
                        $interval_x = $interval_x + 0;

                    }

                    imagettftext($img_obj, $v['f'], 0, $interval_x,$interval_y,
                        imagecolorallocate($img_obj, $color_arr[0],$color_arr[1],$color_arr[2]),
                        $this->font, $v['t']);

                    $interval_x = $interval_x+$fontBox_width_all;
                }

            }
            else{
                if($left_size){
                    $interval_x = $left_size[0];
                }else{
                    $interval_x = ceil(($img_data_width-$fontBox_width_all)/2);
                }


                foreach ($text_arr as $k=>$v){
                    //获取文字所需的尺寸大小
                    $fontBox = imagettfbbox($v['f'], 0, $this->font, $v['t']);
                    $min_x = min( array($fontBox[0], $fontBox[2], $fontBox[4], $fontBox[6]) );
                    $max_x = max( array($fontBox[0], $fontBox[2], $fontBox[4], $fontBox[6]) );
                    $min_y = min( array($fontBox[1], $fontBox[3], $fontBox[5], $fontBox[7]) );
                    $max_y = max( array($fontBox[1], $fontBox[3], $fontBox[5], $fontBox[7]) );
                    $fontBox_width_all  = ( $max_x - $min_x );
                    $color_arr = [000, 000, 000];
                    if($v['c']){
                        $color_arr = $v['c'];
                    }

                    if($k == 0){
                        $interval_x = $interval_x + 0;

                    }

                    if($v['b'] > 0){

                        for ($i=1;$i<=$v['b'];$i++){
                            //参数 1 图片资源  2字体大小 3旋转角度 4x轴位置 5y轴位置 6颜色 7字体文件 8要渲染的字符串
                            imagettftext($img_obj, $v['f'], 0, $interval_x+$i,$interval_y,
                                imagecolorallocate($img_obj, $color_arr[0],$color_arr[1],$color_arr[2]),
                                $this->font, $v['t']);
                        }

                    }
                    else{
                        //参数 1 图片资源  2字体大小 3旋转角度 4x轴位置 5y轴位置 6颜色 7字体文件 8要渲染的字符串
                        imagettftext($img_obj, $v['f'], 0, $interval_x,$interval_y,
                            imagecolorallocate($img_obj, $color_arr[0],$color_arr[1],$color_arr[2]),
                            $this->font, $v['t']);
                    }





                    $interval_x = $interval_x+$fontBox_width_all+10;
                }


            }
        }
        else{
            //换行写入，以左对齐
            foreach ($text_arr as $k=>$v){
                if($k == 0){
                    $height_txt = imagettfbbox($v['f'], 0,$this->font, "书");
                    //总高度加上 距离顶部
                    $distance = $distance+$m_top;
                }else{
                    //总高度加上 间距
                    $distance = $distance+$interval;
                }

                $color_arr = [239, 239, 239];
                if($v['c']){
                    $color_arr = $v['c'];
                }

                if($v['b'] > 0){

                   for ($i=1;$i<=$v['b'];$i++){
                       //参数 1 图片资源  2字体大小 3旋转角度 4x轴位置 5y轴位置 6颜色 7字体文件 8要渲染的字符串
                       imagettftext($img_obj, $v['f'], 0, $left_size[$k]+$i, $distance,
                           imagecolorallocate($img_obj, $color_arr[0],$color_arr[1],$color_arr[2]), $this->font, $v['t']);
                   }

                }
                else{
                    //参数 1 图片资源  2字体大小 3旋转角度 4x轴位置 5y轴位置 6颜色 7字体文件 8要渲染的字符串
                    imagettftext($img_obj, $v['f'], 0, $left_size[$k], $distance,
                        imagecolorallocate($img_obj,$color_arr[0],$color_arr[1],$color_arr[2]), $this->font, $v['t']);
                }


            }
        }


        imagepng($img_obj, $img_path);//保存图片到指定的地址

        imagedestroy($img_obj);
    }


    /**
     * 为指定图片的指定位置添加指定的小图片
     * @param $image_source_src 源图片地址
     * @param $image_target_src 小图片的地址
     * @param int $img_x 放的x轴位置
     * @param int $img_y 放的y轴位置
     * @throws \Exception
     */
    public function Image_copy(string $image_source_src,string $image_target_src,int $img_x=35,int $img_y=20) :void
    {
        $image_source_dat=getimagesize($image_source_src);

        $hz_source = explode("/",$image_source_dat['mime']);
        switch ($hz_source[1]){
            case 'jpg':
            case 'jpeg':
                $imagecreatefromjpeg = 'imagecreatefromjpeg';
                $imageTypr = 'imagejpeg';
                break;
            case 'png':
                $imagecreatefromjpeg = 'imagecreatefrompng';
                $imageTypr = 'imagepng';
                break;
            default:
                throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片。');
                break;
        }
        //获取源图片资源
        $im0=$imagecreatefromjpeg($image_source_src);

        if(!$image_target_src){
            throw new \Exception('缺少小图片。');
        }

        $image_target_data=getimagesize($image_target_src);
        $image1_width=$image_target_data[0];
        $image1_height=$image_target_data[1];
        $hz_target = explode("/",$image_target_data['mime']);
        switch ($hz_target[1]){
            case 'jpg':
            case 'jpeg':
                $imagecreatefromjpeg1 = 'imagecreatefromjpeg';
                break;
            case 'png':
                $imagecreatefromjpeg1 = 'imagecreatefrompng';
                break;
            default:
                throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片。');
                break;
        }
        $im1=$imagecreatefromjpeg1($image_target_src);


        $new_witch = $img_x;//小
        $new_height = $img_y;


        //参数 1目标图像资源 2源图片资源 3目标X 4目标Y 5源X 6源Y 7目标宽度 8目标高度 9源图像宽度 10 源图像高度
        imagecopyresampled($im0,$im1,intval($new_witch),$new_height,0,0,
              $image1_width,$image1_height, $image1_width,$image1_height);

        $imageTypr($im0,$image_source_src);
        imagedestroy($im0);

        imagedestroy($im1);

    }



    /**
     * 拷贝图像
     * @param $image_source_src 源图像
     * @param $image_target_src 拷贝图像保存的位置
     * @throws \Exception
     */
    public function create_image_copy($image_source_src,$image_target_src) :void
    {
        try{
            //获取源图像数据
            $image_source_dat=getimagesize($image_source_src);

            $hz_source = explode("/",$image_source_dat['mime']);
            switch ($hz_source[1]){
                case 'jpg':
                case 'jpeg':
                    $imagecreatefromjpeg = 'imagecreatefromjpeg';
                    $imageTypr = 'imagejpeg';
                    break;
                case 'png':
                    $imagecreatefromjpeg = 'imagecreatefrompng';
                    $imageTypr = 'imagepng';
                    break;
                default:
                    throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片。');
                    break;
            }
            //获取源图片资源
            $im_source=$imagecreatefromjpeg($image_source_src);
            imagesavealpha($im_source,true);//这里很重要 意思是不要丢了$sourePic图像的透明

            $im_source_width=$image_source_dat[0];
            $im_source_height=$image_source_dat[1];

            //目标图片资源
            $dst_im = imagecreatetruecolor($im_source_width, $im_source_height);


            imagealphablending($dst_im, false );

            imagesavealpha($dst_im, true );

            //参数 $dst_im	目标图像  $im_source	被拷贝的源图像 dst_x	目标图像开始 x 坐标
            //dst_y	目标图像开始 y 坐标，x,y同为 0 则从左上角开始
            //src_x	拷贝图像开始 x 坐标
            //src_y	拷贝图像开始 y 坐标，x,y同为 0 则从左上角开始拷贝
            //$im_source_width	（从 src_x 开始）拷贝的宽度
            //$im_source_height	（从 src_y 开始）拷贝的高度
            imagecopy($dst_im, $im_source, 0, 0, 0, 0, $im_source_width, $im_source_height );

//            imagecopyresampled($dst_im, $im_source, 0, 0, 0, 0,$im_source_width,$im_source_height, $im_source_width, $im_source_height );

            $new_hz = explode('.',$image_target_src);
            switch ($new_hz[1]){
                case 'jpg':
                case 'jpeg':
                    imagejpeg($dst_im, $image_target_src);
                    break;
                case 'png':
                    imagepng($dst_im, $image_target_src);
                    break;
            }
            imagedestroy($dst_im);
            imagedestroy($im_source);
        }catch (\Exception $e){
            throw new \Exception('生成图片失败！。');
        }

    }



    /**
     * 拼接两张图片的上下布局
     * @param $imageSrc0    上面图片的地址
     * @param $imageSrc1    下面保存的地址
     * @param $dirPath      保存文件的地址
     * @param int $type     1上下拼接 2 下面图片重叠上面的图片 3上面的图片重叠下面的图片（未实现）
     * @param $distance      重叠的距离
     * @return string
     */
    public  function mergeImage($imageSrc0, $imageSrc1, $dirPath,$type=1,$distance=0){
        if($type == 1){
            !file_exists($dirPath)&&mkdir($dirPath);
            $size0=getimagesize($imageSrc0);
            $size1=getimagesize($imageSrc1);
            $image0_width=$size0[0];
            $image0_height=$size0[1];
            $image1_width=$size1[0];
            $image1_height=$size1[1];
            if($image0_width>$image1_width){
                $canvas_width=$image0_width;
            }else{
                $canvas_width=$image1_width;
            }
            $canvas_height=$image0_height+$image1_height;
            $hz_0 = explode("/",$size0['mime']);
            $hz_1 = explode("/",$size1['mime']);
            switch ($hz_0[1]){
                case 'jpg':
                case 'jpeg':
                    $imagecreatefromjpeg = 'imagecreatefromjpeg';
                    break;
                case 'png':
                    $imagecreatefromjpeg = 'imagecreatefrompng';
                    break;
                default:
                    throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片。');
                    break;
            }
            switch ($hz_1[1]){
                case 'jpg':
                case 'jpeg':
                    $imagecreatefromjpeg1 = 'imagecreatefromjpeg';
                    break;
                case 'png':
                    $imagecreatefromjpeg1 = 'imagecreatefrompng';
                    break;
                case 'gif':
                default:
                    throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片。');
                    break;
            }

            $im0=$imagecreatefromjpeg($imageSrc0);
            $im1=$imagecreatefromjpeg1($imageSrc1);
            $canvas=imagecreatetruecolor($canvas_width,$canvas_height);
            imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));//生成一个背景图片
            $img0_x=($canvas_width-$image0_width)/2;
            $img1_x=($canvas_width-$image1_width)/2;
            //把图片复制到生成的空白图片上
            imagecopyresampled($canvas,$im0,intval($img0_x),0,0,0,$image0_width,$image0_height,
                $image0_width,$image0_height);
            imagecopyresampled($canvas,$im1,intval($img1_x),$image0_height,0,0,$image1_width,
                $image1_height, $image1_width,$image1_height);


            file_exists($dirPath)&&unlink($dirPath);
            imagejpeg($canvas,$dirPath,100);//保存图片
            imagedestroy($canvas);//删除资源
            imagedestroy($im0);//删除资源
            imagedestroy($im1);//删除资源

        }
        else if($type == 2){
            //上下重叠
            $size0=getimagesize($imageSrc0);
            $size1=getimagesize($imageSrc1);
            $image0_width=$size0[0];
            $image0_height=$size0[1];
            $image1_width=$size1[0];
            $image1_height=$size1[1];

            $create_width = $image0_width;

            $create_height = ($size0[1]+$size1[1])-$distance;

            //生成背景图片
            $canvas=imagecreatetruecolor($create_width,$create_height);

            imagealphablending( $canvas, true );

            imagesavealpha( $canvas, true );


            imagefill($canvas,0,0,imagecolorallocate($canvas,255,0,0));//生成一个背景图片

            $hz_0 = explode("/",$size0['mime']);
            switch ($hz_0[1]){
                case 'jpg':
                case 'jpeg':
                    $imagecreatefromjpeg = 'imagecreatefromjpeg';
                    break;
                case 'png':
                    $imagecreatefromjpeg = 'imagecreatefrompng';
                    break;
                default:
                    throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片。');
                    break;
            }
            $hz_1 = explode("/",$size1['mime']);
            switch ($hz_1[1]){
                case 'jpg':
                case 'jpeg':
                    $imagecreatefromjpeg1 = 'imagecreatefromjpeg';
                    break;
                case 'png':
                    $imagecreatefromjpeg1 = 'imagecreatefrompng';
                    break;
                case 'gif':
                default:
                    throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片。');
                    break;
            }

            $im0=$imagecreatefromjpeg($imageSrc0);
            imagesavealpha($im0,true);      //这里很重要 意思是不要丢了图像的透明色;

            $im1=$imagecreatefromjpeg1($imageSrc1);
            imagesavealpha($im1,true);      //这里很重要 意思是不要丢了图像的透明色;


            //把图片复制到生成的空白图片上
            imagecopy($canvas, $im0, 0, 0, 0, 0, $image1_width, $image1_height );

            //参数 $dst_im	目标图像  $im_source	被拷贝的源图像
            // dst_x	目标图像开始 x 坐标
            //dst_y	目标图像开始 y 坐标，x,y同为 0 则从左上角开始
            //src_x	拷贝图像开始 x 坐标
            //src_y	拷贝图像开始 y 坐标，x,y同为 0 则从左上角开始拷贝
            //$im_source_width	（从 src_x 开始）拷贝的宽度
            //$im_source_height	（从 src_y 开始）拷贝的高度
            imagecopy($canvas, $im1, 0, $image0_height-$distance, 0, 0, $image1_width, $image1_height );

//            imagepng($canvas,$dirPath,100);//保存图片

            $new_hz = explode('.',$dirPath);
            switch ($new_hz[1]){
                case 'jpg':
                case 'jpeg':
                    imagejpeg($canvas, $dirPath,100);
                    break;
                case 'png':
                    imagepng($canvas, $dirPath,9);
                    break;
            }

            imagedestroy($canvas);//删除资源
            imagedestroy($im0);//删除资源
            imagedestroy($im1);//删除资源
        }else if($type == 3)
        {
            throw new \Exception('未实现！。');
        }else{
            throw new \Exception('类型错误！。');
        }


    }




    /**
     * 把图片放在指定图片的上面
     * @param $imageSrc0  底下的图片
     * @param $imageSrc1  遮罩层图片（上面的图片）
     */
    public function coverImage($imageSrc0,$imageSrc1){

        $size0=getimagesize($imageSrc0);
        $size1=getimagesize($imageSrc1);
        $image0_width=$size0[0];
        $image0_height=$size0[1];
        $image1_width=$size1[0];
        $image1_height=$size1[1];



        //生成背景图片
        $hz_0 = explode("/",$size0['mime']);
        switch ($hz_0[1]){
            case 'jpg':
            case 'jpeg':
                $imagecreatefromjpeg = 'imagecreatefromjpeg';
                break;
            case 'png':
                $imagecreatefromjpeg = 'imagecreatefrompng';
                break;
            default:
                throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片。');
                break;
        }
        $hz_1 = explode("/",$size1['mime']);
        switch ($hz_1[1]){
            case 'jpg':
            case 'jpeg':
                $imagecreatefromjpeg1 = 'imagecreatefromjpeg';
                break;
            case 'png':
                $imagecreatefromjpeg1 = 'imagecreatefrompng';
                break;
            case 'gif':
            default:
                throw new \Exception('不支持图片类型！请使用jpg、jpeg、png后缀图片。');
                break;
        }

        $im0=$imagecreatefromjpeg($imageSrc0);
        imagesavealpha($im0,true);      //这里很重要 意思是不要丢了图像的透明色;

        $im1=$imagecreatefromjpeg1($imageSrc1);
        imagesavealpha($im1,true);      //这里很重要 意思是不要丢了图像的透明色;


        //把图片复制到生成的空白图片上
//        imagecopy($canvas, $im0, 0, 0, 0, 0, $image1_width, $image1_height );

        //参数 $dst_im	目标图像  $im_source	被拷贝的源图像
        // dst_x	目标图像开始 x 坐标
        //dst_y	目标图像开始 y 坐标，x,y同为 0 则从左上角开始
        //src_x	拷贝图像开始 x 坐标
        //src_y	拷贝图像开始 y 坐标，x,y同为 0 则从左上角开始拷贝
        //$im_source_width	（从 src_x 开始）拷贝的宽度
        //$im_source_height	（从 src_y 开始）拷贝的高度
        imagecopy($im0, $im1, 0, 0, 0, 0, $image1_width,$image1_height );


        imagedestroy($im0);//删除资源
        imagedestroy($im1);//删除资源

    }


    /**
     * 拉取url图片
     * @param $url 拉取的图片地址
     * @param string $filename  文件地址
     * @return string
     * @throws Exception
     */
    public function GrabImage($url, $filename = "")
    {

        if ($url == "") {
            throw new \think\Exception('缺少网络地址', 10006);
        }
        $ext = strrchr($url, ".");
        //得到$url的图片格式
        if ($ext != ".png" && $ext != ".jpg") {
            throw new \think\Exception('网络图片的后缀不符合规范！', 10006);
        }


        if(!file_exists($filename)){
            throw new \think\Exception('保存文件地址检查错误！', 10006);
        }


        ob_start();//打开输出
        readfile($url);//输出图片文件
        $img = ob_get_contents();//得到浏览器输出
        ob_end_clean();//清除输出并关闭
        $size = strlen($img);//得到图片大小
        $fp2 = @fopen($filename, "a");
        fwrite($fp2, $img);//向当前目录写入图片文件
        fclose($fp2);
        return $filename;//返回新的文件路径
    }

}