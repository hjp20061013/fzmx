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