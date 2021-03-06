<?php
define('__ROOT__', dirname(__FILE__)); 
require_once(__ROOT__.'/config.php'); 

function send_line_notify($message, $token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "message=$message");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $headers = array("Content-type: application/x-www-form-urlencoded", "Authorization: Bearer $token");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$jsonInput = file_get_contents('php://input');
$arrInput  = json_decode($jsonInput, true);
//error_log($jsonInput);
if ($arrInput['check_result']['result_description'] == '') {
    echo ("No input");
    die();
}

$twTz  = new DateTimeZone('Asia/Taipei');
$utcTz = new DateTimeZone('UTC');

$alertTriggeredAt = new DateTime($arrInput['check_result']['triggered_at']);
$alertTriggeredAt->setTimezone($utcTz);
$ms = $alertTriggeredAt->format('u') / 1000;
$ms = str_pad($ms, 3, '0', STR_PAD_LEFT);
$to   = $alertTriggeredAt->format('Y-m-d\TH:i:s') . "." . $ms . "Z";
$from = clone $alertTriggeredAt;
$from = $from->sub(new DateInterval('PT10M'))->format('Y-m-d\TH:i:s') . "." . $ms . "Z";

//error_log($from);
//error_log($to);

$streamUrl = 
    'http://graylog.pin2wall.com/streams/' . $arrInput['stream']['id'] . '/messages?' .
    'rangetype=absolute&' .
    'from=' . $from . '&' .
    'to=' . $to . '&' .
    'q=*';
//error_log($streamUrl);

$alertCondition = $arrInput['stream']['alert_conditions'][0];
$message = "" . PHP_EOL;
$alertTriggeredAt->setTimezone($twTz);
$message = $message . PHP_EOL . "`[" . $arrInput['stream']['title'] . "] " . $alertTriggeredAt->format('Y-m-d H:i:s') . '`';
$message = $message . PHP_EOL . urlencode($streamUrl);

// get logs detail
$checkResult = $arrInput["check_result"];
$where = "";
if (array_key_exists("matching_messages", $checkResult)) {
    $matchingMessage = $checkResult["matching_messages"];
    foreach($matchingMessage as $key => $value) {
        $s1 = $value["fields"]["file"];
        $s1 = str_replace("/deploy/www/", "", $s1);
        $s1 = str_replace("/deploy/logs/app", "", $s1);
        $s1 = str_replace("/deploy/logs/app-mall", "", $s1);
        $s1 = str_replace("/deploy/logs/app-center", "", $s1);

        if (strpos($s1, '/storage/logs'))
            $s1 = substr($s1, 0, strpos($s1, '/storage/logs'));

        if (strpos($s1, '/laravel'))
            $s1 = substr($s1, 0, strpos($s1, '/laravel'));

        $where = $where . PHP_EOL . $value["fields"]["name"] . " => " . $s1;
    }
}

$message = $message . PHP_EOL . $where;
//error_log($message);

$res = send_line_notify($message, TOKEN);
error_log($res);

?>
