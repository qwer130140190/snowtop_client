<?php
define('IN_FRAMEWORK', true);
isset($_SERVER['REMOTE_ADDR']) && exit('Command Line Only!');

date_default_timezone_set('Asia/Shanghai');
$config = include 'config/config.php';
require_once 'common/functions.php';
require_once 'class/crypt.class.php';
set_time_limit(0);
ini_set('memory_limit',$config['memory_limit']);

if (!function_exists('exec')) {
    printLog('exec函数不可用，请修改php.ini打开exec权限！', true);
}
$ret = curl('http://jk.thesnowtop.com:7001/api/time/index.html');
$ret = json_decode($ret, true);
if (!is_array($ret)) {
    printLog('客户端和服务器时间同步检查失败，请检查网络！', true);
}
$server_time = $ret['data']['time'];
$local_time = time();
if ($local_time - $server_time > 1 || $server_time - $local_time > 1) {
    printLog('客户端和服务器时间同步检查失败，请检查服务器时间！', true);
}

//$Crypt = new Crypt($config['appkey'], $config['key']);

$file_path = dirname(__FILE__).'/union_id.txt';

if (!file_exists($file_path)) {
    $union_id = md5(uniqid());
    $first_run = true;
    file_put_contents($file_path, $union_id);
} else {
    $first_run = false;
    $union_id =  file_get_contents($file_path);
}

echo "\r\n";
printLog('------------------------------- 脚本初始化完成 ! -------------------------------');
printLog("此客户端ID： {$union_id}");
printLog('正在上报客户端信息... ');
$data = array(
    'union_id' => $union_id
);
$ret = $ret_old = curl('http://jk.thesnowtop.com:7001/api/client/add.html', 'post', $data);
$ret = json_decode($ret, true);
//$ret = $Crypt->decrypt($ret);
if (!$ret) {
    $ret = json_decode($ret, true);
    if (is_array($ret) && !empty($ret)) {
        printLog("\033[37m上报返回错误！ (ㄒoㄒ)  code:{$ret['code']}  msg:{$ret['msg']}\033[0m");
    } else {
        printLog("\033[37m上报返回错误！ (ㄒoㄒ) 返回：{$ret_old}\033[0m");
    }
    exit;
} else if ($ret['code'] != 1) {
    printLog("\033[37m上报返回错误！ (ㄒoㄒ)  code:{$ret['code']}  msg:{$ret['msg']}\033[0m");
    exit;
}

printLog("\033[32m上报完成，结果 => 成功  ^_^  ".($first_run ? '请尽快去网页端认领客户端' : '')." \033[0m");
unset($ret);

smart_sleep();
$counter = 0;
while (true) {
    $ret = $ret_old = curl('http://jk.thesnowtop.com:7001/api/client/task.html', 'post', $data);
    $ret = json_decode($ret, true);

    if (!is_array($ret)) {
        printLog("\033[37m获取任务返回错误！ (ㄒoㄒ) 返回：{$ret_old}\033[0m");
        sleep(60);
        continue;
    }
    if ($ret['code'] != 1) {
        printLog("\033[37m{$ret['msg']}\033[0m");
        sleep(60);
        continue;
    }
    $task_count = count($ret['data']['task_list']);
    printLog("获取到任务 {$task_count} 个");
    printLog(str_repeat('-', 60));
    foreach ($ret['data']['task_list'] as $task) {
        if (0 == $task['type']) {
            printLog("|    正在检测 [all]  {$task['url']}");
            $info = curl_info($task['url']);
            $speed = formatBytes($info['speed_download']);
            printLog("|    total_time :          {$info['total_time']} s");
            printLog("|    http_code :           {$info['http_code']}");
            printLog("|    connect_time :        {$info['connect_time']} s");
            printLog("|    starttransfer_time :  {$info['starttransfer_time']} s");
            printLog("|    namelookup_time :     {$info['namelookup_time']} s");
            printLog("|    speed_download :      {$speed}/s");
            unset($info['url']);
        } else if (1 == $task['type']) {
            printLog("|    正在检测 [dns]  {$task['url']}");
            $info = cmd_dig($config['dig_path'], $task['url']);
            if ($info) {
                printLog("|    query_time :   {$info['dns_time']} ms");
                foreach ($info['dns_server'] as $server) {
                    printLog("|    {$server[0]}    {$server[1]}    {$server[2]}");
                }
                $info['dns_server'] = json_encode($info['dns_server']);
            } else {
                printLog("检测失败");
                $info['dns_time'] = 0;
                $info['dns_server'] = '';
            }

        } else if (2 == $task['type']) {
            printLog("|    正在检测 [ping]  {$task['url']}");
            $info = cmd_ping($config['ping_path'], $task['url']);
            if (false == $info) {
                $info = array(
                    'ping_min' => 0,
                    'ping_avg' => 0,
                    'ping_max' => 0,
                    'ping_mdev' => 0,
                    'ping_ttl' => 0
                );
                printLog("|    \033[37mping 失败\033[0m");
            } else {
                printLog("|    min :           {$info['ping_min']} ms");
                printLog("|    avg :           {$info['ping_avg']} ms");
                printLog("|    max :           {$info['ping_max']} ms");
                printLog("|    mdev :          {$info['ping_mdev']} ms");
                printLog("|    ttl :           {$info['ping_ttl']}");
            }
        }
        $data['task_id'] = $task['task_id'];
        $data['union_id'] = $union_id;
        $data['info'] = $info;
        $ret = $ret_old = curl('http://jk.thesnowtop.com:7001/api/client/feedback.html', 'POST', $data);
        $ret = json_decode($ret, true);
        if (!is_array($ret)) {
            printLog("|    \033[37m反馈数据错误！ (ㄒoㄒ) 返回：{$ret_old}\033[0m");
            printLog(str_repeat('-', 60));
            continue;
        }
        if ($ret['code'] != 1) {
            printLog("|    \033[37m{$ret['msg']}\033[0m");
            printLog(str_repeat('-', 60));
            continue;
        }
        printLog("|    \033[32m数据反馈成功 ^_^\033[0m");
        printLog(str_repeat('-', 60));
    }
    sleep(60);
}




function check_task ($max) {
    $cmd = "ps -ef |grep sender.php |wc -l ";
    exec($cmd, $output);
    $num = intval($output[0])-2;
    if ($num >= $max) {
        printLog('sender.php进程数（'.$num.'）超过'.$max.'，暂时睡眠等待...');
        sleep(5);
        return check_task($max);
    } else {
        return $num;
    }
}
