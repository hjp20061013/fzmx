<?php
// 应用公共文件
/*
 * 调试输出
*/
function mtrace(...$args)
{
    $params = count($args) == 1 ? $args[0] : $args;
    echo '<pre>';
    print_r($params);
    echo PHP_EOL;
    exit();
}

/**
 * ApiCall
 */
function apiCall($app_key, $action, $params, $hasVerifyResponseEmpty=true)
{
    $sourceConfig = config('sources');
    $url = $sourceConfig[$app_key]['api_url']??'';
    if( empty($url) ){
        throw new \Exception('appKey不存在');
    }
    $url .= $action;
    //签名
    $ts = time();
    $sign = makeSign($app_key, $ts);
    $header_list = [
        'Content-Type:application/json',
        'appKey:' . $app_key,
        'ts:' . time(),
        'sign:' . $sign
    ];
    $response = curlPost($url, json_encode($params), $header_list);
    if ($hasVerifyResponseEmpty) {
        if( empty($response) ){
            throw new \Exception("请求接口失败 Url:" . $url);
        }
        $response = @json_decode($response, true);
    }
    return $response;
}

/**
 * curlPost
 */
if (!function_exists('curlPost')) {
    function curlPost($url, $data, $header_list = [])
    { // 模拟提交数据函数
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $header = [
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($data),
        ];
        $header = array_merge($header, $header_list);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        if (substr($url, 0, 5) == 'https') {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查SSL加密算法是否存在
        }
        $tmpInfo = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            //echo 'Errno' . curl_error($curl);//捕抓异常
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            throw new \Exception("请求超时,url:{$url}");
        }
        curl_close($curl); // 关闭CURL会话
        return $tmpInfo;
    }

    function makeSign($app_key, $ts){
        $sourceConfig = config('sources');
        if( !isset($sourceConfig[$app_key]) ){
            throw new \Exception("appKey未定义，签名失败");
        }
        $str = "appKey,{$app_key};secretKey,{$sourceConfig[$app_key]['sign_key']};ts,{$ts};";
        return md5($str);
    }


}