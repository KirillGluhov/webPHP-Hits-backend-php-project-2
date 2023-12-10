<?php
require_once 'vendor/autoload.php';

use Ramsey\Uuid\Uuid;

function verifySorting($sorting)
{
    if (gettype($sorting) == "string")
    {
        switch ($sorting)
        {
            case 'CreateAsc':
                return 1;
            case 'CreateDesc':
                return 1;
            case 'LikeAsc':
                return 1;
            case 'LikeDesc':
                return 1;
            default:
                return 0;
        }
    }
    else
    {
        return 2;
    }
}

function verifyBoolean($value)
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

function verifyTitle($title)
{
    if (gettype($title) == "string")
    {
        if (strlen($title) >= 5 && strlen($title) <= 1000)
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

function verifyNatural($value)
{
    if (gettype($value) == "integer")
    {
        if ($value >= 1 && $value <= 2**31-1)
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

function verifyDescription($description)
{
    if (gettype($description) == "string")
    {
        if (strlen($description) >= 5 && strlen($description) <= 5000)
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

function verifyReadingTime($readingTime)
{
    if (gettype($readingTime) == "integer")
    {
        if ($readingTime >= 0 && $readingTime <= 2**31-1)
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

function isCorrectUrl($image)
{
    return preg_match('/^(http|https|ftp):\/\/.*$/', $image);
}

function verifyImage($image)
{
    if (gettype($image) == "string")
    {
        if (isCorrectUrl($image))
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

function verifyAddress($addressId, $Link)
{
    if (gettype($addressId) == "string")
    {
        if (preg_match('/^[0-9A-Fa-f]{8}\-[0-9A-Fa-f]{4}\-[0-9A-Fa-f]{4}\-[0-9A-Fa-f]{4}\-[0-9A-Fa-f]{12}$/', $addressId))
        {
            $isExistAdress = $Link->query("SELECT objectguid FROM addres_object WHERE addres_object.objectguid = '$addressId' AND Актуальность = 1;")->fetch_assoc();
            $isExistBuilding = $Link->query("SELECT objectguid FROM houses WHERE houses.objectguid = '$addressId' AND Актуальность = 1;")->fetch_assoc();

            if ($isExistAdress || $isExistBuilding)
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
    else
    {
        return 3;
    }
}

function verifyTag($tags, $Link)
{
    if (gettype($tags) == "array")
    {
        if (empty($tags)) 
        {
            return 1;
        }

        $counter = 0;
        $errors = [];

        foreach ($tags as $value)
        {
            $isExist = $Link->query("SELECT id FROM tag WHERE tag.id = '$value';")->fetch_assoc();

            if ($isExist)
            {
                $counter++;
            }
            else
            {
                $errors[] = $value;
            }
        }

        if ($counter == count($tags))
        {
            return 1;
        }
        else
        {
            return $errors;
        }
    }
    else
    {
        return 2;
    }
}

function verifyTags($tags, $Link)
{
    if (gettype($tags) == "array" || gettype($tags) == "object")
    {

        $counter = 0;
        $errors = [];

        for ($i = 0; $i < count($tags); $i++)
        {
            $value = $tags[$i];
            $isExist = $Link->query("SELECT id FROM tag WHERE tag.id = '$value';")->fetch_assoc();

            if ($isExist)
            {
                $counter++;
            }
            else
            {
                $errors[] = $value;
            }
        }

        if ($counter == count($tags))
        {
            return 1;
        }
        else
        {
            return $errors;
        }
    }
    else
    {
        return 2;
    }
}

function createPost($body, $token)
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
                                $postInsertResult = $Link->query("INSERT INTO post(`id`, `Заголовок`, `Описание`, `Время чтения`, `Изображение`, `authorId`, `addressId`) VALUES('$uuid', '$title', '$description', '$readingTime', '$image', '$userId', '$addressId')");
                            }
                            else if ($image != null)
                            {
                                $postInsertResult = $Link->query("INSERT INTO post(`id`, `Заголовок`, `Описание`, `Время чтения`, `Изображение`, `authorId`) VALUES('$uuid', '$title', '$description', '$readingTime', '$image', '$userId')");
                            }
                            else if ($addressId != null)
                            {
                                $postInsertResult = $Link->query("INSERT INTO post(`id`, `Заголовок`, `Описание`, `Время чтения`, `authorId`, `addressId`) VALUES('$uuid', '$title', '$description', '$readingTime', '$userId', '$addressId')");
                            }
                            else
                            {
                                $postInsertResult = $Link->query("INSERT INTO post(`id`, `Заголовок`, `Описание`, `Время чтения`, `authorId`) VALUES('$uuid', '$title', '$description', '$readingTime', '$userId')");
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
                    setHTTPStatus("404", "Токен не подходит ни одному пользователю");
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
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя");
    }
}

function putLikeToPost($postId, $token)
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

                    $isExistPost = $Link->query("SELECT * FROM post WHERE post.id = '$postId';")->fetch_assoc();

                    if ($isExistPost)
                    {
                        $postIsExistForUser = $Link->query("SELECT DISTINCT p.* FROM post p
                        LEFT JOIN community c ON p.communityId = c.id
                        LEFT JOIN `user-community` uc ON c.id = uc.communityId AND uc.userId = '$userId'
                        WHERE (p.communityId IS NULL OR c.Закрытость = 0 OR (c.Закрытость = 1 AND (uc.Роль = 'Subscriber' OR uc.Роль = 'Administrator'))) AND p.id = '$postId';")->fetch_assoc();

                        if ($postIsExistForUser)
                        {
                            $isLikeExist = $Link->query("SELECT * FROM `like` WHERE userId = '$userId' AND postId = '$postId';")->fetch_assoc();

                            if ($isLikeExist)
                            {
                                setHTTPStatus("400", "Поставить лайк дважды на один и тот же пост, один и тот же пользователь не может");
                            }
                            else
                            {
                                $likeInsert = $Link->query("INSERT INTO `like`(`userId`, `postId`) VALUES('$userId', '$postId');");

                                if ($likeInsert)
                                {
                                    bodyWithRequest("200", null);
                                }
                                else
                                {
                                    setHTTPStatus("500", "Ошибка при постановке лайка " .$Link->error);
                                }
                            }
                        }
                        else
                        {
                            setHTTPStatus("403", "Пользователь может ставить лайк только постам без сообществ, либо постам в открытых сообществах, либо постам в закрытых сообществах, если пользователь - его участник");
                        }
                    }
                    else
                    {
                        setHTTPStatus("404", "Такого сообщества нет");
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
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя");
    }
}

function deleteLikeFromPost($postId, $token)
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

                    $isExistPost = $Link->query("SELECT * FROM post WHERE post.id = '$postId';")->fetch_assoc();

                    if ($isExistPost)
                    {
                        $postIsExistForUser = $Link->query("SELECT DISTINCT p.* FROM post p
                        LEFT JOIN community c ON p.communityId = c.id
                        LEFT JOIN `user-community` uc ON c.id = uc.communityId AND uc.userId = '$userId'
                        WHERE (p.communityId IS NULL OR c.Закрытость = 0 OR (c.Закрытость = 1 AND (uc.Роль = 'Subscriber' OR uc.Роль = 'Administrator'))) AND p.id = '$postId';")->fetch_assoc();

                        if ($postIsExistForUser)
                        {
                            $isLikeExist = $Link->query("SELECT * FROM `like` WHERE userId = '$userId' AND postId = '$postId';")->fetch_assoc();

                            if (!$isLikeExist)
                            {
                                setHTTPStatus("400", "Убрать непоставленный лайк нельзя");
                            }
                            else
                            {
                                $likeDelete = $Link->query("DELETE FROM `like` WHERE userId = '$userId' AND postId = '$postId';");

                                if ($likeDelete)
                                {
                                    bodyWithRequest("200", null);
                                }
                                else
                                {
                                    setHTTPStatus("500", "Ошибка при убирании лайка " .$Link->error);
                                }
                            }
                        }
                        else
                        {
                            setHTTPStatus("403", "Пользователь может убрать лайк только постам без сообществ, либо постам в открытых сообществах, либо постам в закрытых сообществах, если пользователь - его участник");
                        }
                    }
                    else
                    {
                        setHTTPStatus("404", "Такого сообщества нет");
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
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя");
    }
}

function addCommentToPost($postId, $token, $body)
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

                    $isExistPost = $Link->query("SELECT * FROM post WHERE post.id = '$postId';")->fetch_assoc();

                    if ($isExistPost)
                    {
                        $postIsExistForUser = $Link->query("SELECT DISTINCT p.* FROM post p
                        LEFT JOIN community c ON p.communityId = c.id
                        LEFT JOIN `user-community` uc ON c.id = uc.communityId AND uc.userId = '$userId'
                        WHERE (p.communityId IS NULL OR c.Закрытость = 0 OR (c.Закрытость = 1 AND (uc.Роль = 'Subscriber' OR uc.Роль = 'Administrator'))) AND p.id = '$postId';")->fetch_assoc();

                        if ($postIsExistForUser)
                        {
                            if (isset($body))
                            {
                                if (isset($body["content"]))
                                {
                                    $content = $body["content"];
                                    $uuidComment = Uuid::uuid4()->toString();

                                    if (strlen($content) > 1000 || strlen($content) < 1)
                                    {
                                        setHTTPStatus("400", "Длина комментария должна входить в диапазон от 1 до 1000");
                                    }
                                    else
                                    {
                                        if (isset($body["parentId"]))
                                        {
                                            $parentId = $body["parentId"];

                                            $findPathOfParentComment = $Link->query("SELECT `Путь` FROM `comment_hierarchy` WHERE commentId = '$parentId';")->fetch_assoc();

                                            if ($findPathOfParentComment)
                                            {
                                                $newPath = $findPathOfParentComment["Путь"] . "." . $uuidComment;

                                                $createPost = $Link->query("INSERT INTO comment(`id`, `Содержимое`, `authorId`, `postId`) VALUES('$uuidComment', '$content', '$userId', '$postId')");
                                                $addToHierarchy = $Link->query("INSERT INTO `comment_hierarchy`(`commentId`, `Путь`, `parentId`) VALUES('$uuidComment', '$newPath', '$parentId');");

                                                if ($createPost && $addToHierarchy)
                                                {
                                                    bodyWithRequest("200", null);
                                                }
                                                else
                                                {
                                                    setHTTPStatus("500", "Ошибка при добавлении комментария " .$Link->error);
                                                }

                                                
                                            }
                                            else
                                            {
                                                setHTTPStatus("404", "Комментарий, к которому вы хотите добавить подкомментарий не существует");
                                            }

                                        }
                                        else
                                        {
                                            $createPost = $Link->query("INSERT INTO comment(`id`, `Содержимое`, `authorId`, `postId`) VALUES('$uuidComment', '$content', '$userId', '$postId')");
                                            $addToHierarchy = $Link->query("INSERT INTO `comment_hierarchy`(`commentId`, `Путь`) VALUES('$uuidComment', '$uuidComment');");

                                            if ($createPost && $addToHierarchy)
                                            {
                                                bodyWithRequest("200", null);
                                            }
                                            else
                                            {
                                                setHTTPStatus("500", "Ошибка при добавлении комментария " .$Link->error);
                                            }
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
                            setHTTPStatus("403", "Пользователь может оставить комментарий только постам без сообществ, либо постам в открытых сообществах, либо постам в закрытых сообществах, если пользователь - его участник");
                        }
                    }
                    else
                    {
                        setHTTPStatus("404", "Такого поста нет");
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
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя");
    }
}

function getPostInfo($postId, $token)
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
            $isExistPost = $Link->query("SELECT * FROM post WHERE post.id = '$postId';")->fetch_assoc();

            if ($isExistPost)
            {
                $postIsExistForUser = null;

                $userId = null;

                if (isset($token))
                {
                    $userWithThisToken = $Link->query("SELECT `Идентификатор пользователя` FROM token WHERE token.`Значение токена` = '$token'")->fetch_assoc();

                    if (isset($userWithThisToken["Идентификатор пользователя"]))
                    {
                        $userId = $userWithThisToken["Идентификатор пользователя"];

                        $postIsExistForUser = $Link->query("SELECT DISTINCT p.* FROM post p
                        LEFT JOIN community c ON p.communityId = c.id
                        LEFT JOIN `user-community` uc ON c.id = uc.communityId AND uc.userId = '$userId'
                        WHERE (p.communityId IS NULL OR c.Закрытость = 0 OR (c.Закрытость = 1 AND (uc.Роль = 'Subscriber' OR uc.Роль = 'Administrator'))) AND p.id = '$postId';")->fetch_assoc();
                    }
                    else
                    {
                        setHTTPStatus("401", "Токен не подходит ни одному пользователю");
                        exit;
                    }
                }
                else
                {
                    $postIsExistForUser = $Link->query("SELECT DISTINCT p.* FROM post p
                        LEFT JOIN community c ON p.communityId = c.id
                        WHERE (p.communityId IS NULL OR c.Закрытость = 0) AND p.id = '$postId';")->fetch_assoc();
                }

                if ($postIsExistForUser)
                {
                    $postAdditionalInfo = $Link->query("SELECT p.*, `user`.`ФИО`, `community`.`Название` FROM post p 
                    LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                    LEFT JOIN `community` ON `community`.`id` = p.communityId
                    WHERE p.id = '$postId';")->fetch_assoc();

                    if ($postAdditionalInfo)
                    {
                        $body = array(
                            "title" => $postAdditionalInfo["Заголовок"],
                            "description" => $postAdditionalInfo["Описание"],
                            "readingTime" => intval($postAdditionalInfo["Время чтения"]),
                            "image" => $postAdditionalInfo["Изображение"],
                            "authorId" => $postAdditionalInfo["authorId"],
                            "author" => $postAdditionalInfo["ФИО"],
                            "communityId" => $postAdditionalInfo["communityId"],
                            "communityName" => $postAdditionalInfo["Название"],
                            "addressId" => $postAdditionalInfo["addressId"],
                            "likes" => intval($postAdditionalInfo["Число лайков"]),
                            "commentsCount" => intval($postAdditionalInfo["Число комментариев"]),
                            "id" => $postAdditionalInfo["id"],
                            "createTime" => str_replace(" ", "T", $postAdditionalInfo["Время создания"])
                        );

                        $hasLikes = null;
                        $tags = [];
                        $comments = [];

                        if ($userId != null)
                        {
                            $isHasLikeFromUser = $Link->query("SELECT * FROM `like` WHERE userId = '$userId' AND postId = '$postId';")->fetch_assoc();

                            if ( $isHasLikeFromUser)
                            {
                                $body["hasLikes"] = true;
                            }
                            else
                            {
                                $body["hasLikes"] = false;
                            }
                        }
                        else
                        {
                            $body["hasLikes"] = false;
                        }

                        $allTags = $Link->query("SELECT tag.* FROM `tag-post` tp
                        LEFT JOIN tag ON tag.id = tp.tagId
                        WHERE postId = '$postId';");

                        while ($row = $allTags->fetch_assoc()) 
                        {
                            $tags[] = array(
                                "name" => $row["Название"],
                                "id"=> $row["id"],
                                "createTime" => str_replace(" ", "T", $row["Время создания"])
                            );
                        }
                        $allTags->free();

                        $body["tags"] = $tags;

                        $allComments = $Link->query("SELECT c.*, `user`.`ФИО` FROM `comment` c
                        LEFT JOIN comment_hierarchy ON comment_hierarchy.commentId = c.id
                        LEFT JOIN `user` ON  `user`.`Идентификатор пользователя` = c.authorId
                        WHERE c.postId = '$postId' AND comment_hierarchy.parentId IS NULL;");

                        while ($row = $allComments->fetch_assoc()) 
                        {
                            $comments[] = array(
                                "content" => $row["Содержимое"],
                                "modifiedDate" => $row["modifiedDate"],
                                "deleteDate" => $row["deleteDate"],
                                "authorId" => $row["authorId"],
                                "author" => $row["ФИО"],
                                "subComments" => intval($row["subcomments"]),
                                "id"=> $row["id"],
                                "createTime" => str_replace(" ", "T", $row["createTime"])
                            );
                        }
                        $allComments->free();

                        $body["comments"] = $comments;


                        bodyWithRequest ("200", $body);
    
                    }
                    else
                    {
                        setHTTPStatus("500", "Ошибка при получении данных поста " .$Link->error);
                    }
                }
                else
                {
                    setHTTPStatus("403", "Вы не можете посмотреть данный пост");
                }
            }
            else
            {
                setHTTPStatus("404", "Такого поста нет");
            }
            
        }
        else
        {
            setHTTPStatus("500", "Ошибка при удалении старых токенов " .$Link->error);
        }

        mysqli_close($Link);
    }
}

function getAllPosts($params, $token)
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
            $onlyMyCommunities = false;
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
                        case 'onlyMyCommunities':
                            if ($onlyMyCommunities == false)
                            {
                                $onlyMyCommunities = ($nameAndValue[1] == "true") ? true : false;
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
            $flagOnlyMyCommunities = 1;
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

            if ($onlyMyCommunities != null)
            {
                $flagOnlyMyCommunities = verifyBoolean($onlyMyCommunities);

                if ($flagOnlyMyCommunities != 1)
                {
                    if ($flagOnlyMyCommunities == 0)
                    {
                        $errors["onlyMyCommunities"] = ["Неправильный тип данных"];
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
            else if ($flagForTags * $flagForMin * $flagForMax * $flagForSorting * $flagOnlyMyCommunities * $flagPage * $flagSize == 1)
            {
                $posts = [];

                if (isset($token))
                {
                    $userWithThisToken = $Link->query("SELECT `Идентификатор пользователя` FROM token WHERE token.`Значение токена` = '$token'")->fetch_assoc();
                    $stringForQuery = null;

                    if (isset($userWithThisToken["Идентификатор пользователя"]))
                    {
                        $userId = $userWithThisToken["Идентификатор пользователя"];

                        if ($onlyMyCommunities == false && count($tags) == 0)
                        {
                            $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, c.`Название` FROM post p
                            LEFT JOIN community c ON p.communityId = c.id
                            LEFT JOIN `user-community` uc ON c.id = uc.communityId AND uc.userId = '$userId'
                            LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                            WHERE (p.communityId IS NULL OR c.Закрытость = 0 OR (c.Закрытость = 1 AND (uc.Роль = 'Subscriber' OR uc.Роль = 'Administrator')));";
                        }
                        else if ($onlyMyCommunities == true && count($tags) == 0)
                        {
                            $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, c.`Название` FROM post p
                            LEFT JOIN community c ON p.communityId = c.id
                            LEFT JOIN `user-community` uc ON c.id = uc.communityId AND uc.userId = '$userId'
                            LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                            WHERE (p.communityId IS NULL OR uc.userId = '$userId');";
                        }
                        else if (count($tags) > 0)
                        {
                            if ($onlyMyCommunities == true)
                            {
                                $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, `tag-post`.tagId, c.`Название` FROM post p
                                LEFT JOIN community c ON p.communityId = c.id
                                LEFT JOIN `user-community` uc ON c.id = uc.communityId AND uc.userId = '$userId'
                                LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                                LEFT JOIN `tag-post` ON `tag-post`.postId = p.id
                                WHERE (p.communityId IS NULL OR uc.userId = '$userId')";
                            }
                            else if ($onlyMyCommunities == false)
                            {
                                $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, `tag-post`.tagId, c.`Название` FROM post p
                                LEFT JOIN community c ON p.communityId = c.id
                                LEFT JOIN `user-community` uc ON c.id = uc.communityId AND uc.userId = '$userId'
                                LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                                LEFT JOIN `tag-post` ON `tag-post`.postId = p.id
                                WHERE (p.communityId IS NULL OR c.Закрытость = 0 OR
                                (c.Закрытость = 1 AND (uc.Роль = 'Subscriber' OR uc.Роль = 'Administrator')))"; 
                            }

                            $additionalString = " AND ( ";

                            for ($i = 0; $i < count($tags); $i++)
                            {
                                $additionalString = $additionalString . "`tag-post`.tagId = " . '"' . $tags[$i] . '"' . " OR ";
                            }

                            $additionalString = substr($additionalString, 0, -3);
                            $additionalString = $additionalString . " );";

                            $stringForQuery = $stringForQuery . $additionalString;
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

                    if ($onlyMyCommunities == false && count($tags) == 0)
                    {
                        $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, c.`Название` FROM post p
                        LEFT JOIN community c ON p.communityId = c.id
                        LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                        WHERE (p.communityId IS NULL OR c.Закрытость = 0);";
                    }
                    else if ($onlyMyCommunities == true && count($tags) == 0)
                    {
                        $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, c.`Название` FROM post p
                        LEFT JOIN community c ON p.communityId = c.id
                        LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                        WHERE (p.communityId IS NULL);";
                    }
                    else if (count($tags) > 0)
                    {
                        if ($onlyMyCommunities == true)
                        {
                            $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, `tag-post`.tagId, c.`Название` FROM post p
                            LEFT JOIN community c ON p.communityId = c.id
                            LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                            LEFT JOIN `tag-post` ON `tag-post`.postId = p.id
                            WHERE (p.communityId IS NULL)";
                        }
                        else if ($onlyMyCommunities == false)
                        {
                            $stringForQuery = "SELECT DISTINCT p.*, `user`.`ФИО`, `tag-post`.tagId, c.`Название` FROM post p
                            LEFT JOIN community c ON p.communityId = c.id
                            LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = p.authorId
                            LEFT JOIN `tag-post` ON `tag-post`.postId = p.id
                            WHERE (p.communityId IS NULL OR c.Закрытость = 0)"; 
                        }

                        $additionalString = " AND ( ";

                        for ($i = 0; $i < count($tags); $i++)
                        {
                            $additionalString = $additionalString . "`tag-post`.tagId = " . '"' . $tags[$i] . '"' . " OR ";
                        }

                        $additionalString = substr($additionalString, 0, -3);
                        $additionalString = $additionalString . " );";

                        $stringForQuery = $stringForQuery . $additionalString;
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

function createOrGetPosts($method, $body, $params, $token)
{
    if ($method == "GET")
    {
        getAllPosts($params, $token);
    }
    else if ($method == "POST")
    {
        createPost($body, $token);
    }
    else
    {
        setHTTPStatus("400", "Не тот метод");
    }
}

function likeOrUnlike($method, $postId, $token)
{
    if ($method == "POST")
    {
        putLikeToPost($postId, $token);
    }
    else if ($method == "DELETE")
    {
        deleteLikeFromPost($postId, $token);
    }
    else
    {
        setHTTPStatus("400", "Не тот метод");
    }
}

function chooseEndpointForPost($method, $uriList, $token, $body)
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
                case 'like':
                    likeOrUnlike($method, $uriList[3], $token);
                    break;
                case 'comment':
                    if ($method == "POST")
                    {
                        addCommentToPost($uriList[3], $token, $body);
                    }
                    else
                    {
                        setHTTPStatus("400", "Не тот метод");
                    }
                    break;
                default:
                    setHTTPStatus("404", "Вы отправили запрос на несуществующую часть api");
                    break;
            }
        }
    }
    else
    {
        if ($method == "GET")
        {
            getPostInfo($uriList[3], $token);
        }
        else
        {
            setHTTPStatus("400", "Не тот метод");
        }
    }
}

function posts($method, $uriList, $body, $params, $token)
{
    if (isset($uriList[3]))
    {
        switch ($uriList[3]) 
        {
            default:
                chooseEndpointForPost($method, $uriList, $token, $body);
                break;
        }
    }
    else
    {
        createOrGetPosts($method, $body, $params, $token);
    }
}

?>