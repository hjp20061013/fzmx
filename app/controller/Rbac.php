<?php
/**
 * Created by PhpStorm.
 * User: jok
 * Date: 2018/3/26
 * Time: 上午10:59
 */

namespace app\controller;

use app\exception\BussinessException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Request;
use think\facade\Session;
use think\facade\Cache;
use think\facade\Env;
use think\facade\View;
use think\route\Url;

/**
 * 后台权限管理控制器
 */
class Rbac extends AdminBase
{

    protected $page = 1;
    protected $page_size = 10;

    /**
     * 角色列表
     */
    public function role()
    {
        $adminInfo = $this->adminAuthInfo();
        $page = input('page', 1);
        $page_size = input('limits', 10);

        $total_count = Db::table('base_role')->count();
        //总页数
        $total_page = ceil($total_count / $page_size);

        $role_list = Db::table('base_role')
            ->field('*')
            ->page($page, $page_size)
            ->order('created_at desc')
            ->select();
        $page_url = url('Rbac/role', ['page' => '__page__', 'limits' => '__limits__'])->build();
        return view('role', [
            'page'        => $page,
            'total_count' => $total_count,
            'page_size'   => $page_size,
            'total_page'  => $total_page,
            'role_list'   => $role_list,
            'page_url'    => $page_url,
        ]);
    }

    /**
     * 添加、修改角色
     */
    public function insertUpdateRole()
    {
        $adminInfo = $this->adminAuthInfo();

        //节点信息
        $node_list = $this->_node_tree();

        $role_id = input('param.id');
        if ($role_id) { //修改
            $role_info = Db::table('base_role')->where('id', $role_id)->find();
            if (!$role_info) {
                throw new BussinessException("角色不存在");
            }
            View::assign('role_info', $role_info);
            //角色权限
            $auth_list = Db::table('base_auth')->where('role_id', $role_id)->select()->toArray();
            $auth_list = array_combine(array_column($auth_list, 'node_id'), $auth_list);
            View::assign('auth_list', $auth_list);
        }

        View::assign('node_list', $node_list);
        return View::fetch('role_info');
    }

    /**
     * 添加、修改角色post
     */
    public function doInsertUpdateRole()
    {
        $param = input('post.');
        $role_id = $param['role_id'];
        $name = $param['name'];
        $status = isset($param['status']) ? $param['status'] : 1;
        $node_id = isset($param['node_id']) ? $param['node_id'] : [];
        $remark = $param['remark'];
        $time_at = time();

        $data = [
            'name'   => $name,
            'status' => $status,
            'remark' => $remark
        ];
        if ($role_id) { //修改
            $data['updated_at'] = $time_at;
            Db::table('base_role')->where('id', $role_id)->update($data);
            Db::table('base_auth')->where('role_id', $role_id)->delete();
        } else {  //新增
            $data['created_at'] = $time_at;
            $role_id = Db::table('base_role')->insertGetId($data);
        }
        //插入权限信息
        $auth_list = [];
        if ($node_id) {
            foreach ($node_id as $item) {
                $auth_list[] = [
                    'role_id' => $role_id,
                    'node_id' => $item
                ];
            }
        }
        Db::table('base_auth')->insertAll($auth_list);
        return $this->output(0, []);
    }

    /**
     * 删除角色
     */
    public function delRole()
    {
        $param = input('post.');
        $role_ids = $param['role_ids'];
        if (empty($role_ids)) {
            return $this->output(COMMON_ERROR_TIP, '请选择要删除的数据');
        }
        //删除角色
        Db::table('base_role')->whereIn('id', $role_ids)->delete();
        //删除角色对应的权限
        Db::table('base_auth')->whereIn('role_id', $role_ids)->delete();
        return $this->output(0, []);
    }

    /**
     * 节点管理
     */
    public function node()
    {
        $page = input('page', 1);
        $page_size = input('limits', 10);
        $pid = input('pid', 0);

        //父节点信息
        if ($pid > 0) {
            $node_info = Db::table('base_node')->where('id', $pid)->find();
            View::assign('node_info', $node_info);
        }

        $total_count = Db::table('base_node')->where('pid', $pid)->count();
        //总页数
        $total_page = ceil($total_count / $page_size);

        $node_list = Db::table('base_node')
            ->field('*')
            ->where('pid', $pid)
            ->order('created_at desc, id desc')
            ->page($page, $page_size)
            ->order('created_at desc')
            ->select();
        $page_url = url('Rbac/node', ['pid' => $pid, 'page' => '__page__', 'limits' => '__limits__'])->build();
        View::assign('page', $page);
        View::assign('total_count', $total_count);
        View::assign('page_size', $page_size);
        View::assign('total_page', $total_page);
        View::assign('node_list', $node_list);
        View::assign('page_url', $page_url);
        View::assign('pid', $pid);

        return View::fetch('node');
    }

    /**
     * 添加、修改节点
     */
    public function nodeInfo()
    {
        $node_id = input('id');
        $pid = input('pid');
        if ($node_id) {
            $node_info = Db::table('base_node')->where('id', $node_id)->find();
            if (!$node_info) {
                throw new BussinessException("节点数据不存在");
            }
            View::assign('node_info', $node_info);
        }
        View::assign('pid', $pid);
        return View::fetch('node_info');
    }

    /**
     * 添加、修改节点post
     */
    public function doNodeInfo()
    {
        $id = input('post.id', 0);
        $pid = input('post.pid', 0);
        $title = input('title');
        $iconfont = htmlspecialchars_decode(input('iconfont'));
        $code = input('code');
        $sort = input('sort');
        $show = input('show');
        $module = input('module', 'admin');
        $time_at = time();
        //验证参数 TODO

        $level = $pid > 0 ? 1 : 0;

        $data = [
            'title'    => $title,
            'pid'      => $pid,
            'iconfont' => $iconfont,
            'code'     => $code,
            'level'    => $level,
            'sort'     => $sort,
            'show'     => $show,
            'module'   => $module
        ];
        if ($id) {    //修改
            $node_info = Db::table('base_node')->where('id', $id)->find();
            if (!$node_info) {
                return $this->output(5000, '节点数据不存在');
            }
            $data['updated_at'] = $time_at;
            $rs = Db::table('base_node')->where('id', $id)->update($data);
        } else {  //新增
            $data['pid'] = $pid;
            $data['created_at'] = $time_at;
            $rs = Db::table('base_node')->insertGetId($data);
        }
        if ($rs) {
            return $this->output(0, []);
        }
        return $this->output(5001, '操作失败');
    }

    /**
     * 删除节点
     */
    public function delNode()
    {
        $param = input('post.');
        $node_ids = $param['node_ids'];
        if (empty($node_ids)) {
            return $this->output(COMMON_ERROR_TIP, '请选择要删除的数据');
        }
        //删除节点
        Db::table('base_node')->whereIn('id', $node_ids)->delete();
        //删除节点对应的子节点
        Db::table('base_auth')->whereIn('node_id', $node_ids)->delete();
        return $this->output(0, []);
    }

    /**
     * 管理员
     */
    public function user()
    {
        $page = input('page', 1);
        $page_size = input('limits', 10);

        $total_count = Db::table('base_user')->count();
        //总页数
        $total_page = ceil($total_count / $page_size);

        $user_list = Db::table('base_user')
            ->alias('u')
            ->join(['base_user_role' => 'ur'], 'u.id = ur.user_id', 'LEFT')
            ->join(['base_role' => 'r'], 'ur.role_id = r.id', 'LEFT')
            ->field('u.*, r.name')
            ->page($page, $page_size)
            ->order('u.created_at desc')
            ->select();
        $page_url = url('Rbac/user', ['page' => '__page__', 'limits' => '__limits__'])->build();
        View::assign('page', $page);
        View::assign('total_count', $total_count);
        View::assign('page_size', $page_size);
        View::assign('total_page', $total_page);
        View::assign('user_list', $user_list);
        View::assign('page_url', $page_url);
        return View::fetch('user');
    }

    /**
     * 管理员详情
     */
    public function userInfo()
    {
        $id = input('id');
        //权限信息
        $role_list = Db::table('base_role')->where('pid', 0)->select();
        if ($id) {
            $up_user_info = Db::table('base_user')->where('id', $id)->find();
            if (!$up_user_info) {
                throw new BussinessException("管理员数据不存在");
            }
            //此用户所属权限组
            $user_role_info = Db::table('base_user_role')->where('user_id', $id)->find();
            View::assign('up_user_info', $up_user_info);
            View::assign('user_role_info', $user_role_info);
        }
        View::assign('role_list', $role_list);
        return View::fetch('user_info');
    }

    /**
     * 新增,修改管理员
     */
    public function doUserInfo()
    {
        $id = input('post.id');
        $username = input('post.username');
        $passwd = input('post.passwd');
        $passwd_confirm = input('post.passwd_confirm');
        $realname = input('post.realname');
        $status = input('post.status');
        $role_id = input('post.role_id');
        $time_at = time();

        $data = [
            'username' => $username,
            'realname' => $realname,
            'status'   => $status
        ];
        if ($id) {
            $data['updated_at'] = $time_at;
            $rs = Db::table('base_user')->where('id', $id)->update($data);
            if ($rs) {//权限组
                Db::table('base_user_role')->where('user_id', $id)->update([
                    'role_id' => $role_id
                ]);
                return $this->output(0, []);
            }
        } else {
            if (empty($passwd) || $passwd != $passwd_confirm) {
                return $this->output(COMMON_ERROR_TIP, '两次输入的密码不一致');
            }
            $salt = mt_rand(1000, 9999);
            $data['salt'] = $salt;
            $data['password'] = md5($passwd . $salt);
            $data['created_at'] = $time_at;


            $id = Db::table('base_user')->insertGetId($data);
            Db::table('base_user_role')->insertGetId([
                'user_id' => $id,
                'role_id' => $role_id
            ]);
            return $this->output(0, []);
        }
        return $this->output(5000, '操作失败');
    }

    /**
     * 删除管理员
     */
    public function delUser()
    {
        $user_id = input('post.user_id');
        if (empty($user_id)) {
            return $this->output(COMMON_ERROR_TIP, '请选择要删除的数据');
        }
        //删除用户
        Db::table('base_user')->where('id', $user_id)->delete();
        //删除用户权限
        Db::table('base_user_role')->where('user_id', $user_id)->delete();
        return $this->output(0, []);
    }

    /**
     * 获取节点树
     */
    private function _node_tree()
    {
        //一级
        $nodes = Db::table('base_node')
            ->where('pid', 0)
            ->order('sort DESC')
            ->select()->toArray();
        foreach ($nodes as $key => $node) {
            //二级
            $sub_nodes = Db::table('base_node')
                ->where('pid', $node['id'])
                ->order('sort DESC')
                ->select();
            $nodes[$key]['sub_nodes'] = $sub_nodes;
        }
        return $nodes;
    }


}
