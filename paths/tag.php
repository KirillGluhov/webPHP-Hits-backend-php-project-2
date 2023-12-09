<?php

function giveTags()
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
        $tagsEntryFromDB = [];

        $tags = $Link->query("SELECT id, `Время создания`, `Название` FROM tag;");

        if ($tags)
        {
            while ($row = $tags->fetch_assoc()) 
            {
                $tagsEntryFromDB[] = $row;
            }
            $tags->free();

            $body = [];

            foreach ($tagsEntryFromDB as $row)
            {
                $body[] = array(
                    "name"=> $row["Название"],
                    "id"=> $row["id"],
                    "createTime"=> str_replace(" ", "T", $row["Время создания"])
                );
            }

            bodyWithRequest("200", $body);
        }
        else
        {
            setHTTPStatus("500", "Ошибка при попытке получения списка тегов " .mysqli_connect_error());
            exit;
        }
    }

    mysqli_close($Link);
}

function allTags($method, $uriList)
{
    if (isset($uriList[3]))
    {
        setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
    }
    else
    {
        if ($method == "GET")
        {
            giveTags();
        }
        else
        {
            setHTTPStatus("400", "Не тот метод");
        }
    }
}

?>