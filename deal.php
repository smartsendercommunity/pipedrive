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
if ($input["title"] == NULL && $input["dealId"] == NULL) {
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
    $lead["label"] = $input["label"];
}
if ($input["amount"] != NULL) {
    $lead["value"] = $input["amount"];
    settype($lead["value"], "int");
    if ($input["currency"] != NULL) {
        $lead["currency"] = $input["currency"];
    }
}
if ($input["pipeline"] != NULL) {
    if (file_exists("pdData/pipelines.json") && $oldTime < $dateUpdate["pipelines"]) {
        $getPipelines = json_decode(file_get_contents("pdData/pipelines.json"), true);
        $log["pipelines"]["source"] = "file";
        $log["pipelines"]["data"] = $getPipelines;
    } else {
        $getPipelines = json_decode(send_request("https://api.pipedrive.com/v1/pipelines?api_token=".$pdKey), true);
        if ($getPipelines["success"] === false) {
            sleep(1);
            $getPipelines = json_decode(send_request("https://api.pipedrive.com/v1/pipelines?api_token=".$pdKey), true);
        }
        $log["pipelines"]["source"] = "API";
        $log["pipelines"]["data"] = $getPipelines;
        if ($getPipelines["success"] === true) {
            file_put_contents("pdData/pipelines.json", json_encode($getPipelines));
            $dateUpdate["pipelines"] = time();
        } else {
            $logData["state"] = "warning";
            $logDescription[] = "Ошибка получения воронок";
        }
    }
    if (is_array($getPipelines["data"])) {
        foreach ($getPipelines["data"] as $onePipeline) {
            if ($input["pipeline"] == $onePipeline["name"]) {
                $lead["pipeline_id"] = $onePipeline["id"];
                break;
            }
        }
    }
}
if ($input["stage"] != NULL) {
    if (file_exists("pdData/stages_for_".$lead["pipeline_id"].".json") && $oldTime < $dateUpdate["stages_for_".$lead["pipeline_id"]]) {
        $getStages = json_decode(file_get_contents("pdData/stages_for_".$lead["pipeline_id"].".json"), true);
        $log["stages_for_".$lead["pipeline_id"]]["source"] = "file";
        $log["stages_for_".$lead["pipeline_id"]]["data"] = $getStages;
    } else {
        $getStages = json_decode(send_request("https://api.pipedrive.com/v1/stages?api_token=".$pdKey."&pipeline_id=".$lead["pipeline_id"]), true);
        if ($getStages["success"] === false) {
            sleep(1);
            $getStages = json_decode(send_request("https://api.pipedrive.com/v1/stages?api_token=".$pdKey."&pipeline_id=".$lead["pipeline_id"]), true);
        }
        $log["stages_for_".$lead["pipeline_id"]]["source"] = "API";
        $log["stages_for_".$lead["pipeline_id"]]["data"] = $getStages;
        if ($getStages["success"] === true) {
            file_put_contents("pdData/stages_for_".$lead["pipeline_id"].".json", json_encode($getStages));
            $dateUpdate["stages_for_".$lead["pipeline_id"]] = time();
        } else {
            $logData["state"] = "warning";
            $logDescription[] = "Ошибка получения этапов воронки ".$lead["pipeline_id"];
        }
    }
    if (is_array($getStages["data"])) {
        foreach ($getStages["data"] as $oneStage) {
            if ($oneStage["name"] == $input["stage"]) {
                $lead["stage_id"] = $oneStage["id"];
            }
        }
    }
}
if ($input["status"] == "won") {
    $lead["status"] = "won";
} else if ($input["status"] == "lost") {
    $lead["status"] = "lost";
    if ($input["reason"] != NULL) {
        $lead["lost_reason"] = $input["reason"];
    }
} else if ($input["status"] == "open") {
    $lead["status"] = $input["status"];
}
if ($input["exceptDate"] != NULL) {
    $lead["expected_close_date"] = date("Y-m-d", strtotime($input["exceptDate"]));
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
if ($input["dealId"] == NULL) {
    // Создание контакта
    $createLead = json_decode(send_request("https://api.pipedrive.com/v1/deals?api_token=".$pdKey, [], "POST", $lead), true);
    if ($createLead["success"] === false) {
        sleep(1);
        $createLead = json_decode(send_request("https://api.pipedrive.com/v1/deals?api_token=".$pdKey, [], "POST", $lead), true);
    }
    if ($createLead["data"]["id"] != NULL) {
        $dealId = $createLead["data"]["id"];
        $logDescription[] = "Создана сделка: ".$dealId;
        $result["dealId"] = $dealId;
        $result["action"] = "create";
        $result["result"] = $createLead;
    }  else {
        $logData["state"] = "false";
        $logDescription[] = "Ошибка создания сделки";
        $result["state"] = false;
        $result["result"] = $createLead;
    }
    $log["deal"]["action"] = "create";
    $log["deal"]["send"] = $deal;
    $log["deal"]["result"] = $createLead;
} else {
    // Обновление контакта
    $updateLead = json_decode(send_request("https://api.pipedrive.com/v1/deals/".$input["dealId"]."?api_token=".$pdKey, [], "PUT", $lead), true);
    if ($updateLead["success"] === false) {
        sleep(1);
        $updateLead = json_decode(send_request("https://api.pipedrive.com/v1/deals/".$input["dealId"]."?api_token=".$pdKey, [], "PUT", $lead), true);
    }
    if ($updateLead["success"] === true) {
        $logDescription[] = "Обновлена сделка ".$input["dealId"];
        $result["dealId"] = $input["dealId"];
        $result["action"] = "update";
        $result["result"] = $updateLead;
    } else {
        $logData["state"] = "warning";
        $logDescription[] = "Ошибка обновления сделки";
        $result["state"] = false;
        $result["result"] = $updateLead;
    }
    $log["deal"]["action"] = "update";
    $log["deal"]["id"] = $input["dealId"];
    $log["deal"]["send"] = $deal;
    $log["deal"]["result"] = $updateLead;
}


file_put_contents("pdData/time.json", json_encode($dateUpdate));

echo json_encode($result);

// Логирование
$logData["description"] = implode("<br>", $logDescription);
send_forward(json_encode($log), $logUrl."?".http_build_query($logData));

