<?php
include_once "helpers/headers.php";
include_once "paths/user.php";
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
    if (file_get_contents('php://input'))
    {
        return json_decode(file_get_contents('php://input'), true);
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

//echo  $requestMethod ."   ". $token . "  ";
//print_r($params);
//print_r($requestURI);
//print_r($requestBody);

//$requestUri = $_SERVER['REQUEST_URI'];
//$requestMethod = $_SERVER['REQUEST_METHOD'];
//$requestBody = json_decode(file_get_contents('php://input'));
//$headers = apache_request_headers();

//echo $headers["Authorization"] . "   ";
//echo $requestUri . "   ";
//echo $requestMethod . "  ";
//print_r($requestBody);

//$token = $headers["Authorization"]

if (isset($requestURI[1]) && isset($requestURI[2]))
{
    if ($requestURI[1] == "api")
    {
        switch ($requestURI[2]) 
        {
            case 'address':
                # code...
                break;
            case 'author':
                # code...
                break;
            case 'comment':
                # code...
                break;
            case 'community':
                # code...
                break;
            case 'post':
                # code...
                break;
            case 'tag':
                # code...
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