<?php

namespace app\controller;

use app\BaseController;
use app\exception\UnauthorizedException;
use think\Exception;
use think\exception\HttpResponseException;
use think\facade\Cache;
use think\facade\Request;
use think\facade\Session;
use think\facade\View;
use think\facade\Db;
use think\helper\Str;
use utils\Utils;

class AdminBase extends BaseController
{
    // 初始化
    protected function initialize()
    {
        //白名单
        $controller_name = strtolower(Request::controller());
        $action_name = strtolower(Request::action());

        $can_access = false;

        //值验证到controller级别，所有action都通过
        $checkC = strtolower($controller_name . '.' . '*');
        $checkM = strtolower($controller_name . '.' . 'manage');
        $checkCA = strtolower($controller_name . '.' . $action_name);
        //mtrace([$checkC, $checkM, $checkCA]);
        //不需要登陆的路由
        $nologin = [
            'admin.login',
            'admin.dologin'
        ];
        if (!in_array($checkCA, $nologin)) {
            //用户信息
            $adminInfo = $this->adminAuthInfo();
            $authNodes = $adminInfo['auth_nodes'];
            //mtrace([$authNodes, $checkCA, $checkC]);

            if (isset($authNodes[$checkC]) || isset($authNodes[$checkCA]) || isset($authNodes[$checkM])) {
                $can_access = true;
            }
            // 权限判断
            if ($can_access === false) {
                if (Request::isAjax()) {
                    throw new \Exception('没有权限', 99);
                } else {
                    return redirect('Admin/login');
                }
            }
        }
    }

    /*
     * 登陆信息验证
    */
    public function adminAuthInfo(){
        $adminInfo = session('adminInfo');
        $adminInfo = json_decode($adminInfo, true);

        if( isset($adminInfo['id']) && $adminInfo['id'] > 0 ){
            View::assign('adminInfo',$adminInfo);
            return $adminInfo;
        }else{
            throw new UnauthorizedException('未登陆或没有权限');
        }
    }


    /**
     * 生成用户权限信息
     */
    public function _getAuthList($user_id) {
        $authList = [
            'auth_roles' => [],
            'auth_codes' => [],
            'auth_nodes' => [],
            'menu_node_tree' => [],
        ];
        // 获取该用户所属的状态正常的角色列表
        $sql = "SELECT role.id,role.name,role.pid,role.remark from base_user_role a
                LEFT JOIN base_role role ON a.role_id = role.id
                WHERE a.status = 1 and role.status = 1 and a.user_id = {$user_id}";
        $userRoleList = Db::query($sql);
        if (!$userRoleList) {
            return $authList;
        }
        $authList['auth_roles'] = $userRoleList;

        // 获取为所属角色分配的(正常的节点的)授权信息列表
        # --- 所有直接授权的节点 ---
        $role_ids = [];
        foreach ($userRoleList as $val) {
            $role_ids[] = $val['id'];
        }
        $role_ids = implode(',', $role_ids);
        $sql = "SELECT node.id,node.pid,node.title,node.iconfont,
                    node.code,node.level,node.show,node.sort,node.module,
                    max(auth.can_access) can_access,
                    max(auth.can_export) can_export,
                    max(auth.can_seeall) can_seeall,
                    max(auth.can_areaall) can_areaall
                FROM base_auth auth LEFT JOIN base_node node ON auth.node_id = node.id
                WHERE auth.role_id in ({$role_ids}) and node.status = 1
                GROUP BY auth.node_id ORDER BY node.sort DESC,node.created_at DESC";
        $nodeList = Db::query($sql);
//        print_r($nodeList);exit();
        if (!$nodeList) {
            return $authList;
        }

        // 菜单节点树
        $menu_node_tree = [];
        // 授权节点(包含直接授权和继承授权)
        $auth_nodes = [];

        $temp = [];
        $node_ids = [];
        $level_0_ids = $level_1_ids = [];
        foreach ($nodeList as $node) {
            $node['auth_status'] = 'direct'; // 直接授权节点
            $node['code']        = strtolower($node['code']);
            $temp[$node['id']]   = $node;
            $node['level'] == 0 && $level_0_ids[] = $node['id'];
            $node['level'] == 1 && $level_1_ids[] = $node['id'];
        }
        $nodeList = $temp;

        // 从直接授权的顶级节点向下扩充二级节点
        if ($level_0_ids) {
            $all_ids = implode(',', $node_ids);
            $level_0_ids = implode(',', $level_0_ids);
            $sql = "SELECT id,pid,title,iconfont,code,level,`show`,`sort`,module
                    FROM base_node WHERE pid IN ({$level_0_ids})
                    AND status = 1";
            $all_ids && $sql .= " AND id NOT IN ({$all_ids})";
            $sql .= " ORDER BY sort DESC,id ASC";
            $data = Db::query($sql);
            if ($data) {
                $temp = [];
                foreach ($data as $node) {
                    $node_ids[] = $node['id'];
                    $node['auth_status'] = 'inherit'; // 继承的节点
                    $node['can_access'] = $nodeList[$node['pid']]['can_access'];
                    $node['can_export'] = $nodeList[$node['pid']]['can_export'];
                    $node['can_seeall'] = $nodeList[$node['pid']]['can_seeall'];
                    $node['can_areaall'] = $nodeList[$node['pid']]['can_areaall'];
                    $temp[$node['id']] = $node;
                    $node['level'] == 1 && $level_1_ids[] = $node['id'];
                }
                $nodeList = $nodeList + $temp;
            }
        }
        // 从二级节点扩充至三级节点
        if ($level_1_ids) {
            $all_ids = implode(',', $node_ids);
            $level_1_ids = implode(',', $level_1_ids);
            $sql = "SELECT id,pid,title,iconfont,code,level,`show`,`sort`,module
                    FROM base_node WHERE pid IN ({$level_1_ids})
                    AND status = 1";
            $all_ids && $sql .= " AND id NOT IN ({$all_ids})";
            $sql .= " ORDER BY sort DESC,id ASC";
            $data = Db::query($sql);
            if ($data) {
                $temp = [];
                foreach ($data as $node) {
                    $node['auth_status'] = 'inherit'; // 继承的节点
                    $node['can_access'] = $nodeList[$node['pid']]['can_access'];
                    $node['can_export'] = $nodeList[$node['pid']]['can_export'];
                    $node['can_seeall'] = $nodeList[$node['pid']]['can_seeall'];
                    $node['can_areaall'] = $nodeList[$node['pid']]['can_areaall'];
                    $temp[$node['id']] = $node;
                }
                $nodeList = $nodeList + $temp;
            }
        }
        $authList['auth_nodes'] = $nodeList;
        // $menu_node_tree1 为当前能确定的从顶级节点开始的节点树(当没有直接授权的顶级节点时,该树为空)
        // 调用结束后$nodeList为没有顶级节点或没有二级节点的二三级节点列表
        $menu_node_tree1 = $this->_menu_node_tree($nodeList);

        // 从剩余的节点列表里分别找出所有直接授权的二三级节点的pid,并反溯找到其父节点
        $menu_node_tree2 = [];
        if ($nodeList) {
            $node_ids = $level_1_pids = $level_2_pids = [];
            // var_dump($nodeList);exit;
            foreach ($nodeList as $key => $node) {
                $node_ids[] = $node['id'];
                $node['level'] == 1 && $level_1_pids[] = $node['pid'];
                $node['level'] == 2 && $level_2_pids[] = $node['pid'];
            }

            // 反溯到二级节点
            $all_ids = implode(',', $node_ids);
            $level_2_pids = implode(',', $level_2_pids);
            $sql = "SELECT id,pid,title,iconfont,code,level,`show`,`sort`,module
                    FROM base_node WHERE id IN ({$level_2_pids})
                    AND status = 1";
            $all_ids && $sql .= " AND id NOT IN ({$all_ids})";
            $sql .= " ORDER BY sort DESC,id ASC";
            $data = !empty($level_2_pids) ? Db::query($sql) : [];
            if ($data) {
                $temp = [];
                foreach ($data as $node) {
                    $node_ids[] = $node['id'];
                    $node['auth_status'] = 'false'; // 反溯扩充的节点,仅用于生成目录
                    $temp[$node['id']] = $node;
                    $node['level'] == 1 && $level_1_pids[] = $node['pid'];
                }
                $nodeList = $nodeList + $temp;
            }

            // 反溯到顶级节点
            $all_ids = implode(',', $node_ids);
            $level_1_pids = implode(',', $level_1_pids);
            $sql = "SELECT id,pid,title,iconfont,code,level,`show`,`sort`,module
                    FROM base_node WHERE id IN ({$level_1_pids})
                    AND status = 1";
            $all_ids && $sql .= " AND id NOT IN ({$all_ids})";
            $sql .= " ORDER BY sort DESC,id ASC";
            $data = !empty($level_1_pids) ? Db::query($sql) : [];
            if ($data) {
                $temp = [];
                foreach ($data as $node) {
                    $node['auth_status'] = 'false'; // 反溯扩充的节点,仅用于生成目录
                    $temp[$node['id']] = $node;
                }
                $nodeList = $nodeList + $temp;
            }

            // 反溯完后,生成第二棵从顶级节点开始的节点树
            $menu_node_tree2 = $this->_menu_node_tree($nodeList);
        }
        $menu_node_tree = array_merge($menu_node_tree1, $menu_node_tree2);

        $authList['menu_node_tree'] = $menu_node_tree;
        $authList['auth_codes'] = array_column($authList['auth_nodes'], 'code');
        foreach ($authList['auth_codes'] as &$tmp){
            $tmp = strtolower($tmp);
        }
        $authList['auth_nodes'] = array_combine($authList['auth_codes'], $authList['auth_nodes']);
        $authList['token'] = md5($user_id . microtime(true));
        return $authList;
    }

    /**
     * 生成节点树
     */
    public function _menu_node_tree(&$nodeList, $pid = 0) {
        $nodes = [];
        if ($nodeList) {
            foreach ($nodeList as $key => $node) {
                if ($node['show'] == 0) {
                    unset($nodeList[$key]);
                    continue;
                }
                if ($node['pid'] == $pid) {
                    //操作
                    $mca = $node['code'] !='' ? explode('.', $node['code']) : [];
                    $node['controller'] = isset($mca[0])&&$mca[0]!='*' ? $mca[0] : 'index';
                    $node['action']     = isset($mca[1])&&$mca[1]!='*' ? $mca[1] : 'index';
                    if(empty($node['module'])){
                        $node['url']        = url($node['controller'].'/'.$node['action'])->build();
                    }else{
                        $node['url']        = url($node['module'].'/'.$node['controller'].'/'.$node['action'])->build();
                    }
                    $nodes[$node['id']] = $node;
                    unset($nodeList[$key]);
                    $ret = $this->_menu_node_tree($nodeList, $node['id']);
                    if( $ret ){
                        $nodes[$node['id']]['sub_nodes'] = $ret;
                    }else{
                        $nodes[$node['id']]['sub_nodes'] = [];
                    }
                }
            }
        }
        return $nodes;
    }

    /*
     * 表格数据返回
    */
    public function tableOutput($errcode, $data, $count=0, $extend = [], $hearder=[], $options=[]){
        $response_data = [
            'code'   => $errcode,
            'msg'    => $errcode>0?$data:'',
            'count'  => $count,
            'data'   => $errcode>0?[]:$data,
            'extend' => $extend,
        ];
        $response = json($response_data, 200, $hearder, $options);
        throw new HttpResponseException($response);
    }
}
