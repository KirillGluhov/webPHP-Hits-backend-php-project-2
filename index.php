<?php
include_once "helpers/headers.php";

header("Content-type: application/json");

if (isset($_SERVER['REDIRECT_URL']))
{
    $originalURL = $_SERVER['REDIRECT_URL'];

    $urlList = explode('/', ltrim($originalURL, '/'));

    $router = $urlList[0];

    if ($router == 'api' && rtrim($originalURL, '/') == $originalURL) 
    {
        if (isset($urlList[1]))
        {
            echo $urlList[1];
            echo 'Вы обратились по нужному адресу';
        }
        else
        {
            setHTTPStatus("404", "Вы отправили запрос не на api");
        }
    }
    else if (rtrim($originalURL, '/') == $originalURL)
    {
        setHTTPStatus("404", "Вы отправили запрос не на api");
    }
    else
    {
        setHTTPStatus("404", "Вы отправили запрос на /api/, а не на эндпоинт");
    }
}
else
{
    setHTTPStatus("404", "Вы отправили запрос не на api");
}

?>