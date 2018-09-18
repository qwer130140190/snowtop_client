<?php
define('IN_FRAMEWORK', true);
isset($_SERVER['REMOTE_ADDR']) && exit('Command Line Only!');
date_default_timezone_set('Asia/Shanghai');
$config = include 'config/config.php';
require_once 'common/functions.php';
set_time_limit(60);
ini_set('memory_limit','128M');

$arg = $_SERVER['argv'];
count($arg) != 3 && exit("使用方法：son.php <task> <union_id>\r\n");
list($null, $task, $union_id) = $arg;
$task = json_decode(base64_decode($task), true);

echo "\r\n";

if (0 == $task['type']) {
    printLog("正在检测 [all]  {$task['url']}");
    $info = curl_info($task['url']);
    $speed = formatBytes($info['speed_download']);
    printLog("total_time :          {$info['total_time']} s");
    printLog("http_code :           {$info['http_code']}");
    printLog("connect_time :        {$info['connect_time']} s");
    printLog("starttransfer_time :  {$info['starttransfer_time']} s");
    printLog("namelookup_time :     {$info['namelookup_time']} s");
    printLog("speed_download :      {$speed}/s");
    unset($info['url']);
} else if (1 == $task['type']) {
    printLog("正在检测 [dns]  {$task['url']}");
    $info = cmd_dig($config['dig_path'], $task['url']);
    if ($info) {
        printLog("query_time :   {$info['dns_time']} ms");
        foreach ($info['dns_server'] as $server) {
            printLog("{$server[0]}    {$server[1]}    {$server[2]}");
        }
        $info['dns_server'] = json_encode($info['dns_server']);
    } else {
        printLog("检测失败");
        $info['dns_time'] = 0;
        $info['dns_server'] = '';
    }

} else if (2 == $task['type']) {
    printLog("正在检测 [ping]  {$task['url']}");
    $info = cmd_ping($config['ping_path'], $task['url']);
    if (false == $info) {
        $info = array(
            'ping_min' => 0,
            'ping_avg' => 0,
            'ping_max' => 0,
            'ping_mdev' => 0,
            'ping_ttl' => 0
        );
        printLog("\033[37mping 失败\033[0m");
    } else {
        printLog("min :           {$info['ping_min']} ms");
        printLog("avg :           {$info['ping_avg']} ms");
        printLog("max :           {$info['ping_max']} ms");
        printLog("mdev :          {$info['ping_mdev']} ms");
        printLog("ttl :           {$info['ping_ttl']}");
    }
}
$data = array(
    'union_id' => $union_id,
    'task_id'  => $task['task_id'],
    'info'  => $info,
);
$ret = $ret_old = curl('http://jk.thesnowtop.com:7001/api/client/feedback.html', 'POST', $data);
$ret = json_decode($ret, true);
if (!is_array($ret)) {
    printLog("\033[37m反馈数据错误！ (ㄒoㄒ) 返回：{$ret_old}\033[0m", true);
}
if ($ret['code'] != 1) {
    printLog("\033[37m{$ret['msg']}\033[0m", true);
}
printLog("\033[32m数据反馈成功 ^_^\033[0m");