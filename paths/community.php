<?php

require_once 'vendor/autoload.php';

use Ramsey\Uuid\Uuid;

function verifyBool($value)
{
    if (gettype($value) == 'boolean')
    {
        return 1;
    }
    else
    {
        return 0;
    }
}

function isValidUuid($uuid)
{
    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    return preg_match($pattern, $uuid);
}

function getAllCommunities()
{
    global $config;

    $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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

function verifyText($text)
{
    if (gettype($text) == "string")
    {
        if (strlen($text) > 0)
        {
            return 1;
        }
        else
        {
            return 0;
        }
    }
    else
    {
        return 2;
    }
}

function communitiesOfUser($token)
{
    if (isset($token))
    {
        global $config;

        $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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
        global $config;

        $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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
        global $config;

        $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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
        global $config;

        $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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
        global $config;

        $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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
        global $config;

        $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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

function getPost($idCommunity, $params, $token)
{
    global $config;

    $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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
            $userId = null;

            $tags = [];
            $author = null;
            $min = null;
            $max = null;
            $sorting = null;
            $page = null;
            $size = null;

            if ($params != null)
            {
                for ($i = 0; $i < count($params); $i++)
                {
                    $nameAndValue = explode("=", $params[$i]);

                    switch ($nameAndValue[0]) {
                        case 'tags':
                            $tags[] = $nameAndValue[1];
                            break;
                        case 'author':
                            $author = urldecode($nameAndValue[1]);
                            break;
                        case 'min':
                            if ($min == null)
                            {
                                $min = intval($nameAndValue[1]);
                            }
                            break;
                        case 'max':
                            if ($max == null)
                            {
                                $max = intval($nameAndValue[1]);
                            }
                            break;
                        case 'sorting':
                            if ($sorting == null)
                            {
                                $sorting = $nameAndValue[1];
                            }
                            break;
                        case 'page':
                            if ($page == null)
                            {
                                $page = intval($nameAndValue[1]);
                            }
                            break;
                        case 'size':
                            if ($size == null)
                            {
                                $size = intval($nameAndValue[1]);
                            }
                            break;
                        default:
                            break;
                    }
                }

            }
            else
            {
                $sorting = "CreateDesc";
            }

            $errors = [];

            $flagForTags = 1;
            $flagForMin = 1;
            $flagForMax = 1;
            $flagForSorting = 1;
            $flagPage = 1;
            $flagSize = 1;

            if ($min != null)
            {
                $flagForMin = verifyReadingTime($min);

                if ($flagForMin != 1)
                {
                    if ($flagForMin == 0)
                    {
                        $errors["min"] = ["Диапазон значений от 0 до 2**31-1"];
                    }
                    else if ($flagForMin == 2)
                    {
                        $errors["min"] = ["Неправильный тип данных"];
                    }
                }
            }

            if ($max != null)
            {
                $flagForMax = verifyReadingTime($max);

                if ($flagForMax != 1)
                {
                    if ($flagForMax == 0)
                    {
                        $errors["max"] = ["Диапазон значений от 0 до 2**31-1"];
                    }
                    else if ($flagForMax == 2)
                    {
                        $errors["max"] = ["Неправильный тип данных"];
                    }
                }
            }

            if (count($tags) > 0)
            {
                $flagForTags = verifyTag($tags, $Link);

                if ($flagForTags != 1)
                {
                    if ($flagForTags == 2)
                    {
                        $errors["tags"] = ["Неправильный тип данных"];
                    }
                    else
                    {
                        $errors["tags"] = [];

                        for ($i = 0; $i < count($flagForTags); $i++)
                        {
                            $errors["tags"][] = ["Тег с id = ". $flagForTags[$i]. " не существует"];
                        }
                    }
                }
            }

            if ($sorting != null)
            {
                $flagForSorting = verifySorting($sorting);

                if ($flagForSorting != 1)
                {
                    if ($flagForSorting == 0)
                    {
                        $errors["sorting"] = ["Такой сортировки нет"];
                    }
                    else if ($flagForSorting == 2)
                    {
                        $errors["sorting"] = ["Неправильный тип данных"];
                    }
                }
            }

            if ($page != null)
            {
                $flagPage = verifyNatural($page);

                if ($flagPage != 1)
                {
                    if ($flagPage == 0)
                    {
                        $errors["page"] = ["Диапазон значений от 1 до 2**31-1"];
                    }
                    else if ($flagPage == 2)
                    {
                        $errors["page"] = ["Неправильный тип данных"];
                    }
                }
            }

            if ($size != null)
            {
                $flagSize = verifyNatural($size);

                if ($flagSize != 1)
                {
                    if ($flagSize == 0)
                    {
                        $errors["size"] = ["Диапазон значений от 1 до 2**31-1"];
                    }
                    else if ($flagSize == 2)
                    {
                        $errors["size"] = ["Неправильный тип данных"];
                    }
                }
            }

            if (gettype($flagForTags) == "array" || gettype($flagForTags) == "object")
            {
                if (count($flagForTags) == 1)
                {
                    setHTTPStatus("404", $errors["tags"][0][0]);
                }
                else
                {
                    $idOfTags = "" . $flagForTags[0];

                    for ($i = 1; $i < count($flagForTags); $i++)
                    {
                        $idOfTags = $idOfTags . ", " . $flagForTags[$i];

                    }

                    setHTTPStatus("404", "Теги с id = " . $idOfTags . " не существуют");
                }
                
            }
            else if ($flagForTags * $flagForMin * $flagForMax * $flagForSorting * $flagPage * $flagSize == 1)
            {
                $posts = [];

                if (isset($token))
                {
                    $userWithThisToken = $Link->query("SELECT `Идентификатор пользователя` FROM token WHERE token.`Значение токена` = '$token'")->fetch_assoc();
                    $stringForQuery = null;

                    if (isset($userWithThisToken["Идентификатор пользователя"]))
                    {
                        $userId = $userWithThisToken["Идентификатор пользователя"];

                        $isExistCommunityForUser = $Link->query("SELECT * FROM community c 
                        LEFT JOIN `user-community` uc ON uc.communityId = c.id AND uc.userId = '$userId'
                        WHERE c.id = '$idCommunity' AND (c.`Закрытость` = 0 OR (c.`Закрытость` = 1 AND uc.`Роль` IS NOT NULL));")->fetch_assoc();

                        if ($isExistCommunityForUser)
                        {
                            if (count($tags) == 0)
                            {
                                $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, c.`Название` FROM post p
                                LEFT JOIN community c ON p.communityId = c.id AND c.id = '$idCommunity'
                                LEFT JOIN `user-community` uc ON c.id = uc.communityId AND uc.userId = '$userId'
                                LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                                WHERE (c.Закрытость = 0 OR (c.Закрытость = 1 AND (uc.Роль = 'Subscriber' OR uc.Роль = 'Administrator')));";
                            }
                            else if (count($tags) > 0)
                            {
                                $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, c.`Название` FROM post p
                                LEFT JOIN community c ON p.communityId = c.id AND c.id = '$idCommunity'
                                LEFT JOIN `user-community` uc ON c.id = uc.communityId AND uc.userId = '$userId'
                                LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                                LEFT JOIN `tag-post` ON `tag-post`.postId = p.id
                                WHERE (c.Закрытость = 0 OR (c.Закрытость = 1 AND (uc.Роль = 'Subscriber' OR uc.Роль = 'Administrator')));";

                                $additionalString = " AND ( ";

                                for ($i = 0; $i < count($tags); $i++)
                                {
                                    $additionalString = $additionalString . "`tag-post`.tagId = " . '"' . $tags[$i] . '"' . " OR ";
                                }

                                $additionalString = substr($additionalString, 0, -3);
                                $additionalString = $additionalString . " );";

                                $stringForQuery = $stringForQuery . $additionalString;
                            }
                        }
                        else
                        {
                            setHTTPStatus("403", "Вы не можете посмотреть посты этого сообщества, так как оно закрыто, а вы не его участник");
                            exit;
                        }

                        if ($author != null)
                        {
                            $stringForQuery = substr($stringForQuery, 0, -1);
                            $stringForQuery = $stringForQuery . " AND `user`.`ФИО` LIKE '%" . $author . "%';";
                        }

                        if ($min != null)
                        {
                            $stringForQuery = substr($stringForQuery, 0, -1);
                            $stringForQuery = $stringForQuery . " AND p.`Время чтения` >= " . $min . ";";
                        }

                        if ($max != null)
                        {
                            $stringForQuery = substr($stringForQuery, 0, -1);
                            $stringForQuery = $stringForQuery . " AND p.`Время чтения` <= " . $max . ";";
                        }

                        if ($sorting != null)
                        {
                            $stringForQuery = substr($stringForQuery, 0, -1);
                            $stringForQuery = $stringForQuery . " ORDER BY ";

                            switch ($sorting) 
                            {
                                case 'CreateAsc':
                                    $stringForQuery = $stringForQuery . "p.`Время создания` ASC;";
                                    break;
                                case 'CreateDesc':
                                    $stringForQuery = $stringForQuery . "p.`Время создания` DESC;";
                                    break;
                                case 'LikeAsc':
                                    $stringForQuery = $stringForQuery . "p.`Число лайков` ASC;";
                                    break;
                                case 'LikeDesc':
                                    $stringForQuery = $stringForQuery . "p.`Число лайков` DESC;";
                                    break;
                            }
                        }

                        $postIsExistForUser = $Link->query($stringForQuery);

                        while ($row = $postIsExistForUser->fetch_assoc()) 
                        {
                            $posts[] = $row;
                        }
                        $postIsExistForUser->free();
                    }
                    else
                    {
                        setHTTPStatus("401", "Токен не подходит ни одному пользователю");
                        exit;
                    }
                }
                else
                {
                    $isExistCommunityForUser = $Link->query("SELECT * FROM community c 
                        WHERE c.id = '$idCommunity' AND (c.`Закрытость` = 0);")->fetch_assoc();

                    if ($isExistCommunityForUser)
                    {
                        if (count($tags) == 0)
                        {
                            $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, c.`Название` FROM post p
                            LEFT JOIN community c ON p.communityId = c.id AND c.id = '$idCommunity'
                            LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                            WHERE (c.Закрытость = 0);";
                        }
                        else if (count($tags) > 0)
                        {
                            $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, `tag-post`.tagId, c.`Название` FROM post p
                                LEFT JOIN community c ON p.communityId = c.id AND c.id = '$idCommunity'
                                LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                                LEFT JOIN `tag-post` ON `tag-post`.postId = p.id
                                WHERE (c.Закрытость = 0)"; 

                            $additionalString = " AND ( ";

                            for ($i = 0; $i < count($tags); $i++)
                            {
                                $additionalString = $additionalString . "`tag-post`.tagId = " . '"' . $tags[$i] . '"' . " OR ";
                            }

                            $additionalString = substr($additionalString, 0, -3);
                            $additionalString = $additionalString . " );";

                            $stringForQuery = $stringForQuery . $additionalString;
                        }
                    }
                    else
                    {
                        setHTTPStatus("403", "Вы не можете посмотреть посты этого сообщества, так как оно закрыто, а вы не его участник");
                        exit;
                    }

                    if ($author != null)
                    {
                        $stringForQuery = substr($stringForQuery, 0, -1);
                        $stringForQuery = $stringForQuery . " AND `user`.`ФИО` LIKE '%" . $author . "%';";
                    }

                    if ($min != null)
                    {
                        $stringForQuery = substr($stringForQuery, 0, -1);
                        $stringForQuery = $stringForQuery . " AND p.`Время чтения` >= " . $min . ";";
                    }

                    if ($max != null)
                    {
                        $stringForQuery = substr($stringForQuery, 0, -1);
                        $stringForQuery = $stringForQuery . " AND p.`Время чтения` <= " . $max . ";";
                    }

                    if ($sorting != null)
                    {
                        $stringForQuery = substr($stringForQuery, 0, -1);
                        $stringForQuery = $stringForQuery . " ORDER BY ";

                        switch ($sorting) 
                        {
                            case 'CreateAsc':
                                $stringForQuery = $stringForQuery . "p.`Время создания` ASC;";
                                break;
                            case 'CreateDesc':
                                $stringForQuery = $stringForQuery . "p.`Время создания` DESC;";
                                break;
                            case 'LikeAsc':
                                $stringForQuery = $stringForQuery . "p.`Число лайков` ASC;";
                                break;
                            case 'LikeDesc':
                                $stringForQuery = $stringForQuery . "p.`Число лайков` DESC;";
                                break;
                        }
                    }

                    $postIsExistForUser = $Link->query($stringForQuery);

                    while ($row = $postIsExistForUser->fetch_assoc()) 
                    {
                        $posts[] = $row;
                    }
                    $postIsExistForUser->free();
                }

                $postsWithoutRepeats = [];
                $uniqueIds = [];

                foreach($posts as $post)
                {
                    if (!in_array($post["id"], $uniqueIds))
                    {
                        $uniqueIds[] = $post["id"];
                        $postsWithoutRepeats[] = $post;
                    }
                }

                if ($page == null)
                {
                    $page = 1;
                }

                if ($size == null)
                {
                    $size = 5;
                }

                $count = ceil(count($postsWithoutRepeats) / $size);

                $body = [];
                $body["pagination"] = array(
                    "size" => $size,
                    "count" => $count,
                    "current" => $page
                );

                $body["posts"] = [];

                if ($page * $size > count($postsWithoutRepeats))
                {
                    if (($page-1) * $size <= count($postsWithoutRepeats))
                    {
                        for ($i = $size * ($page - 1); $i < count($postsWithoutRepeats); $i++)
                        {
                            $hasLike = null;
                            $idOfPost = $postsWithoutRepeats[$i]["id"];

                            if ($userId == null)
                            {
                                $hasLike = false;
                            }
                            else
                            {
                                

                                $isHasLike = $Link->query("SELECT * FROM `like` WHERE postId = '$idOfPost' AND userId = '$userId'")->fetch_assoc();

                                if ($isHasLike)
                                {
                                    $hasLike = true;
                                }
                                else
                                {
                                    $hasLike = false;
                                }
                            }

                            $tagList = $Link->query("SELECT * FROM `tag-post`
                            LEFT JOIN tag ON tag.id = tagId
                            WHERE postId = '$idOfPost';");

                            $someTags = [];

                            if ($tagList)
                            {
                                while ($row = $tagList->fetch_assoc()) 
                                {
                                    $someTags[] = array(
                                        "id" => $row["id"],
                                        "createTime" => $row["Время создания"],
                                        "name" => $row["Название"]
                                    );
                                }
                                $tagList->free();
                                
                            }
                            else
                            {
                                setHTTPStatus("500", "Ошибка приполучении тегов поста " .$Link->error);
                                exit;
                            }

                            $body["posts"][] = array(
                                "title" => $postsWithoutRepeats[$i]["Заголовок"],
                                "description" => $postsWithoutRepeats[$i]["Описание"],
                                "readingTime" => intval($postsWithoutRepeats[$i]["Время чтения"]),
                                "image" => $postsWithoutRepeats[$i]["Изображение"],
                                "authorId" => $postsWithoutRepeats[$i]["authorId"],
                                "author" => $postsWithoutRepeats[$i]["ФИО"],
                                "communityId" => $postsWithoutRepeats[$i]["communityId"],
                                "communityName" => $postsWithoutRepeats[$i]["Название"],
                                "addressId" => $postsWithoutRepeats[$i]["addressId"],
                                "likes" => intval($postsWithoutRepeats[$i]["Число лайков"]),
                                "hasLike" => $hasLike,
                                "commentsCount" => intval($postsWithoutRepeats[$i]["Число комментариев"]),
                                "tags" => $someTags,
                                "id" => $postsWithoutRepeats[$i]["id"],
                                "createTime" => str_replace(" ", "T", $postsWithoutRepeats[$i]["Время создания"])
                            );
                        }
                    }
                    else
                    {
                        setHTTPMessage("Error", "400", "Такой страницы при данных параметрах сортировки и фильтрации нет");
                        exit;
                    }
                }
                else
                {
                    for ($i = $size * ($page - 1); $i < $page * $size; $i++)
                    {
                        $hasLike = null;

                        $idOfPost = $postsWithoutRepeats[$i]["id"];

                        if ($userId == null)
                        {
                            $hasLike = false;
                        }
                        else
                        {
                            

                            $isHasLike = $Link->query("SELECT * FROM `like` WHERE postId = '$idOfPost' AND userId = '$userId'")->fetch_assoc();

                            if ($isHasLike)
                            {
                                $hasLike = true;
                            }
                            else
                            {
                                $hasLike = false;
                            }
                        }

                        $tagList = $Link->query("SELECT * FROM `tag-post`
                        LEFT JOIN tag ON tag.id = tagId
                        WHERE postId = '$idOfPost';");

                        $someTags = [];

                        if ($tagList)
                        {
                            while ($row = $tagList->fetch_assoc()) 
                            {
                                $someTags[] = array(
                                    "id" => $row["id"],
                                    "createTime" => $row["Время создания"],
                                    "name" => $row["Название"]
                                );
                            }
                            $tagList->free();
                            
                        }
                        else
                        {
                            setHTTPStatus("500", "Ошибка приполучении тегов поста " .$Link->error);
                            exit;
                        }

                        $body["posts"][] = array(
                            "title" => $postsWithoutRepeats[$i]["Заголовок"],
                            "description" => $postsWithoutRepeats[$i]["Описание"],
                            "readingTime" => intval($postsWithoutRepeats[$i]["Время чтения"]),
                            "image" => $postsWithoutRepeats[$i]["Изображение"],
                            "authorId" => $postsWithoutRepeats[$i]["authorId"],
                            "author" => $postsWithoutRepeats[$i]["ФИО"],
                            "communityId" => $postsWithoutRepeats[$i]["communityId"],
                            "communityName" => $postsWithoutRepeats[$i]["Название"],
                            "addressId" => $postsWithoutRepeats[$i]["addressId"],
                            "likes" => intval($postsWithoutRepeats[$i]["Число лайков"]),
                            "hasLike" => $hasLike,
                            "commentsCount" => intval($postsWithoutRepeats[$i]["Число комментариев"]),
                            "tags" => $someTags,
                            "id" => $postsWithoutRepeats[$i]["id"],
                            "createTime" => str_replace(" ", "T", $postsWithoutRepeats[$i]["Время создания"])
                        );
                    }
                }

                bodyWithRequest("200", $body);

            }
            else
            {
                setHTTPStatus("400", "Неккоректные данные поста", $errors);
            }
            
        }
        else
        {
            setHTTPStatus("500", "Ошибка при удалении старых токенов " .$Link->error);
        }

        mysqli_close($Link);
    }
}

function createCommunity($body, $token)
{
    if (isset($token))
    {
        global $config;

        $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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

                    if (isset($body["name"]) && isset($body["isClosed"]))
                    {
                        $name = $body["name"];
                        $isClosed = $body["isClosed"];
                        $description = null;

                        $isUniqueName = $Link->query("SELECT * FROM community WHERE `Название` = '$name';")->fetch_assoc();

                        if ($isUniqueName)
                        {
                            setHTTPStatus("400", "Сообщество с таким названием существует");
                        }
                        else
                        {
                            $flagName = verifyText($name);
                            $flagIsClosed = verifyBool($isClosed);
                            $flagDescription = 1;

                            if (isset($body["description"]))
                            {
                                $description = $body["description"];
                                $flagDescription = verifyText($description);
                            }

                            $errors = array();

                            if ($flagName != 1)
                            {
                                if ($flagName == 0)
                                {
                                    $errors["name"] = ["Длина названия сообщества должна быть не менее 1 символа"];
                                }
                                else if ($flagName == 2)
                                {
                                    $errors["name"] = ["Неправильный тип данных"];
                                }
                            }

                            if ($flagDescription != 1)
                            {
                                if ($flagDescription == 0)
                                {
                                    $errors["description"] = ["При указании описания необходимо, чтобы его длина была больше 1, либо не указываёте описание вовсе"];
                                }
                                else if ($flagDescription == 2)
                                {
                                    $errors["description"] = ["Неправильный тип данных"];
                                }
                            }

                            if ($flagIsClosed != 1)
                            {
                                if ($flagIsClosed == 0)
                                {
                                    $errors["isClosed"] = ["Неправильный тип данных"];
                                }
                            }

                            if ($flagName * $flagDescription * $flagIsClosed == 1)
                            {
                                $createCommunity = null;
                                $isClosed = $isClosed ? 1 : 0;
                                $uuid = Uuid::uuid4()->toString();

                                if ($description != null)
                                {
                                    $createCommunity = $Link->query("INSERT INTO community(`id`, `Название`, `Описание`, `Закрытость`) VALUES('$uuid', '$name', '$description', '$isClosed')");
                                }
                                else
                                {
                                    $createCommunity = $Link->query("INSERT INTO community(`id`, `Название`, `Закрытость`) VALUES('$uuid', '$name', '$isClosed')");
                                }

                                if ($createCommunity)
                                {
                                    $makeUserAdmin = $Link->query("INSERT INTO `user-community`(`userId`, `communityId`, `Роль`) VALUES('$userId', '$uuid', 'Administrator')");

                                    if ($makeUserAdmin)
                                    {
                                        echo $uuid;
                                    }
                                    else
                                    {
                                        setHTTPStatus("500", "Ошибка при назначении пользователя администратором сообщества " .$Link->error);
                                    }
                                }
                                else
                                {
                                    setHTTPStatus("500", "Ошибка при создании сообщества " .$Link->error);
                                }
                            }
                            else
                            {
                                setHTTPStatus("400", "Неккоректные данные создаваемого сообщества", $errors);
                            }
                        }
                    }
                    else
                    {
                        setHTTPStatus("400", "Вы не передали минимальные требуемые данные для создания сообщества");
                    }

                }
                else
                {
                    setHTTPStatus("404", "Токен не подходит ни одному пользователю");
                }
            }
            else
            {
                setHTTPStatus("500", "Ошибка при удалении токенов " .$Link->error);
            }
        }

    }
    else
    {
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя");
    }
}

function createNewAdmin($communityId, $idOfNewAdmin, $token)
{
    if (isset($token))
    {
        global $config;

        $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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

                    $isExistCommunity = $Link->query("SELECT * FROM community WHERE id = '$communityId'")->fetch_assoc();
                    $isExistUser = $Link->query("SELECT * FROM user WHERE `Идентификатор пользователя` = '$idOfNewAdmin'")->fetch_assoc();

                    if ($isExistCommunity && $isExistUser)
                    {
                        $peopleIsAdmin = $Link->query("SELECT * FROM `user-community` WHERE userId = '$userId' AND communityId = '$communityId' AND `Роль` = 'Administrator';")->fetch_assoc();

                        if ($peopleIsAdmin)
                        {
                            if ($idOfNewAdmin != $userId)
                            {
                                $isUserNotAdmin = $Link->query("SELECT * FROM `user-community` WHERE userId = '$idOfNewAdmin' AND communityId = '$communityId' AND `Роль` = 'Administrator';")->fetch_assoc();

                                if ($isUserNotAdmin)
                                {
                                    setHTTPStatus("400", "Человек, которого вы собираетесь назначить администратором уже администратор");
                                    exit;
                                }
                                else
                                {
                                    $isUserSubscriber = $Link->query("SELECT * FROM `user-community` WHERE userId = '$idOfNewAdmin' AND communityId = '$communityId' AND `Роль` = 'Subscriber';")->fetch_assoc();

                                    if ($isUserSubscriber)
                                    {
                                        $addAdmin = $Link->query("INSERT INTO `user-community`(`userId`, `communityId`, `Роль`) VALUES('$idOfNewAdmin', '$communityId', 'Administrator')");

                                        if ($addAdmin)
                                        {
                                            bodyWithRequest("200", null);
                                        }
                                        else
                                        {
                                            setHTTPStatus("500", "Ошибка при назначении пользователя подписчиком " .$Link->error);
                                        }
                                    }
                                    else
                                    {
                                        setHTTPStatus("400", "Пользователь должен являться подписчиком сообщества");
                                    }
                                }
                            }
                            else
                            {
                                setHTTPStatus("400", "Человек самого себя назначить администратором не может");
                            }
                        }
                        else
                        {
                            setHTTPStatus("403", "Только администраторы сообщества могут назначать подписчиков новыми администраторами");
                        }
                    }
                    else if ($isExistUser)
                    {
                        setHTTPStatus("404", "Сообщество не существует");
                    }
                    else if ($isExistCommunity)
                    {
                        setHTTPStatus("404", "Пользователь, которого вы собираетесь назначить новым администратором не существует");
                    }
                    else
                    {
                        setHTTPStatus("404", "Нет ни сообщества, ни пользователя с такими идентификаторами");
                    }
                }
            }
            else
            {
                setHTTPStatus("500", "Ошибка при удалении токенов " .$Link->error);
            }
        }
    }
    else
    {
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя");
    }
}

function deleteAdmin($communityId, $idOfNewAdmin, $token)
{
    if (isset($token))
    {
        global $config;

        $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

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

                    $isExistCommunity = $Link->query("SELECT * FROM community WHERE id = '$communityId'")->fetch_assoc();
                    $isExistUser = $Link->query("SELECT * FROM user WHERE `Идентификатор пользователя` = '$idOfNewAdmin'")->fetch_assoc();

                    if ($isExistCommunity && $isExistUser)
                    {
                        $peopleIsAdmin = $Link->query("SELECT * FROM `user-community` WHERE userId = '$userId' AND communityId = '$communityId' AND `Роль` = 'Administrator';")->fetch_assoc();

                        if ($peopleIsAdmin)
                        {
                            if ($idOfNewAdmin != $userId)
                            {
                                $isUserAdmin = $Link->query("SELECT * FROM `user-community` WHERE userId = '$idOfNewAdmin' AND communityId = '$communityId' AND `Роль` = 'Administrator';")->fetch_assoc();

                                if ($isUserAdmin)
                                {
                                    $deleteUser = $Link->query("DELETE FROM `user-community` WHERE userId = '$idOfNewAdmin' AND communityId = '$communityId' AND `Роль` = 'Administrator';");

                                    if ($deleteUser)
                                    {
                                        bodyWithRequest("200", null);
                                    }
                                    else
                                    {
                                        setHTTPStatus("500", "Ошибка при удалении администратора" .$Link->error);
                                    }
                                }
                                else
                                {
                                    setHTTPStatus("400", "Человек, которого вы собираетесь удалить из администраторов, не администратор");
                                }
                            }
                            else
                            {
                                setHTTPStatus("400", "Человек не может удалить себя из администраторов");
                            }
                        }
                        else
                        {
                            setHTTPStatus("403", "Только администраторы сообщества могут удалить администратора");
                        }
                    }
                    else if ($isExistUser)
                    {
                        setHTTPStatus("404", "Сообщество не существует");
                    }
                    else if ($isExistCommunity)
                    {
                        setHTTPStatus("404", "Пользователь, которого вы собираетесь удалить среди администраторов не существует");
                    }
                    else
                    {
                        setHTTPStatus("404", "Нет ни сообщества, ни пользователя с такими идентификаторами");
                    }
                }
            }
            else
            {
                setHTTPStatus("500", "Ошибка при удалении токенов " .$Link->error);
            }
        }
    }
    else
    {
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя");
    }
}

function checkMethodForGetAllCommunities($method)
{
    if ($method == "GET") 
    {
        getAllCommunities();
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

function createNewCommunity($method, $uriList, $body, $token)
{
    if (isset($uriList[4]))
    {
        setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
    }
    else
    {
        if ($method == "POST")
        {
            createCommunity($body, $token);
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

function deleteOrCreateAdmin($method, $uriList, $token)
{
    if (isset($uriList[5]))
    {
        if (isset($uriList[6]))
        {
            setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
        }
        else
        {
            if ($method == "POST")
            {
                createNewAdmin($uriList[3], $uriList[5], $token);
            }
            else if ($method == "DELETE")
            {
                deleteAdmin($uriList[3], $uriList[5], $token);
            }
            else
            {
                setHTTPStatus("400", "Не тот метод");
            }
        }
    }
    else
    {
        setHTTPStatus("404", "Вы отправили запрос на несуществующую часть api");
    }
}

function checkForNextPartOfRequest($method, $uriList, $body, $params, $token)
{
    if (isset($uriList[4]))
    {
        switch ($uriList[4]) 
        {
            case "post":
                if (isset($uriList[5]))
                {
                    setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
                }
                else
                {
                    createOrGetPost($method, $uriList[3], $body, $params, $token);
                }
                break;
            case "role":
                if (isset($uriList[5]))
                {
                    setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
                }
                else
                {
                    checkMethodForRoleEndPoint($method, $uriList[3], $token);
                }
                break;
            case "subscribe":
                if (isset($uriList[5]))
                {
                    setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
                }
                else
                {
                    checkSubscribe($method, $uriList[3], $token);
                }
                break;
            case "unsubscribe":
                if (isset($uriList[5]))
                {
                    setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
                }
                else
                {
                    checkUnsubscribe($method, $uriList[3], $token);
                }
                break;
            case "admin":
                deleteOrCreateAdmin($method, $uriList, $token);
                break;
            default:
                setHTTPStatus("400", "Такого эндпоинта нет");
                break;
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
            case "create":
                createNewCommunity($method, $uriList, $body, $token);
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