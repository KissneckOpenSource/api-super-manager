<?php
declare (strict_types=1);

namespace app\middleware;

use app\model\AdminFields;
use app\util\DataType;
use app\util\ReturnCode;
use think\facade\Cache;
use think\facade\Validate;

class RequestFilter {

    /**
     * 接口请求字段过滤【只验证数据的合法性，不再过滤数据】
     * @param $request
     * @param \Closure $next
     * @return mixed|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function handle($request, \Closure $next) {
        $apiInfo = $request->API_CONF_DETAIL;
        $data = $request->param();


        $data_file = $request->file();

        if(isset($data_file) && !empty($data_file)){
            $data = array_merge($data,$data_file);
        }


        $has = Cache::has('RequestFields:NewRule:' . $apiInfo['hash']);
        if ($has) {
            $newRule = cache('RequestFields:NewRule:' . $apiInfo['hash']);
        } else {
            $rule = (new AdminFields())->where('hash', $apiInfo['hash'])->where('type', 0)->select();
            $newRule = $this->buildValidateRule($rule);
            cache('RequestFields:NewRule:' . $apiInfo['hash'], $newRule);
        }

        if ($newRule) {
            $validate = Validate::rule($newRule);
            if (!$validate->check($data)) {
                return json(['code' => ReturnCode::PARAM_INVALID, 'msg' => $validate->getError(), 'data' => []]);
            }
        }

        return $next($request);
    }

    /**
     * 将数据库中的规则转换成TP_Validate使用的规则数组
     * @param array $rule
     * @return array
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     */
    public function buildValidateRule($rule = []) {
        $newRule = [];
        if ($rule) {
            foreach ($rule as $value) {
                if ($value['is_must']) {
                    $newRule[$value['field_name'] . '|' . $value['info']][] = 'require';
                }
                switch ($value['data_type']) {
                    case DataType::TYPE_INTEGER:
                        $newRule[$value['field_name'] . '|' . $value['info']][] = 'number';
                        if ($value['range']) {
                            $range = htmlspecialchars_decode($value['range']);
                            $range = json_decode($range, true);
                            if (isset($range['min'])) {
                                $newRule[$value['field_name'] . '|' . $value['info']]['egt'] = $range['min'];
                            }
                            if (isset($range['max'])) {
                                $newRule[$value['field_name'] . '|' . $value['info']]['elt'] = $range['max'];
                            }
                        }
                        break;
                    case DataType::TYPE_STRING:
                        if ($value['range']) {
                            $range = htmlspecialchars_decode($value['range']);
                            $range = json_decode($range, true);
                            if (isset($range['min'])) {
                                $newRule[$value['field_name'] . '|' . $value['info']]['min'] = $range['min'];
                            }
                            if (isset($range['max'])) {
                                $newRule[$value['field_name'] . '|' . $value['info']]['max'] = $range['max'];
                            }
                        }
                        break;
                    case DataType::TYPE_ENUM:
                        if ($value['range']) {
                            $range = htmlspecialchars_decode($value['range']);
                            $range = json_decode($range, true);
                            $newRule[$value['field_name'] . '|' . $value['info']]['in'] = implode(',', $range);
                        }
                        break;
                    case DataType::TYPE_FLOAT:
                        $newRule[$value['field_name'] . '|' . $value['info']][] = 'float';
                        if ($value['range']) {
                            $range = htmlspecialchars_decode($value['range']);
                            $range = json_decode($range, true);
                            if (isset($range['min'])) {
                                $newRule[$value['field_name'] . '|' . $value['info']]['egt'] = $range['min'];
                            }
                            if (isset($range['max'])) {
                                $newRule[$value['field_name'] . '|' . $value['info']]['elt'] = $range['max'];
                            }
                        }
                        break;
                    case DataType::TYPE_ARRAY:
                        $newRule[$value['field_name']][] = 'array';
                        if ($value['range']) {
                            $range = htmlspecialchars_decode($value['range']);
                            $range = json_decode($range, true);
                            if (isset($range['min'])) {
                                $newRule[$value['field_name'] . '|' . $value['info']]['min'] = $range['min'];
                            }
                            if (isset($range['max'])) {
                                $newRule[$value['field_name'] . '|' . $value['info']]['max'] = $range['max'];
                            }
                        }
                        break;
                    case DataType::TYPE_MOBILE:
                        $newRule[$value['field_name'] . '|' . $value['info']]['regex'] = '/^1[3456789]\d{9}$/';
                        break;
                }
            }
        }

        return $newRule;
    }
}
