<?php


namespace app\controller\api;


use app\model\ConfigM;
use app\model\help;
use app\model\Menu;
use app\model\Profit;
use app\model\Member;
use app\model\Admin as AdminModel;
use app\model\QuestionnaireRecord;
use app\util\ReturnCode;
use app\util\Tools;
use think\Exception;
use think\facade\Cache;
use \think\facade\Db;
use Wxcheck\WxMini;

/**
 * 后台接口
 * Class Admin
 * @package app\controller\api
 */
class Admin extends Base
{

    /**
     * 后台登录
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        if (!$account) {
            return $this->buildFailed(ReturnCode::LOGIN_ERROR, '缺少用户名!');
        }
        if (!$password) {
            return $this->buildFailed(ReturnCode::LOGIN_ERROR, '缺少密码!');
        } else {
            $password = Tools::userMd5($password);
        }

        $userInfo = (new AdminModel())->where('account', $account)->where('password', $password)->find();

        if (!empty($userInfo)) {
            if (!$userInfo['status']) {
                return $this->buildFailed(ReturnCode::LOGIN_ERROR, '用户已被封禁，请联系管理员');
            }
        } else {
            return $this->buildFailed(ReturnCode::LOGIN_ERROR, '用户名密码不正确');
        }

        $token_arr = [
            'uid' => $userInfo['id'],
            'user_type' => $userInfo['user_type'],
            'create_time' => $userInfo['create_time'],
            'location'=>2,  //用户位置 1前端 2后台
        ];

        $exp = time() + config('app.MAX_TIME');
        $token_my = handlerUserLogin($token_arr,$exp);
        $re_token = cache_token('a'.$token_arr['uid'],$token_my,2,$exp);
        if(!$re_token['s']){
            return $this->buildFailed(ReturnCode::LOGIN_ERROR, '授权登录失败，请重试！');
        }
        $userInfo['token'] = $re_token['t'];
        $key = 'admin_menu_'.$token_arr['uid'];

        //查询缓存是否存在用户的菜单，如果存在，获取缓存
        $re_menu = Cache::get($key);

        if(!$re_menu){
            $re_menu = Menu::getMenuTree('admin',$token_arr['uid']);

            if($re_menu){
                \app\model\Base::setTagCache($key,'menu_role',$re_menu);
            }
        }

        $re_user_role = Db::name('y_admin_role')->where('uid',$token_arr['uid'])->column('role_id');

        $re_user_role_str = implode(',',$re_user_role);

        $re_role_menu = Db::name('y_role_menu')->where('role_id','in',$re_user_role_str)->column('menu_id');

        $userInfo['menu'] = $re_role_menu;

        return $this->buildSuccess($userInfo->toArray(), '登录成功');
    }

    /**
     * 后台隐私协议设置
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function userPrivacy()
    {
        $privacy_agreement = $this->request->param("privacy_agreement");
        if(empty($privacy_agreement)){
            return $this->buildSuccess(['privacy_agreement' => help::get_help()[3]['des']]);
        }

        (new help())
            ->where(['type' => 3])
            ->save(['description' => $privacy_agreement]);
        Cache::delete('cache_help');
        return $this->buildSuccess();

    }

    /**
     * 后台用户协议设置
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function userAgreement()
    {
        $user_agreement = $this->request->param("user_agreement");
        if(empty($user_agreement)){
            return $this->buildSuccess(['user_agreement' => help::get_help()[2]['des']]);
        }
        (new help())
            ->where(['type' => 2])
            ->save(['description' => $user_agreement]);
        Cache::delete('cache_help');
        return $this->buildSuccess();
    }

    /**
     * 后台奖励设置
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function rewardSet()
    {
        $car_owner_reward = $this->request->param("car_owner_reward");
        $problem_reward = $this->request->param("problem_reward");

        if(empty($car_owner_reward) && empty($problem_reward)){
            return $this->buildSuccess([
                "problem_reward" => ConfigM::get_all_config()['problem_reward'],
                "car_owner_reward" => ConfigM::get_all_config()['car_owner_reward'],
            ]);
        }
        if(!empty($car_owner_reward))
            (new ConfigM())
                ->where(['config_key' => "car_owner_reward"])
                ->save(['value' => $car_owner_reward]);

        if(!empty($problem_reward))
            (new ConfigM())
            ->where(['config_key' => "problem_reward"])
            ->save(['value' => $problem_reward/100]);

        Cache::delete('cache_config');
        return $this->buildSuccess();

    }

    /**
     * 后台用户收益明细列表
     * @return \think\Response
     * @throws \think\db\exception\DbException
     */
    public function userProfitList()
    {
        $limit = $this->request->get('size', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $start = $this->request->get('page', 1);
        $keywords = $this->request->get('keywords', null);


        $profit = Profit::withJoin(['member' => ['nickname','mobile','user_type'],
            'questionnaireType' => ['name']], 'LEFT');
        if(!empty($keywords)){
            $profit->where('member.mobile', 'like', $keywords.'%');
        }

        $listObj = $profit->order('create_time', 'DESC')
            ->paginate(['page' => $start, 'list_rows' => $limit])->toArray();
        $data = [];
        foreach ($listObj['data'] as $value) {
            $data[] = [
                'create_time' => $value['create_time'],
                'id' => $value['id'],
                'money' => $value['money'],
                'uid' => $value['uid'],
                'nickname' => $value['member']['nickname'],
                'mobile' => $value['member']['mobile'],
                'user_type' => $value['member']['user_type'],
                'name' => $value['questionnaireType']['name']
            ];
        }

        return $this->buildSuccess([
            'list'  => $data,
            'count' => $listObj['total']
        ]);
    }

    /**
     * 后台控制面板
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getDashboard()
    {
        $member = new Member();
        $record = new QuestionnaireRecord();
        $total_member = $member->count();
        $today_add_member = $member->whereDay("create_time")->count();
        $questionnaire_record = $record->whereDay("create_time")->count();
        $questionnaire_member_record = Db::table("y_questionnaire_record_apiadmin")->whereDay("create_time")->group("uid")->count();

        $statistics = Db::table('y_member_apiadmin')
            ->field('count(uid) questionnaire_member_record ,member.city')
            ->alias('member')
            ->join('y_questionnaire_record_apiadmin record','member.id = record.uid','left')
            ->where("member.city <>''")
            ->group('member.city')->select()->toArray();

        $today_city_add_member = Db::table('y_member_apiadmin')
            ->field('count(id) today_add_member ,city')
            ->where("city <>''")
            ->whereDay("create_time")
            ->group('city')
            ->select()->toArray();

        if(!empty($today_city_add_member))
            $today_city_add_member = array_column($today_city_add_member,'today_add_member','city');

        foreach ($statistics as &$item)
        {
            if(empty($today_city_add_member)){
                $item['today_city_add_member'] = 0;
            }else{
                if(empty($today_city_add_member[$item['city']])){
                    $item['today_city_add_member'] = 0;
                }else{
                    $item['today_city_add_member'] = $today_city_add_member[$item['city']];
                }
            }
        }

        return $this->buildSuccess([
            'total_member'  => $total_member,
            'today_add_member'  => $today_add_member,
            'questionnaire_record'  => $questionnaire_record,
            'questionnaire_member_record'  => $questionnaire_member_record,
            'statistics'  => $statistics,
        ]);
    }

    /**
     * 后台海报设置
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function uploadPostImage()
    {
        $res = ConfigM::where(['config_key' => "poster"])->find();
        $old_file = '';
        if(!empty($res)){
            $old_file = $res['value'];
        }
        if(empty($_FILES['file'])){
            return $this->buildSuccess([
                'fileUrl'  => $old_file
            ]);
        }

        $name = $_FILES['file']['name'];
        $tmp_name = $_FILES['file']['tmp_name'];
        $error = $_FILES['file']['error'];
        $path = '/upload/' . date('Ymd', time()) . '/';
        //过滤错误
        if ($error) {
            switch ($error) {
                case 1:
                    $error_message = '您上传的文件超过了PHP.INI配置文件中UPLOAD_MAX-FILESIZE的大小';
                    break;
                case 2:
                    $error_message = '您上传的文件超过了PHP.INI配置文件中的post_max_size的大小';
                    break;
                case 3:
                    $error_message = '文件只被部分上传';
                    break;
                case 4:
                    $error_message = '文件不能为空';
                    break;
                default:
                    $error_message = '未知错误';
            }
            die($error_message);
        }
        $arr_name = explode('.', $name);
        $hz = array_pop($arr_name);
        $new_name = md5(time() . uniqid()) . '.' . $hz;
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
            mkdir($_SERVER['DOCUMENT_ROOT'] . $path, 0755, true);
        }

        if (move_uploaded_file($tmp_name, $_SERVER['DOCUMENT_ROOT'] . $path . $new_name)) {
            if(!empty($old_file)) {
                $old_path = parse_url($old_file);
                $old_path = app()->getRootPath().'public'.$old_path['path'];
                if(file_exists($old_path)) unlink($old_path);

            }

            $file = $this->request->domain() . $path . $new_name;
            (new ConfigM())
                ->where(['config_key' => "poster"])
                ->save(['value' => $file]);
            Cache::delete('cache_config');
            return $this->buildSuccess([
                'fileUrl'  => $file
            ]);
        } else {
            return $this->buildFailed(ReturnCode::FILE_SAVE_ERROR, '文件上传失败');
        }
    }

    //用户列表
    public function userList(){
        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " yma.delete_time is null " ;

        //id
        if(isset($params['id']) && !empty($params['id'])){
            $where[] = 'yma.id='.$params['id'];
        }

        //积分
        if(isset($params['jf']) && !empty($params['jf'])){
            $params['jf'] = bcmul($params['jf'],100,0);
            $where[] = 'locate("'.trim($params['jf']).'",ymp.jf)';
        }

        //消费额
        if(isset($params['consumption_all']) && !empty($params['consumption_all'])){
            $params['consumption_all'] = bcmul($params['consumption_all'],100,0);
            $where[] = 'locate("'.trim($params['consumption_all']).'",ymp.consumption_all)';
        }

        //手机号
        if(isset($params['tel']) && !empty($params['tel'])){
            $where[] = 'locate("'.trim($params['tel']).'",yma.tel)';
        }

        //用户昵称
        if(isset($params['nickname']) && !empty($params['nickname'])){
            $where[] = 'locate("'.trim($params['nickname']).'",yma.nickname)';
        }

        //会员等级
        if(isset($params['level_id']) && is_numeric($params['level_id'])){
            $where[] = ' ymp.level_id ='.$params['level_id'];
        }

        //订单查询时间
        if((isset($params['start_time']) && !empty($params['start_time'])) && isset($params['end_time']) && !empty($params['end_time'])){
            if(strtotime($params['end_time']) < strtotime($params['start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'yma.create_time >= "'.$params['start_time'].'"';
            $where[] = 'yma.create_time <= "'.$params['end_time'].'"';
        }

        $where_str = implode(' and ',$where);

        $user = db::name("y_member")
            ->alias("yma")
            ->leftJoin("y_member_profile ymp","yma.id=ymp.uid")
            ->leftJoin("y_member yma2","yma.n_p_id=yma2.id")
            ->field("yma.id,yma.nickname,yma.tel,yma.create_time,yma.recommend_num,yma.gender,yma2.nickname as tj_username,ymp.level_id,ymp.jf,ymp.consumption_all")
            ->where($where_str)
            ->order("yma.create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();
        ;

        foreach ($user as &$op){
            $op['jf'] = bcdiv($op['jf'],100,2);
            $op['consumption_all'] = bcdiv($op['consumption_all'],100,2);

            if($op['gender'] == 1){
                $op['gender'] = "男";
            }elseif($op['gender'] == 2){
                $op['gender'] = "女";
            }else{
                $op['gender'] = "未知";
            }

//            $op['gender'] = $op['gender'] == 1 ? "男" :'女';


            $op['level_id'] = $op['level_id'] == 1 ? "会员" :'非会员';

            $op['nickname'] = emoji_decode($op['nickname']);

            $op['tj_username'] = emoji_decode($op['tj_username']);

        }

        $total_number = db::name("y_member")
            ->alias("yma")
            ->leftJoin("y_member_profile ymp","yma.id=ymp.uid")
            ->leftJoin("y_member yma2","yma.n_p_id=yma2.id")
            ->where($where_str)
            ->count();

        if($params['export'] == 1){
            $column_arr = ['ID','用户名','手机号','创建时间','推荐总人数','性别','推荐人名称','用户等级'];
            $upload_file_path = '';
            $upload_root_path = root_path().'/public/';

            $url_path = 'excel/';

            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('Y').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('m').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('d').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('YmdHis', time()) . '会员列表.xlsx';
            $upload_file_path = $upload_root_path. $url_path;
            try{
                excel_action($user,$column_arr,'会员列表',$upload_file_path);
            }catch (\Exception $e){
//            return json(result_create_arr(new \stdClass(),'导出失败，请重试 !'));
                return $this->buildFailed(ReturnCode::INVALID,'导出失败！');

            }
          return $this->buildSuccess([config('app.api_url').'/'.$url_path], '导出成功');
        }

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);
    }


    //用户详情
    public function userInfo(){

        $params = $this->request->param();

        $user = db::name("y_member")
            ->alias("yma")
            ->leftJoin("y_member_profile ymp","yma.id=ymp.uid")
            ->leftJoin("y_member yma2","yma.n_p_id=yma2.id")
            ->where("yma.id",$params['id'])
            ->field("yma.nickname,yma.tel,yma.avatar,yma.gender,yma.province,yma.city,yma.county,yma.community,yma.building,yma.create_time,yma2.nickname as tj_username,ymp.level_id,yma.recommend_num,ymp.jf,ymp.consumption_all,ymp.quota")
            ->find();

        if(!$user) return $this->buildFailed(ReturnCode::INVALID, '会员不存在!');

        if($user['gender'] == 1){
            $user['gender'] = "男";
        }elseif($user['gender'] == 2){
            $user['gender'] = "女";
        }else{
            $user['gender'] = "男";
        }

//        $user['gender'] = $user['gender'] == 1 ? "男" :'女';

        $user['level_id'] = $user['level_id'] == 1 ? "会员" :'非会员';

        $user['register_address'] = $user['province'].$user['city'].$user['county'].$user['community'].$user['building'];

        //消费总金额
        $user['consumption_all'] = bcdiv($user['consumption_all'],100,2);

        //查询会员的消费记录  未查
        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);

        $page_start = limit_arr($page,$limit);

        $where[] = " delete_time is null " ;

        $where[] = ' uid ='.$params['id'];

        $where_str = implode(' and ',$where);

        $order = db::name("y_products_order")
            ->field("order_num,id,price,paytime,create_time,type")
            ->where($where_str)
            ->order("create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        foreach ($order as &$op){
            $op['price'] = bcdiv($op['price'],100,2);
            $op['type'] = $op['type'] == 1 ? "销售单品" :($op['type'] == 2 ? '销售套餐':($op['type'] == 3?'销售水卡':''));
        }

        $total_number = db::name("y_products_order")
            ->where($where_str)
            ->count();

        $order = [
            'list' =>$order,
            'count' => $total_number
        ];

        //查询会员的发票管理
        $limit2 = $this->request->get('limit2', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page2 = $this->request->get('pages2', 1);

        $page_start2 = limit_arr($page,$limit);

        $where[] = " delete_time is null " ;

        $where[] = ' uid ='.$params['id'];

        $where_str2 = implode(' and ',$where);

        $invoice = db::name("y_member_invoice")
            ->field("*")
            ->where($where_str2)
            ->order("create_time desc")
            ->limit($page_start,$limit2)
            ->select()->toArray();

        $total_number2 = db::name("y_member_invoice")
            ->where($where_str)
            ->count();

        $invoice = [
            'list' =>$invoice,
            'count' => $total_number2
        ];

        //查询我的地址
        $location = Db::name("y_member_location")->field("name,phone,province,city,district,address,quota,up_data,status,id,is_default")->where("uid",$params['id'])->where("delete_time is null")->select()->toArray();

        foreach ($location as &$op){
            $op['up_data'] = json_decode($op['up_data'],true);
            if($op['up_data']){
                foreach ($op['up_data'] as $key =>$val){
                switch ($key) {
                    case "quota":
                        $op['up_data'][$key] = "用户指标:".$val;
                        break;
                    case "name":
                        $op['up_data'][$key] = "收件人名称:".$val;
                        break;
                    case "phone":
                        $op['up_data'][$key] = "收件人电话:".$val;
                        break;
                    case "province":
                        $op['up_data'][$key] = "收件人省:".$val;
                        break;
                    case "city":
                        $op['up_data'][$key] = "收件人市:".$val;
                        break;
                    case "district":
                        $op['up_data'][$key] = "收件人区:".$val;
                        break;
                    case "address":
                        $op['up_data'][$key] = "收件人地址:".$val;
                        break;
                    case "is_default":
                        $op['up_data'][$key] = "是否默认地址:".$val=="1"?"默认地址":"非默认地址";
                        break;
                    default:
                        break;
                }
                }
            }

            $op['is_default_text'] =  $op['is_default'] ==1?"默认地址":"非默认地址";
            $op['status_text'] =  $op['status'] ==1?"可使用":"审核中";
        }

        return $this->buildSuccess([
            'list'  => $user,
            'order' => $order,
            'invoice' => $invoice,
            'address' =>$location
        ]);
    }

    //用户修改
    public function usersava(){
        $params = $this->request->param();

        $data = [];

        $user =  db::name("y_member")->where("id",$params['id'])->find();

        if(!$user)  return $this->buildFailed(ReturnCode::INVALID, '用户不存在!');

//        if(isset($params['tel'])){
//
//            $tel = $params['tel'];
//
//            if($tel == $user['tel']){
//                return $this->buildFailed(ReturnCode::INVALID, '与原手机号一致无需修改!');
//            }
//
//            if (!is_mobile($tel)){
//                return $this->buildFailed(ReturnCode::INVALID, '要修改的手机号格式不正确!');
//            }
//
//            $is_user = db::name("y_member")->where("tel",$tel)->find();
//
//            if($is_user){
//                return $this->buildFailed(ReturnCode::INVALID, '该手机号已存在!');
//            }
//
//            $data['tel'] = $tel;
//
//        }
//
//        if(isset($params['gender'])){
//            if(!in_array($params['gender'],[1,2])){
//                return $this->buildFailed(ReturnCode::INVALID, '参数不正确!');
//            }
//            $data['gender'] = $params['gender'];
//        }

       $up = Db::name("y_member_profile")
           ->where("uid",$params['id'])
           ->update(['jf'=>$params['jf']]);

       if($up){
           return $this->buildSuccess([],"修改成功");
       }else{
           return $this->buildFailed(ReturnCode::INVALID, '修改失败!');
       }

    }

    //地址管理
    public function examineList(){
        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " delete_time is null " ;


        //收件人
        if(isset($params['name']) && !empty($params['name'])){
            $where[] = 'locate("'.trim($params['name']).'",name)';
        }

        //手机号
        if(isset($params['phone']) && !empty($params['phone'])){
            $where[] = 'locate("'.trim($params['phone']).'",phone)';
        }

        //状态
        if(isset($params['is_default']) && is_numeric($params['is_default'])){
            $where[] = ' is_default ='.$params['is_default'];
        }

        $where_str = implode(' and ',$where);

        //查询我的地址
        $location = Db::name("y_member_location")
            ->field("name,phone,province,city,district,address,quota,up_data,status,id,is_default")
            ->where($where_str)
            ->order("create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        $total_number = Db::name("y_member_location")
            ->where($where_str)
            ->count();

        foreach ($location as &$op){
            $op['up_data'] = json_decode($op['up_data'],true);
            if($op['up_data']){
                foreach ($op['up_data'] as $key =>$val){
                    switch ($key) {
                        case "quota":
                            $op['up_data'][$key] = "用户指标:".$val;
                            break;
                        case "name":
                            $op['up_data'][$key] = "收件人名称:".$val;
                            break;
                        case "phone":
                            $op['up_data'][$key] = "收件人电话:".$val;
                            break;
                        case "province":
                            $op['up_data'][$key] = "收件人省:".$val;
                            break;
                        case "city":
                            $op['up_data'][$key] = "收件人市:".$val;
                            break;
                        case "district":
                            $op['up_data'][$key] = "收件人区:".$val;
                            break;
                        case "address":
                            $op['up_data'][$key] = "收件人地址:".$val;
                            break;
                        case "is_default":
                            $op['up_data'][$key] = "是否默认地址:".$val=="1"?"默认地址":"非默认地址";
                            break;
                        default:
                            break;
                    }
                }
            }

            $op['is_default_text'] =  $op['is_default'] ==1?"默认地址":"非默认地址";
            $op['status_text'] =  $op['status'] ==1?"可使用":"审核中";
        }

        return $this->buildSuccess([
            'list'  => $location,
            'count' => $total_number
        ]);

    }

    //用户地址审核
    public function userExamine(){
        $params = $this->request->param();

        $location = Db::name("y_member_location")->where("id",$params['id'])->where("delete_time is null")->find();

        if(!$location) return $this->buildFailed(ReturnCode::INVALID,'地址不存在！');

        if($location['status'] ==1) return $this->buildFailed(ReturnCode::INVALID,'该地址无法审核！');

        $msg = "";

        if($params['type'] ==1){

            $data = json_decode($location['up_data'],true); //要修改的数据

            //进行两个处理    第一个判断指标
            if(isset($data['quota']) && $data['quota']!=$location['quota']){   //判断指标是否修改
                $data['deposit'] = ($location['deposit']/$location['quota'])*$data['quota']; //计算修改指标后的会员押金
                $quota = Db::name("y_member_profile")->where("uid",$location['uid'])->value("quota");
                $quota2 = 0;

                $user_info = [];

                if($data['quota'] > $location['quota']){   //修改指标大于原始指标时要从空闲指标里面拿

                    $quota2 = $data['quota'] - $location['quota'];   //获取要减的指标
                    if($quota >= $quota2 && $quota > 0){  //判断空闲指标里面是否足够

                        //修改空闲指标的数量  减指标
                        $user_info = ['quota'=>$quota-$quota2];

                    }else{   //不进行任何修改

                        $msg = "当前空闲指标不足够";
                        $data = [];

                    }

                }else{   //修改指标小于原始指标时

                    $quota2 = $location['quota'] - $data['quota'];   //获取要增加的指标
                    //修改空闲指标的数量   加指标
                    $user_info = ['quota'=>$quota+$quota2];

                }

                $commit = 1;   //是否提交

                // 启动事务
                Db::startTrans();
                try {

                    if($user_info){  //修改用户信息

                        $up_user = Db::name("y_member_profile")
                            ->where("uid",$location['uid'])
                            ->update($user_info);

                        if(!$up_user){

                            $msg = "修改指标失败";
                            $data = [];  //不进行任何修改
                            $commit = 0;   //是否提交

                        }

                        //并且把对应指标从桶记录中减少
                        $re_bucket_log  = Db::name('y_bucket_log')
                            ->where('ml_id',$params['id'])
                            ->where('uid',$location['uid'])
                            ->field('id,price,quota,bucket_num,update_time,bucket_fee,bucket_number')
                            ->find();
                        if(!$re_bucket_log){
                            throw new Exception('修改对应地址的桶记录错误-1！',11186);
                        }

                        $updatea_bucket_log = [];

                        if($user_info['quota'] > $quota){
                            //增加指标
                            $updatea_bucket_log = [
                                'price'=>$re_bucket_log['price']+$re_bucket_log['bucket_fee']*$data['quota'],
                                'bucket_num'=>$re_bucket_log['bucket_num']+$re_bucket_log['bucket_number']*$data['quota'],
                                'quota'=>$re_bucket_log['quota']+$data['quota'],
                            ];
                        }elseif($user_info['quota'] < $quota){
                            //减少指标
                            $updatea_bucket_log = [
                                'price'=>$re_bucket_log['price']-$re_bucket_log['bucket_fee']*$data['quota'],
                                'bucket_num'=>$re_bucket_log['bucket_num']-$re_bucket_log['bucket_number']*$data['quota'],
                                'quota'=>$re_bucket_log['quota']-$data['quota'],
                            ];
                        }

                        if($updatea_bucket_log){
                            $updatea_bucket_log['update_time'] = date('Y-m-d H:i:s');
                            $re_up_bucket_log = Db::name('y_bucket_log')
                                ->where('id',$re_bucket_log['id'])
                                ->where('update_time',$re_bucket_log['update_time'])
                                ->update($updatea_bucket_log);

                            if(!$re_up_bucket_log){
                                throw new Exception('修改对应地址的桶记录错误-2！',11186);
                            }
                        }

                    }

                    //判断是否将其设为默认地址
                    if(isset($data['is_default']) && $data['is_default'] == '1'){  //修改所有地址
                        $up_location = Db::name("y_member_location")->where("uid",$location['uid'])->update(['is_default'=>0]);

                        if(!$up_location){

                            $msg = "修改默认地址失败";
                            $data = [];  //不进行任何修改
                            $commit = 0;   //是否提交

                        }

                    }

                    if($commit) Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
                }

            }

        }

        $data['status'] = 1;

        $updata = Db::name("y_member_location")
            ->where("id",$params['id'])
            ->update($data);

        if($updata){
            $data = [
                'action_name' =>"用户地址审核",
                'uid' => $this->uid,  //用户uid
                'type' => 1, //类型
                'data' => $params, //数据
            ];
            //$journal_add = journal_add($data);
            return $this->buildSuccess([], '审核成功'.$msg);
        }else{
            return $this->buildFailed(ReturnCode::INVALID,'驳回审核！');
        }
    }

    //指定用户分配业务员
    public function distribution(){
        $params = $this->request->param();

        if($params['type'] ==1){ //查询

            $where[] = " delete_time is null " ;

            $where[] = " is_salesman = 1";

            $where_str = implode(' and ',$where);
            $user = Db::name("y_member")
                ->field("id,nickname")
                ->where($where_str)
                ->order("create_time desc")
                ->select()->toArray();

            return $this->buildSuccess([
                'list'  => $user,
            ]);
        }else if($params['type'] ==2){  //指派

            if(!isset($params['id']) && !isset($params['p_salesman'])) return $this->buildFailed(ReturnCode::INVALID,'参数不全！');

            $user = Db::name("y_member")->where("id",$params['id'])->find();

            $user2 = Db::name("y_member")->where("id",$params['p_salesman'])->where("is_salesman",1)->find();

            if(!$user || !$user2) return $this->buildFailed(ReturnCode::INVALID,'业务员或被指派人不存在！');

            $data = [
                'p_salesman' =>$params['p_salesman'],
                'is_appoint' => 1
            ];
            $updata = Db::name("y_member")->where("id",$params['id'])->update($data);

            if($updata){
                return $this->buildSuccess([], '指派成功');
            }else{
                return $this->buildFailed(ReturnCode::INVALID,'指派失败！');
            }


        }

    }

    //删除和批量删除用户
    public function userDelete(){
        $params = $this->request->param();

        $save = db::name("y_member")->where("id","in",$params['id'])->update(['delete_time'=>date("Y-m-d H:i:s",time())]);

        if($save){
            $data = [
                'action_name' =>"删除用户",
                'uid' => $this->uid,  //用户uid
                'type' => 1, //类型
                'data' => $params, //数据
            ];
            //$journal_add = journal_add($data);
            return $this->buildSuccess([], '删除成功');
        }else{
            return $this->buildFailed(ReturnCode::INVALID,'删除失败！');
        }
    }

    //用户推荐用户
    public function userTjList(){

        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " yma.delete_time is null " ;

        $where[] = " yma.n_p_id =".$params['id'];

        $where_str = implode(' and ',$where);

        $user = db::name("y_member")
            ->alias("yma")
            ->leftJoin("y_member_profile ymp","yma.id=ymp.uid")
            ->field("yma.id,yma.nickname,yma.tel,yma.create_time,yma.recommend_num,yma.gender,ymp.level_id")
            ->where($where_str)
            ->order("yma.create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();
        ;

        foreach ($user as &$op){
            if($op['gender'] == 1){
                $op['gender'] = "男";
            }elseif($op['gender'] == 2){
                $op['gender'] = "女";
            }else{
                $op['gender'] = "男";
            }

//            $op['gender'] = $op['gender'] == 1 ? "男" :'女';

            $op['level_id'] = $op['level_id'] == 1 ? "会员" :'非会员';

        }

        $total_number = db::name("y_member")
            ->alias("yma")
            ->leftJoin("y_member_profile ymp","yma.id=ymp.uid")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);

    }

    //业务员列表
    public function salesmanList(){

        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " delete_time is null " ;

        $where[] = " is_salesman = 1";

        $where_str = implode(' and ',$where);

        $user = Db::name("y_member")
            ->field("id,nickname,tel,create_time,gender,enable,salesman_region,qrcode_fid,birthday,mobile,ref_no")
            ->where($where_str)
            ->order("create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        $image_qrcode_obj = new \ImageQrcode();

        foreach ($user as &$op){
            if($op['gender'] == 1){
                $op['gender'] = "男";
            }elseif($op['gender'] == 2){
                $op['gender'] = "女";
            }else{
                $op['gender'] = "男";
            }

//            $op['gender'] = $op['gender'] == 1 ? "男" :'女';

            $op['enable'] = $op['enable'] == 1 ? "正常" :'禁用';

            $op['age'] = birthday_show($op['birthday']);

            $op['tj_num'] = Db::name("y_member")->where("delete_time is null")->where("p_salesman_type",2)->where("p_salesman",$op['id'])->count("id");

            $qr_code = Cache::get('ye_code'.$op['id']);

            if(!$qr_code){
                $qr_code = $image_qrcode_obj->create_qrcode($op['ref_no']);
                Cache::set('ye_code_'.$op['id'],$qr_code,60*5);
            }

            $op['ref_no'] = $qr_code;

            $op['nickname'] = emoji_decode($op['nickname']);


        }

        $total_number = db::name("y_member")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);


    }

    //业务员添加编辑查看和删除
    public function salesmanSave(){
        $params = $this->request->param();

        $data = [
            'uid' => $this->uid,  //用户uid
            'type' => 1, //类型
            'data' => $params, //数据
        ];

        if($params['type_info'] ==1){ //查看

            if(!isset($params['id'])) return $this->buildFailed(ReturnCode::INVALID,'缺少参数！');

            $member = Db::name("y_member")
                ->field("tel,nickname,gender,birthday,enable,salesman_city,mobile,salesman_region")
                ->where("id",$params['id'])->where("is_salesman",1)->find();

            if(!$member) return $this->buildFailed(ReturnCode::INVALID,'数据不存在或不是业务员！');

            $data['action_name'] = "业务员查看";
            //$journal_add = journal_add($data);
            return $this->buildSuccess([
                'list'  => $member,
            ]);

        }else if($params['type_info'] ==2){  //新增

            $updata = [];

            if((!isset($params['tel']) || empty($params['tel'])) || !isset($params['password']) || empty(($params['password']))){
                return $this->buildFailed(ReturnCode::INVALID,'账号密码必须！');
            }

            if(isset($params['mobile']) && !is_mobile($params['mobile']))  return $this->buildFailed(ReturnCode::INVALID,'手机号格式不正确！');

            $is_member = db::name("y_member")->where("tel",$params['tel'])->find();

            if($is_member) return $this->buildFailed(ReturnCode::INVALID,'该账号已存在！');

            $params['password'] = md5($params['password']);

            $params['ref_no'] = \UuidHelper::generate()->string;

            unset($params['type_info']);

            $params['is_salesman'] = 1;
            $params['nickname'] = "业务员";

            if($params){

                $save = db::name("y_member")->insert($params);
                if(!$save) $this->buildFailed(ReturnCode::INVALID,'新增失败！');

            }

            $data['action_name'] = "业务员新增";
            //$journal_add = journal_add($data);
            return $this->buildSuccess([], '操作成功');

        }else if($params['type_info'] ==3){  //修改

            if(!isset($params['id'])) return $this->buildFailed(ReturnCode::INVALID,'缺少参数！');

            $member = Db::name("y_member")->where("id",$params['id'])->find();

            if(!$member) return $this->buildFailed(ReturnCode::INVALID,'数据不存在！');

            $is_member = db::name("y_member")->where("tel",$params['tel'])->find();

            if($is_member && $is_member['tel'] != $member['tel'] ) return $this->buildFailed(ReturnCode::INVALID,'该账号已存在！');

            if(isset($params['password'])){
                $params['password'] = md5($params['password']);
            }

            unset($params['type_info']);

            $save = db::name("y_member")->where("id",$params['id'])->update($params);

            if($save){
                $data['action_name'] = "业务员修改";
                //$journal_add = journal_add($data);
                return $this->buildSuccess([], '修改成功');
            }else{
                return $this->buildFailed(ReturnCode::INVALID,'修改失败！');
            }

        }else if($params['type_info'] ==4){ //删除
            if(!isset($params['id'])) return $this->buildFailed(ReturnCode::INVALID,'缺少参数！');

            $member = Db::name("y_member")->where("id",$params['id'])->find();

            if(!$member) return $this->buildFailed(ReturnCode::INVALID,'数据不存在！');

            $save = db::name("y_member")->where("id",$params['id'])->update(['delete_time'=>date("Y-m-d H:i:s",time())]);

            if($save){
                Cache::delete('banner_list');
                $data['action_name'] = "业务员删除";
                //$journal_add = journal_add($data);
                return $this->buildSuccess([], '删除成功');
            }else{
                return $this->buildFailed(ReturnCode::INVALID,'删除失败！');
            }

        }

    }

    //业务员管理推荐合伙人列表
    public function salesmanTjList(){

        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " yma.delete_time is null " ;
//
        //名字
        if(isset($params['nickname']) && !empty($params['nickname'])){
            $where[] = 'locate("'.trim($params['nickname']).'",yma.nickname)';
        }

        //会员等级
        if(isset($params['level_id']) && is_numeric($params['level_id'])){
            $where[] = ' ymp.level_id ='.$params['level_id'];
        }

        if($params['type_info'] ==1){ //业务员推荐
            $where[] = "yma.p_salesman=".$params['id'];
        }else if($params['type_info'] ==2){ //普通推荐
            $where[] = "yma.n_p_id=".$params['id'];
        }

        //推荐的普通用户还是合伙人
        if(isset($params['p_salesman_type']) && is_numeric($params['p_salesman_type'])){
            $where[] = ' yma.p_salesman_type ='.$params['p_salesman_type'];
        }


        $where_str = implode(' and ',$where);
//        var_dump($where_str);die;
        $user = db::name("y_member")
            ->alias("yma")
            ->leftJoin("y_member_profile ymp","yma.id=ymp.uid")
            ->field("yma.id,yma.nickname,yma.tel,yma.create_time,yma.gender,yma.enable,yma.salesman_region,yma.qrcode_fid,yma.birthday,ymp.consumption_all,yma.n_p_id,ymp.level_id,yma.avatar")
            ->where($where_str)
            ->order("yma.create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        $tj_member = 0;
        $tj_lelvel = 0;

        foreach ($user as &$op){

            $op['tj_num'] = Db::name("y_member")->where("delete_time is null")->where("p_salesman_type",2)->where("n_p_id",$op['id'])->count("id");

            $op['consumption_all'] = bcdiv($op['consumption_all'],100,2);

            if($op['level_id'] ==0){
                $tj_member += 1;
            }else{
                $tj_lelvel += 1;
            }

            $op['level_id'] = $op['level_id'] == 1 ? "会员" :'非会员';

        }

        $total_number = db::name("y_member")
            ->alias("yma")
            ->leftJoin("y_member_profile ymp","yma.id=ymp.uid")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number,
            'tj_member' =>$tj_member,
            'tj_lelvel' =>$tj_lelvel
        ]);

    }

    //业务员管理推荐合伙人列表消费
    public function salesmanConsumption(){
        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " ypoi.delete_time is null " ;
//
        //订单编号
        if(isset($params['order_num']) && !empty($params['order_num'])){
            $where[] = 'locate("'.trim($params['order_num']).'",ypo.order_num)';
        }

//        if((isset($params['type']) && $params['type'] ==1) && (isset($params['type_info']) && !empty($params['type_info']))) {
//            //查询会员的所有下级
//            if ($params['type_info'] == 1) {
//                $wheres = "p_salesman_type =1  and p_salesman=" . $params['uid']; //and delete_time is null
//            } else if ($params['type_info'] == 2) {
//                $wheres = "p_salesman_type =2  and p_salesman=" . $params['uid'];
//            }
//            $ids = Db::name("y_member")->where($wheres)->field("id")->select()->toArray();
//            $tj_ids = "";
//            foreach ($ids as &$op) {
//                if ($tj_ids) {
//                    $tj_ids .= "," . $op['id'];
//                } else {
//                    $tj_ids = $op['id'];
//                }
//            }
//            $params['uid'] = $tj_ids;
//        }

        //创建时间
        if(isset($params['end_time']) && !empty($params['end_time']) && isset($params['start_time']) && !empty($params['start_time'])){
            if(strtotime($params['end_time']) < strtotime($params['start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'ypoi.create_time >= "'.$params['start_time'].'"';
            $where[] = 'ypoi.create_time <= "'.$params['end_time'].'"';
        }

        if($params['uid'])  $where[] = ' ypoi.uid in ('.$params['uid'].')';

        $where_str = implode(' and ',$where);

        $user = db::name("y_products_order_info")
            ->alias("ypoi")
            ->leftJoin("y_products_order ypo","ypoi.order_id=ypo.id")
            ->field("ypo.order_num,ypoi.id,ypoi.price,ypo.paytime,ypoi.create_time,ypoi.products_info,ypoi.total_sum,ypo.id as zhu_id,ypoi.fact_price")
            ->where($where_str)
            ->order("ypoi.create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        foreach ($user as &$op){
            $op['price'] = bcdiv($op['price'],100,2);
            $op['fact_price'] = bcdiv($op['fact_price'],100,2);
            $op['products_info'] = json_decode($op['products_info'],true);
        }

        $total_number = db::name("y_products_order_info")
            ->alias("ypoi")
            ->leftJoin("y_products_order ypo","ypoi.order_id=ypo.id")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);


    }

    //业务员管理推荐合伙人列表业绩
    public function salesmanAchievements(){
        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " ypo.delete_time is null " ;
//
        //订单编号
        if(isset($params['order_num']) && !empty($params['order_num'])){
            $where[] = 'locate("'.trim($params['order_num']).'",ypo.order_num)';
        }

        //用户名
        if(isset($params['tj_username']) && !empty($params['tj_username'])){
            $where[] = 'locate("'.trim($params['tj_username']).'",ym2.nickname)';
        }

        //创建时间
        if(isset($params['end_time']) && !empty($params['end_time']) && isset($params['start_time']) && !empty($params['start_time'])){
            if(strtotime($params['end_time']) < strtotime($params['start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'ypo.create_time >= "'.$params['start_time'].'"';
            $where[] = 'ypo.create_time <= "'.$params['end_time'].'"';
        }

        $where[] = ' ym.p_salesman != 0 ';

        $where_str = implode(' and ',$where);

        $user = db::name("y_products_order")
            ->alias("ypo")
            ->leftJoin("y_member ym","ypo.uid=ym.id")
            ->leftJoin("y_member ym2","ym.p_salesman=ym2.id")
            ->field("ypo.id,ypo.order_num,ypo.price,ypo.paytime,ypo.create_time,ypo.uid,ym2.nickname as tj_username,ypo.type,ym.nickname")
            ->where($where_str)
            ->order("create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        foreach ($user as &$op){
            $op['price'] = bcdiv($op['price'],100,2);

            $op['type_text'] = $op['type'] == 1 ? "销售单品" :($op['type'] == 2 ? '销售套餐':($op['type'] == 3?'销售水卡':''));
        }

        if($params['export'] == 1){
            unset($op['type']);unset($op['uid']);
//            var_dump($user);die;
            $column_arr = ['ID','订单号','金额','支付时间','创建时间','推荐人名称','用户名称','类型'];
            $upload_file_path = '';
            $upload_root_path = root_path().'/public/';

            $url_path = 'excel/';

            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('Y').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('m').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('d').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('YmdHis', time()) . '业绩员业绩列表.xlsx';
            $upload_file_path = $upload_root_path. $url_path;
            try{
                excel_action($user,$column_arr,'业绩员业绩列表',$upload_file_path);
            }catch (\Exception $e){
//            return json(result_create_arr(new \stdClass(),'导出失败，请重试 !'));
                return $this->buildFailed(ReturnCode::INVALID,'导出失败！');

            }
          return $this->buildSuccess([config('app.api_url').'/'.$url_path], '导出成功');

        }

        $total_number =  Db::name("y_products_order")
            ->alias("ypo")
            ->leftJoin("y_member ym","ypo.uid=ym.id")
            ->leftJoin("y_member ym2","ym.p_salesman=ym2.id")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);
    }

    //商品列表
    public function shopList(){

        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " delete_time is null " ;


        //商品名称
        if(isset($params['name']) && !empty($params['name'])){
            $where[] = 'locate("'.trim($params['name']).'",name)';
        }

        //商品发布状态
        if(isset($params['product_status']) && is_numeric($params['product_status'])){
            if($params['product_status'] == 3){
                $where[] = ' stock <= 0';
            }else{
                $where[] = ' product_status ='.$params['product_status'];
            }
        }

        //商品价格
//        if(isset($params['fact_price_start']) && !empty($params['fact_price_start'])){
//            if($params['fact_price_start'] > $params['fact_price_end']){
//                return $this->buildFailed(ReturnCode::INVALID, '金额开始区间不能大于结束区间!');
//            }
//
//            $where[] = 'fact_price >= "'.bcmul($params['fact_price_start'],100,2).'"';
//            $where[] = 'fact_price <= "'.bcmul($params['fact_price_end'],100,2).'"';
//        }

        if(isset($params['fact_price']) && !empty($params['fact_price'])){
            $where[] = 'fact_price = "'.bcmul($params['fact_price'],100,2).'"';
        }

        //会员价格
//        if(isset($params['vip_price_start']) && !empty($params['vip_price_start'])){
//            if($params['vip_price_start'] > $params['vip_price_end']){
//                return $this->buildFailed(ReturnCode::INVALID, '金额开始区间不能大于结束区间!');
//            }
//
//            $where[] = 'vip_price >= "'.bcmul($params['vip_price_start'],100,2).'"';
//            $where[] = 'vip_price <= "'.bcmul($params['vip_price_end'],100,2).'"';
//        }

        if(isset($params['vip_price']) && !empty($params['vip_price'])){
            $where[] = 'vip_price = "'.bcmul($params['vip_price'],100,2).'"';
        }

        //上架时间
        if(isset($params['up_start_time']) && !empty($params['up_start_time'])){
            if(strtotime($params['up_end_time']) < strtotime($params['up_start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'on_time >= "'.$params['up_start_time'].'"';
            $where[] = 'on_time <= "'.$params['up_end_time'].'"';
        }

        //下架时间
        if(isset($params['lo_start_time']) && !empty($params['lo_start_time'])){
            if(strtotime($params['lo_end_time']) < strtotime($params['lo_start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'on_time >= "'.$params['lo_start_time'].'"';
            $where[] = 'on_time <= "'.$params['lo_end_time'].'"';
        }

        $where_str = implode(' and ',$where);

        $user = db::name("y_products")
            ->field("id,name,fact_price,vip_price,stock,on_time,off_time,product_status,images,ordercount,pro_shipping_type,pro_online_date,pro_offline_date,onlyId,sortby,isrecommend")
            ->where($where_str)
            ->order("create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        foreach ($user as &$op){

            $op['product_status_text'] = $op['product_status'] == 1 ? "上架中" :"已下架";

            if($op['stock'] <= 0)  $op['product_status_text'] = "已售罄";

            $op['fact_price'] = bcdiv($op['fact_price'],100,2);

            $op['vip_price'] = bcdiv($op['vip_price'],100,2);

            $op['images'] = json_decode($op['images'],true);

            if($params['export'] == 1)  unset($op['product_status'],$op['images']);

        }

        if($params['export'] == 1){
            $column_arr = ['ID','商品名称','价格','会员价格','库存','上架时间','下架时间','状态'];
            $upload_file_path = '';
            $upload_root_path = root_path().'/public/';

            $url_path = 'excel/';

            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('Y').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('m').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('d').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('YmdHis', time()) . '商品列表.xlsx';
            $upload_file_path = $upload_root_path. $url_path;
            try{
                excel_action($user,$column_arr,'商品列表',$upload_file_path);
            }catch (\Exception $e){
//            return json(result_create_arr(new \stdClass(),'导出失败，请重试 !'));
                return $this->buildFailed(ReturnCode::INVALID,'导出失败！'.new \stdClass());

            }
          return $this->buildSuccess([config('app.api_url').'/'.$url_path], '导出成功');

        }

        $total_number = db::name("y_products")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);

    }




    //商品囤水套餐列表
    public function waterList(){
        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " delete_time is null " ;


        //商品名称
        if(isset($params['name']) && !empty($params['name'])){
            $where[] = 'locate("'.trim($params['name']).'",name)';
        }


        //商品价格
//        if(isset($params['fact_price_start']) && !empty($params['fact_price_start']) && isset($params['fact_price_end']) && !empty($params['fact_price_end'])){
//            if($params['fact_price_start'] > $params['fact_price_end']){
//                return $this->buildFailed(ReturnCode::INVALID, '金额开始区间不能大于结束区间!');
//            }
//
//            $where[] = 'fact_price >= "'.bcmul($params['fact_price_start'],100,2).'"';
//            $where[] = 'fact_price <= "'.bcmul($params['fact_price_end'],100,2).'"';
//        }

        if(isset($params['product_status']) && !empty($params['product_status'])){
            $where[] = 'product_status = '.$params['product_status'];
        }

        //商品的状态
        if(isset($params['name']) && !empty($params['name'])){
            $where[] = 'locate("'.trim($params['name']).'",name)';
        }


        $where_str = implode(' and ',$where);

        $user = db::name("y_products_meal")
            ->field("id,name,bucket_price,create_time,images,water_num,product_status,fact_price,create_time,stock,vip_price,ordercount,pro_online_date,pro_offline_date,sku,sortby,isrecommend")
            ->where($where_str)
            ->order("create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();
        ;

        foreach ($user as &$op){

            $op['product_status_text'] = $op['product_status'] == 1 ? "上架中" :($op['product_status'] == 2 ? '已下架':($op['product_status'] == 3?'审核中':''));

            if($op['stock'] <= 0)  $op['product_status_text'] = "已售罄";

            $op['fact_price'] = bcdiv($op['fact_price'],100,2);
            $op['vip_price'] = bcdiv($op['vip_price'],100,2);

            $op['images'] = json_decode($op['images'],true);

            $op['is_member'] = $op['bucket_price'] == 0?"是":"否";

            if($params['export'] == 1)  unset($op['product_status'],$op['images'],$op['stock'],$op['bucket_price']);

        }

        if($params['export'] == 1){
            $column_arr = ['ID','商品名称','创建时间','水次','价格','状态','是否会员专享'];
            $upload_file_path = '';
            $upload_root_path = root_path().'/public/';

            $url_path = 'excel/';

            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('Y').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('m').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('d').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('YmdHis', time()) . '囤水列表.xlsx';
            $upload_file_path = $upload_root_path. $url_path;
            try{
                excel_action($user,$column_arr,'囤水列表',$upload_file_path);
            }catch (\Exception $e){
//            return json(result_create_arr(new \stdClass(),'导出失败，请重试 !'));
                return $this->buildFailed(ReturnCode::INVALID,'导出失败！');

            }
          return $this->buildSuccess([config('app.api_url').'/'.$url_path], '导出成功');

        }

        $total_number = db::name("y_products_meal")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);
    }

    //商品水卡列表
    public function waterCarList(){

        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " ypc.delete_time is null " ;


        //商品名称
        if(isset($params['name']) && !empty($params['name'])){
            $where[] = 'locate("'.trim($params['name']).'",ypc.name)';
        }

        //购买绑定状态 1 未购买  2已购买  3未绑定  4已绑定
        if(isset($params['pay_type']) && is_numeric($params['pay_type'])){
            switch ($params['pay_type']) {
                case 1:
                    $where[] = ' ypc.buy_member = 0';
                    break;
                case 3:
                    $where[] = ' ypc.buy_member !=0 and ypc.bind_member = 0 ';
                    break;
                case 4:
                    $where[] = ' ypc.buy_member !=0 and ypc.bind_member != 0 ';
                    break;
                default:
                    break;
            }
        }

        //水卡号
        if(isset($params['id']) && !empty($params['id'])){
            $where[] = $params['id'].'=ypc.card_num';
        }
        if(isset($params['username']) && !empty($params['username'])){
            $where[] = 'locate("'.trim($params['username']).'",ypc.order_name) > 0';
        }

        //是否激活
        if(isset($params['is_activation']) && (int)$params['is_activation'] >= 0){
            if(!in_array($params['is_activation'],[0,1])){
                return $this->buildFailed('激活不符合规范不符合规范！');
            }
            $where[] = 'ypc.is_activation='.$params['is_activation'];
        }

        //线上/线下
        if(isset($params['card_online']) && (int)$params['card_online'] > 0){
            if(!in_array($params['card_online'],[1,2])){
                return $this->buildFailed(ReturnCode::INVALID,'水卡线上、线下选择不符合规范不符合规范！');
            }
            $where[] = 'ypc.card_online='.$params['card_online'];
        }

        $where_str = implode(' and ',$where);

        $user = db::name("y_products_card")
            ->alias("ypc")
            ->leftJoin("y_member ym","ypc.buy_member=ym.id")
            ->leftJoin("y_member ymb","ypc.bind_member=ymb.id")
            ->field("ypc.id,ypc.name,ypc.create_time,ypc.images")
            ->field('ypc.create_time,ypc.card_code,ypc.order_name as buy_nickname,ymb.nickname as bind_nickname')
            ->field('ypc.is_activation,ypc.activation_note,ypc.activation_time')
            ->field('ypc.card_num,ypc.mini_qrcode,card_online,ypc.order_name,ypc.cord_img,ypc.card_code')
            ->field('ypc.buy_member,ypc.bind_member,ypc.update_time,ym.tel as xd_tel')
            ->where($where_str)
            ->order("ypc.card_num desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        $image_qrcode_obj = new \ImageQrcode();

        $execl_data = [];
        foreach ($user as &$op){

            if($op['is_activation'] == 1){
                $op['product_status_text'] = "激活";

            }
            elseif($op['is_activation'] == 0){
                $op['product_status_text'] = "未激活";
            }
            elseif($op['product_status'] == 4){
                $op['product_status_text'] = "已绑定";
            }

//            $op['fact_price'] = bcdiv($op['fact_price'],100,2);

            $op['images'] = json_decode($op['images'],true);

//            $op['buy_nickname'] = $op['order_name'];

            //购买状态
            if($op['buy_member'] == 0){
                $op['pay_type'] = "1";
                $op['pay_type_text'] = "未购买";
            }
            else if($op['buy_member']!=0 && $op['bind_member']==0){
                $op['pay_type'] = "3";
                $op['pay_type_text'] = "已购买未绑定";
            }
            else if($op['buy_member']!=0 && $op['bind_member']!=0){
                $op['pay_type'] = "4";
                $op['pay_type_text'] = "已购买已绑定";
            }

//            if(!$op['mini_qrcode']){
//                $qr_code = $mini_obj->getUnlimited($op['card_code'],config('app.wx_card_path'));
////                $tem_code = config('app.wx_card_path').'?code='.$op['card_code'];
////                $qr_code = $image_qrcode_obj->createQrcodeImage($tem_code,$op['id']);
//            }
            $tem_code = config('app.wx_card_path') . '?aa=' . $op['card_code'];
            $op['card_code_address'] = $tem_code;
            if($params['export'] == 1){
                //表示下载图片

//                $qr_code = Cache::get('cache_card_img_'.$op['id']);
//
//                if(!$qr_code){
//                    $tem_code = config('app.wx_card_path').'?aa='.$op['card_code'];
//                    $qr_code = $image_qrcode_obj->createQrcodeImage($tem_code,$op['id']);
//                    Cache::set('cache_card_img_'.$op['id'],$qr_code,60*60*24);
//                }
                if(!$op['cord_img']) {
                    $qr_code = $image_qrcode_obj->createQrcodeImage($tem_code,$op['id']);
                    if($qr_code){
                        $re_up = Db::name('y_products_card')->where('id',$op['id'])->where('update_time',$op['update_time'])
                            ->update(['cord_img'=>$qr_code,'update_time'=>date('Y-m-d H:i:s')]);
                        if(!$re_up){
                            $qr_code = '';
                        }
                    }
                    $op['cord_img']          = $qr_code;
                    $op['card_code_address'] = $tem_code;
                }

            } else{
//                $qr_code = Cache::get('water_code_'.$op['id']);
//
//                if(!$qr_code){
//                    $qr_code = $image_qrcode_obj->create_qrcode($op['card_code']);
//                    Cache::set('water_code_'.$op['id'],$qr_code,60*5);
//                }
                if(!$op['cord_img']) {
                    //                $tem_code = config('app.wx_card_path').'?aa=1111';
                    //                $qr_code = $image_qrcode_obj->createQrcodeImage($tem_code,$op['id']);
                    $qr_code = $image_qrcode_obj->createQrcodeImage($tem_code,$op['id']);
                    if($qr_code){
                        $re_up = Db::name('y_products_card')->where('id',$op['id'])->where('update_time',$op['update_time'])
                            ->update(['cord_img'=>$qr_code,'update_time'=>date('Y-m-d H:i:s')]);
                        if(!$re_up){
                            $qr_code = '';
                        }
                    }

                    $op['cord_img']          = $qr_code;
                    $op['card_code_address'] = $tem_code;
                }

            }

            $op['card_code'] = $op['cord_img'];
            $op['bind_nickname'] = emoji_decode($op['bind_nickname']);

            if($params['export'] == 1) {
                if($op['card_online'] == 1){
                    $card_online_tem = '线上';
                }else if($op['card_online'] == 2){
                    $card_online_tem = '线下';
                }else{
                    $card_online_tem = '暂未激活';
                }

                if($op['is_activation'] == 0){
                    $is_activation = '未激活';
                }else{
                    $is_activation = '已激活';
                }

//                if($op['bind_member'] <= 0){
//                    $bind_member = '已购买已绑定';
//                }else{
//                    $bind_member = '未购买已绑定';
//                }

                $execl_data[] = [
                    'card_num'          => $op['card_num'],
                    'name'              => $op['name'],
                    'create_time'       => $op['create_time'],
                    'card_online'       => $card_online_tem,
                    'card_code'         => $op['cord_img'],
                    'card_code_address' => $op['card_code_address'],
                    'buy_nickname'      => $op['buy_nickname'],
                    'bind_nickname'     => $op['bind_nickname'],
                    'is_activation'     => $is_activation,
                    'bind_member'       => $op['pay_type_text'],
                    'xd'                => $op['xd_tel']
                    ];
            }

            if($op['is_activation'] == 0){

                $op['buy_nickname'] = '';
            }
            unset($op);
        }
        if($params['export'] == 1){


//            $column_arr = ['ID','商品名称','创建时间','水次','状态','价格','二维码','下单人','绑定人','激活状态','购买绑定状态'];
            if(isset($params['is_xd_tel'])){
                $column_arr = ['水卡编号','商品名称','创建时间','状态','二维码','二维码打印地址','下单人','绑定人','激活状态','购买绑定状态','下单人手机号'];
            }else{
                $column_arr = ['水卡编号','商品名称','创建时间','状态','二维码','二维码打印地址','下单人','绑定人','激活状态','购买绑定状态'];
            }

            $upload_file_path = '';
            $upload_root_path = root_path().'/public/';

            $url_path = 'excel/';

            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('Y').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('m').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('d').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('YmdHis', time()) . '水卡列表.xlsx';
            $upload_file_path = $upload_root_path. $url_path;

            try{
                excel_action($execl_data,$column_arr,'水卡列表',$upload_file_path);
            }
            catch (\Exception $e){
//            return json(result_create_arr(new \stdClass(),'导出失败，请重试 !'));
                return $this->buildFailed(ReturnCode::INVALID,'导出失败！');

            }
          return $this->buildSuccess([config('app.api_url').'/'.$url_path], '导出成功');

        }

        $total_number =  db::name("y_products_card")
            ->alias("ypc")
            ->leftJoin("y_member ym","ypc.buy_member=ym.id")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);


    }

    //商品水卡新增
//    public function waterCarSave(){
//
//    }

    //评论列表
    public function commentList(){

        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " yc.delete_time is null " ;


        //订单编号
        if(isset($params['order_num']) && !empty($params['order_num'])){
            $where[] = 'locate("'.trim($params['order_num']).'",ypo.order_num)';
        }

        //订单商品  暂无
        if(isset($params['shop_name']) && !empty($params['shop_name'])){
            $where[] = 'locate("'.trim($params['shop_name']).'",yc.shop_name)';
        }

        //评价类型
        if(isset($params['splicing']) && !empty($params['splicing'])){
            $where[] = 'yc.splicing='.$params['splicing'];
        }

        //是否回复
        if(isset($params['reply']) && is_numeric($params['reply'])){
            $where[] = ' yc.is_reply ='.$params['reply'];
        }

        //上架时间
        if(isset($params['start_time']) && !empty($params['start_time'])){
            if(strtotime($params['end_time']) < strtotime($params['start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'yc.create_time >= "'.$params['start_time'].'"';
            $where[] = 'yc.create_time <= "'.$params['end_time'].'"';
        }

        $where[] = 'yc.pid = 0';

        $where_str = implode(' and ',$where);

        $user = Db::name("y_comment")
            ->alias("yc")
            ->leftJoin("y_products_order ypo","yc.order_id=ypo.id")
            ->field("yc.id,yc.create_time,yc.is_selected,yc.is_reply,ypo.order_num,yc.shop_name,yc.pro_type,yc.pro_id,yc.splicing,yc.display")
            ->where($where_str)
            ->order("yc.create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        foreach ($user as &$op){


            $op['is_selected'] = $op['is_selected'] == 0?"否":"是";

            $op['is_reply'] = $op['is_reply'] == 0?"否":"是";

            $op['splicing'] = $op['splicing'] == 1 ? "好评" :($op['splicing'] == 2 ? '中评':($op['splicing'] == 3?'差评':''));

            if($op['pro_type'] ==1){  //商品

                $op['water_num'] = Db::name("y_products")->where("id",$op['pro_id'])->value("water_num");

            }else if($op['pro_type'] ==2){  //套餐

                $op['water_num'] = Db::name("y_products_card")->where("id",$op['pro_id'])->value("water_num");

            }else{  //水卡

                $op['water_num'] = Db::name("y_products_card")->where("id",$op['pro_id'])->value("water_num");

            }

        }

        $total_number = db::name("y_comment")
            ->alias("yc")
            ->leftJoin("y_products_order ypo","yc.order_id=ypo.id")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);



    }

    //评论详情 修改等
    public function commentSave(){
        $params = $this->request->param();

        $shi = Db::name("y_comment")
            ->alias("yc")
            ->leftJoin("y_products_order ypo","yc.order_id=ypo.id")
            ->leftJoin("y_comment yc2","yc.id=yc2.pid")
            ->field("yc.uid,yc.id,yc.create_time,yc.is_selected,yc.is_reply,ypo.order_num,yc.display")
            ->field("yc.shop_name,yc.pro_type,yc.pro_id,yc.content,yc.content_img,yc2.content member_content")
            ->field('yc.order_id')
            ->where("yc.id",$params['id'])
            ->find();

        if(!$shi)  return $this->buildFailed(ReturnCode::INVALID,'数据不存在！');

        $data = [
            'uid' => $this->uid,  //用户uid
            'type' => 1, //类型
            'data' => $params, //数据
        ];
        printLog("评论设置-1",$params,"commont");
        if($params['type'] ==1){ //查看

            if($shi['pro_type'] ==1){  //商品

                $shi['water_num'] = Db::name("y_products")->where("id",$shi['pro_id'])->value("water_num");

            }else if($shi['pro_type'] ==2){  //套餐

                $shi['water_num'] = Db::name("y_products_card")->where("id",$shi['pro_id'])->value("water_num");

            }else{  //水卡

                $shi['water_num'] = Db::name("y_products_card")->where("id",$shi['pro_id'])->value("water_num");

            }

            $shi['content_img'] = json_decode($shi['content_img'],true);

            $data['action_name'] = "查看评论";

            //$journal_add = journal_add($data);

            return $this->buildSuccess([
                'list'  => $shi,
            ]);

        }
        else if($params['type'] == 2){  //修改

            $updata = [];
            if(isset($params['is_selected']) && is_numeric($params['is_selected'])){
                if((int)$shi['is_selected'] != (int)$params['is_selected']){
                    $updata['is_selected'] = $params['is_selected'];
                }

            }
            $updata = [];

            if(isset($params['display']) && is_numeric($params['display'])){
                if((int)$shi['display'] != (int)$params['display']){
                    $updata['display'] = $params['display'];
                }
            }

            if(!$shi['uid'])  return $this->buildFailed(ReturnCode::INVALID,'平台数据无法回复！');
            //插入新的评论
            $updata2 = [];
            //修改原来的评论
            $update_comment = [];
            if($shi['is_reply'] != 1){   // 新增一条回复信息
                if(isset($params['content']) && !empty($params['content'])){
                    $updata2 = [
                        'type'        => 2,
                        'uid'         => 0,
                        'content'     => $params['content'],
                        'is_selected' => $params['is_selected'],
                        'pid'         => $params['id'],
                        'shop_name'   => $shi['shop_name'],
                        'pro_id'      => $shi['pro_id'],
                        'order_id'    => $shi['order_id'],
                    ];
                    $updata['is_reply'] = 1;
                }
            }
            else{
                if(isset($params['content']) && !empty($params['content'])){
                    //获取原来的二级评论  对应评论进行修改
                    $re_comment_2 = Db::name('y_comment')
                        ->where('pid',$params['id'])
                        ->where('type',2)
                        ->where('delete_time',null)
                        ->field('id,content,update_time')
                        ->find();
                    if(!$re_comment_2){
                        return $this->buildFailed(ReturnCode::INVALID,'查询平台回复内容失败！');
                    }
                    if($re_comment_2['content'] != $params['content']){
                        $update_comment['content'] = $params['content'];
                    }

                    if($shi['content'] != $params['u_content']){
                        $updata['content'] = $params['u_content'];
                    }
                }
                $updata['is_selected'] = $params['is_selected'];
//                return $this->buildFailed(ReturnCode::INVALID,'平台已回复！');
            }
            printLog("评论设置",$updata,"commont");
            // 启动事务
            Db::startTrans();
            try {

                if($updata){ //修改数据
                    $save = db::name("y_comment")->where("id",$params['id'])->update($updata);
                }
                if($updata2 && !$update_comment){ //新增数据
                    $save2 = db::name("y_comment")->insert($updata2);
                    if(!$save2)  return $this->buildFailed(ReturnCode::INVALID,'回复评论失败！');
                }

                if(!$updata2 && $update_comment){
                    $re_up_comment = Db::name('y_comment')->where('id',$re_comment_2['id'])
                        ->where('update_time',$re_comment_2['update_time'])
                        ->update($update_comment);

                    if(!$re_up_comment){
                        throw new Exception('更新评论失败，请重试！',11186);
                    }
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
            }

            $data['action_name'] = "回复评论";

            return $this->buildSuccess([], '操作成功');

        }
        else if($params['type'] ==3){  //删除

            $save = db::name("y_comment")->where("id","in",$params['id'])->update(['delete_time'=>date("Y-m-d H:i:s",time())]);

            if($save){
                $data['action_name'] = "删除评论";

                //$journal_add = journal_add($data);
                return $this->buildSuccess([], '删除成功');
            }else{
                return $this->buildFailed(ReturnCode::INVALID,'删除失败！');
            }

        }

    }

    //区域管理
    public function regionList(){


        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " delete_time is null " ;

        //名称
        if(isset($params['name']) && !empty($params['name'])){
            $where[] = 'locate("'.trim($params['name']).'",name)';
        }

        //开通状态
        if(isset($params['is_on']) && is_numeric($params['is_on'])){
            $where[] = ' is_on ='.$params['is_on'];
        }

        //创建时间
        if((isset($params['start_time']) && !empty($params['start_time'])) && (isset($params['end_time']) && !empty($params['end_time']))){
            if(strtotime($params['end_time']) < strtotime($params['start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'create_time >= "'.$params['start_time'].'"';
            $where[] = 'create_time <= "'.$params['end_time'].'"';
        }

        $where_str = implode(' and ',$where);

        $user = Db::name("y_address_shi")
            ->where($where_str)
//            ->order("create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();
        ;

        foreach ($user as &$op){

            $op['is_on'] = $op['is_on'] == 0?"未开通":"已开通";

        }

        $total_number = Db::name("y_address_shi")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);

    }

    //区域详情 修改等
    public function regionSave(){

        $params = $this->request->param();

        $shi = Db::name("y_address_shi")->where("id",$params['id'])->find();

        if(!$shi)  return $this->buildFailed(ReturnCode::INVALID,'数据不存在！');

        if($params['type'] ==1){ //查看

            return $this->buildSuccess([
                'list'  => $shi,
            ]);

        }else if($params['type'] ==2){  //修改

            if(!isset($params['is_on'])) return $this->buildFailed(ReturnCode::INVALID,'参数未传！');

            $up = Db::name("y_address_shi")->where("id",$params['id'])->update(['is_on'=>$params['is_on']]);

            if($up){
                $data = [
                    'action_name' =>"修改区域详情",
                    'uid' => $this->uid,  //用户uid
                    'type' => 1, //类型
                    'data' => $params, //数据
                ];
                //$journal_add = journal_add($data);
                return $this->buildSuccess([], '修改成功');
            }else{
                return $this->buildFailed(ReturnCode::INVALID,'修改失败！');
            }


        }
    }

    //消息列表
    public function newsList(){

    }

    //日志管理
    public function logList(){


        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where = [] ;

        //账号
        if(isset($params['nickname']) && !empty($params['nickname'])){
            $where[] = 'locate("'.trim($params['nickname']).'",nickname)';
        }

        //创建时间
        if((isset($params['start_time']) && !empty($params['start_time'])) && (isset($params['end_time']) && !empty($params['end_time']))){
            if(strtotime($params['end_time']) < strtotime($params['start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'add_time >= "'.$params['start_time'].'"';
            $where[] = 'add_time <= "'.$params['end_time'].'"';
        }

        $where_str = implode(' and ',$where);

        $user = Db::name("y_admin_user_action")
            ->where($where_str)
            ->order("add_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        $total_number = Db::name("y_admin_user_action")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);

    }

    //发票列表
    public function invoiceList(){
        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " yoii.delete_time is null " ;

        //订单编号
        if(isset($params['order_num']) && !empty($params['order_num'])){
            $where[] = 'locate("'.trim($params['order_num']).'",ypo.order_num)';
        }

        //手机号
        if(isset($params['tel']) && !empty($params['tel'])){
            $where[] = 'locate("'.trim($params['tel']).'",ym.tel)';
        }

        //发票状态
        if(isset($params['is_on']) && is_numeric($params['is_on'])){
            $where[] = ' yoii.is_on ='.$params['is_on'];
        }

        //发票抬头
        if(isset($params['title']) && !empty($params['title'])){
            $where[] = 'locate("'.trim($params['title']).'",yoii.title)';
        }

        //创建时间
        if((isset($params['start_time']) && !empty($params['start_time'])) && (isset($params['end_time']) && !empty($params['end_time']))){
            if(strtotime($params['end_time']) < strtotime($params['start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'yoii.create_time >= "'.$params['start_time'].'"';
            $where[] = 'yoii.create_time <= "'.$params['end_time'].'"';
        }

        $where[] = 'ypo.invoice_id != 0';

        $where[] = 'ypo.status = 4';

        $where_str = implode(' and ',$where);

        $user = Db::name("y_products_order")
            ->alias("ypo")
            ->leftJoin("y_order_invoice_info yoii","ypo.invoice_id=yoii.id")
            ->leftJoin("y_member ym","ypo.uid=ym.id")
            ->leftJoin("y_products_order_info ordinfo","ypo.id=ordinfo.order_id")
            ->field("yoii.id,yoii.title,yoii.email,yoii.tax_num,yoii.create_time,
            yoii.address,yoii.bank,yoii.bank_number,yoii.is_on,ypo.order_num,ym.tel,ypo.price,ym.nickname,ordinfo.products_info")
            ->where($where_str)
            ->order("ypo.create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        foreach ($user as &$op){
            $op['is_on_text'] = $op['is_on'] == 0?"未开":"已开";
            $op['product_name'] = '';
            if(!empty($op['products_info'])){
                $products_info = json_decode($op['products_info'], true);
                if(!empty($products_info)){
                    $op['product_name'] = $products_info['n'];
                }
            }
        }

        if($params['export'] == 1){
            $column_arr = ['ID','抬头','邮箱','纳税人识别号','创建时间','地址','开户行','开户账号','状态','订单号','手机号','金额','是否已开票','用户昵称'];
            $upload_file_path = '';
            $upload_root_path = root_path().'/public/';

            $url_path = 'excel/';

            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('Y').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('m').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('d').'/';
            $upload_file_path = $upload_root_path.$url_path;
            if (!file_exists($upload_file_path)) {
                mkdir($upload_file_path, 0755, true);
            }
            $url_path = $url_path.date('YmdHis', time()) . '发票列表.xlsx';
            $upload_file_path = $upload_root_path. $url_path;
            try{
                excel_action($user,$column_arr,'发票列表',$upload_file_path);
            }catch (\Exception $e){
//            return json(result_create_arr(new \stdClass(),'导出失败，请重试 !'));
                return $this->buildFailed(ReturnCode::INVALID,'导出失败！');

            }
          return $this->buildSuccess([config('app.api_url').'/'.$url_path], '导出成功');

        }

        $total_number =  Db::name("y_products_order")
            ->alias("ypo")
            ->leftJoin("y_order_invoice_info yoii","ypo.invoice_id=yoii.id")
            ->leftJoin("y_member ym","ypo.uid=ym.id")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);
    }

    //发票开票和批量删除
    public function invoiceSave()
    {

        $params = $this->request->param();

        $shi = Db::name("y_order_invoice_info")->where("id", $params['id'])->find();

        if (!$shi) return $this->buildFailed(ReturnCode::INVALID, '数据不存在！');

        $data = [
            'uid' => $this->uid,  //用户uid
            'type' => 1, //类型
            'data' => $params, //数据
        ];


        if ($params['type'] == 1) { //开票

            if($shi['is_on'] == 1)   return $this->buildFailed(ReturnCode::INVALID,'已开票！');

            if(!isset($params['is_on']) || !is_numeric($params['is_on']) || $params['is_on'] != 1){
                return $this->buildFailed(ReturnCode::INVALID,'参数错误只能传1！');
            }

            $save = Db::name("y_order_invoice_info")->where("id",$params['id'])->update(['is_on'=>$params['is_on']]);

            if($save){
                $data['action_name'] ="开票成功";
                //$journal_add = journal_add($data);
                return $this->buildSuccess([], '开票成功');
            }else{
                return $this->buildFailed(ReturnCode::INVALID, '开票失败！');
            }

        } else if ($params['type'] == 2) {  //批量删除

            $save = Db::name("y_order_invoice_info")->where("id","in",$params['id'])->update(['delete_time'=>date("Y-m-d H:i:s",time())]);

            if($save){
                $data['action_name'] ="删除发票成功";
                //$journal_add = journal_add($data);
                return $this->buildSuccess([], '删除成功');
            }else{
                return $this->buildFailed(ReturnCode::INVALID, '删除失败！');
            }

        }
    }

    //轮播图列表
    public function carouselList(){
        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " delete_time is null " ;

        $where_str = implode(' and ',$where);

        $user = Db::name("y_banner")
            ->where($where_str)
            ->order("sort desc")
            ->limit($page_start,$limit)
            ->select()->toArray();
        ;

        foreach ($user as &$op){
            $op['is_disable'] = $op['is_disable'] == 1?"启用":"禁用";
        }


        $total_number = Db::name("y_banner")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);
    }

    //查询，新增，修改，删除轮播
    public function carouselSave(){
        $params = $this->request->param();

        $data = [
            'uid' => $this->uid,  //用户uid
            'type' => 1, //类型
            'data' => $params, //数据
        ];

        if($params['type_info'] ==1){ //查看

            if(!isset($params['id'])) return $this->buildFailed(ReturnCode::INVALID,'缺少参数！');

            $y_banner = Db::name("y_banner")->where("id",$params['id'])->find();

            if(!$y_banner) return $this->buildFailed(ReturnCode::INVALID,'数据不存在！');

            $data['action_name'] = "查看轮播详情";
            //$journal_add = journal_add($data);
            return $this->buildSuccess([
                'list'  => $y_banner,
            ]);

        }else if($params['type_info'] ==2){  //新增

            $updata = [];

            if(isset($params['img'])){
                $updata['img'] = $params['img'];
            }

            if(isset($params['url'])){
                $updata['url'] = $params['url'];
            }

            if(isset($params['type'])){
                $updata['type'] = $params['type'];
            }

            if(isset($params['title'])){
                $updata['title'] = $params['title'];
            }

            if(isset($params['obj_id'])){
                $updata['obj_id'] = $params['obj_id'];
            }

            if(isset($params['is_disable'])){
                $updata['is_disable'] = $params['is_disable'];
            }

            if(isset($params['sort'])){
                $updata['sort'] = $params['sort'];
            }

            if(isset($params['banner_upload_type'])){
                $updata['banner_upload_type'] = $params['banner_upload_type'];
            }

            if($updata){

                $save = db::name("y_banner")->insert($updata);

                if(!$save) $this->buildFailed(ReturnCode::INVALID,'新增失败！');

            }
            Cache::delete('banner_list');

            $data['action_name'] = "新增轮播";
            //$journal_add = journal_add($data);
            return $this->buildSuccess([], '操作成功');

        }else if($params['type_info'] ==3){  //修改

            if(!isset($params['id'])) return $this->buildFailed(ReturnCode::INVALID,'缺少参数！');

            $y_banner = Db::name("y_banner")->where("id",$params['id'])->find();

            if(!$y_banner) return $this->buildFailed(ReturnCode::INVALID,'数据不存在！');

            unset($params['type_info']);

            $save = db::name("y_banner")->where("id",$params['id'])->update($params);

            if($save){
                Cache::delete('banner_list');
                $data['action_name'] = "修改轮播";
                //$journal_add = journal_add($data);
                return $this->buildSuccess([], '修改成功');
            }else{
                return $this->buildFailed(ReturnCode::INVALID,'修改失败！');
            }

        }else if($params['type_info'] ==4){ //删除
            if(!isset($params['id'])) return $this->buildFailed(ReturnCode::INVALID,'缺少参数！');

            $y_banner = Db::name("y_banner")->where("id",$params['id'])->find();

            if(!$y_banner) return $this->buildFailed(ReturnCode::INVALID,'数据不存在！');

            $save = db::name("y_banner")->where("id",$params['id'])->update(['delete_time'=>date("Y-m-d H:i:s",time())]);

            if($save){
                Cache::delete('banner_list');
                $data['action_name'] = "删除轮播";
                //$journal_add = journal_add($data);
                return $this->buildSuccess([], '删除成功');
            }else{
                return $this->buildFailed(ReturnCode::INVALID,'删除失败！');
            }

        }

    }

    //系统设置
    public function settingConfig(){
        $params = $this->request->param();

        if($params['type_info'] ==1){ //查看

            $config = Db::name("y_config")->where("id in (11,12)")->field("config_key,value")->select()->toArray();

            return $this->buildSuccess([
                'list'  => $config,
            ]);

        }else if($params['type_info'] ==2){  //修改
            // 启动事务
            Db::startTrans();
            try {

                if(isset($params['weixin_pay']) && is_numeric($params['weixin_pay'])){
                    $save = Db::name("y_config")->where("id","11")->update(['value'=>$params['weixin_pay']]);
                }

                if(isset($params['ta_pay']) && is_numeric($params['ta_pay'])){
                    $save2 = Db::name("y_config")->where("id","12")->update(['value'=>$params['ta_pay']]);
                }

                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
            }

            Cache::delete('cache_config');

            $data = [
                'action_name' =>"系统设置",
                'uid' => $this->uid,  //用户uid
                'type' => 1, //类型
                'data' => $params, //数据
            ];
            //$journal_add = journal_add($data);
            return $this->buildSuccess([], '操作成功');

        }
    }

    //系统设置自定义模块
    public function settingConfig2(){
        $params = $this->request->param();

        if($params['type_info'] ==1){ //查看

            $config = Db::name("y_config")->where("id = 4")->field("config_key,value")->find();

            $config['value'] = json_decode($config['value']);

            return $this->buildSuccess([
                'list'  => $config,
            ]);

        }else if($params['type_info'] ==2){  //修改
            if(!isset($params['data']) && empty($params['data']))    return $this->buildFailed(ReturnCode::INVALID,'请上传配置参数！');

            $save = Db::name("y_config")->where("id","4")->update(['value'=>$params['data']]);

            if(!$save)  return $this->buildFailed(ReturnCode::INVALID,'修改失败！');

            Cache::delete('cache_config');

            $data = [
                'action_name' =>"系统设置自定义模块",
                'uid' => $this->uid,  //用户uid
                'type' => 1, //类型
                'data' => $params, //数据
            ];
            //$journal_add = journal_add($data);
            return $this->buildSuccess([], '操作成功');

        }
    }

    //用户协议设置
    public function agreement(){
        $params = $this->request->param();

        if($params['type_info'] ==1){ //查看

            $y_help = Db::name("y_help")->where("type",$params['type'])->find();

            return $this->buildSuccess([
                'list'  => $y_help,
            ]);

        }
        else if($params['type_info'] ==2){  //修改
            // 启动事务
            Db::startTrans();
            try {

                if(isset($params['description'])){
                    $update['description'] = $params['description'];
                }

                $save = Db::name("y_help")->where("type",$params['type'])->update($update);

                if(!$save)   return $this->buildFailed(ReturnCode::INVALID,'修改失败！');

                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
            }

            Cache::delete('cache_help');
            $data = [
                'action_name' =>"用户协议设置",
                'uid' => $this->uid,  //用户uid
                'type' => 1, //类型
                'data' => $params, //数据
            ];

            //$journal_add = journal_add($data);
            return $this->buildSuccess([], '操作成功');

        }


    }


    //已开通城市区域
    public function openCity(){
        //查询已开通城市
        $city = Db::name("y_address_shi")->field("id,code,name")->where("is_on",1)->select()->toArray();

        foreach ($city as &$op){
            $op['area'] = Db::name("y_address_xian")->field("id,name")->where("pcode",$op['code'])->select()->toArray();
        }

        return $this->buildSuccess($city, '操作成功');
    }

    //业务员 业绩查询
    public function yeAchievement(){
        $params = $this->request->param();
        $uid = $this->uid;

        $where[] = " delete_time is null ";
        //创建时间
        if(isset($params['start_time']) && !empty($params['start_time'])){
            if(strtotime($params['end_time']) < strtotime($params['start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'create_time >= "'.$params['start_time'].'"';
            $where[] = 'create_time <= "'.$params['end_time'].'"';
        }

        $where1 = " type =1 ";
        $where2 = " type =2 ";
        $where3 = " type =3 ";

        $where[] = " uid =".$uid;

        $where_str = implode(' and ',$where);

        $sentence = "select
                    count(case when $where1 then type else null  end) as type1_num,
                    count(case when $where2 then type else null  end) as type2_num,
                    count(case when $where3 then type else null end) as type3_num
                    from y_products_order where $where_str";

        $list = Db::query($sentence);

        $where_str2 = "delete_time is null and uid =".$uid;

        $sentence2 = "select
                    count(case when $where1 then type else null  end) as type1_num_all,
                    count(case when $where2 then type else null  end) as type2_num_all,
                    count(case when $where3 then type else null end) as type3_num_all
                    from y_products_order where $where_str2 ";

        $list2 = Db::query($sentence2);

        return $this->buildSuccess([
            'list'  => $list,


            'list2'  => $list2
        ]);

    }


    //我的押金
    public function deposit(){
        $params = $this->request->param();
        $uid = $this->uid;

        //查询我的押金
        $users = Db::name("y_member_profile")->where("uid",$uid)->field("deposit,card_deposit,uid")->find();
        $users['deposit'] = bcdiv($users['deposit'],100,2);
        $users['card_deposit'] = bcdiv($users['card_deposit'],100,2);

        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);

        $page_start = limit_arr($page,$limit);

        $where[] = " delete_time is null " ;

        $where[] = " uid =".$uid;

        $where_str = implode(' and ',$where);

        $user = db::name("y_vip_log")
            ->field("type,price,create_time")
            ->where($where_str)
            ->order("create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();


        foreach ($user as &$op){

            $op['type'] = $op['type'] == 1 ? "缴纳押金" :'退还押金';

        }

        $total_number = db::name("y_vip_log")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number,
            'user' =>$users
        ]);

    }

    //修改密码
    public function modify_password(){
        $params = $this->request->param();

        $uid = $this->uid;

        if (!is_password($params['old_password'],6,16)){
            return $this->buildFailed(ReturnCode::INVALID, '请输入正确的旧密码，不能包含特殊字符，长度为6-16位!');
        }

        if (!is_password($params['password'],6,16)){
            return $this->buildFailed(ReturnCode::INVALID, '请输入正确的新密码，不能包含特殊字符，长度为6-16位!');
        }

        if (!is_password($params['password2'],6,16)){
            return $this->buildFailed(ReturnCode::INVALID, '请输入正确的新的确认密码，不能包含特殊字符，长度为6-16位!');
        }

        if($params['password'] != $params['password2']){
            return $this->buildFailed(ReturnCode::INVALID, '新密码确认不一致');
        }

        $same_member = db::name("y_member")->where("id",$uid)->where("password",md5($params['old_password']))->find();

        if(!$same_member) return $this->buildFailed(ReturnCode::INVALID, '旧密码输入错误!');

        $update = db::name("y_member")->where("id",$uid)->update(['password'=>md5($params['password'])]);

        if($update){
            return $this->buildSuccess([], '修改密码成功');
        }else{
            return $this->buildFailed(ReturnCode::INVALID, '密码修改失败!');
        }
    }

    //水池管理
    public function poolList(){
        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " ymp.delete_time is null " ;

        //订单编号
        if(isset($params['nickname']) && !empty($params['nickname'])){
            $where[] = 'locate("'.trim($params['nickname']).'",ym.nickname)';
        }

        //创建时间
        if((isset($params['start_time']) && !empty($params['start_time'])) && (isset($params['end_time']) && !empty($params['end_time']))){
            if(strtotime($params['end_time']) < strtotime($params['start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'ymp.create_time >= "'.$params['start_time'].'"';
            $where[] = 'ymp.create_time <= "'.$params['end_time'].'"';
        }


        $where_str = implode(' and ',$where);

        $user = Db::name("y_member_profile")
            ->alias("ymp")
            ->leftJoin("y_member ym","ymp.uid=ym.id")
            ->field("ymp.id,ym.nickname,ymp.water_change,ymp.water_no,ymp.create_time")
            ->where($where_str)
            ->order("create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();

        foreach ($user as &$op){
            $op['nickname'] = emoji_decode($op['nickname']);
        }

        $total_number =  Db::name("y_member_profile")
            ->alias("ymp")
            ->leftJoin("y_member ym","ymp.uid=ym.id")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);
    }

    //水池详情
    public function poolciInfo(){
        $params = $this->request->param();

        if($params['type'] ==1){

            $user = Db::name("y_member_profile")
                ->alias("ymp")
                ->leftJoin("y_member ym","ymp.uid=ym.id")
                ->field("ymp.id,ym.nickname,ymp.water_change,ymp.water_no,ymp.create_time,ymp.uid")
                ->where("ymp.id",$params['id'])
                ->find();

            if(!$user) return $this->buildFailed(ReturnCode::INVALID, '水池数据不存在!');


            //查询水次变动情况
            $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
            $page = $this->request->get('pages', 1);

            $page_start = limit_arr($page,$limit);

            $where[] = " delete_time is null " ;

            $where[] = ' uid ='.$user['uid'];

            $where_str = implode(' and ',$where);

            $order = db::name("y_member_water_info")
                ->field("attr_named,water_change,water_no,note,create_time")
                ->where($where_str)
                ->order("create_time desc")
                ->limit($page_start,$limit)
                ->select()->toArray();

            foreach ($order as &$op){
                $op['water'] = $op['water_change'] + $op['water_no'];
            }

            $total_number = db::name("y_member_water_info")->where($where_str)->count();

            $re = Db::name('y_water_log')
                ->where('uid',$user['uid'])->whereOr('to_uid',$user['uid'])
                ->where('delete_time',null)
                ->where('a_type',2)
                ->field('id,water_num,create_time,status_type')
                ->order('create_time','desc')
                ->select()->toArray();



            return $this->buildSuccess([
                'list'       => $order,
                'water_list' => $re,
                'count'      => $total_number,
                'info'       => $user
            ]);
        }else if($params['type'] ==2){  //删除

            $save = db::name("y_member_profile")->where("id","in",$params['id'])->update(['delete_time'=>date("Y-m-d H:i:s",time())]);
            if($save) {
                $data = [
                    'action_name' => "删除水池详情",
                    'uid' => $this->uid,  //用户uid
                    'type' => 1, //类型
                    'data' => $params, //数据
                ];
                //$journal_add = journal_add($data);
                return $this->buildSuccess([], '删除成功');
            }
        }


    }


    //用户反馈列表
    public function userFeedback(){
        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);
        $params = $this->request->param();

        $page_start = limit_arr($page,$limit);

        $where[] = " yf.delete_time is null " ;

        //用户昵称
        if(isset($params['nickname']) && !empty($params['nickname'])){
            $where[] = 'locate("'.trim($params['nickname']).'",yma.nickname)';
        }


        //订单查询时间
        if((isset($params['start_time']) && !empty($params['start_time'])) && isset($params['end_time']) && !empty($params['end_time'])){
            if(strtotime($params['end_time']) < strtotime($params['start_time'])){
                return $this->buildFailed(ReturnCode::INVALID, '查询开始时间不能大于结束时间!');
            }

            $where[] = 'yf.create_time >= "'.$params['start_time'].'"';
            $where[] = 'yf.create_time <= "'.$params['end_time'].'"';
        }

        $where_str = implode(' and ',$where);

        $user = db::name("y_feedback")
            ->alias("yf")
            ->leftJoin("y_member yma","yf.uid=yma.id")
            ->leftJoin("y_feedback_type yft","yf.type_id=yft.id")
            ->field("yf.*,yma.nickname,yft.title")
            ->where($where_str)
            ->order("yf.create_time desc")
            ->limit($page_start,$limit)
            ->select()->toArray();
        ;

        foreach ($user as &$op){

            $op['status_text'] = $op['status'] == 0 ? "未处理" :'已处理';

            $op['user_type'] = $op['status'] == 1 ? "业务员" :'用户';

            $op['image'] = json_decode($op['image'],true);

        }

        $total_number = db::name("y_feedback")
            ->alias("yf")
            ->leftJoin("y_member yma","yf.uid=yma.id")
            ->leftJoin("y_feedback_type yft","yf.type_id=yft.id")
            ->where($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);
    }

    //用户反馈详情和删除
    public function userFeedbackInfo(){
        $params = $this->request->param();

        $shi =  $user = db::name("y_feedback")
            ->alias("yf")
            ->leftJoin("y_member yma","yf.uid=yma.id")
            ->leftJoin("y_feedback_type yft","yf.type_id=yft.id")
            ->field("yf.*,yma.nickname,yft.title")
            ->where("yf.id",$params['id'])
            ->find();

        if(!$shi)  return $this->buildFailed(ReturnCode::INVALID,'数据不存在！');

        $data = [
            'uid' => $this->uid,  //用户uid
            'type' => 1, //类型
            'data' => $params, //数据
        ];

        if($params['type'] ==1){ //查看
            $shi['status_text'] = $shi['status'] == 0 ? "未处理" :'已处理';

            $shi['user_type'] = $shi['status'] == 1 ? "业务员" :'用户';

            $shi['image'] = json_decode($shi['image'],true);

            $data['action_name'] = "查看评论";

            //$journal_add = journal_add($data);

            return $this->buildSuccess([
                'list'  => $shi,
            ]);

        }else if($params['type'] ==2){  //回复

            if(!isset($params['reply']) || empty($params['reply'])){
                return $this->buildFailed(ReturnCode::INVALID,'请输入回复信息！');
            }

            if($shi['status'])  return $this->buildFailed(ReturnCode::INVALID,'该反馈已回复！');

            $updata = [
                'status' =>1,
                'update_time'=>date("Y-m-d H:i:s",time()),
                'reply' =>$params['reply']
            ];

            // 启动事务
            Db::startTrans();
            try {

                $save = db::name("y_feedback")->where("id",$params['id'])->update($updata);
                if(!$save)  return $this->buildFailed(ReturnCode::INVALID,'反馈回复失败！');

                $data['action_name'] = "回复用户反馈";

                $data_message[] = [
                    'type'=>2,
                    'uid'=>$shi['uid'],  //发送用户可以自己给自己发
                    'obj_id'=>$params['id'],
                    'touid'=>$shi['uid'],    //发送给那个用户
                    'content'=>"您的反馈已回复",
                    'm_type'=>5,
                    'user_type'=>3

                ];
                $msg_obj = new Message($this->app);
                $msg_obj->create_message($data_message,0);

               Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
            }

            //$journal_add = journal_add($data);

            return $this->buildSuccess([], '操作成功');

        }else if($params['type'] ==3){  //删除

            $save = db::name("y_feedback")->where("id","in",$params['id'])->update(['delete_time'=>date("Y-m-d H:i:s",time())]);

            if($save){
                $data['action_name'] = "删除用户反馈";

                //$journal_add = journal_add($data);
                return $this->buildSuccess([], '删除成功');
            }else{
                return $this->buildFailed(ReturnCode::INVALID,'删除失败！');
            }

        }
    }

    //会员费用、会员免桶设置
    public function showVip(){
        $config_data = ConfigM::get_all_config();

        $re_data = [];
        if(isset($config_data['vip_fee']) && !empty($config_data['vip_fee'])){
            $re_data['vip_fee'] = bcdiv($config_data['vip_fee'],100,2);

        }

        if(isset($config_data['vip_bucket']) && !empty($config_data['vip_bucket'])){
            $re_data['vip_bucket'] = $config_data['vip_bucket'];
        }

        if(!$re_data){
            return $this->buildFailed(ReturnCode::INVALID,'请联系技术人员处理会员费用数据错误！');
        }

        return $this->buildSuccess($re_data,'会员设置！');
    }

    //编辑会员费用、会员免桶设置
    public function updateVip(){
        $param = $this->request->param();

        if(!isset($param['vip_fee']) || (float)$param['vip_fee'] <= 0){
            return $this->buildFailed(ReturnCode::INVALID,'请设置会员费用！');
        }

        if(!isset($param['vip_bucket']) || (int)$param['vip_bucket'] < 0){
            return $this->buildFailed(ReturnCode::INVALID,'请设置会员免桶数量！');
        }

        $config_data = ConfigM::get_all_config();

        $update_config = [];

        $param['vip_fee'] = bcmul($param['vip_fee'],100);

        if($config_data['vip_fee'] !=  $param['vip_fee']){
            $update_config['vip_fee'] = $param['vip_fee'];
        }

        if($config_data['vip_bucket'] !=  $param['vip_bucket']){
            $update_config['vip_bucket'] = $param['vip_bucket'];
        }

        if(!$update_config){
            return $this->buildFailed(ReturnCode::INVALID,'请修改后再保存数据！');
        }


        $update_time = date('Y-m-d H:i:s');


        // 启动事务
        Db::startTrans();
        try {



            if(isset($update_config['vip_fee']) && !empty($update_config['vip_fee'])){
                $re_vip_fee = Db::name('y_config')->where('config_key','vip_fee')
                    ->update(['value'=>$update_config['vip_fee'],'update_time'=>$update_time]);

                if(!$re_vip_fee){
                    throw new Exception('更新会员费用失败，请重试！');
                }
            }



            if(isset($update_config['vip_bucket']) && !empty($update_config['vip_bucket'])){
                $re_vip_bucket = Db::name('y_config')->where('config_key','vip_bucket')
                    ->update(['value'=>$update_config['vip_bucket'],'update_time'=>$update_time]);
                if(!$re_vip_bucket){
                    throw new Exception('更新会员免桶数量失败，请重试！');
                }

            }


            $re_cache = Cache::delete('cache_config');

            if(!$re_cache){
                throw new Exception('清楚缓存信息失败，请重试！');
            }

            $re_config = [
                'vip_fee'=>bcdiv($param['vip_fee'],100,2),
                'vip_bucket'=>$param['vip_bucket']
            ];

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();

            return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
        }

        return $this->buildSuccess($re_config,'编辑会员数据完成！');

    }


    //管理员列表
    public function adminList(){

        $params = $this->request->param();

        $limit = $this->request->get('limit', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $page = $this->request->get('pages', 1);

        $page_start = limit_arr($page,$limit);

        $where[] = " ya.delete_time is null " ;

        //用户昵称
        if(isset($params['realname']) && !empty($params['realname'])){
            $where[] = 'locate("'.trim($params['realname']).'",ya.realname)';
        }

        $where_str = implode(' and ',$where);

        $user = Db::name('y_admin')->alias('ya')->whereRaw($where_str)
            ->field('id,account,realname,avatar,email,gender,mobile,power,create_time')
            ->order('create_time','desc')
            ->limit($page_start,$limit)
            ->select()
            ->toArray();

        if($user){

            $admin_id_arr = array_column($user,'id');
            $re_role_name = Db::name('y_user_role')
                ->alias('yur')
                ->leftJoin('y_role yr','yur.role_id=yr.id')
                ->where('uid','in',$admin_id_arr)
                ->field('yur.uid,yr.name,yr.id')
                ->select();
            foreach ($user as $k=>&$item){

                if($item['gender'] == 1){
                    $item['gender'] = "男";
                }elseif($item['gender'] == 2){
                    $item['gender'] = "女";
                }else{
                    $item['gender'] = "未知";
                }

                $item['realname'] = emoji_decode($item['realname']);

                $item['role_name'] = [];
                $item['role_id'] = [];

                foreach ($re_role_name as $item_s){
                    if($item['id'] == $item_s['uid']){
                        $item['role_name'][] = $item_s['name'];
                        $item['role_id'] [] = $item_s['id'];
                    }
                }
            }
        }

        $total_number = Db::name('y_admin')
            ->alias('ya')
            ->whereRaw($where_str)
            ->count();

        return $this->buildSuccess([
            'list'  => $user,
            'count' => $total_number
        ]);
    }

    //管理员操作
    public function action_admin(){
        $params = $this->request->param();



        if(in_array($params['type'],[1,2])){
            if(!isset($params['name']) || empty($params['name'])){
                return $this->buildFailed(ReturnCode::INVALID,'请设置名称！');
            }

            if(!isset($params['mobile']) || empty($params['mobile'])){
                return $this->buildFailed(ReturnCode::INVALID,'请设置手机号码！');
            }

            if(!isset($params['r_id']) || empty($params['r_id'])){
                return $this->buildFailed(ReturnCode::INVALID,'请设置值角色！');
            }else{
                try{
                    $re_check_str = check_id_str($params['r_id']);
                }catch (Exception $e){
                    return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
                }

                //检查角色是否存在
                $re_role = Db::name('y_role')
                    ->where('id','in',$re_check_str)
                    ->field('id')
                    ->select()->toArray();
                if(!$re_role){
                    return $this->buildFailed(ReturnCode::INVALID,'选择的角色查询错误！');
                }
            }
        }

        if($params['type'] == 1){
            //新增
            if(!isset($params['password']) || empty($params['password'])){
                return $this->buildFailed(ReturnCode::INVALID,'请设置密码 ！');
            }

            //检查用户称是否已存在
            $re_admin = Db::name('y_admin')
                ->where('mobile',$params['mobile'])
                ->field('id')
                ->where('status',1)
                ->find();
            if($re_admin){
                //return json(result_create_arr(new \stdClass(),"填写的手机号码已存在！"));
                return $this->buildFailed(ReturnCode::INVALID,'填写的手机号码已存在！');
            }
            $ins = [
                'realname'=>$params['name'],
                'account'=>$params['mobile'],
//                'password'=>md5($params['password']),
                'password' =>Tools::userMd5($params['password']),
                'mobile'=>$params['mobile'],
            ];

            // 启动事务
            Db::startTrans();
            try {

                $re_id = Db::name('y_admin')->insertGetId($ins);

                if(!$re_id){
                    throw new \think\Exception('新增管理员失败，请重试！', 10006);
                }
                unset($ins['password']);
                $ins['id'] = $re_id;
                $ins_role = [];
                foreach ($re_role as $v){
                    $ins_role[] = ['uid'=>$re_id,'role_id'=>$v['id']];
                }

                if($ins_role){
                    $re_ids = Db::name('y_user_role')->insertAll($ins_role);
                    if($re_ids != count($ins_role)){
                        throw new \think\Exception('新增管理员设置角色失败，请重试！', 10006);
                    }
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                //return json(result_create_arr(new \stdClass(),$e->getMessage()));
                return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
            }

        }
        else if($params['type'] == 2){
            if(!isset($params['id']) || empty($params['id'])){
//                return json(result_create_arr(new \stdClass(),"缺少id"));
                return $this->buildFailed(ReturnCode::INVALID,'缺少id');
            }

            $re_admin = Db::name('y_admin')
                ->where('id',$params['id'])
                ->where('status',1)
                ->field('id,mobile,password,realname')
                ->find();

            if(!$re_admin){
                //return json(result_create_arr(new \stdClass(),"查询修改的管理员错误，请检查管理员是否创建！"));
                return $this->buildFailed(ReturnCode::INVALID,'查询修改的管理员错误，请检查管理员是否创建！');
            }

            $update = [];

            if(isset($params['name']) && !empty($params['name'])){
                if($params['name'] != $re_admin['realname']){
                    $update['realname'] = $params['name'];
                }
            }



            if(isset($params['password']) && !empty($params['password'])){
                if(Tools::userMd5($params['password']) != $re_admin['password']){
                    $update['password'] = Tools::userMd5($params['password']);
                }
            }

            if(isset($params['mobile']) && !empty($params['mobile'])){
                if($params['mobile'] != $re_admin['mobile']){
                    //检查用户的手机号码是否已存在
                    $check_mobile = Db::name('y_admin')
                        ->where('mobile',$params['mobile'])
                        ->where('id','<>',$params['id'])
                        ->where('status',1)
                        ->find();
                    if($check_mobile){
//                    return json(result_create_arr(new \stdClass(),"修改的手机号码已存在！"));
                        return $this->buildFailed(ReturnCode::INVALID,'修改的手机号码已存在！');
                    }

                    $update['mobile'] = $params['mobile'];

                }
            }



            //获取用户分配的角色
            $check_role = Db::name('y_role')
                ->where('id','in',$params['r_id'])
//                ->field('id')
                ->column('id');

            $re_role_arr = array_column($re_role,'id');

            $re_role_str = implode(',',$re_role_arr);

            $check_role_str = implode(',',$check_role);

            if($re_role_str != $check_role_str){
                $update['role'] = $re_role_str;
            }

            if(!$update){
//                return json(result_create_arr(new \stdClass(),"请修改后再保存！"));
                return $this->buildFailed(ReturnCode::INVALID,'请修改后再保存！');
            }



            // 启动事务
            Db::startTrans();
            try {
                if(isset($update['role']) && !empty($update['role'])){
                    $re_user_role_del = Db::name('y_user_role')->where('uid',$params['id'])->delete();
                    if(!$re_user_role_del){
                        throw new \think\Exception('修改管理员设置角色失败，请重试！', 10006);
                    }
                    $ins_role = [];
                    foreach ($re_role_arr as $v){
                        $ins_role[] = ['uid'=>$params['id'],'role_id'=>$v];
                    }

                    if($ins_role){
                        $re_ids = Db::name('y_user_role')->insertAll($ins_role);
                        if($re_ids != count($ins_role)){
                            throw new \think\Exception('修改管理员设置角色失败，请重试！', 10006);
                        }
                    }
                    unset($update['role']);
                }

                if($update){
                    $update['update_time']=date('Y-m-d H:i:s');
                    $re_up_admin = Db::name('y_admin')->where('id',$params['id'])->update($update);

                    if(!$re_up_admin){
                        throw new \think\Exception('修改管理员失败，请重试！', 10006);
                    }
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
            }


        }
        else if($params['type'] == 3){
            $re_admin = Db::name('y_admin')
                ->where('id',$params['id'])
                ->where('status',1)
                ->field('id,mobile,password,realname,status')
                ->find();
            if(!$re_admin){
                //return json(result_create_arr(new \stdClass(),"查询删除的管理员错误，请检查管理员是否创建！"));
                return $this->buildFailed(ReturnCode::INVALID,"查询删除的管理员错误，请检查管理员是否创建！");
            }

            // 启动事务
            Db::startTrans();
            try {
                $re_user_role_del = Db::name('y_user_role')->where('uid',$params['id'])->delete();
                if(!$re_user_role_del){
                    throw new \think\Exception('删除管理员设置角色失败，请重试！', 10006);
                }

                $d_time = date('Y-m-d H:i:s');

                $update = [
                    'status'=>-1,
                    'update_time'=>$d_time,
                    'delete_time'=>$d_time
                ];

                $re_up_admin = Db::name('y_admin')->where('id',$params['id'])->update($update);

                if(!$re_up_admin){
                    throw new \think\Exception('删除管理员失败，请重试！', 10006);
                }

                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return $this->buildFailed(ReturnCode::INVALID,$e->getMessage());
            }
        }


        if(isset($ins)){
            $re_arr = $ins;
        }else{
            $re_arr = [];
        }

//        return json(result_create_arr($re_arr,'操作成功！',1));
        return $this->buildSuccess($re_arr,'操作成功！');
    }


}