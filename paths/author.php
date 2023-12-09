<?php

function authors()
{
    $Link = mysqli_connect("127.0.0.1", "root", "kirillgluhov", "blog");

    if (!$Link)
    {
        setHTTPStatus("500", "Ошибка соединения с БД " .mysqli_connect_error());
        exit;
    }
    else
    {
        $authors = $Link->query("SELECT 
        `user`.`ФИО`,  
        `user`.`Дата создания`,
        `user`.`День рождения`,  
        `user`.`Пол`, 
        COUNT(post.id) AS 'Посты пользователя',
        SUM(post.`Число лайков`) AS 'Лайки пользователя'
        FROM post
        LEFT JOIN `user` ON `user`.`Идентификатор пользователя` = post.authorId
        GROUP BY `user`.`Идентификатор пользователя` ORDER BY `user`.`ФИО`;");

        if ($authors)
        {
            $authorsInfo = [];
            $body = [];

            while ($row = $authors->fetch_assoc())
            {
                $authorsInfo[] = $row;
            }
            $authors->free();

            for ($i = 0; $i < count($authorsInfo); $i++)
            {
                $body[] = array(
                    "fullName" => $authorsInfo[$i]["ФИО"],
                    "birthDate"=> $authorsInfo[$i]["День рождения"],
                    "gender" => $authorsInfo[$i]["Пол"],
                    "posts" => intval($authorsInfo[$i]["Посты пользователя"]),
                    "likes" => intval($authorsInfo[$i]["Лайки пользователя"]),
                    "created" => str_replace(" ", "T", $authorsInfo[$i]["Дата создания"])
                );
            }

            bodyWithRequest("200", $body);
        }
        else
        {
            setHTTPStatus("500", "Получить авторов не удалось " . $Link->error);
        }


    }

    mysqli_close($Link);
}

function getAuthors($method)
{
    if ($method == "GET")
    {
        authors();
    }
    else
    {
        setHTTPStatus("400", "Не тот метод");
    }
}

function authorEndpoints($method, $uriList)
{
    if (isset($uriList[3]))
    {
        if ($uriList[3] == "list")
        {
            if (isset($uriList[4]))
            {
                setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
            }
            else
            {
                getAuthors($method);
            }
        }
        else
        {
            setHTTPStatus("404", "Вы отправили запрос на несуществующую часть api");
        }
    }
    else
    {
        setHTTPStatus("404", "Вы отправили запрос на несуществующую часть api");
    }
}

?>