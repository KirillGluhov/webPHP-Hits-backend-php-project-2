<?php

require_once 'vendor/autoload.php';

use Ramsey\Uuid\Uuid;

function isValidUuid($uuid)
{
    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    return preg_match($pattern, $uuid);
}

function getAllCommunities()
{
    $Link = mysqli_connect("127.0.0.1", "root", "kirillgluhov", "blog");

    if (!$Link) 
    {
        setHTTPStatus("500", "Ошибка соединения с БД " .mysqli_connect_error());
        exit;
    }
    else
    {
        $communityList = [];

        $communityListFromDB = $Link->query("SELECT * FROM community;");

        if ($communityListFromDB)
        {
            while ($row = $communityListFromDB->fetch_assoc()) 
            {
                $communityList[] = $row;
            }
            $communityListFromDB->free();

            $body = [];

            foreach ($communityList as $row)
            {
                $body[] = array(
                    "name"=> $row["Название"],
                    "description"=> $row["Описание"],
                    "isClosed"=> ($row["Закрытость"] == "0") ? false : true,
                    "subscribersCount" => intval($row["Число подписчиков"]),
                    "id" => $row["id"],
                    "createTime" => str_replace(" ", "T", $row["Дата создания"])
                );
            }

            bodyWithRequest("200", $body);
        }
        else
        {
            setHTTPStatus("500", "Ошибка при попытке получения списка сообществ " .mysqli_connect_error());
            exit;
        }

    }
}

function communitiesOfUser($token)
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
                    $adminCommunity = [];
                    $subscriberCommunity = [];

                    $userId = $userWithThisToken["Идентификатор пользователя"];

                    $communitiesWhereAdministrator = $Link-> query("SELECT DISTINCT communityId
                    FROM `user-community`
                    WHERE `Роль` = 'Administrator' AND userId = '$userId';");

                    $communitiesWhereSubscriberAndNotAdministrator = $Link->query("SELECT DISTINCT communityId
                    FROM `user-community`
                    WHERE communityId NOT IN (
                        SELECT communityId
                        FROM `user-community`
                        WHERE `Роль` = 'Administrator' AND userId = '$userId'
                    )
                    AND `Роль` = 'Subscriber'AND userId = '$userId';");

                    if ($communitiesWhereAdministrator && $communitiesWhereSubscriberAndNotAdministrator)
                    {
                        while ($row = $communitiesWhereAdministrator->fetch_assoc()) 
                        {
                            $adminCommunity[] = $row['communityId'];
                        }
                        $communitiesWhereAdministrator->free();

                        while ($row = $communitiesWhereSubscriberAndNotAdministrator->fetch_assoc())
                        {
                            $subscriberCommunity[] = $row['communityId'];
                        }
                        $communitiesWhereSubscriberAndNotAdministrator->free();

                        $body = [];

                        foreach($adminCommunity as $row)
                        {
                            $body[] = array(
                                "userId" => $userId,
                                "communityId" => $row,
                                "role"=> "Administrator",
                            );
                        }

                        foreach($subscriberCommunity as $row)
                        {
                            $body[] = array(
                                "userId" => $userId,
                                "communityId" => $row,
                                "role"=> "Subscriber",
                            );
                        }

                        bodyWithRequest ("200", $body);
                    }
                    else
                    {
                        setHTTPStatus("500", "Ошибка при получении сообществ" .$Link->error);
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

function infoAboutCommunity($uuid)
{
    if (isValidUuid($uuid))
    {
        $Link = mysqli_connect("127.0.0.1", "root", "kirillgluhov", "blog");

        if (!$Link)
        {
            setHTTPStatus("500", "Ошибка соединения с БД " .mysqli_connect_error());
            exit;
        }
        else
        {
            $body = [];
            $admins = [];

            $communityInfoFromDB = $Link->query("SELECT * FROM community
            WHERE id = '$uuid';")->fetch_assoc();

            $administratorsFromDB = $Link->query("SELECT userId, `Дата создания`, `ФИО`, `День рождения`, `Пол`, `Email`, `Телефон` FROM `user-community`
            INNER JOIN user ON user.`Идентификатор пользователя` = `user-community`.userId
            WHERE communityId = '$uuid' AND `Роль` = 'Administrator';");

            if ($administratorsFromDB && $communityInfoFromDB)
            {
                while ($row = $administratorsFromDB->fetch_assoc()) 
                {
                    $admins[] = array(
                        "id"=> $row["userId"],
                        "createTime" => str_replace(" ", "T", $row["Дата создания"]),
                        "fullName" => $row["ФИО"],
                        "birthDate"=> str_replace(" ", "T", $row["День рождения"]),
                        "gender"=> $row["Пол"],
                        "email"=> $row["Email"],
                        "phoneNumber"=> $row["Телефон"]
                    );
                }
                $administratorsFromDB->free();

                $body[] = array(
                    "id" => $communityInfoFromDB["id"],
                    "createDate" => str_replace(" ", "T", $communityInfoFromDB["Дата создания"]),
                    "name" => $communityInfoFromDB["Название"],
                    "description" => $communityInfoFromDB["Описание"],
                    "isClosed" => ($communityInfoFromDB["Закрытость"] == "0") ? false : true,
                    "subscribersCount" => intval($communityInfoFromDB["Число подписчиков"]),
                    "administrators" => $admins
                );

                bodyWithRequest("200", $body);
            }
            else if ($administratorsFromDB)
            {
                setHTTPStatus("404", "С таким UUID нет сообществ");
            }
            else
            {
                setHTTPStatus("500", "Ошибка при получении сообщества и администраторов" .$Link->error);
            }
        }

        mysqli_close($Link);
    }
    else
    {
        setHTTPStatus("404","Из-за неподходящего под формат UUID идентификатора сообщества, никакая информация о сообществе не может быть найдена");
    }
}

function getRoleOfUserInCommunity($id, $token)
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

                    $userRole = $Link->query("SELECT * FROM `user-community` WHERE userId = '$userId' AND communityId = '$id';");

                    if ($userRole)
                    {
                        $roles = [];

                        if ($userRole->num_rows > 1)
                        {
                            while ($row = $userRole->fetch_assoc()) 
                            {
                                $roles[] = $row["Роль"];
                            }
                            $userRole->free();

                            $flag = false;

                            for ($i = 0; $i < count($roles); $i++)
                            {
                                if ($roles[$i] == "Administrator")
                                {
                                    bodyWithRequest("200", $roles[$i]);
                                    $flag = true;
                                    break;
                                }
                            }

                            if (!$flag)
                            {
                                bodyWithRequest("200", "Subscriber");
                            }
                        }
                        else if ($userRole->num_rows == 1)
                        {
                            $hisRole = $userRole->fetch_assoc();
                            bodyWithRequest("200", $hisRole["Роль"]);
                        }
                        else
                        {
                            bodyWithRequest("200", null, 1);
                        }
                    }
                    else
                    {
                        setHTTPStatus("500", "Ошибка при получении роли " .$Link->error);
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
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя, которые создаёт аккаунт");
    }
}

function subscribeUserToCommunity($id, $token)
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

                    $isSubscribe = $Link->query("SELECT * FROM `user-community` WHERE userId = '$userId' AND communityId = '$id' AND `Роль` = 'Subscriber';")->fetch_assoc();

                    if ($isSubscribe)
                    {
                        setHTTPStatus("400", "Пользователь и так уже подписан на сообщество");
                    }
                    else
                    {
                        $subscribe = $Link->query("INSERT INTO `user-community`(`userId`, `communityId`, `Роль`) VALUES('$userId', '$id', 'Subscriber')");

                        if ($subscribe)
                        {
                            bodyWithRequest("200", null);
                        }
                        else
                        {
                            setHTTPStatus("500", "Ошибка при попытке подписать пользователя на сообщество " .$Link->error);
                        }
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
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя, которые создаёт аккаунт");
    }
}

function unsubscribeUserToCommunity($id, $token)
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

                    $isSubscribe = $Link->query("SELECT * FROM `user-community` WHERE userId = '$userId' AND communityId = '$id' AND `Роль` = 'Subscriber';")->fetch_assoc();

                    if (!$isSubscribe)
                    {
                        setHTTPStatus("400", "Пользователь не был подписан на сообщество => отписать его нельзя");
                    }
                    else
                    {
                        $unsubscribe = $Link->query("DELETE FROM `user-community` WHERE userId = '$userId' AND communityId = '$id' AND `Роль` = 'Subscriber';");

                        if ($unsubscribe)
                        {
                            bodyWithRequest("200", null);
                        }
                        else
                        {
                            setHTTPStatus("500", "Ошибка при попытке отписать пользователя от сообщества " .$Link->error);
                        }
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
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя, которые создаёт аккаунт");
    }
}

function postPost($idCommunity, $body, $token)
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
                $isExistCommunity = $Link->query("SELECT * FROM community WHERE community.id = '$idCommunity'")->fetch_assoc();

                if (isset($userWithThisToken["Идентификатор пользователя"]) && isset($isExistCommunity))
                {
                    $userId = $userWithThisToken["Идентификатор пользователя"];

                    $isAdmin = $Link->query("SELECT * FROM `user-community` WHERE userId = '$userId' AND communityId = '$idCommunity' AND `Роль` = 'Administrator'")->fetch_assoc();

                    if ($isAdmin)
                    {
                        if (isset($body["title"]) && isset($body["description"]) && isset($body["readingTime"]) && isset($body["tags"]))
                        {
                            $title = $body["title"];
                            $description = $body["description"];
                            $readingTime = $body["readingTime"];
                            $tags = $body["tags"];

                            $flagTitle = verifyTitle($title);
                            $flagDescription = verifyDescription($description);
                            $flagReadingTime = verifyReadingTime($readingTime);
                            $flagTags = verifyTags($tags, $Link);

                            $flagImage = 1;
                            $flagAddress = 1;

                            $image = null;
                            $addressId = null;

                            if (isset($body["image"]))
                            {
                                $image = $body["image"];
                                $flagImage = verifyImage($image);
                            }

                            if (isset($body["addressId"]))
                            {
                                $addressId = $body["addressId"];
                                $flagAddress = verifyAddress($addressId, $Link);
                            }

                            $errors = array();

                            if ($flagTitle != 1)
                            {
                                if ($flagTitle == 2)
                                {
                                    $errors["title"] = ["Неправильный тип данных"];
                                }
                                else if ($flagTitle == 0)
                                {
                                    $errors["title"] = ["Длина должна быть не меньше 5 и не больше 1000"];
                                }
                            }

                            if ($flagDescription != 1)
                            {
                                if ($flagDescription == 0)
                                {
                                    $errors["description"] = ["Длина должна быть не меньше 5 и не больше 5000"];
                                }
                                else if ($flagDescription == 2)
                                {
                                    $errors["description"] = ["Неправильный тип данных"];
                                }
                            }

                            if ($flagReadingTime != 1)
                            {
                                if ($flagReadingTime == 0)
                                {
                                    $errors["readingTime"] = ["Время не может быть отрицательным"];
                                }
                                else if ($flagReadingTime == 2)
                                {
                                    $errors["readingTime"] = ["Неправильный тип данных"];
                                }
                            }

                            if ($flagImage != 1)
                            {
                                if ($flagImage == 0)
                                {
                                    $errors["image"] = ["Неправильный формат ссылки (она должна быть или http, или https, или ftp)"];
                                }
                                else if ($flagImage == 2)
                                {
                                    $errors["image"] = ["Неправильный тип данных"];
                                }
                            }

                            if ($flagAddress != 1)
                            {
                                if ($flagAddress == 0)
                                {
                                    $errors["addressId"] = ["addressId с objectid = " . $addressId . " не существует" ];
                                }
                                else if ($flagAddress == 2)
                                {
                                    $errors["addressId"] = ["addressId должен являться guid-ом"];
                                }
                                else if ($flagAddress == 3)
                                {
                                    $errors["addressId"] = ["Неправильный тип данных"];
                                }
                            }

                            if ($flagTags != 1)
                            {
                                if ($flagTags == 2)
                                {
                                    $errors["tags"] = ["Неправильный тип данных"];
                                }
                                else
                                {
                                    $errors["tags"] = [];

                                    for ($i = 0; $i < count($flagTags); $i++)
                                    {
                                        $errors["tags"][] = ["Тег с id = ". $flagTags[$i]. " не существует"];
                                    }
                                }
                            }

                            if (gettype($flagTags) == "array" || gettype($flagTags) == "object")
                            {
                                if (count($flagTags) == 1)
                                {
                                    setHTTPStatus("404", $errors["tags"][0][0]);
                                }
                                else
                                {
                                    $idOfTags = "" . $flagTags[0];

                                    for ($i = 1; $i < count($flagTags); $i++)
                                    {
                                        $idOfTags = $idOfTags . ", " . $flagTags[$i];

                                    }

                                    setHTTPStatus("404", "Теги с id = " . $idOfTags . " не существуют");
                                }
                                
                            }
                            else if ($flagTitle * $flagDescription * $flagReadingTime * $flagTags * $flagImage * $flagAddress == 1)
                            {
                                $uuid = Uuid::uuid4()->toString();
                                $postInsertResult = null;

                                if ($image != null && $addressId != null)
                                {
                                    $postInsertResult = $Link->query("INSERT INTO post(`id`, `Заголовок`, `Описание`, `Время чтения`, `Изображение`, `authorId`, `addressId`, `communityId`) VALUES('$uuid', '$title', '$description', '$readingTime', '$image', '$userId', '$addressId', '$idCommunity')");
                                }
                                else if ($image != null)
                                {
                                    $postInsertResult = $Link->query("INSERT INTO post(`id`, `Заголовок`, `Описание`, `Время чтения`, `Изображение`, `authorId`, `communityId`) VALUES('$uuid', '$title', '$description', '$readingTime', '$image', '$userId', '$idCommunity')");
                                }
                                else if ($addressId != null)
                                {
                                    $postInsertResult = $Link->query("INSERT INTO post(`id`, `Заголовок`, `Описание`, `Время чтения`, `authorId`, `addressId`, `communityId`) VALUES('$uuid', '$title', '$description', '$readingTime', '$userId', '$addressId', '$idCommunity')");
                                }
                                else
                                {
                                    $postInsertResult = $Link->query("INSERT INTO post(`id`, `Заголовок`, `Описание`, `Время чтения`, `authorId`, `communityId`) VALUES('$uuid', '$title', '$description', '$readingTime', '$userId', '$idCommunity')");
                                }

                                if ($postInsertResult)
                                {
                                    $queryForTags = "INSERT INTO `tag-post`(`tagId`, `postId`) VALUES";
                                    foreach ($tags as $tag) 
                                    {
                                        $queryForTags = $queryForTags . "('" . $tag . "','" . $uuid . "'),";
                                    }

                                    $queryForTags = substr($queryForTags, 0, -1);
                                    $queryForTags = $queryForTags. ";";

                                    $insertTags = $Link->query($queryForTags);

                                    if ($insertTags)
                                    {
                                        echo $uuid;
                                    }
                                    else
                                    {
                                        setHTTPStatus("500", "Ошибка при добавлении тегов к посту " .$Link->error);
                                    }
                                }
                                else
                                {
                                    setHTTPStatus("500", "Ошибка при добавлении поста " .$Link->error);
                                }
                            }
                            else
                            {
                                setHTTPStatus("400", "Неккоректные данные поста", $errors);
                            }
                        }
                        else
                        {
                            setHTTPStatus("400", "Вы не передали минимальные требуемые данные для создания поста");
                        }
                    }
                    else
                    {
                        setHTTPStatus("403", "Только администраторы могут создать пост в сообществе");
                    }
                }
                else if (isset($isExistCommunity))
                {
                    setHTTPStatus("404", "Токен не подходит ни одному пользователю");
                }
                else if (isset($userWithThisToken["Идентификатор пользователя"]))
                {
                    setHTTPStatus("404", "Нет такого сообщества");
                }
                else
                {
                    setHTTPStatus("404", "Нет такого сообщества и пользователя с таким токеном тоже");
                }
            }
            else
            {
                setHTTPStatus("500", "Ошибка при попытке получения пользователя " .$Link->error);
            }
        }

        mysqli_close($Link);

    }
    else
    {
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя, которые создаёт аккаунт");
    }
}

function checkMethodForGetAllCommunities($method)
{
    if ($method == "GET") 
    {
        ////
    }
    else
    {
        setHTTPStatus("400", "Неправильный метод");
    }
}

function getListCommunitiesOfThisUser($method, $uriList, $token)
{
    if (isset($uriList[4]))
    {
        setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
    }
    else
    {
        if ($method == "GET")
        {
            communitiesOfUser($token);
        }
        else
        {
            setHTTPStatus("400", "Не тот метод");
        }
    }
}

function checkMethodForInfoAboutCommunity($method, $id)
{
    if ($method == "GET")
    {
        infoAboutCommunity($id);
    }
    else
    {
        setHTTPStatus("400", "Не тот метод");
    }
}

function checkMethodForRoleEndPoint($method, $id, $token)
{
    if ($method == "GET")
    {
        getRoleOfUserInCommunity($id, $token);
    }
    else
    {
        setHTTPStatus("400", "Не тот метод");
    }
}

function checkSubscribe($method, $id, $token)
{
    if ($method == "POST")
    {
        subscribeUserToCommunity($id, $token);
    }
    else
    {
        setHTTPStatus("400", "Не тот метод");
    }
}

function checkUnsubscribe($method, $id, $token)
{
    if ($method == "DELETE")
    {
        unsubscribeUserToCommunity($id, $token);
    }
    else
    {
        setHTTPStatus("400", "Не тот метод");
    }
}

function createOrGetPost($method, $idCommunity, $body, $params, $token)
{
    if ($method == "POST")
    {
        postPost($idCommunity, $body, $token);
    }
    else if ($method == "GET")
    {
        getPost($idCommunity, $params, $token);
    }
    else
    {
        setHTTPStatus("400", "Не тот метод");
    }
}

function checkForNextPartOfRequest($method, $uriList, $body, $params, $token)
{
    if (isset($uriList[4]))
    {
        if (isset($uriList[5]))
        {
            setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
        }
        else
        {
            switch ($uriList[4]) 
            {
                case "post":
                    createOrGetPost($method, $uriList[3], $body, $params, $token);
                    break;
                case "role":
                    checkMethodForRoleEndPoint($method, $uriList[3], $token);
                    break;
                case "subscribe":
                    checkSubscribe($method, $uriList[3], $token);
                    break;
                case "unsubscribe":
                    checkUnsubscribe($method, $uriList[3], $token);
                    break;
                default:
                    setHTTPStatus("400", "Такого эндпоинта нет");
                    break;
            }
        }
    }
    else
    {
        checkMethodForInfoAboutCommunity($method, $uriList[3]);
    }
}

function communityEndPoints($method, $uriList, $body, $params, $token)
{
    if (isset($uriList[3]))
    {
        switch ($uriList[3]) {
            case "my":
                getListCommunitiesOfThisUser($method, $uriList, $token);
                break;
            default:
                checkForNextPartOfRequest($method, $uriList, $body, $params, $token);
                break;
        }
    }
    else
    {
        checkMethodForGetAllCommunities($method);
    }
}

?>