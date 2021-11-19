<?php
declare (strict_types = 1);

namespace app\middleware;

use app\BaseController;
use Firebase\JWT\JWT;
use think\facade\Cache;
use think\facade\Log;

class JwtMiddleware extends BaseController
{
    const  ALG = array('HS256');
    const  AUTH_HEADER='X-AUTH-TOKEN';
    const MIN_TIME=60*60*100;
    const MAX_TIME = 60*60*1000;



    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {

        $auth = $request->header(self::AUTH_HEADER);

        $key = config('app.JWT_KEY');
        $header = config('apiadmin.CROSS_DOMAIN');

        //缓存已超过短时间的键值对的token形式是 旧token:新toekn
        //缓存已已登录的有效token的键值对

        //jwt的key
        if(!empty($auth)) {
            try {
                //当出现并行的时候，多个请求同事发出
//                if(Cache::has($auth)){
//                    $auth = Cache::get($auth);
//                }
//
//                $payload = (array)JWT::decode($auth, $key, self::ALG);
//                Log::write("获取用户的保存信息：".print_r($payload,true));
//                //【注释单点登录】判断对应的登录用户是否token一致，如果不一致，认为是被另一个账号挤下区了
//                $re = cache_token($payload['uid'],$auth);
//                if(!$re['s']){
//                    if($re['m'] == 1){
//                        $re_json = json(['code'=>401,'data'=>"",'msg'=>'您的账号已在其他设备登录！']);
//                    }elseif ($re['m'] == 2){
//                        $re_json = json(['code'=>401,'data'=>"",'msg'=>'请登录后在访问！']);
//                    }else{
//                        $re_json = json(['code'=>401,'data'=>"",'msg'=>'请登录']);
//                    }
//                    return $re_json;
//                }
//                //验证token的短时间是否已超过，如果超过设置新的token并在头部添加返回
//                $c_time = time();
//                if(($c_time > $payload['iat']+self::MIN_TIME) && ($c_time <$payload['exp'])){
//                    $exp = time() + (self::MAX_TIME);
//                    unset($payload['jti'],$payload['iss'],$payload['iat'],$payload['exp']);
//                    $new_token = handlerUserLogin($payload,$exp);
//                    //设置短时间过期的token保存，已应对并发问题
//                    $re_set = Cache::set($auth,$new_token,20);
//
//                    if(!$re_set){
//                        throw new \think\Exception('账号已失效', 10006);
//                    }
//                    header(self::AUTH_HEADER . ':' . $new_token);
//                    //重新设置用户对应的新的token
//                    $re_token = cache_token($payload['uid'],$new_token,2,$exp);
//                    if(!$re_token['s']){
//                        throw new \think\Exception('账号已失效', 10006);
//                    }
//                }
//                //如果这次使用的token，已超过设置的短时间的有效期，则生成新的token,在头部添加返回前端
                //当出现并行的时候，多个请求同事发出
                if(Cache::has($auth)){
                    $auth = Cache::get($auth);
                    header(self::AUTH_HEADER . ': ' . $auth);
                    $payload = (array)JWT::decode($auth, $key, self::ALG);
                }
                else{
                    $payload = (array)JWT::decode($auth, $key, self::ALG);
//                    Log::write("获取用户的保存信息：".print_r($payload,true));
                    //【注释单点登录】判断对应的登录用户是否token一致，如果不一致，认为是被另一个账号挤下区了
                    $re = cache_token($payload['uid'],$auth,1,null,$payload);

                    if(!$re['s']){
//                        header('Access-Control-Allow-Origin: *');
//                        header('Content-Type:application/json: charset=utf-8');
//                        header('Access-Control-Allow-Methods: POST,PUT,GET,DELETE');
//                        header('Access-Control-Allow-Headers: access-token,content-type,x-auth-token,X-AUTH-TOKEN,Version, Access-Token, User-Token, Api-Auth, User-Agent, Keep-Alive, Origin, No-Cache, X-Requested-With, If-Modified-Since, Pragma, Last-Modified, Cache-Control, Expires, Content-Type, X-E4M-With');
//                        header('Access-Control-Expose-Headers: access-token,content-type,VersionX-AUTH-TOKEN,x-auth-token');
                        if($re['m'] == 1){
                            return json(['code'=>401,'data'=>"",'msg'=>'您的账号已在其他设备登录！'])->header($header);
                        }elseif ($re['m'] == 2){
                            return json(['code'=>401,'data'=>"",'msg'=>'请登录后在访问！'])->header($header);
                        }else{
                            return json(['code'=>401,'data'=>"",'msg'=>'请登录'])->header($header);
                        }
//                        return $re_json;
                    }else{
                        if($re['r'] == 11){
                            header(config('app.AUTH_HEADER') . ': ' . $re['t']);
                        }
                    }
                }

            }
            catch (\Exception $e){
                $header = config('apiadmin.CROSS_DOMAIN');
//                header('Access-Control-Allow-Origin: *');
//                header('Content-Type:application/json: charset=utf-8');
//                header('Access-Control-Allow-Methods: POST,PUT,GET,DELETE');
//                header('Access-Control-Allow-Headers: access-token,content-type,x-auth-token,X-AUTH-TOKEN,Version, Access-Token, User-Token, Api-Auth, User-Agent, Keep-Alive, Origin, No-Cache, X-Requested-With, If-Modified-Since, Pragma, Last-Modified, Cache-Control, Expires, Content-Type, X-E4M-With');
//                header('Access-Control-Expose-Headers: access-token,content-type,VersionX-AUTH-TOKEN,x-auth-token');
                if($e->getMessage() == 'Syntax error, malformed JSON'){

                    return json(['code'=>401,'data'=>"",'msg'=>'错误TOKEN！'])->header($header);
                }
                return json(['code'=>401,'data'=>"",'msg'=>'账号已失效，请重新登录！'])->header($header);
            }

            $request->jwt_payload = $payload;
        }


        $response = $next($request);
        return $response;
    }
}
