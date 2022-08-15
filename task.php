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
if ($result["state"] === false) {
    echo json_encode($result);
    exit;
} else {
    $result["state"] = true;
}

if ($input["title"] != NULL) {
    $task["subject"] = $input["title"];
}
if ($input["time"] != NULL) {
    $time = strtotime($input["time"]);
    date_default_timezone_set('UTC');
    $task["due_date"] = date("Y-m-d", $time);
    $task["due_time"] = date("G:i", $time);
}
if ($input["duration"] != NULL) {
    $task["duration"] = $input["duration"];
}
if ($input["leadId"] != NULL) {
    $task["lead_id"] = $input["leadId"];
}
if ($input["dealId"] != NULL) {
    $task["deal_id"] = $input["dealId"];
}
$task["person_id"] = $contactId;
if ($input["text"] != NULL) {
    $task["note"] = $input["text"];
}
if ($input["location"] != NULL) {
    $task["location"] = $input["location"];
}
if ($input["description"] != NULL) {
    $task["public_description"] = $input["description"];
}
if ($input["type"] == "call" || $input["type"] == "meeting" || $input["type"] == "task" || $input["type"] == "deadline" || $input["type"] == "email" || $input["type"] == "lunch") {
    $task["type"] = $input["type"];
} else if ($input["type"] != NULL) {
    // Определение типа задачи
    $getType = json_decode(send_request("https://api.pipedrive.com/v1/activityTypes?api_token=".$pdKey), true);
    if ($getType["success"] !== true) {
        sleep(1);
        $getType = json_decode(send_request("https://api.pipedrive.com/v1/activityTypes?api_token=".$pdKey), true);
    }
    if ($getType["data"] != NULL && is_array($getType["data"])) {
        foreach ($getType["data"] as $oneType) {
            if ($oneType["name"] == $input["type"]) {
                $task["type"] = $oneType["key_string"];
                break;
            }
        }
    }
}
if ($input["done"] == true) {
    $task["done"] = 1;
}

if ($input["taskId"] == NULL) {
    $createTask = json_decode(send_request("https://api.pipedrive.com/v1/activities?api_token=".$pdKey, [], "POST", $task), true);
    if ($createTask["success"] === false) {
        sleep(1);
        $createTask = json_decode(send_request("https://api.pipedrive.com/v1/activities?api_token=".$pdKey, [], "POST", $task), true);
    }
    if ($createTask["data"]["id"] != NULL) {
        $taskId = $createTask["data"]["id"];
        $logDescription[] = "Создана задача: ".$taskId;
        $result["taskId"] = $taskId;
        $result["action"] = "create";
        $result["result"] = $createTask;
    }  else {
        $logData["state"] = "false";
        $logDescription[] = "Ошибка создания задачи";
        $result["state"] = false;
        $result["result"] = $createTask;
    }
    $log["task"]["action"] = "create";
    $log["task"]["send"] = $task;
    $log["task"]["result"] = $createTask;
} else {
    $createTask = json_decode(send_request("https://api.pipedrive.com/v1/activities/".$input["taskId"]."?api_token=".$pdKey, [], "PUT", $task), true);
    if ($createTask["success"] === false) {
        sleep(1);
        $createTask = json_decode(send_request("https://api.pipedrive.com/v1/activities/".$input["taskId"]."?api_token=".$pdKey, [], "PUT", $task), true);
    }
    if ($createTask["data"]["id"] != NULL) {
        $taskId = $createTask["data"]["id"];
        $logDescription[] = "Обновлена задача: ".$taskId;
        $result["taskId"] = $taskId;
        $result["action"] = "update";
        $result["result"] = $createTask;
    }  else {
        $logData["state"] = "false";
        $logDescription[] = "Ошибка обновления задачи";
        $result["state"] = false;
        $result["result"] = $createTask;
    }
    $log["task"]["action"] = "update";
    $log["task"]["send"] = $task;
    $log["task"]["result"] = $createTask;
}

echo json_encode($result);
