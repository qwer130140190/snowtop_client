<?php

//!defined('IN_FRAMEWORK') && exit('No direct script access allowed');

/**
 * 通过curl方式请求url
 *
 * @author binbin.yin
 *
 * @param string $url 待请求的网址
 * @param string $method 请求方式，
 * @param array $params 请求时附带参数
 * @param bool $multi 是否以multi方式
 * @return string
 */
function curl ($url, $method = 'get', $params = array(), $multi = false) {
    $method = strtolower($method);
    $ci = curl_init();
    curl_setopt($ci, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.41 Safari/537.36');
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ci, CURLOPT_TIMEOUT, 60);
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ci, CURLOPT_ENCODING, '');
    curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ci, CURLOPT_HEADER, false);
    curl_setopt($ci, CURLINFO_HEADER_OUT, true);

    if ('post' == $method) {
        curl_setopt($ci, CURLOPT_POST, true);
        if (!$multi && (is_array($params) || is_object($params))) {
            $params = http_build_query($params);
        }
        if (!empty($params)) {
            curl_setopt($ci, CURLOPT_POSTFIELDS, $params);
        }
    } else {
        $url .= (false !== strpos($url, '?') ? '&' : '?') . (is_array($params) ? http_build_query($params) : $params);
    }
    curl_setopt($ci, CURLOPT_URL, $url);
    $response = curl_exec($ci);
    curl_close ($ci);
    return $response;
}


function cmd_dig ($dig_path, $address) {
    $data = array();
    if (substr($address, 0, 4) == 'http') {
        $address = parse_url($address);
        if (!isset($address['host'])) {
            return false;
        }
        $address = $address['host'];
    }
    if (strcasecmp(PHP_OS, 'WINNT') === 0) {

    } elseif (strcasecmp(PHP_OS, 'Linux') === 0 || strcasecmp(PHP_OS, 'Darwin') === 0) {
        $digresult = exec("{$dig_path} {$address}", $output, $status);

        $flag = 0;
        $data['dns_server'] = array();
        foreach ($output as $key => $line) {
            if (false !== strpos($line, ';; Query time:')) {
                $data['dns_time'] = intval(str_replace(array(';; Query time:', 'msec'), array('', ''), $line));
            }
            if (false !== strpos($line, 'ANSWER SECTION')) {
                $flag = 1;
                continue;
            }
            if ($flag == 1) {
                $line = str_replace(' ', "\t", $line);
                $line = explode("\t", $line);
                $line = array_values(array_filter($line));
                if (5 == count($line) && $line[0] == $address.'.') {
                    $data['dns_server'][] = array(
                        $line[1], //dns_expired
                        $line[3], //dns_type
                        substr($line[4], 0, -1) //dns_server
                    );
                }

            }
        }
    }
    return $data;
}

function cmd_ping($bin_path, $address, $count = 5) {
    $data = array();
    if (substr($address, 0, 4) == 'http') {
        $address = parse_url($address);
        if (!isset($address['host'])) {
            return false;
        }
        $address = $address['host'];
    }

    if (strcasecmp(PHP_OS, 'WINNT') === 0) {
        $pingresult = exec("{$bin_path} -n {$count} {$address}", $output, $status);
    } elseif (strcasecmp(PHP_OS, 'Linux') === 0) {
        $pingresult = exec("{$bin_path} {$address} -c {$count} -i 0.2", $output, $status);
        if (strpos($pingresult, 'min/avg/max/mdev') === false) {
            return false;
        }
        $pingresult = trim(str_replace(array('rtt min/avg/max/mdev = ', 'ms'), array('', ''), $pingresult));
        $pingresult = explode('/', $pingresult);
        list($data['ping_min'], $data['ping_avg'], $data['ping_max'], $data['ping_mdev']) = $pingresult;
        $data['ping_mdev'] = floatval($data['ping_mdev']);
        $ttl = $output[1];
    } elseif (strcasecmp(PHP_OS, 'Darwin') === 0) {
        $pingresult = exec("{$bin_path} {$address} -c {$count} -i 0.2", $output, $status);
        if (strpos($pingresult, 'min/avg/max/stddev') === false) {
            return false;
        }
        $pingresult = trim(str_replace(array('round-trip min/avg/max/stddev =', 'ms'), array('', ''), $pingresult));
        $pingresult = explode('/', $pingresult);
        list($data['ping_min'], $data['ping_avg'], $data['ping_max'], $data['ping_mdev']) = $pingresult;
        $data['ping_mdev'] = floatval($data['ping_mdev']);
        $ttl = $output[1];
    }
    preg_match('/ttl=(\d{1,10})/', $ttl, $match);
    $data['ping_ttl'] = count($match) == 2 ? intval($match[1]) : 0;
    return $data;
}

function curl_info ($url, $timeout = 20, $cookie = array(), $referer = '', $user_agent = '', $max_speed = 0, $max_size = 0, $nobody = false) {
    $ci = curl_init();
    $user_agent = empty($user_agent) ? 'stone chart/1.0' : $user_agent;
    curl_setopt($ci, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ci, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ci, CURLOPT_ENCODING, '');
    curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ci, CURLOPT_HEADER, false);
    curl_setopt($ci, CURLINFO_HEADER_OUT, true);
    curl_setopt($ci, CURLOPT_URL, $url);
    if ($max_size > 0) {
        curl_setopt($ci, CURLOPT_RANGE, "0-".($max_size*1024));
    }
    if ($max_speed > 0) {
        curl_setopt($ci, CURLOPT_MAX_RECV_SPEED_LARGE, $max_speed*1024);
    }
    if ($nobody) {
        curl_setopt($ci, CURLOPT_NOBODY, true);
    }
    $content = curl_exec($ci);
    $info = curl_getinfo($ci);
    curl_close ($ci);
    return array(
        'url'                => $info['url'],
        'total_time'         => $info['total_time'],
        'http_code'          => $info['http_code'],
        'connect_time'       => $info['connect_time'],
        'starttransfer_time' => $info['starttransfer_time'],
        'namelookup_time'    => $info['namelookup_time'],
        'speed_download'     => $info['speed_download'],
        'content'            => $content
    );
}

function smart_sleep () {
    while (true) {
        if ('00' == date('s')) {
            break;
        }
    }
    if ('00' == date('s')) {
        return true;
    } else {
        usleep(100000);
        smart_sleep();
    }

}

function PrintLog($log, $exit=false){
    if (is_array($log)) {
        foreach ($log as $v){
            PrintLog($v, false);
        }
        $exit && exit();
        return true;
    }
    $mem = formatBytes(memory_get_usage(true));
    $mem = $mem.str_repeat(' ', 12-strlen($mem));
    echo date('Y-m-d H:i:s')."     {$mem}{$log}\r\n";
    $exit && exit();
    return true;
}

function formatBytes($size) {
    $units = array(' B', ' KB', ' MB', ' GB', ' TB');
    for ($i = 0; $size >= 1024 && $i < 4; $i++) {
        $size /= 1024;
    }
    return round($size, 2).$units[$i];
}

function isEmail($email) {
    return strlen($email) > 6 && preg_match("/^([A-Za-z0-9\-_.+]+)@([A-Za-z0-9\-]+[.][A-Za-z0-9\-.]+)$/", $email);
}

function get_mx($email){
    if (!isEmail($email)) {
        printLog("错误的邮箱: {$email}");
        exit;
    }
    printLog('正在获得MX记录...');
    $email_arr = explode('@', $email);
    $domain = array_pop($email_arr);
    $mx_arr = array();
    $output = dns_get_record($domain, DNS_MX);
    if (empty($output)) {
        return '';
    }
    $output = multi_array_sort($output, 'pri', SORT_ASC);
    $mx = $output[0]['target'];

    //随机获取mx
     foreach ($output as $v){
         $mx_arr[] = $v['target'];
     }
     $mx = $mx_arr[array_rand($mx_arr)];
//     printLog('成功得到MX记录：'.$mx);
    return $mx;
}

function multi_array_sort($multi_array, $sort_key, $sort=SORT_ASC){
    if(is_array($multi_array)){
        foreach ($multi_array as $row_array){
            if(is_array($row_array)){
                $key_array[] = $row_array[$sort_key];
            }else{
                return false;
            }
        }
    }else{
        return false;
    }
    array_multisort($key_array,$sort,$multi_array);
    return $multi_array;
}

function get_email_domain($email){
    $arr = explode('@', $email);
    return $arr[count($arr)-1];
}

function get_sendEmail_ret($output) {
    if (empty($output)) {
        return '';
    }

    $last_line = $output[count($output)-1];
    if (false !== strpos($last_line, 'Email was sent successfully')) {
        return array('code'=>250, 'reason'=>'Email was sent successfully');
    }
    $reason = array();

    $output_all_str = implode('|', $output);
    preg_match('/\s{2}\d{3}\s{1}/', $output_all_str, $matches);
    $code = empty($matches) ? 0 : intval($matches[0]);

    foreach ($output as $line) {
        $line = explode(' => ', $line);
        if (count($line)>1) {
            $reason[] = array_pop($line);
        }

    }
    return array('code'=>$code, 'reason'=>implode("|", $reason));
}
