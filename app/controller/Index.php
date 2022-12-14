<?php

namespace app\controller;

use app\BaseController;
use app\exception\UnauthorizedException;
use think\Exception;
use think\facade\Cache;
use think\facade\Request;
use think\facade\Session;
use think\facade\View;
use think\facade\Db;
use think\helper\Str;
use utils\Utils;

class Index extends BaseController
{

    /**
     * 首页
     */
    public function index()
    {
        $userInfo = session('userInfo');
        if (!empty($userInfo)) {
            $reportList = Db::table('fzmx_report')
                ->where('test_customer_mobile', $userInfo['mobile'])
                ->order('id','desc')
                ->select();
            return view('index', [
                'title'      => '报告列表',
                'userInfo'   => $userInfo,
                'reportList' => $reportList,
            ]);
            //return "欢迎，{$userInfo['name']},<a href='./index/logout.html'>退出</a><br />";
        } else {
            return redirect('Index/login');
        }
    }

    /**
     * 登陆
     */
    public function login()
    {
        return view('login', [
            'title' => '登录'
        ]);
    }


    /**
     * 执行登陆
     */
    public function doLogin()
    {
        $mobile = input('post.mobile');
        $password = input('post.password');
        $ip = Request::ip();
        if (!$mobile || !$password) {
            $this->output(COMMON_ERROR_TIP, '账号或密码错误!');
        }
        $userInfo = Db::table('fzmx_member')->where('mobile', $mobile)->find();
        if (!$userInfo) {
            $this->output(COMMON_ERROR_TIP, '账号不存在!');
        }
        //验证密码
        if (password_verify($password, $userInfo['password']) === false) {
            $this->output(COMMON_ERROR_TIP, '密码错误!');
        }
        Db::table('fzmx_member')->where('id', $userInfo['id'])->update([
            'last_login_ip' => $ip,
            'last_login_at' => time(),
        ]);
        session('userInfo', $userInfo);
        $this->output(SUCCESS, '登陆成功!');
    }

    /**
     * 登出
     */
    public function logout()
    {
        Session::clear();
        return redirect('/');
    }

    /**
     * 重置密码
     */
    public function passreset()
    {
        return view('passreset', [
            'title' => '重置密码'
        ]);
    }

    /**
     * 发送验证码
     */
    public function sendVerifyCode()
    {
        $code = mt_rand(1000, 9999);
        $mobile = input('post.mobile');
        if (!preg_match("/^1[0123456789]{1}\d{9}$/", $mobile)) {
            $this->output(COMMON_ERROR_TIP, '请输入正确的手机号');
        }
        try {
            //Utils::sendCode($mobile, $code);
            Cache::set('SMS:VERIFYCODE:' . $mobile, $code, 600);//10分钟内有效
        } catch (\Exception $e) {
            $this->output(COMMON_ERROR_TIP, '操作失败:' . $e->getMessage());
        }
        $this->output(SUCCESS, ['code' => $code]);

    }

    /**
     * 重置密码
     */
    public function passwordReset()
    {
        $params = input('post.');
        //参数验证
        if (!isset($params['mobile']) || empty($params['mobile'])) {
            $this->output(COMMON_ERROR_TIP, "手机号不能为空");
        }
        $infoObject = Db::table('fzmx_member')->where('mobile', $params['mobile']);
        if ($infoObject->count() <= 0) {
            $this->output(COMMON_ERROR_TIP, "手机号不存在");
        }
        if (!isset($params['identifier_code']) || $params['identifier_code'] !== Cache::get('SMS:VERIFYCODE:' . $params['mobile'])) {
            $this->output(COMMON_ERROR_TIP, '验证码不正确');
        }
        if (!isset($params['password']) || empty($params['password'])) {
            $this->output(COMMON_ERROR_TIP, "密码不能为空");
        }
        $rs = $infoObject->update([
            'password' => password_hash($params['password'], PASSWORD_DEFAULT)
        ]);
        if (!$rs) {
            $this->output(COMMON_ERROR_TIP, "操作失败");
        }
        Cache::delete('SMS:VERIFYCODE:' . $params['mobile']);
        return $this->output(SUCCESS, []);

    }

    /**
     * 注册
     */
    public function register()
    {
        return view('register', [
            'title' => '用户注册'
        ]);

    }

    /**
     * 注册
     */
    public function doRegister()
    {
        $params = input('post.');
        //参数验证
        if (!isset($params['mobile']) || empty($params['mobile'])) {
            $this->output(COMMON_ERROR_TIP, "手机号不能为空");
        }
        if (!isset($params['identifier_code']) || $params['identifier_code'] !== Cache::get('SMS:VERIFYCODE:' . $params['mobile'])) {
            $this->output(COMMON_ERROR_TIP, '验证码不正确');
        }
        if (!isset($params['password']) || empty($params['password'])) {
            $this->output(COMMON_ERROR_TIP, "密码不能为空");
        }
        //是否已经存在此用户
        $has = Db::table('fzmx_member')->where('mobile', $params['mobile'])->count();
        if ($has > 0) {
            $this->output(COMMON_ERROR_TIP, "此手机已经注册");
        }
        //注册
        $data = [
            'mobile'     => $params['mobile'],
            'password'   => password_hash($params['password'], PASSWORD_DEFAULT),
            'gender'     => $params['gender'],
            'name'       => $params['name'],
            'created_at' => time(),
        ];
        $uid = Db::name('fzmx_member')->insertGetId($data);
        if (!$uid) {
            $this->output(COMMON_ERROR_TIP, "注册失败");
        }
        Cache::delete('SMS:VERIFYCODE:' . $params['mobile']);
        //执行登陆
        $userInfo = Db::table('fzmx_member')->where('mobile', $params['mobile'])->find();
        session('userInfo', $userInfo);
        return $this->output(SUCCESS, []);
    }

    /**
     * 提交报告
     */
    public function postReport()
    {
        $userInfo = session('userInfo');
        if (!$userInfo) {
            return redirect('/');
        }
        return view('postreport', [
            'title'    => '提交报告',
            'userInfo' => $userInfo
        ]);

    }

    /**
     * post提交报告
     */
    public function doPostReport()
    {
        $params = input('post.');
        //检测类型 92=医疗型，93=精准型
        $testProductCode = $params['test_product_code'] == '医疗型' ? 92 : 93;
        //采样日期
        $testBloodDrawTime = @strtotime($params['test_blood_draw_time']);
        $testBloodDrawTime = date('Y-m-d H:i:s', $testBloodDrawTime);
        //
        $data = [
            'test_no'              => $params['test_no'] ?? '',
            'test_company'         => $params['test_company'] ?? '',
            'test_type'            => $params['test_type'] ?? '',
            'test_dept'            => $params['test_dept'] ?? '',
            'test_in_no'           => $params['test_in_no'] ?? '',
            'test_bed_no'          => $params['test_bed_no'] ?? '',
            'test_product_code'    =>  $testProductCode,
            'test_customer_name'   => $params['test_customer_name'] ?? '',
            'test_customer_gender' => $params['test_customer_gender'] ?? '',
            'test_customer_age'    => $params['test_customer_age'] ?? '',
            'test_customer_mobile' => $params['test_customer_mobile'] ?? '',
            'test_blood_draw_time' => $testBloodDrawTime,
            'created_at'           => time(),
        ];

        try {
            Db::table('fzmx_report')->startTrans();
            $id = Db::table('fzmx_report')->insertGetId($data);
            if (!$id) {
                $this->output(COMMON_ERROR_TIP, "提交失败");
            }
            //提交到远程
            $appKey = env('source.appKey', '9c3a20b9f0d2');
            $params = [
                'testingNo'         => $data['test_no'],
                'sampleSendCompany' => $data['test_company'],
                'sampleSendDept'    => $data['test_dept'],
                'inNo'              => $data['test_in_no'],
                'bedNo'             => $data['test_bed_no'],
                'productCode'       => $data['test_product_code'],
                'customerName'      => $data['test_customer_name'],
                'customerGender'    => $data['test_customer_gender'],
                'customerMobile'    => $data['test_customer_mobile'],
                'customerAge'       => $data['test_customer_age'],
                'bloodDrawTime'     => $data['test_blood_draw_time'],
                'sampleSendSource'  => $data['test_type'],
            ];
            $result = apiCall($appKey, '/wxp/api/testing/sample/receive', $params);
            if( !$result || !isset($result['code']) ){
                throw new \Exception('提交失败');
            }
            if( $result['code'] > 0 ){
                throw new \Exception($result['msg']);
            }
            Db::table('fzmx_report')->commit();
        } catch (\Exception $e) {
            Db::table('fzmx_report')->rollback();
            $this->output(COMMON_ERROR_TIP, $e->getMessage());
        }
        return $this->output(SUCCESS, []);
    }

    /**
     * 报告列表
     */
    public function reportList()
    {
        $userInfo = session('userInfo');
        if (!$userInfo) {
            return redirect('/');
        }
        $reportList = Db::table('fzmx_report')->where('test_customer_mobile', $userInfo['mobile'])->select();
        return view('reportlist', [
            'title'      => '报告列表',
            'reportList' => $reportList,
        ]);
    }

    /**
     * 申请发票
     */
    public function requestInvoice()
    {
        $userInfo = session('userInfo');
        return view('requestnvoice', [
            'title'    => '申请发票',
            'userInfo' => $userInfo,
        ]);
    }

    /**
     * 执行申请发票
     */
    public function doRequestInvoice()
    {
        $userInfo = session('userInfo');
        if (!$userInfo) {
            $this->output(COMMON_ERROR_TIP, "请先登陆");
        }
        $params = input('post.');
        $data = [
            'type'       => $params['type'] ?? 0,
            'name'       => $params['name'] ?? '',
            'mobile'     => $params['mobile'] ?? '',
            'address'    => $params['address'] ?? '',
            'created_at' => time(),
        ];
        $id = Db::name('fzmx_invoice')->insertGetId($data);
        if (!$id) {
            $this->output(COMMON_ERROR_TIP, "提交失败");
        }
        return $this->output(SUCCESS, []);
    }

    /**
     * 查看报告
     */
    public function showReport()
    {
        $userInfo = session('userInfo');
        if (!$userInfo) {
            throw new UnauthorizedException('未登陆或没有权限');
        }
        $id = input('id');
        $info = Db::name('fzmx_report')->find($id);
        if (!$info) {
            $this->output(COMMON_ERROR_TIP, "数据不存在");
        }
        $appKey = env('source.appKey', '9c3a20b9f0d2');
        $params = ['testingNo' => $info['test_no']];
        try {
            $result = apiCall($appKey, '/wxp/api/testing/report', $params, false);
            if (Str::length($result) < 100) {
                throw new \Exception($result);
            }
        } catch (\Exception $e) {
            $this->output(COMMON_ERROR_TIP, "操作失败，{$e->getMessage()}");
        }
        $path = public_path('download/') . $info['test_no'] . ".pdf";
        $fp = fopen($path, 'w');
        fwrite($fp, $result);
        fclose($fp);
        if( !file_exists($path) ){
            $this->output(COMMON_ERROR_TIP, "操作失败}");
        }
        $this->output(SUCCESS, [
            'test_no' => $info['test_no'],
            'url' => url('Index/downloadReport', ['test_no' => $info['test_no']])->build(),
        ]);
    }

    /**
     * 查看报告
     */
    public function downloadReport()
    {
        $test_no = input('test_no');
        $path = public_path('download/') . $test_no . ".pdf";
        return download($path, $test_no . "检测报告.pdf");
    }
}
