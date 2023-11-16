<?php
include_once "helpers/headers.php";
include_once "helpers/secondPartOfEndpoint.php";

header("Content-type: application/json");

if (isset($_SERVER['REDIRECT_URL']))
{
    $originalURL = $_SERVER['REDIRECT_URL'];

    $urlList = explode('/', ltrim($originalURL, '/'));

    $router = $urlList[0];

    if ($router == 'api') 
    {
        if (isset($urlList[1]))
        {
            routeSecondPartOfRoute($urlList[1]);
        }
        else
        {
            setHTTPStatus("404", "Вы отправили запрос на api, но нужно отправлять на конкретный эндпоинт");
        }
    }
    else
    {
        setHTTPStatus("404", "Вы отправили запрос не на /api, а на нечто иное");
    }
}
else
{
    setHTTPStatus("404", "Вы отправили запрос не на api");
}

?>