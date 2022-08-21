<?php

ini_set('max_execution_time', '1700');
set_time_limit(1700);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

/////////////////////////////////////////////////
////////  C O N F I G U R A T I O N  ////////////
/////////////////////////////////////////////////

$pdKey = "";
$url = "https://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["PHP_SELF"]);
$url = explode("?", $url);
$url = $url[0];
$logUrl = "https://log.mufiksoft.com/smartsender-pipedrive";

/////////////////////////////////////////////////
////////////  S E T T I N G S   /////////////////
/////////////////////////////////////////////////

$oldTime = time() - 900; // 900 секунд (15мин) - лимит устаревания данных
if (file_exists('pdData') != true) {
    mkdir('pdData');
}
/////////////////////////////////////////////////
////////////  F U N K T I O N S  ////////////////
/////////////////////////////////////////////////

function send_forward($inputJSON, $link){
    $request = 'POST';	
    $descriptor = curl_init($link);
     curl_setopt($descriptor, CURLOPT_POSTFIELDS, $inputJSON);
     curl_setopt($descriptor, CURLOPT_RETURNTRANSFER, 1);
     curl_setopt($descriptor, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
     curl_setopt($descriptor, CURLOPT_CUSTOMREQUEST, $request);
    $itog = curl_exec($descriptor);
    curl_close($descriptor);
    return $itog;
}
function send_bearer($url, $token, $type = "GET", $param = []){
    $descriptor = curl_init($url);
     curl_setopt($descriptor, CURLOPT_POSTFIELDS, json_encode($param));
     curl_setopt($descriptor, CURLOPT_RETURNTRANSFER, 1);
     curl_setopt($descriptor, CURLOPT_HTTPHEADER, array('User-Agent: Smart Sender - GitHub', 'Content-Type: application/json', 'Authorization: Bearer '.$token)); 
     curl_setopt($descriptor, CURLOPT_CUSTOMREQUEST, $type);
    $itog = curl_exec($descriptor);
    curl_close($descriptor);
    return $itog;
}
function send_request($url, $header = [], $type = 'GET', $param = [], $raw = "json") {
    $descriptor = curl_init($url);
    if ($type != "GET") {
        if ($raw == "json") {
             curl_setopt($descriptor, CURLOPT_POSTFIELDS, json_encode($param));
            $header[] = 'Content-Type: application/json';
        } else if ($raw == "form") {
             curl_setopt($descriptor, CURLOPT_POSTFIELDS, http_build_query($param));
            $header[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
             curl_setopt($descriptor, CURLOPT_POSTFIELDS, $param);
        }
    }
    $header[] = 'User-Agent: Smart Sender - GitHub(https://github.com/smartsendercommunity)';
     curl_setopt($descriptor, CURLOPT_RETURNTRANSFER, 1);
     curl_setopt($descriptor, CURLOPT_HTTPHEADER, $header); 
     curl_setopt($descriptor, CURLOPT_CUSTOMREQUEST, $type);
    $itog = curl_exec($descriptor);
    //$itog["code"] = curl_getinfo($descriptor, CURLINFO_RESPONSE_CODE);
    curl_close($descriptor);
    return $itog;
}
