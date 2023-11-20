<?php

function setHTTPStatus($status = null, $message = null, $errors = null)
{
    switch ($status) 
    {
        default:
        case '200':
            $status = "HTTP/1.0 200 OK";
            break;
        case "400":
            $status = "HTTP/1.0 400 Bad Request";
            break;
        case "401":
            $status = "HTTP/1.0 401 Unauthorized";
            break;
        case "403":
            $status = "HTTP/1.0 403 Forbidden";
            break;
        case "404":
            $status = "HTTP/1.0 404 Not Found";
            break;
        case "409":
            $status = "HTTP/1.0 409 Conflict";
            break;
        case "500":
            $status = "HTTP/1.0 500 Iternal Server Error";
            break;
    }

    header($status);

    if (!is_null($message) && !is_null($errors) && !is_null($status))
    {
        echo json_encode(['message' => $message, "status" => $status, "errors"=> $errors]);
    }
    else if (!is_null($message) && !is_null($status))
    {
        echo json_encode(['message' => $message, "status" => $status]);
    }
    else if (!is_null($message) && !is_null($errors))
    {
        echo json_encode(['message' => $message, "errors" => $errors]);
    }
    else if (!is_null($status) && !is_null($errors))
    {
        echo json_encode(['status' => $status, "errors" => $errors]);
    }
    else if (!is_null($status))
    {
        echo json_encode(['status' => $status]);
    }
    else if (!is_null($errors))
    {
        echo json_encode(["errors" => $errors]);
    }
    else if (!is_null($status))
    {
        echo json_encode(["status" => $status]);
    }
}

function bodyWithRequest ($status = null, $body = null)
{
    switch ($status) 
    {
        default:
        case '200':
            $status = "HTTP/1.0 200 OK";
            break;
        case "400":
            $status = "HTTP/1.0 400 Bad Request";
            break;
        case "401":
            $status = "HTTP/1.0 401 Unauthorized";
            break;
        case "403":
            $status = "HTTP/1.0 403 Forbidden";
            break;
        case "404":
            $status = "HTTP/1.0 404 Not Found";
            break;
        case "409":
            $status = "HTTP/1.0 409 Conflict";
            break;
        case "500":
            $status = "HTTP/1.0 500 Iternal Server Error";
            break;
    }

    header($status);

    if (!is_null($body))
    {
        echo json_encode($body);
    }
}

?>