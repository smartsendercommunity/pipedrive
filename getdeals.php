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


$filterKey = ["title","creator_user_id","user_id","value","currency","weighted_value","weighted_value_currency","probability","pipeline","person_id","stage_id","status","stage_change_time","won_time","lost_time","close_time","lost_reason","product_quantity","product_amount","expected_close_date","org_id","label","add_time","update_time","next_activity_date","last_activity_date","last_incoming_mail_time","last_outgoing_mail_time","visible_to","id","activities_count","done_activities_count","undone_activities_count","email_messages_count"];
// Подготовка полей сделки
if (file_exists("pdData/leadFields.json") && $oldTime < $dateUpdate["leadFields"]) {
    $leadFields = json_decode(file_get_contents("pdData/leadFields.json"), true);
    $log["leadFields"]["source"] = "file";
    //$log["leadFields"]["data"] = $getLeadFields;
} else {
    $getLeadFields = json_decode(send_request("https://api.pipedrive.com/v1/dealFields?api_token=".$pdKey."&limit=500"), true);
    if ($getLeadFields["success"] === false) {
        sleep(2);
        $getLeadFields = json_decode(send_request("https://api.pipedrive.com/v1/dealFields?api_token=".$pdKey."&limit=500"), true);
    }
    $log["leadFields"]["source"] = "API";
    //$log["leadFields"]["data"] = $getLeadFields;
    if ($getLeadFields["success"] === true) {
        if (is_array($getLeadFields["data"])) {
            foreach ($getLeadFields["data"] as $oneLeadFields) {
                if (in_array($oneLeadFields["key"], $filterKey) != true) {
                    $leadFields[$oneLeadFields["name"]] = $oneLeadFields["key"];
                }
            }
        }
        file_put_contents("pdData/leadFields.json", json_encode($leadFields));
        $dateUpdate["leadFields"] = time();
    } else {
        $logData["state"] = "warning";
        $logDescription[] = "Ошибка получения полей сделки";
    }
}
$log["leadFields"]["formated"] = $leadFields;


if ($input["dealId"] != NULL) {
    // Получение отдельного лида
    $getLead = json_decode(send_request("https://api.pipedrive.com/v1/deals/".$input["dealId"]."?api_token=".$pdKey), true);
    if ($getLead["success"] === false) {
        sleep(1);
        $getLead = json_decode(send_request("https://api.pipedrive.com/v1/deals/".$input["dealId"]."?api_token=".$pdKey), true);
    }
    if ($getLead["success"] !== false) {
        $getLead = $getLead["data"];
    } else {
        $result["state"] = false;
        $result["result"] = $getLead;
        echo json_encode($result);
        exit;
    }
    if ($leadFields != NULL && is_array($leadFields)) {
        foreach ($leadFields as $fieldKey => $fieldValue) {
            if ($getLead[$fieldValue] != NULL) {
                $getLead[$fieldKey] = $getLead[$fieldValue];
            }
        }
    }
    $result["contactId"] = $contactId;
    $result["dealId"] = $input["dealId"];
    $result["deal"] = $getLead;
} else {
    // Получение всех лидов контакта
    $getLead = json_decode(send_request("https://api.pipedrive.com/v1/persons/".$contactId."/deals?api_token=".$pdKey), true);
    if ($getLead["success"] === false) {
        sleep(1);
        $getLead = json_decode(send_request("https://api.pipedrive.com/v1/persons/".$contactId."/deals?api_token=".$pdKey), true);
    }
    if ($getLead["success"] !== false) {
        $getLead = $getLead["data"];
    } else {
        $result["state"] = false;
        $result["result"] = $getLead;
        echo json_encode($result);
        exit;
    }
    if ($getLead != NULL && is_array($getLead)) {
        foreach ($getLead as &$oneGetLead) {
            if ($leadFields != NULL && is_array($leadFields)) {
                foreach ($leadFields as $fieldKey => $fieldValue) {
                    if ($oneGetLead[$fieldValue] != NULL) {
                        $oneGetLead[$fieldKey] = $oneGetLead[$fieldValue];
                    }
                }
            }
        }
    }
    
    $result["contactId"] = $contactId;
    $result["deals"] = $getLead;
}

file_put_contents("pdData/time.json", json_encode($dateUpdate));

echo json_encode($result);

// Логирование
$logData["description"] = implode("<br>", $logDescription);
send_forward(json_encode($log), $logUrl."?".http_build_query($logData));

