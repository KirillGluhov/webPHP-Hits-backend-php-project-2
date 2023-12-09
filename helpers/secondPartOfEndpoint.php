<?php
include_once "helpers/headers.php";

function routeSecondPartOfRoute($partOfRoute)
{
    $message = "";

    switch ($partOfRoute) {
        case "address":
            $message = "Вы отправили на адрес";
            break;
        case "author":
            $message = "Вы отправили на автора";
            break;
        case "comment":
            $message = "Вы отправили на комментарий";
            break;
        case "community":
            $message = "Вы отправили на сщщбщество";
            break;
        case "post":
            $message = "Вы отправили на пост";
            break;
        case "tag":
            $message = "Вы отправили на тег";
            break;
        case "account":
            $message = "Вы отправили на пользователей";
            break;
        default:
            $message = "Вы отправили в никуда";
            break;
    }

    if ($message == "Вы отправили в никуда") 
    {
        setHTTPStatus("404", $message);
    }
    else
    {
        setHTTPStatus("200", $message);
    }
    
}


?>