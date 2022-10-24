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

    /**
     * 登陆用户信息
     */
    public function userInfo()
    {
        $adminInfo = $this->adminAuthInfo();
        $adminInfo = Db::table('base_user')->where('id', $adminInfo['id'])->find();
        if( Request::isPost() ){
            $realname       = input('post.realname');
            $telphone       = input('post.telphone');
            $orgin_passwd   = input('post.orgin_passwd');
            $new_passwd     = input('post.new_passwd');
            $comfirm_passwd = input('post.comfirm_passwd');
            $update_data = [];
            //修改真实姓名
            if( !empty($realname) && $realname != $adminInfo['realname'] ){
                $update_data['realname'] = $realname;
            }

            //修改手机
            if( !empty($telphone) && $telphone != $adminInfo['telphone'] ){
                $update_data['telphone'] = $telphone;
            }

            //密码
            if(!empty($orgin_passwd) || !empty($new_passwd) || !empty($comfirm_passwd)){
                if( md5($orgin_passwd.$adminInfo['salt']) != $adminInfo['password'] ){
                    return $this->output(COMMON_ERROR_TIP, '原始密码错误');
                }

                if( empty($new_passwd) || $new_passwd != $comfirm_passwd ){
                    return $this->output(20001, '新密码为空或两次密码不一致');
                }
                $salt = mt_rand(1000, 9999);
                $update_data['salt'] = $salt;
                $update_data['password'] = md5($new_passwd.$salt);
            }
            if( !empty($update_data) ){
                Db::table('base_user')->where('id', $adminInfo['id'])->update($update_data);
            }
            return $this->output(0, $update_data);
        }else{
            return view('user_info', [
                'adminInfo' => $adminInfo
            ]);
        }
    }

    /**
     * 刷新权限
     */
    public function refresh(){
        $adminInfo = $this->adminAuthInfo();
        $info = Db::table('base_user')->where('id', $adminInfo['id'])->find();
        $data = $this->_getAuthList($adminInfo['id']);
        $sData = json_encode(array_merge($adminInfo, $data));
        Session::set('adminInfo', $sData);
        return $this->output(0, []);
    }

    /**
     * 登出
     */
    public function logOut(){
        $adminInfo = $this->adminAuthInfo();
        Session::clear();
        return redirect('Admin/login');
    }


}
