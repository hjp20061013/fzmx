<?php

namespace app\controller;

use app\BaseController;
use think\facade\Cache;
use think\facade\Request;
use think\facade\Session;
use think\facade\View;
use think\facade\Db;
use utils\Utils;

class Api extends BaseController
{

    // 初始化
    protected function initialize()
    {
        $this->params = Request::instance()->param();
        $this->headerParams = Request::header();

        if (is_null($this->headerParams) || empty($this->headerParams) || !is_array($this->headerParams)) {
            $this->apiOutput(DATA_ERROR, '请求数据参数为空或者格式错误');
        }
        //验证签名  appkey,%s;secretKey,%s;ts,%s;
        if (!isset($this->headerParams['appkey']) || empty($this->headerParams['appkey'])) {
            $this->apiOutput(DATA_ERROR, '请求数据参数appkey为空或缺失');
        }
        if (!isset($this->headerParams['ts']) || empty($this->headerParams['ts']) || !is_numeric($this->headerParams['ts'])) {
            $this->apiOutput(DATA_ERROR, '请求数据参数ts为空或缺失');
        }
        $timeAt = time();
        if (abs($timeAt - $this->headerParams['ts']) > 600) {
            $this->apiOutput(DATA_ERROR, 'sign签名已过期');
        }
        if (!isset($this->headerParams['sign']) || empty($this->headerParams['sign'])) {
            $this->apiOutput(DATA_ERROR, '请求数据参数sign为空或缺失');
        }
        $sourceConfig = config('sources');
        if (!isset($sourceConfig[$this->headerParams['appkey']])) {
            $this->apiOutput(DATA_ERROR, '签名失败 code:001');
        }
        //验证签名
        $checkSign = makeSign($this->headerParams['appkey'], $this->headerParams['ts']);
        if ($checkSign != $this->headerParams['sign']) {
            $this->apiOutput(DATA_ERROR, '签名失败 code:002【sign=\'' . $checkSign . '\'】');
        }
        unset($this->headerParams['sign'], $this->headerParams['ts']);
    }

    /**
     * 接收同步信息新增
     */
    public function receive()
    {
        $required = [
            'testingNo',
            'sampleSendCompany',
            'sampleSendDept',
            'productCode',
            'customerName',
            'customerGender',
            'customerMobile',
            'customerAge',
            'bloodDrawTime',
        ];
        $errors = [];
        foreach ($required as $key) {
            //必填项
            if (!isset($this->params[$key])) {
                $errors[] = $key;
            }
        }
        if (!empty($errors)) {
            $this->apiOutput(DATA_ERROR, '缺少参数 ' . implode(',', $errors));
        }
        $info = Db::table('fzmx_report')->where('test_no', $this->params['testingNo'])->find();
        if( $info ){
            $this->apiOutput(COMMON_ERROR_TIP,"报告已存在");
        }
        //注册
        $data = [
            'test_no'              => $this->params['testingNo'] ?? '',
            'test_company'         => $this->params['sampleSendCompany'] ?? '',
            'test_type'            => $this->params['sampleSendDept'] ?? '',
            'test_dept'            => $this->params['productCode'] ?? '',
            'test_in_no'           => $this->params['inNo'] ?? '',
            'test_bed_no'          => $this->params['bedNo'] ?? '',
            'test_product_code'    => $this->params['productCode'] ?? '', //检测类型 92=医疗型，93=精准型
            'test_customer_name'   => $this->params['customerName'] ?? '',
            'test_customer_gender' => $this->params['customerGender'] ?? '',
            'test_customer_age'    => $this->params['customerAge'] ?? '',
            'test_customer_mobile' => $this->params['customerMobile'] ?? '',
            'test_blood_draw_time' => $this->params['bloodDrawTime'] ?? '',
            'source'               => $this->headerParams['appkey'],//信息来源
            'created_at'           => time(),
        ];
        $id = Db::table('fzmx_report')->insertGetId($data);
        if (!$id) {
            $this->apiOutput(DATA_ERROR, '提交失败');
        }
        return $this->apiOutput(SUCCESS, []);
    }

    /**
     * 接收同步信息更新
     */
    public function receiveUpdate()
    {
        $required = [
            'testingNo',
            'sampleSendCompany',
            'sampleSendDept',
            'productCode',
            'customerName',
            'customerGender',
            'customerMobile',
            'customerAge',
            'bloodDrawTime',
            'sampleReceiveTime',
            'resultUpdatedAt',
        ];
        $errors = [];
        foreach ($required as $key) {
            //必填项
            if (!isset($this->params[$key])) {
                $errors[] = $key;
            }
        }
        if (!empty($errors)) {
            $this->apiOutput(DATA_ERROR, '缺少参数 ' . implode(',', $errors));
        }
        $info = Db::table('fzmx_report')->where('test_no', $this->params['testingNo'])->find();
        if( !$info ){
            $this->apiOutput(COMMON_ERROR_TIP,"报告不存在");
        }
        //
        $data = [
            'test_company'             => $this->params['sampleSendCompany'] ?? '',
            'test_type'                => $this->params['sampleSendDept'] ?? '',
            'test_dept'                => $this->params['productCode'] ?? '',
            'test_in_no'               => $this->params['inNo'] ?? '',
            'test_bed_no'              => $this->params['bedNo'] ?? '',
            'test_product_code'        => $this->params['productCode'] ?? '', //检测类型 92=医疗型，93=精准型
            'test_customer_name'       => $this->params['customerName'] ?? '',
            'test_customer_gender'     => $this->params['customerGender'] ?? '',
            'test_customer_age'        => $this->params['customerAge'] ?? '',
            'test_customer_mobile'     => $this->params['customerMobile'] ?? '',
            'test_blood_draw_time'     => $this->params['bloodDrawTime'] ?? '',
            'test_sample_receive_time' => $this->params['sampleReceiveTime'] ?? '',
            'test_result_updated_at'   => $this->params['resultUpdatedAt'] ?? '',
        ];
        $id = Db::table('fzmx_report')->where('test_no', $this->params['testingNo'])->update($data);
        if (!$id) {
            $this->apiOutput(DATA_ERROR, '提交失败');
        }
        return $this->apiOutput(SUCCESS, []);
    }

}
