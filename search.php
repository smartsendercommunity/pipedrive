<?php

include("config.php");

$input = json_decode(file_get_contents("php://input"), true);
if ($input["contact"]["field"] != NULL && $input["contact"]["value"] != NULL) {
    $search = "contact";
} else if ($input["deal"]["field"] != NULL && $input["deal"]["value"] != NULL) {
    $search = "deal";
} else {
    $result["state"] = false;
    $result["error"]["message"][] = "not enough data to search";
    echo json_encode($result);
    exit;
}

// Поиск контакта
if ($search == "contact") {
    $searchContact = json_decode(send_request("https://api.pipedrive.com/v1/persons/search?api_token=".$pdKey."&limit=500&term=".urlencode($input["contact"]["value"])."&fields=custom_fields"), true);
    if ($searchContact["data"]["items"] != NULL) {
        foreach ($searchContact["data"]["items"] as $oneContact) {
            $checkContact = json_decode(send_request("https://api.pipedrive.com/v1/persons/".$oneContact["item"]["id"]."?api_token=".$pdKey), true);
            if ($checkContact["data"][$input["contact"]["field"]] == $input["contact"]["value"]) {
                $result["contact"] = $checkContact["data"];
                if ($input["userId"] != NULL && $input["bind"] == true && $checkContact["data"]["id"] != NULL) {
                    if (file_exists('pdData/users.json')) {
                        $users = json_decode(file_get_contents('pdData/users.json'), true);
                    }
                    $users[$input["userId"]] = $checkDeal["data"]["id"];
                    file_put_contents('pdData/users.json', json_encode($users));
                }
                break;
            }
        }
    }
}

// Поиск сделки
if ($search == "deal") {
    $searchDeal = json_decode(send_request("https://api.pipedrive.com/v1/deals/search?api_token=".$pdKey."&limit=500&term=".urlencode($input["deal"]["value"])."&fields=custom_fields"), true);
    if ($searchDeal["data"]["items"] != NULL) {
        foreach ($searchDeal["data"]["items"] as $oneDeal) {
            $checkDeal = json_decode(send_request("https://api.pipedrive.com/v1/deals/".$oneDeal["item"]["id"]."?api_token=".$pdKey), true);
            if ($checkDeal["data"][$input["deal"]["field"]] == $input["deal"]["value"]) {
                $result["deal"] = $checkDeal["data"];
                if ($input["userId"] != NULL && $input["bind"] == true && $checkDeal["data"]["person_id"]["value"] != NULL) {
                    if (file_exists('pdData/users.json')) {
                        $users = json_decode(file_get_contents('pdData/users.json'), true);
                    }
                    $users[$input["userId"]] = $checkDeal["data"]["person_id"]["value"];
                    file_put_contents('pdData/users.json', json_encode($users));
                }
                break;
            }
        }
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
