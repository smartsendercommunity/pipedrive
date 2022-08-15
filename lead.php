<?php

include('config.php');

$input = json_decode(file_get_contents("php://input"), true);

$log["url"] = $url;

if ($input["userId"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "'userId' is missing";
} else {
    if (file_exists('pdData/users.json')) {
        $users = json_decode(file_get_contents('pdData/users.json'), true);
        $contactId = $users[$input['userId']];
    }
    if ($contactId == NULL) {
        $result["state"] = false;
        $result["error"]["message"][] = "the contact was not created by this integration";
    }
}
if ($input["title"] == NULL && $input["leadId"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "'title' is missing";
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
        sleep(1);
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

// Дополнение всех остальных полей
if ($input["title"] != NULL) {
    $lead["title"] = $input["title"];
}
$lead["person_id"] = $contactId;
if ($input["label"] != NULL) {
    if (file_exists("pdData/leadLabels.json") && $oldTime < $dateUpdate["leadLabels"]) {
        $getlaedLabels = json_decode(file_get_contents("pdData/leadLabels.json"), true);
        $log["leadLabels"]["source"] = "file";
    } else {
        $getlaedLabels = json_decode(send_request("https://api.pipedrive.com/v1/leadLabels?api_token=".$pdKey), true);
        if ($getlaedLabels["success"] === false) {
            sleep(1);
            $getlaedLabels = json_decode(send_request("https://api.pipedrive.com/v1/leadLabels?api_token=".$pdKey), true);
            if ($getlaedLabels["success"] === false) {
                sleep(1);
                $getlaedLabels = json_decode(send_request("https://api.pipedrive.com/v1/leadLabels?api_token=".$pdKey), true);
                if ($getlaedLabels["success"] === false) {
                    sleep(1);
                    $getlaedLabels = json_decode(send_request("https://api.pipedrive.com/v1/leadLabels?api_token=".$pdKey), true);
                }
            }
        }
        $log["leadLabels"]["source"] = "API";
        if ($getlaedLabels["success"] === true) {
            file_put_contents("pdData/leadLabels.json", json_encode($getlaedLabels));
            $dateUpdate["leadLabels"] = time();
        } else {
            $logData["state"] = "warning";
            $logDescription[] = "Ошибка получения меток лидов";
        }
    }
    if (is_array($getlaedLabels["data"])) {
        foreach ($getlaedLabels["data"] as $oneGetLeadLabels) {
            if (is_array($input["label"])) {
                if (in_array($oneGetLeadLabels["name"], $input["label"])) {
                    $lead["label_ids"][] = $oneGetLeadLabels["id"];
                }
            } else {
                if ($oneGetLeadLabels["name"] == $input["label"]) {
                    $lead["label_ids"][] = $oneGetLeadLabels["id"];
                    break;
                }
            }
        }
    }
}
if ($input["amount"] != NULL) {
    $lead["value"]["amount"] = $input["amount"];
    settype($lead["value"]["amount"], "int");
    if ($input["currency"] != NULL) {
        $lead["value"]["currency"] = $input["currency"];
    }
}
if ($input["exceptDate"] != NULL) {
    $lead["expected_close_date"] = date("Y-m-d", strtotime($input["exceptDate"]));
}
foreach ($input as $fieldKey => $fieldValue) {
    if ($leadFields[$fieldKey] != NULL) {
        $lead[$leadFields[$fieldKey]] = $fieldValue;
    }
}

// Установка ответственного менеджера
if ($input["manager"] != NULL) {
    if (file_exists("pdData/manager.json") && $oldTime < $dateUpdate["manager"]) {
        $getManager = json_decode(file_get_contents("pdData/manager.json"), true);
        $log["manager"]["source"] = "file";
        $log["manager"]["data"] = $getManager;
    } else {
        $getManager = json_decode(send_request("https://api.pipedrive.com/v1/users?api_token=".$pdKey), true);
        if ($getManager["success"] === false) {
            sleep(1);
            $getManager = json_decode(send_request("https://api.pipedrive.com/v1/users?api_token=".$pdKey), true);
        }
        $log["manager"]["source"] = "API";
        $log["manager"]["data"] = $getManager;
        if ($getManager["success"] === true) {
            file_put_contents("pdData/manager.json", json_encode($getManager));
            $dateUpdate["manager"] = time();
        } else {
            $logData["state"] = "warning";
            $logDescription[] = "Ошибка получения данных о менеджерах";
        }
    }
    if (is_array($getManager["data"])) {
        foreach ($getManager["data"] as $oneManager) {
            if ($oneManager["email"] == $input["manager"]) {
                $contact["owner_id"] = $oneManager["id"];
                $lead["user_id"] = $oneManager["id"];
            }
        }
    }
}
// Создание/обновление контакта
if ($input["leadId"] == NULL) {
    // Создание контакта
    $createLead = json_decode(send_request("https://api.pipedrive.com/v1/leads?api_token=".$pdKey, [], "POST", $lead), true);
    if ($createLead["success"] === false) {
        sleep(1);
        $createLead = json_decode(send_request("https://api.pipedrive.com/v1/leads?api_token=".$pdKey, [], "POST", $lead), true);
    }
    if ($createLead["data"]["id"] != NULL) {
        $leadId = $createLead["data"]["id"];
        $logDescription[] = "Создан лид: ".$leadId;
        $result["leadId"] = $leadId;
        $result["action"] = "create";
        $result["result"] = $createLead;
    }  else {
        $logData["state"] = "false";
        $logDescription[] = "Ошибка создания лида";
        $result["state"] = false;
        $result["result"] = $createLead;
    }
    $log["lead"]["action"] = "create";
    $log["lead"]["send"] = $lead;
    $log["lead"]["result"] = $createLead;
} else {
    // Обновление контакта
    $updateLead = json_decode(send_request("https://api.pipedrive.com/v1/leads/".$input["leadId"]."?api_token=".$pdKey, [], "PATCH", $lead), true);
    if ($updateLead["success"] === false) {
        sleep(1);
        $updateLead = json_decode(send_request("https://api.pipedrive.com/v1/leads/".$input["leadId"]."?api_token=".$pdKey, [], "PATCH", $lead), true);
    }
    if ($updateLead["success"] === true) {
        $logDescription[] = "Обновлен лид ".$input["leadId"];
        $result["leadId"] = $input["leadId"];
        $result["action"] = "update";
        $result["result"] = $updateLead;
    } else {
        $logData["state"] = "warning";
        $logDescription[] = "Ошибка обновления лида";
        $result["state"] = false;
        $result["result"] = $updateLead;
    }
    $log["lead"]["action"] = "update";
    $log["lead"]["id"] = $input["leadId"];
    $log["lead"]["send"] = $lead;
    $log["lead"]["result"] = $updateLead;
}


file_put_contents("pdData/time.json", json_encode($dateUpdate));

echo json_encode($result);

// Логирование
$logData["description"] = implode("<br>", $logDescription);
send_forward(json_encode($log), $logUrl."?".http_build_query($logData));

