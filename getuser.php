<?php

include('config.php');

$input = json_decode(file_get_contents("php://input"), true);

$log["url"] = $url;

if ($input["userId"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "'userId' is missing";
}
if (file_exists('pdData/users.json')) {
    $users = json_decode(file_get_contents('pdData/users.json'), true);
    $contactId = $users[$input['userId']];
    if ($contactId == NULL) {
        $result["state"] = false;
        $result["error"]["message"][] = "the contact was not created by this integration";
    }
}
if ($result["state"] === false) {
    echo json_encode($result);
    exit;
} else {
    $result["state"] = true;
}
if (file_exists("pdData/time.json")) {
    $dateUpdate = json_decode(file_get_contents("pdData/time.json"), true);
}


$filterKey = ["name","label","last_name","first_name","phone","email","add_time","update_time","org_id","owner_id","open_deals_count","visible_to","next_activity_date","last_activity_date","id","won_deals_count","lost_deals_count","closed_deals_count","activities_count","done_activities_count","undone_activities_count","email_messages_count","picture_id","last_incoming_mail_time","last_outgoing_mail_time"];
// Подготовка полей контакта
if (file_exists("pdData/contactFields.json") && $oldTime < $dateUpdate["contactFields"]) {
    $contactFields = json_decode(file_get_contents("pdData/contactFields.json"), true);
    $log["contactFields"]["source"] = "file";
    //$log["contactFields"]["data"] = $getContactFields;
} else {
    $getContactFields = json_decode(send_request("https://api.pipedrive.com/v1/personFields?api_token=".$pdKey."&limit=500"), true);
    if ($getContactFields["success"] === false) {
        sleep(1);
        $getContactFields = json_decode(send_request("https://api.pipedrive.com/v1/personFields?api_token=".$pdKey."&limit=500"), true);
    }
    $log["contactFields"]["source"] = "API";
    //$log["contactFields"]["data"] = $getContactFields;
    if ($getContactFields["success"] === true) {
        if (is_array($getContactFields["data"])) {
            foreach ($getContactFields["data"] as $oneContactFields) {
                if (in_array($oneContactFields["key"], $filterKey) != true) {
                    $contactFields[$oneContactFields["name"]] = $oneContactFields["key"];
                }
            }
        }
        file_put_contents("pdData/contactFields.json", json_encode($contactFields));
        $dateUpdate["contactFields"] = time();
        
    } else {
        $logData["state"] = "warning";
        $logDescription[] = "Ошибка получения полей контакта";
    }
}

$getContact = json_decode(send_request("https://api.pipedrive.com/v1/persons/".$contactId."?api_token=".$pdKey), true);
if ($getContact["success"] === false) {
    sleep(1);
    $getContact = json_decode(send_request("https://api.pipedrive.com/v1/persons/".$contactId."?api_token=".$pdKey), true);
}
if ($getContact["success"] !== false) {
    $getContact = $getContact["data"];
} else {
    $result["state"] = false;
    $result["result"] = $getContact;
    echo json_encode($result);
    exit;
}
if ($contactFields != NULL && is_array($contactFields)) {
    foreach ($contactFields as $fieldKey => $fieldValue) {
        if ($getContact[$fieldValue] != NULL) {
            $getContact[$fieldKey] = $getContact[$fieldValue];
        }
    }
}
$result["contactId"] = $contactId;
$result["contact"] = $getContact;



file_put_contents("pdData/time.json", json_encode($dateUpdate));

echo json_encode($result);

// Логирование
$logData["description"] = implode("<br>", $logDescription);
send_forward(json_encode($log), $logUrl."?".http_build_query($logData));

