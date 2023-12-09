<?php
include_once "helpers/headers.php";
<<<<<<< HEAD
=======
include_once "../localhost/config.php";
>>>>>>> globals

include_once "paths/user.php";
include_once "paths/address.php";
include_once "paths/tag.php";
include_once "paths/community.php";
include_once "paths/post.php";
include_once "paths/author.php";
include_once "paths/comment.php";
function getURI()
{
    $url = $_SERVER['REQUEST_URI'];
    $urlList = explode("?", $url);
    $urlList[0] = rtrim($urlList[0],"/");
    $urlListParts = explode("/", $urlList[0]);
    return $urlListParts;
}

function getParams()
{
    $url = $_SERVER['REQUEST_URI'];
    $urlList = explode("?", $url);
    if (isset($urlList[1])) 
    {
        $urlListParts = explode("&", $urlList[1]);
        return $urlListParts;
    }
    else
    {
        return null;
    }
}

function getMethod()
{
    return $_SERVER['REQUEST_METHOD'];
}

function getBody()
{
    $input = file_get_contents('php://input');

    if ($input !== false) 
    {
        $input = preg_replace('/,\s*([\]}])/m', '$1', $input);
        $jsonData = json_decode($input, true);
    
        if ($jsonData !== null) 
        {
            return $jsonData;
        } 
        else 
        {
            return null;
        }
    } 
    else 
    {
        return null;
    }
}

function getToken()
{
    $headers = apache_request_headers();

    if (isset($headers["Authorization"]))
    {
        if (preg_match('/Bearer\s(\S+)/', $headers["Authorization"], $matches)) {
            return $matches[1];
        }
    }
}

header("Content-type: application/json");

$requestURI = getURI();
$params = getParams();
$requestMethod = getMethod();
$requestBody = getBody();
$token = getToken();


if (isset($requestURI[1]) && isset($requestURI[2]))
{
    if ($requestURI[1] == "api")
    {
        switch ($requestURI[2]) 
        {
            case 'address':
                addressRequestAnswer($requestMethod, $requestURI, $params);
                break;
            case 'author':
                authorEndpoints($requestMethod, $requestURI);
                break;
            case 'comment':
                commentEndpoints($requestMethod, $requestURI, $requestBody, $token);
                break;
            case 'community':
                communityEndPoints($requestMethod, $requestURI, $requestBody, $params, $token);
                break;
            case 'post':
                posts($requestMethod, $requestURI, $requestBody, $params, $token);
                break;
            case 'tag':
                allTags($requestMethod, $requestURI);
                break;
            case 'account':
                userRequestAnswer($requestMethod, $requestURI, $requestBody, $params, $token);
                break;
            default:
                setHTTPStatus("404", "Вы отправили запрос на несуществующую часть api");
                break;
        }
    }
    else
    {
        setHTTPStatus("404", "Вы отправили запрос не на api");
    }

}
else
{
    setHTTPStatus("404", "Вы отправили запрос не на api");
}


?>