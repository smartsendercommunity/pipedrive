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
if ($input["text"] == NULL) {
    if ($input["noteId"] == NULL || $input["comment"] == NULL) {
        $onlyComment = true;
        $result["state"] = false;
        $result["error"]["message"][] = "'text' is missing";
    }
}
if ($result["state"] === false) {
    echo json_encode($result);
    exit;
} else {
    $result["state"] = true;
}

if ($onlyComment !== true) {
    $note["person_id"] = $contactId;
    $note["content"] = $input["text"];
    if ($input["leadId"] != NULL) {
        $note["lead_id"] = $input["leadId"];
        if ($input["pinLead"]) {
            $note["pinned_to_lead_flag"] = 1;
        }
    }
    if ($input["dealId"] != NULL) {
        $note["deal_id"] = $input["dealId"];
        if ($input["pinDeal"]) {
            $note["pinned_to_deal_flag"] = 1;
        }
    }
    if ($input["pinContact"]) {
        $note["pinned_to_person_flag"] = 1;
    }
    if ($input["noteId"] == NULL) {
        // Создание примечания
        $createNote = json_decode(send_request("https://api.pipedrive.com/v1/notes?api_token=".$pdKey, [], "POST", $note), true);
        if ($createNote["success"] === false) {
            sleep(1);
            $createNote = json_decode(send_request("https://api.pipedrive.com/v1/notes?api_token=".$pdKey, [], "POST", $note), true);
        }
        if ($createNote["data"]["id"] != NULL) {
            $noteId = $createNote["data"]["id"];
            $logDescription[] = "Создано примечание: ".$noteId;
            $result["noteId"] = $noteId;
            $result["action"] = "create";
            $result["result"] = $createNote;
        }  else {
            $logData["state"] = "false";
            $logDescription[] = "Ошибка создания примечания";
            $result["state"] = false;
            $result["result"] = $createNote;
        }
        $log["note"]["action"] = "create";
        $log["note"]["send"] = $note;
        $log["note"]["result"] = $createNote;
    } else {
        // Обновление примечания
        $createNote = json_decode(send_request("https://api.pipedrive.com/v1/notes/".$input["noteId"]."?api_token=".$pdKey, [], "PUT", $note), true);
        if ($createNote["success"] === false) {
            sleep(1);
            $createNote = json_decode(send_request("https://api.pipedrive.com/v1/notes/".$input["noteId"]."?api_token=".$pdKey, [], "PUT", $note), true);
        }
        if ($createNote["data"]["id"] != NULL) {
            $noteId = $createNote["data"]["id"];
            $logDescription[] = "Обновлено примечание: ".$noteId;
            $result["noteId"] = $noteId;
            $result["action"] = "update";
            $result["result"] = $createNote;
        }  else {
            $logData["state"] = "false";
            $logDescription[] = "Ошибка обновления примечания";
            $result["state"] = false;
            $result["result"] = $createNote;
        }
        $log["note"]["action"] = "update";
        $log["note"]["send"] = $note;
        $log["note"]["result"] = $createNote;
    }
}

if ($input["comment"] != NULL) {
    $comment["content"] = $input["comment"];
    $createComment = json_decode(send_request("https://api.pipedrive.com/v1/notes/".$noteId."/comments?api_token=".$pdKey, [], "POST", $comment), true);
    if ($createComment["success"] === false) {
        sleep(1);
        $createComment = json_decode(send_request("https://api.pipedrive.com/v1/notes/".$noteId."/comments?api_token=".$pdKey, [], "POST", $comment), true);
    }
    if ($createComment["data"]["uuid"] != NULL) {
        $commentId = $createComment["data"]["uuid"];
        $logDescription[] = "Добавлен коментарий: ".$commentId;
        $result["commentId"] = $commentId;
        $result["comment"] = $createComment;
    }  else {
        $logData["state"] = "false";
        $logDescription[] = "Ошибка добавления коментария";
        $result["state"] = false;
        $result["comment"] = $createComment;
    }
    $log["comment"]["action"] = "update";
    $log["comment"]["send"] = $comment;
    $log["comment"]["result"] = $createComment;
}

echo json_encode($result);
