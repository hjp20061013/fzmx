<?php

namespace app\controller;

use app\BaseController;
use think\facade\Cache;
use think\facade\Request;
use think\facade\Session;
use think\facade\View;
use think\facade\Db;
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
            return view('index', [
                'title'    => 'index',
                'userInfo' => $userInfo,
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
        $userInfo = Db::table('tmp1')->where('mobile', $mobile)->find();
        if (!$userInfo) {
            $this->output(COMMON_ERROR_TIP, '账号不存在!');
        }
        //验证密码
        if (password_verify($password, $userInfo['password']) === false) {
            $this->output(COMMON_ERROR_TIP, '密码错误!');
        }
        Db::table('tmp1')->where('id', $userInfo['id'])->update([
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
        $infoObject = Db::table('tmp1')->where('mobile', $params['mobile']);
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
        $has = Db::table('tmp1')->where('mobile', $params['mobile'])->count();
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
        $uid = Db::name('tmp1')->insertGetId($data);
        if (!$uid) {
            throw new \Exception("注册失败");
        }
        Cache::delete('SMS:VERIFYCODE:' . $params['mobile']);
        //执行登陆
        $userInfo = Db::table('tmp1')->where('mobile', $params['mobile'])->find();
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
        //注册
        $data = [
            'test_no'              => $params['test_no'] ?? '',
            'test_company'         => $params['test_company'] ?? '',
            'test_type'            => $params['test_type'] ?? '',
            'test_dept'            => $params['test_dept'] ?? '',
            'test_in_no'           => $params['test_in_no'] ?? '',
            'test_bed_no'          => $params['test_bed_no'] ?? '',
            'test_product_code'    => $params['test_product_code'] ?? '',
            'test_customer_name'   => $params['test_customer_name'] ?? '',
            'test_customer_gender' => $params['test_customer_gender'] ?? '',
            'test_customer_age'    => $params['test_customer_age'] ?? '',
            'test_customer_mobile' => $params['test_customer_mobile'] ?? '',
            'test_blood_draw_time' => $params['test_blood_draw_time'] ?? '',
            'created_at'           => time(),
        ];
        $id = Db::name('report')->insertGetId($data);
        if (!$id) {
            throw new \Exception("提交失败");
        }
        return $this->output(SUCCESS, []);
    }

    /**
     * 报告列表
     */
    public function reportList()
    {
        $userInfo = session('userInfo');
        $reportList = Db::table('report')->where('test_customer_mobile', $userInfo['mobile'])->select();
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
            throw new \Exception("请先登陆");
        }
        $params = input('post.');
        $data = [
            'type'       => $params['type'] ?? 0,
            'name'       => $params['name'] ?? '',
            'mobile'     => $params['mobile'] ?? '',
            'address'    => $params['address'] ?? '',
            'created_at' => time(),
        ];
        $id = Db::name('tmp2')->insertGetId($data);
        if (!$id) {
            throw new \Exception("提交失败");
        }
        return $this->output(SUCCESS, []);
    }

}
