<?php

namespace app\controller;

use app\BaseController;
use think\db\Query;
use think\Exception;
use think\facade\Cache;
use think\facade\Session;
use think\facade\Request;
use think\facade\Db;
use think\facade\View;
use think\helper\Str;
use utils\Utils;

class Member extends AdminBase
{
    /**
     * 列表
     */
    public function memberList(){
        // 登陆信息验证
        $adminInfo = $this->adminAuthInfo();
        $search_query = [];
        $page = input('page', 1);
        $page_size = input('limits', 50);
        $search_query['name'] = input('name', '');

        $total_count = Db::table('fzmx_member')
            ->where(function (Query $query)use($search_query){
                if( $search_query['name']!= '' ){
                    $query->whereOr("name LIKE '%{$search_query['name']}%' OR mobile LIKE '%{$search_query['name']}%'");
                }
            })->count();
        //总页数
        $total_page = ceil($total_count / $page_size);

        $member_list = Db::table('fzmx_member')
            ->field('*')
            ->where(function (Query $query)use($search_query){
                if( $search_query['name']!= '' ){
                    $query->whereOr("name LIKE '%{$search_query['name']}%' OR mobile LIKE '%{$search_query['name']}%'");
                }
            })
            ->page($page, $page_size)
            ->order('created_at desc')
            ->select();
        $params = array_merge(['page' => '__page__', 'limits' => '__limits__'], $search_query);
        $page_url = url('Member/memberList', $params)->build();
        View::assign('page', $page);
        View::assign('page_size', $page_size);
        View::assign('total_count', $total_count);
        View::assign('total_page', $total_page);
        View::assign('page_url', $page_url);
        View::assign('member_list', $member_list);
        View::assign('search_query', $search_query);
        return View::fetch('member_list');
    }

}
