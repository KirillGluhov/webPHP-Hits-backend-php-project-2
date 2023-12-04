<?php

function editComment($body, $token, $commentId)
{
    if (isset($token))
    {
        $Link = mysqli_connect("127.0.0.1", "root", "kirillgluhov", "blog");

        if (!$Link)
        {
            setHTTPStatus("500", "Ошибка соединения с БД " .mysqli_connect_error());
            exit;
        }
        else
        {
            $deleteOldTokens = $Link->query("DELETE FROM token WHERE `Действительно до` < NOW()");

            if ($deleteOldTokens)
            {
                $userWithThisToken = $Link->query("SELECT `Идентификатор пользователя` FROM token WHERE token.`Значение токена` = '$token'")->fetch_assoc();

                if (isset($userWithThisToken["Идентификатор пользователя"]))
                {
                    $userId = $userWithThisToken["Идентификатор пользователя"];

                    $isExistComment = $Link->query("SELECT * FROM `comment` WHERE id = '$commentId' AND deleteDate IS NULL;")->fetch_assoc();

                    if ($isExistComment)
                    {
                        $isUserCanEditComment = $Link->query("SELECT * FROM `comment` WHERE authorId = '$userId' AND id = '$commentId';")->fetch_assoc();

                        if ($isUserCanEditComment)
                        {
                            if (isset($body))
                            {
                                if (isset($body["content"]))
                                {
                                    $content = $body["content"];

                                    if (strlen($content) > 1000 || strlen($content) < 1)
                                    {
                                        setHTTPStatus("400", "Длина комментария должна входить в диапазон от 1 до 1000");
                                    }
                                    else
                                    {
                                        $currentTime = new DateTime();
                                        $expirationTime = $currentTime->format('Y-m-d H:i:s.u');

                                        $editComment = $Link->query("UPDATE comment SET `Содержимое` = '$content', `modifiedDate` = '$expirationTime' WHERE id = '$commentId'");

                                        if ($editComment)
                                        {
                                            bodyWithRequest("200", null);
                                        }
                                        else
                                        {
                                            setHTTPStatus("500", "Ошибка при изменении комментария " .$Link->error);
                                        }
                                    }
                                }
                                else
                                {
                                    setHTTPStatus("400", "Вы не передали комментарий (его содержимое)");
                                }
                            }
                            else
                            {
                                setHTTPStatus("400", "Вы не передали комментарий вообще");
                            }
                        }
                        else
                        {
                            setHTTPStatus("403", "Пользователь может редактировать только свои комментарии");
                        }
                    }
                    else
                    {
                        setHTTPStatus("404", "Комментарий не существует");
                    }
                }
                else
                {
                    setHTTPStatus("401", "Токен не подходит ни одному пользователю");
                }
            }
            else
            {
                setHTTPStatus("500", "Ошибка при удалении старых токенов " .$Link->error);
            }

            mysqli_close($Link);
        }
    }
    else
    {
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя, который создаёт аккаунт");
    }
}

function deleteComment($token, $commentId)
{
    if (isset($token))
    {
        $Link = mysqli_connect("127.0.0.1", "root", "kirillgluhov", "blog");

        if (!$Link)
        {
            setHTTPStatus("500", "Ошибка соединения с БД " .mysqli_connect_error());
            exit;
        }
        else
        {
            $deleteOldTokens = $Link->query("DELETE FROM token WHERE `Действительно до` < NOW()");

            if ($deleteOldTokens)
            {
                $userWithThisToken = $Link->query("SELECT `Идентификатор пользователя` FROM token WHERE token.`Значение токена` = '$token'")->fetch_assoc();

                if (isset($userWithThisToken["Идентификатор пользователя"]))
                {
                    $userId = $userWithThisToken["Идентификатор пользователя"];

                    $isExistComment = $Link->query("SELECT * FROM `comment` WHERE id = '$commentId' AND deleteDate IS NULL;")->fetch_assoc();

                    if ($isExistComment)
                    {
                        $isUserCanDeleteComment = $Link->query("SELECT * FROM `comment` WHERE authorId = '$userId' AND id = '$commentId';")->fetch_assoc();

                        if ($isUserCanDeleteComment)
                        {
                            $isHaveChildComments = $Link->query("SELECT * FROM `comment_hierarchy` WHERE parentId = '$commentId';")->fetch_assoc();

                            if ($isHaveChildComments)
                            {
                                $currentTime = new DateTime();
                                $expirationTime = $currentTime->format('Y-m-d H:i:s.u');

                                $changeStatusOfComment = $Link->query("UPDATE comment SET `Содержимое` = NULL, `deleteDate` = '$expirationTime' WHERE id = '$commentId'");

                                if ($changeStatusOfComment)
                                {
                                    bodyWithRequest("200", null);
                                }
                                else
                                {
                                    setHTTPStatus("500", "Ошибка при удалении комментария " .$Link->error);
                                }
                            }
                            else
                            {
                                $deleteComment = $Link->query("DELETE FROM comment WHERE id = '$commentId'");

                                if ($deleteComment)
                                {
                                    bodyWithRequest("200", null);
                                }
                                else
                                {
                                    setHTTPStatus("500", "Ошибка при удалении комментария " .$Link->error);
                                }
                            }
                        }
                        else
                        {
                            setHTTPStatus("403", "Пользователь может удалять только свои комментарии");
                        }
                    }
                    else
                    {
                        setHTTPStatus("404", "Комментарий не существует");
                    }
                }
                else
                {
                    setHTTPStatus("401", "Токен не подходит ни одному пользователю");
                }
            }
            else
            {
                setHTTPStatus("500", "Ошибка при удалении старых токенов " .$Link->error);
            }

            mysqli_close($Link);
        }
    }
    else
    {
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя, который создаёт аккаунт");
    }
}

function getAllCommentsFromLowLevels($parentIdComment)
{
    $Link = mysqli_connect("127.0.0.1", "root", "kirillgluhov", "blog");

    if (!$Link)
    {
        setHTTPStatus("500", "Ошибка соединения с БД " .mysqli_connect_error());
        exit;
    }
    else
    {
        $isExistComment = $Link->query("SELECT * FROM `comment` WHERE id = '$parentIdComment';")->fetch_assoc();

        if ($isExistComment)
        {
            $isRootComment = $Link->query("SELECT * FROM comment_hierarchy WHERE commentId = '$parentIdComment' AND parentId IS NULL;")->fetch_assoc();

            if ($isRootComment)
            {
                $postId = $isExistComment["postId"];

                $allCommentsFromPost = $Link->query("SELECT comment_hierarchy.`Путь`, c.*, `user`.`ФИО` FROM `comment` c
                LEFT JOIN comment_hierarchy ON comment_hierarchy.commentId = c.id
                LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = c.authorId
                WHERE postid = '$postId' ORDER BY c.createTime;");

                $body = [];

                while ($row = $allCommentsFromPost->fetch_assoc()) 
                {
                    $allPartsOfPath = explode(".", $row["Путь"]);

                    if (count($allPartsOfPath) > 1 && $allPartsOfPath[0] == $parentIdComment)
                    {
                        $body[] = array(
                            "content" => $row["Содержимое"],
                            "modifiedDate" => $row["modifiedDate"],
                            "deleteDate" => $row["deleteDate"],
                            "authorId" => $row["authorId"],
                            "author" => $row["ФИО"],
                            "subComments" => intval($row["subcomments"]),
                            "id" => $row["id"],
                            "createTime" => $row["createTime"]
                        );
                    }
                }
                $allCommentsFromPost->free();

                bodyWithRequest ("200", $body);
            }
            else
            {
                setHTTPMessage("Error", "400", "Комментарий с id = " . $parentIdComment . " не корневой");
            }
        }
        else
        {
            setHTTPStatus("404", "Такого комментария нет");
        }
        mysqli_close($Link);
    }
}

function editOrDeleteComment($method, $body, $token, $commentId)
{
    if ($method == "PUT")
    {
        editComment($body, $token, $commentId);
    }
    else if ($method == "DELETE")
    {
        deleteComment($token, $commentId);
    }
    else
    {
        setHTTPStatus("400", "Не тот метод");
    }
}

function commentEndpoints($method, $uriList, $body, $token)
{
    if (isset($uriList[3]))
    {
        if (isset($uriList[4]))
        {
            if ($uriList[4] == "tree")
            {
                if (isset($uriList[5]))
                {
                    setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
                }
                else
                {
                    if ($method == "GET")
                    {
                        getAllCommentsFromLowLevels($uriList[3]);
                    }
                    else
                    {
                        setHTTPStatus("400", "Не тот метод");
                    }
                }
            }
            else
            {
                setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
            }
        }
        else
        {
            editOrDeleteComment($method, $body, $token, $uriList[3]);
        }
    }
    else
    {
        setHTTPStatus("404", "Вы отправили запрос на несуществующую часть api");
    }
}

?>