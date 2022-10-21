<?php

namespace app\controller;

use app\BaseController;
use think\Exception;
use think\facade\Cache;
use think\facade\Session;
use think\facade\Request;
use think\facade\Db;
use think\helper\Str;
use utils\Utils;

class Admin extends AdminBase
{
    /**
     * 后台首页
     */
    public function index()
    {
        $adminInfo = $this->adminAuthInfo();
        return view();
    }

    /**
     * 后台首页
     */
    public function main()
    {
        return view();
    }

    /**
     * 后台首页
     */
    public function login()
    {
        return view();
    }

    /**
     * 执行登陆
     */
    public function doLogin()
    {
        $username = input('post.username');
        $password = input('post.password');
        $ip = Request::ip();
        $time_at = time();
        if (!$username || !$password) {
            return $this->output(COMMON_ERROR_TIP, '账号或密码错误!');
        }
        $user_model = Db::table('base_user');
        $userInfo = $user_model->where("username = '{$username}'")->find();

        if (!$userInfo) {
            return $this->output(COMMON_ERROR_TIP, '账号不存在!');
        }
        if (md5($password . $userInfo['salt']) != $userInfo['password']) {
            return $this->output(COMMON_ERROR_TIP, '密码错误!');
        }

        if ($userInfo['status'] == 0) {
            return $this->output(COMMON_ERROR_TIP, '账号被冻结!');
        }
        $data = $this->_getAuthList($userInfo['id']);
        Db::table('base_user')
            ->where('id', $userInfo['id'])
            ->update([
                'last_login_ip'   => $ip,
                'last_login_time' => $time_at
            ]);

        $sData = json_encode(array_merge($userInfo, $data));
        Session::set('adminInfo', $sData);
        return $this->output(0, '登陆成功!');
    }
}
