<?php

function send_curl($url, $data = '', $method = 'GET', $charset = 'utf-8', $timeout = 15) {
    //初始化并执行curl请求
    $curl = curl_init();
    curl_setopt($curl, CURLOP_TIMEOUT, $timeout);
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
    //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    if (strtoupper($method)=='POST') {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        if (is_string($data)) { //发送JSON数据
            $http_header = array(
                'Content-Type: application/json; charset=' . $charset,
                'Content-Length: ' . strlen($data),
            );
            curl_setopt($curl, CURLOPT_HTTPHEADER, $http_header);
        }
    }
    $result = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    //发生错误，抛出异常
    //if ($error) throw new \Exception('请求发生错误：' . $error);
    //if($error){readdir(C('WEB_URL').C('ERROR_PAGE'));}
    return $result;
}

function curl_post($url, $data) {
    $curl = curl_init();
    $param = http_build_query($data);
    curl_setopt($curl, CURLOPT_URL, $url . '?' . $param);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen($data)
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    if (curl_errno($curl)) {
        echo 'Error:' . curl_error($curl);
    }
    curl_close($curl);
    return $result;
}
/*
 * 短信发送内容
 * @param $tel，手机号码
 * @param $msg，短信内容
 * */

function send_msg($tel, $msg) {
    if($_SESSION['PLATFORM_CODE']=='NYYHD'){
      $username = C('MSG_USER_NAME');
      $password = C('MSG_PASSWORD');
      $send_msg = urlencode(iconv('UTF-8', 'GB2312',  $msg.'【刷卡欢乐送】'));
    }else if($_SESSION['PLATFORM_CODE']=='NYYH'||$_SESSION['PLATFORM_CODE']=='NYYHCJ'){
      $username = C('MSG_USER_NAME');
      $password = C('MSG_PASSWORD');
      $send_msg = urlencode(iconv('UTF-8', 'GB2312','【天天有礼】'.  $msg ));
    }else{
      $username = C('MSG_USER_NAME');
      $password = C('MSG_PASSWORD');
      $send_msg = urlencode(iconv('UTF-8', 'GB2312', '【乐天邦】' . $msg));
    }
    $url = 'http://58.83.147.92:8080/qxt/smssenderv2?user=' . $username . '&password=' . md5($password) . '&tele=' . $tel . '&msg=' . $send_msg;
    $result = send_curl($url);
    return $result;
}




function doLog($action = '', $content = '', $product_id = '',$details = '',$redis = '')
{
    $data = array();
    $PtLogModel = M('log');
    $session = $_SESSION;
    $data['uid']        = $session['id'];
    $data['username']   = '';
    $data['ptid']       = '';
    $data['ptname']     = '';
    $data['productid']  = $product_id;
    $data['action']     = $action;
    $data['content']    = $content;
    $data['ip']         = get_client_ip(0, true);
    $data['longitude']  = '';
    $data['latitude']   = '';
    $data['sex']        = '';
    $data['openid']     = $session['openid'];
    $data['zwcmopenid'] = '';
    $data['createtime'] = date('Y-m-d H:i:s');
    $data['details']    = $details;
    $data['useragent']  =  $_SERVER['HTTP_USER_AGENT'];
    $PtLogModel->data($data)->add();
}
