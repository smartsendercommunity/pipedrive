<?php

include('config.php');

$input = json_decode(file_get_contents("php://input"), true);

$log["url"] = $url;

if ($input["userId"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "'userId' is missing";
}
if ($input["fullName"] == NULL && $input["firstName"] == NULL && $input["lastName"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "'firstName' or 'lastName' or 'fullName' is missing";
}
if ($result["state"] === false) {
    echo json_encode($result);
    exit;
} else {
    $result["state"] = true;
}

if (file_exists('pdData/users.json')) {
    $users = json_decode(file_get_contents('pdData/users.json'), true);
    $contactId = $users[$input['userId']];
}
if (file_exists("pdData/time.json")) {
    $dateUpdate = json_decode(file_get_contents("pdData/time.json"), true);
}

// Поиск контакта
if ($input["phone"] != NULL) {
    $input["phone"] = str_ireplace([" ", "(", ")", "-", "+", "'"], "", $input["phone"]);
    if ($contactId == NULL) {
        $searchContact = json_decode(send_request("https://api.pipedrive.com/v1/persons/search?api_token=".$pdKey."&term=".$input["phone"]), true);
        $log["contact"]["search"]["phone"] = $searchContact;
        if ($searchContact["data"]["items"] != NULL && is_array($searchContact["data"]["items"])) {
            foreach ($searchContact["data"]["items"] as $oneSearchContact) {
                if ($oneSearchContact["item"]["phones"] != NULL && is_array($oneSearchContact["item"]["phones"])) {
                    foreach ($oneSearchContact["item"]["phones"] as $checkPhone) {
                        $checkPhone = str_ireplace([" ", "(", ")", "-", "+"], "", $checkPhone);
                        if ($checkPhone == $input["phone"]) {
                            $contactId = $oneSearchContact["item"]["id"];
                            if ($oneSearchContact["item"]["emails"] != NULL && is_array($oneSearchContact["item"]["emails"])) {
                                $emails = $oneSearchContact["item"]["emails"];
                            }
                            $ownerId = $oneSearchContact["item"]["owner"]["id"];
                            break 2;
                        }
                    }
                }
            }
        }
    }
}
if ($input["email"] != NULL) {
    $input["email"] = strtolower($input["email"]);
    if ($contactId == NULL) {
        $searchContact = json_decode(send_request("https://api.pipedrive.com/v1/persons/search?api_token=".$pdKey."&term=".$input["email"]), true);
        $log["contact"]["search"]["email"] = $searchContact;
        if ($searchContact["data"]["items"] != NULL && is_array($searchContact["data"]["items"])) {
            foreach ($searchContact["data"]["items"] as $oneSearchContact) {
                if ($oneSearchContact["item"]["emails"] != NULL && is_array($oneSearchContact["item"]["emails"])) {
                    foreach ($oneSearchContact["item"]["emails"] as $checkEmail) {
                        $checkEmail = strtolower($checkEmail);
                        if ($checkEmail == $input["email"]) {
                            $contactId = $oneSearchContact["item"]["id"];
                            if ($oneSearchContact["item"]["phones"] != NULL && is_array($oneSearchContact["item"]["phones"])) {
                                $phones = $oneSearchContact["item"]["phones"];
                            }
                            $ownerId = $oneSearchContact["item"]["owner"]["id"];
                            break 2;
                        }
                    }
                }
            }
        }
    }
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
// Дополнение списка контактов (телефон, почта)
if ($phones != NULL && is_array($phones)) {
    foreach ($phones as $getPhone) {
        $getPhone = str_ireplace([" ", "(", ")", "-", "+"], "", $getPhone);
        $sendContact["phone"][]["value"] = $getPhone;
        if ($getPhone == $input["phone"]) {
            $addedPhone = true;
        }
    }
}
if ($addedPhone !== true) {
    $sendContact["phone"][]["value"] = $input["phone"];
}
if ($emails != NULL && is_array($emails)) {
    foreach ($emails as $getEmail) {
        $getEmail = strtolower($getEmail);
        $sendContact["email"][]["value"] = $getEmail;
        if ($getEmail == $input["email"]) {
            $addedEmail = true;
        }
    }
}
if ($addedEmail !== true) {
    $sendContact["email"][]["value"] = $input["email"];
}
// Дополнение всех остальных полей
if ($input["fullName"] != NULL) {
    $sendContact["name"] = $input["fullName"];
} else {
    $sendContact["name"] = $input["firstName"]." ".$input["lastName"];
}
if ($input["label"] != NULL) {
    $sendContact["label"] = $input["label"];
}
foreach ($input as $fieldKey => $fieldValue) {
    if ($contactFields[$fieldKey] != NULL) {
        $sendContact[$contactFields[$fieldKey]] = $fieldValue;
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
if ($contactId == NULL) {
    // Создание контакта
    $createContact = json_decode(send_request("https://api.pipedrive.com/v1/persons?api_token=".$pdKey, [], "POST", $sendContact), true);
    if ($createContact["success"] === false) {
        sleep(1);
        $createContact = json_decode(send_request("https://api.pipedrive.com/v1/persons?api_token=".$pdKey, [], "POST", $sendContact), true);
    }
    if ($createContact["data"]["id"] != NULL) {
        $contactId = $createContact["data"]["id"];
        if (file_exists('pdData/users.json')) {
            $users = json_decode(file_get_contents('pdData/users.json'), true);
        }
        $users[$input["userId"]] = $contactId;
        file_put_contents('pdData/users.json', json_encode($users));
        $logDescription[] = "Создан контакт: ".$contactId;
        $result["contactId"] = $contactId;
        $result["action"] = "create";
        $result["result"] = $createContact;
    }  else {
        $logData["state"] = "false";
        $logDescription[] = "Ошибка создания контакта";
        $result["state"] = false;
        $result["result"] = $createContact;
    }
    $log["contact"]["action"] = "create";
    $log["contact"]["send"] = $sendContact;
    $log["contact"]["result"] = $createContact;
} else {
    // Обновление контакта
    $updateContact = json_decode(send_request("https://api.pipedrive.com/v1/persons/".$contactId."?api_token=".$pdKey, [], "PUT", $sendContact), true);
    if ($updateContact["success"] === false) {
        sleep(1);
        $updateContact = json_decode(send_request("https://api.pipedrive.com/v1/persons/".$contactId."?api_token=".$pdKey, [], "PUT", $sendContact), true);
    }
    if ($updateContact["success"] === true) {
        $logDescription[] = "Обновлен контакт ".$contactId;
        $result["contactId"] = $contactId;
        $result["action"] = "update";
        $result["result"] = $updateContact;
    } else {
        $logData["state"] = "warning";
        $logDescription[] = "Ошибка обновления контакта";
        $result["state"] = false;
        $result["result"] = $updateContact;
    }
    $log["contact"]["action"] = "update";
    $log["contact"]["id"] = $contactId;
    $log["contact"]["send"] = $sendContact;
    $log["contact"]["result"] = $updateContact;
}

if ($input["photo"] != NULL) {
    file_put_contents("pdData/tempPhoto".$input["userId"].".jpg", file_get_contents($input["photo"]));
    $photo["file"] =  new CURLFILE("pdData/tempPhoto".$input["userId"].".jpg", 'image/jpeg',);
    $sendPhoto = json_decode(send_request("https://api.pipedrive.com/v1/persons/".$contactId."/picture?api_token=".$pdKey, [], "POST", $photo, "data"), true);
    $result["addedPhoto"] = $sendPhoto;
}

file_put_contents("pdData/time.json", json_encode($dateUpdate));

echo json_encode($result);

// Логирование
$logData["description"] = implode("<br>", $logDescription);
send_forward(json_encode($log), $logUrl."?".http_build_query($logData));

