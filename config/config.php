<?php
!defined('IN_FRAMEWORK') && exit('No direct script access allowed');
isset($_SERVER['REMOTE_ADDR']) && exit('Command Line Only!');


//这里是需要配置的选项
return array(
    'memory_limit' => '1280M',
    //php执行文件路径
    'php_path' => '/usr/local/webserver/php/bin/php',
    //ping命令 执行文件路径
    'ping_path' => '/bin/ping',
    //dig命令 执行文件路径
    'dig_path' => '/usr/bin/dig',
);