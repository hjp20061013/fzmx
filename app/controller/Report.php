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

class Report extends AdminBase
{
    /**
     * 列表
     */
    public function reportList(){
        // 登陆信息验证
        $adminInfo = $this->adminAuthInfo();
        $search_query = [];
        $page = input('page', 1);
        $page_size = input('limits', 50);
        $search_query['test_no'] = input('test_no', '');

        $total_count = Db::table('fzmx_report')
            ->where(function (Query $query)use($search_query){
                if( $search_query['test_no']!= '' ){
                    $query->whereOr("test_no LIKE '%{$search_query['test_no']}%' OR test_company LIKE '%{$search_query['test_no']}%' OR test_customer_name LIKE '%{$search_query['test_no']}%' ");
                }
            })->count();
        //总页数
        $total_page = ceil($total_count / $page_size);

        $report_list = Db::table('fzmx_report')
            ->field('*')
            ->where(function (Query $query)use($search_query){
                if( $search_query['test_no']!= '' ){
                    $query->whereOr("test_no LIKE '%{$search_query['test_no']}%' OR test_company LIKE '%{$search_query['test_no']}%' OR test_customer_name LIKE '%{$search_query['test_no']}%' ");
                }
            })
            ->page($page, $page_size)
            ->order('created_at desc')
            ->select();
        $params = array_merge(['page' => '__page__', 'limits' => '__limits__'], $search_query);
        $page_url = url('Report/reportList', $params)->build();
        View::assign('page', $page);
        View::assign('page_size', $page_size);
        View::assign('total_count', $total_count);
        View::assign('total_page', $total_page);
        View::assign('page_url', $page_url);
        View::assign('report_list', $report_list);
        View::assign('search_query', $search_query);
        return View::fetch('report_list');
    }

}
