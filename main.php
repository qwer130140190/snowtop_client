<?php
define('IN_FRAMEWORK', true);
isset($_SERVER['REMOTE_ADDR']) && exit('Command Line Only!');

date_default_timezone_set('Asia/Shanghai');
$config = include 'config/config.php';
require_once 'common/functions.php';
require_once 'class/crypt.class.php';
set_time_limit(0);
ini_set('memory_limit',$config['memory_limit']);
if (!file_exists($config['php_path'])) {
    printLog('PHP执行文件不能存在！', true);
}

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
printLog('--------------------- 脚本初始化完成 ! ---------------------');
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
$counter = 0;

while (true) {
    check_task(1);
    $ret = $ret_old = curl('http://jk.thesnowtop.com:7001/api/client/task.html', 'post', $data);
    $ret = json_decode($ret, true);

    if (!is_array($ret)) {
        printLog("\033[37m获取任务返回错误！ (ㄒoㄒ) 返回：{$ret_old}\033[0m");
        sleep(10);
        continue;
    }
    if ($ret['code'] != 1) {
        printLog("\033[37m{$ret['msg']}\033[0m");
        sleep(10);
        continue;
    }
    $task_count = count($ret['data']['task_list']);
    printLog("获取到任务 {$task_count} 个");
    foreach ($ret['data']['task_list'] as $task) {
        $task = base64_encode(json_encode($task));
        $cmd = "nohup {$config['php_path']} son.php {$task} {$union_id} 1>out.txt 2>err.txt &";
        printLog($cmd);
        exec($cmd);
    }
    sleep(10);
}




function check_task ($max) {
    $cmd = "ps -ef |grep son.php |wc -l ";
    exec($cmd, $output);
    $num = intval($output[0])-2;
    if ($num >= $max) {
        printLog('son.php进程数（'.$num.'）超过'.$max.'，暂时睡眠等待...');
        sleep(5);
        return check_task($max);
    } else {
        return $num;
    }
}
