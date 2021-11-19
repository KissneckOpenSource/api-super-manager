<?php
declare (strict_types=1);
/**
 * 接口输入输出字段维护
 * @since   2018-02-21
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\controller\admin;

use app\model\AdminFields;
use app\model\AdminList;
use app\util\DataType;
use app\util\ReturnCode;
use app\util\Tools;
use think\Exception;
use think\facade\Db;
use think\Response;

class Fields extends Base {

    private $dataType = [
        DataType::TYPE_INTEGER => 'Integer',
        DataType::TYPE_STRING  => 'String',
        DataType::TYPE_BOOLEAN => 'Boolean',
        DataType::TYPE_ENUM    => 'Enum',
        DataType::TYPE_FLOAT   => 'Float',
        DataType::TYPE_FILE    => 'File',
        DataType::TYPE_MOBILE  => 'Mobile',
        DataType::TYPE_OBJECT  => 'Object',
        DataType::TYPE_ARRAY   => 'Array'
    ];

    /**
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function index(): Response {
        return $this->buildSuccess($this->dataType);
    }

    /**
     * 获取请求参数
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function request(): Response {
        $limit = $this->request->get('size', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $start = $this->request->get('page', 1);
        $hash = $this->request->get('hash', '');

        if (empty($hash)) {
            return $this->buildFailed(ReturnCode::EMPTY_PARAMS, '缺少必要参数');
        }
        $listObj = (new AdminFields())->where('hash', $hash)->where('type', 0)
            ->paginate(['page' => $start, 'list_rows' => $limit])->toArray();

        $apiInfo = (new AdminList())->where('hash', $hash)->find();

        return $this->buildSuccess([
            'list'     => $listObj['data'],
            'count'    => $listObj['total'],
            'dataType' => $this->dataType,
            'apiInfo'  => $apiInfo
        ]);
    }

    /**
     * 获取返回参数
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function response(): Response {
        $limit = $this->request->get('size', config('apiadmin.ADMIN_LIST_DEFAULT'));
        $start = $this->request->get('page', 1);
        $hash = $this->request->get('hash', '');

        if (empty($hash)) {
            return $this->buildFailed(ReturnCode::EMPTY_PARAMS, '缺少必要参数');
        }
        $listObj = (new AdminFields())->where('hash', $hash)->where('type', 1)
            ->paginate(['page' => $start, 'list_rows' => $limit])->toArray();

        $apiInfo = (new AdminList())->where('hash', $hash)->find();

        return $this->buildSuccess([
            'list'     => $listObj['data'],
            'count'    => $listObj['total'],
            'dataType' => $this->dataType,
            'apiInfo'  => $apiInfo
        ]);
    }

    /**
     * 新增字段
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function add(): Response {
        $postData = $this->request->post();

        if(!isset($postData['orgin_id'])){
            $postData['orgin_id'] = order_num();
        }

//        $postData['orgin_id'] = order_num();

        $postDataCopy = $postData;
        $postData['show_name'] = $postData['field_name'];
        $postData['default'] = $postData['defaults'];
        unset($postData['defaults']);
        $res = AdminFields::create($postData);

        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }

        cache('RequestFields:NewRule:' . $postData['hash'], null);
        cache('RequestFields:Rule:' . $postData['hash'], null);
        cache('ResponseFieldsRule:' . $postData['hash'], null);

        $msg = '';
        if(config('app.api_url') == config('app.sys_api_url')) {
            //向对应项目推送修改指令
            $re_url = Db::name('admin_list')->alias('yal')
                ->leftJoin('admin_app yaa', 'yal.app_group_id=yaa.id')
                ->where('yal.hash', $res->hash)
                ->field('yaa.app_url')
                ->find();

            if ($re_url) {
                //顺带刷新对应app的数据
                try {
                    $app_url_a = $re_url['app_url'];
                    refresh_app($re_url['app_url'] . "/admin/Fields/add", $postDataCopy, $app_url_a);
                } catch (Exception $e) {
                    \think\facade\Log::write('编辑接口字段推送远程端失败！');
                    $msg = '编辑接口字段推送远程端失败';
                }
            } else {
                $msg = '没有找到推送项目url';
            }
        }


        return $this->buildSuccess([],$msg);
    }

    /**
     * 字段编辑
     * @return Response
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function edit(): Response {
        $postData = $this->request->post();

        $postDataCopy = $postData;
        $postData['show_name'] = $postData['field_name'];
        $postData['default'] = $postData['defaults'];
        if(config('app.api_url') == config('app.sys_api_url')){
            $check_admin_fields = Db::name('admin_fields')->where('id',$postData['id'])->field('id,orgin_id')->find();
            if(!$check_admin_fields){
                return $this->buildFailed(ReturnCode::INVALID,'查询对应的数据ID错误！');
            }
            $postDataCopy['orgin_id'] = $check_admin_fields['orgin_id'];
        }
        unset($postData['defaults']);
        if(config('app.api_url') != config('app.sys_api_url')){
            $re_admin_fields = AdminFields::where('orgin_id',$postData['orgin_id'])->field('id,orgin_id')->find();
            if($re_admin_fields && $re_admin_fields['orgin_id']){
                $postData['id'] = $re_admin_fields['id'];
                $res = AdminFields::update($postData);
            }else{
                return $this->buildFailed(ReturnCode::INVALID,'不是系统平台但是编辑字段错误！');
            }

        }else{
            $res = AdminFields::update($postData);
        }


        cache('RequestFields:NewRule:' . $postData['hash'], null);
        cache('RequestFields:Rule:' . $postData['hash'], null);
        cache('ResponseFieldsRule:' . $postData['hash'], null);

        if ($res === false) {
            return $this->buildFailed(ReturnCode::DB_SAVE_ERROR);
        }


        $msg = '';
        if(config('app.api_url') == config('app.sys_api_url')){
            $msg = '已推送';
            //向对应项目推送修改指令
            $re_url = Db::name('admin_list')->alias('yal')
                ->leftJoin('admin_app yaa','yal.app_group_id=yaa.id')
                ->where('yal.hash',$res->hash)
                ->field('yaa.app_url')
                ->find();

            if($re_url){
                //顺带刷新对应app的数据
                try{

                    $app_url_a = $re_url['app_url'];
                    refresh_app($re_url['app_url']."/admin/Fields/edit",$postDataCopy,$app_url_a);
                }catch (Exception $e){
                    \think\facade\Log::write('编辑接口字段推送远程端失败！');
                    $msg = '编辑接口字段推送远程端失败!';
                }

            }else{
                $msg = '没有找到推送项目url';
            }

        }


        return $this->buildSuccess([],$msg);
    }

    /**
     * 字段删除
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function del(): Response {
        $param = $this->request->param();
        if(isset($param['id'])){
            //存在ID
            $fieldsInfo = (new AdminFields())->where('id', $param['id'])->find();
        }elseif(isset($param['orgin_id'])){
            $fieldsInfo = (new AdminFields())->where('orgin_id', $param['orgin_id'])->find();
        }else{
            return $this->buildFailed(ReturnCode::EMPTY_PARAMS, '缺少必要参数');
        }
        $msg = '';

        if($fieldsInfo){
            cache('RequestFields:NewRule:' . $fieldsInfo->hash, null);
            cache('RequestFields:Rule:' . $fieldsInfo->hash, null);
            cache('ResponseFieldsRule:' . $fieldsInfo->hash, null);


            //向对应项目推送修改指令
            $re_url = Db::name('admin_list')->alias('yal')
                ->leftJoin('admin_app yaa','yal.app_group_id=yaa.id')
                ->where('yal.hash',$fieldsInfo->hash)
                ->field('yaa.app_url')
                ->find();
            $msg = '';
            if(config('app.api_url') == config('app.sys_api_url')){
                $msg = '已推送';
                if($re_url){
                    //顺带刷新对应app的数据
                    try{
                        $app_url_a = $re_url['app_url'];
                        refresh_app($re_url['app_url']."/admin/Fields/del?orgin_id=".$fieldsInfo['orgin_id'],[],$app_url_a);
                    }catch (Exception $e){
                        \think\facade\Log::write('编辑接口字段推送远程端失败！');
                        $msg = '编辑接口字段推送远程端失败！';
                    }
                }else{
                    $msg = '没有找到推送项目url';
                }
            }


            AdminFields::destroy($fieldsInfo['id']);

        }


        return $this->buildSuccess([],$msg);
    }

    /**
     * 批量上传返回字段
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function upload(): Response {
        $hash = $this->request->post('hash');
        $type = $this->request->post('type');
        $jsonStr = $this->request->post('jsonStr');
        $jsonStr = html_entity_decode($jsonStr);
        $data = json_decode($jsonStr, true);
        if ($data === null) {
            return $this->buildFailed(ReturnCode::EXCEPTION, 'JSON数据格式有误');
        }
        AdminList::update(['return_str' => json_encode($data)], ['hash' => $hash]);
        $this->handle($data['data'], $dataArr);
        $old = (new AdminFields())->where('hash', $hash)->where('type', $type)->select();
        $old = Tools::buildArrFromObj($old);
        $oldArr = array_column($old, 'show_name');
        $newArr = array_column($dataArr, 'show_name');
        $addArr = array_diff($newArr, $oldArr);
        $delArr = array_diff($oldArr, $newArr);
        if ($delArr) {
            $delArr = array_values($delArr);
            (new AdminFields())->whereIn('show_name', $delArr)->delete();
        }
        if ($addArr) {
            $addData = [];
            foreach ($dataArr as $item) {
                if (in_array($item['show_name'], $addArr)) {
                    $item['orgin_id'] = order_num();
                    $addData[] = $item;
                }
            }
            (new AdminFields())->insertAll($addData);
        }

        cache('RequestFields:NewRule:' . $hash, null);
        cache('RequestFields:Rule:' . $hash, null);
        cache('ResponseFieldsRule:' . $hash, null);

        return $this->buildSuccess();
    }

    /**
     * @param $data
     * @param $dataArr
     * @param string $prefix
     * @param string $index
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    private function handle(array $data, &$dataArr, string $prefix = 'data', string $index = 'data'): void {
        if (!$this->isAssoc($data)) {
            $addArr = [
                'field_name' => $index,
                'show_name'  => $prefix,
                'hash'       => $this->request->post('hash'),
                'is_must'    => 1,
                'data_type'  => DataType::TYPE_ARRAY,
                'type'       => $this->request->post('type')
            ];
            $dataArr[] = $addArr;
            $prefix .= '[]';
            if (isset($data[0]) && is_array($data[0])) {
                $this->handle($data[0], $dataArr, $prefix);
            }
        } else {
            $addArr = [
                'field_name' => $index,
                'show_name'  => $prefix,
                'hash'       => $this->request->post('hash'),
                'is_must'    => 1,
                'data_type'  => DataType::TYPE_OBJECT,
                'type'       => $this->request->post('type')
            ];
            $dataArr[] = $addArr;
            $prefix .= '{}';
            foreach ($data as $index => $datum) {
                $myPre = $prefix . $index;
                $addArr = array(
                    'field_name' => $index,
                    'show_name'  => $myPre,
                    'hash'       => $this->request->post('hash'),
                    'is_must'    => 1,
                    'data_type'  => DataType::TYPE_STRING,
                    'type'       => $this->request->post('type')
                );
                if (is_numeric($datum)) {
                    if (preg_match('/^\d*$/', (string)$datum)) {
                        $addArr['data_type'] = DataType::TYPE_INTEGER;
                    } else {
                        $addArr['data_type'] = DataType::TYPE_FLOAT;
                    }
                    $dataArr[] = $addArr;
                } elseif (is_array($datum)) {
                    $this->handle($datum, $dataArr, $myPre, $index);
                } else {
                    $addArr['data_type'] = DataType::TYPE_STRING;
                    $dataArr[] = $addArr;
                }
            }
        }
    }

    /**
     * 判断是否是关联数组（true表示是关联数组）
     * @param array $arr
     * @return bool
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    private function isAssoc(array $arr): bool {
        if (array() === $arr) return false;

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
