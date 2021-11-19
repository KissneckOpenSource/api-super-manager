<?php
declare (strict_types=1);
/**
 * 工程基类
 * @since   2017/02/28 创建
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\controller\api;

use app\BaseController;
use app\util\ReturnCode;
use think\App;
use think\facade\Env;
use think\Response;
use \think\facade\Db;

class Base extends BaseController {

    //验证api放行方法 全部只能是小写 填写路由名字
    protected $exceptAuthActions=[
        'api/Login/getCode',
        'api/Login/login',
        'api/Admin/login',
    ];

    private $debug = [];
    protected $userInfo = [];
    protected $log_id = 0;
    protected $datas = 0;


    public function __construct(App $app) {
        parent::__construct($app);
        //这部分初始化用户信息可以参考admin模块下的Base去自行处理
        $this->requireLogin();
    }

    public function buildSuccess(array $data = [], string $msg = '操作成功', int $code = ReturnCode::SUCCESS): Response {
        $return = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data
        ];
        if (Env::get('APP_DEBUG') && $this->debug) {
            $return['debug'] = $this->debug;
        }

        $update = [
            'data'=>json_encode($this->datas,JSON_UNESCAPED_UNICODE)
        ];
        

        return json($return);
    }

    public function buildFailed(int $code, string $msg = '操作失败', array $data = []): Response {
        $return = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data
        ];
        if (Env::get('APP_DEBUG') && $this->debug) {
            $return['debug'] = $this->debug;
        }

        return json($return);
    }

    protected function debug($data): void {
        if ($data) {
            $this->debug[] = $data;
        }
    }

    //JWT获取用户登录信息
    protected function requireLogin() {
        $this->initUser();
        //修改为获取路由地址
        $tem_a_c = $this->request->pathinfo();

        if(in_array($tem_a_c,$this->exceptAuthActions)) {
            return;
        }


        if(empty($this->uid)) {
            header('Access-Control-Allow-Origin: *');
            header('Content-Type:application/json: charset=utf-8');
            header('Access-Control-Allow-Methods: POST,PUT,GET,DELETE');
            header('Access-Control-Allow-Headers: access-token,content-type,x-auth-token,X-AUTH-TOKEN,Version, Access-Token, User-Token, Api-Auth, User-Agent, Keep-Alive, Origin, No-Cache, X-Requested-With, If-Modified-Since, Pragma, Last-Modified, Cache-Control, Expires, Content-Type, X-E4M-With');
            header('Access-Control-Expose-Headers: access-token,content-type,VersionX-AUTH-TOKEN,x-auth-token');

            $re = ['code'=>401,'data'=>new \stdClass(),'msg'=>'需要登录'];
            echo json_encode($re,JSON_UNESCAPED_UNICODE);
            die();

        }
    }


    //如果登录了，获取用户的登录信息
    protected function initUser() {
        if(!empty($this->request->middleware('jwt_payload'))) {
            $jwt_arr = $this->request->middleware('jwt_payload');
            foreach ($jwt_arr as $k=>$v){
                $this->$k = $v;
            }
        }
        else {
            $this->uid = 0;
        }
    }


}
