<?php
include_once "helpers/headers.php";
require_once 'vendor/autoload.php';

global $Link;

function levelName($levelNumber)
{
    switch ($levelNumber) {
        case "1":
            return "Region";
        case "2":
            return "AdministrativeArea";
        case "3":
            return "Microdistrict";
        case "4":
            return "RuralSettlement";
        case "5":
            return "City";
        case "6":
            return "Locality";
        case "7":
            return "ElementOfPlanningStructure";
        case "8":
            return "ElementOfRoadNetwork";
        case "15":
            return "AdditionalTerritoriesLevel";
        case "16":
            return "LevelOfObjectsInAdditionalTerritories";
        default:
            return "UnknownLevel";
    }
}

function levelNameText($levelNumber)
{
    switch ($levelNumber) {
        case "1":
            return "Субъект РФ";
        case "2":
            return "Административный район";
        case "3":
            return "Микрорайон";
        case "4":
            return "Сельское поселение";
        case "5":
            return "Город";
        case "6":
            return "Населённый пункт";
        case "7":
            return "Элемент планировочной структуры";
        case "8":
            return "Элемент улично-дорожной сети";
        case "15":
            return "Уровень дополнительных территорий (устаревшее)";
        case "16":
            return "Уровень объектов на дополнительных территориях (устаревшее)";
        default:
            return "Неизвестный уровень адреса";
    }
}

function findElementsOnOneLevelWithThisParams($params)
{
    $Link = mysqli_connect("127.0.0.1", "root", "kirillgluhov", "blog");

    if (!$Link)
    {
        setHTTPStatus("500", "Ошибка соединения с БД " .mysqli_connect_error());
        exit;
    }
    else
    {
        if (isset($params))
        {
            $parentId = null;
            $partOfName = null;

            foreach($params as $nameAndValue)
            {
                $partsOfNameAndValue = explode("=", $nameAndValue);

                if (isset($partsOfNameAndValue[0]) && $partsOfNameAndValue[0] === "query")
                {
                    $partOfName = $partsOfNameAndValue[1];
                }

                if (isset($partsOfNameAndValue[0]) && $partsOfNameAndValue[0] === "parentObjectId")
                {
                    $parentId = $partsOfNameAndValue[1];
                }
            }

            if (isset($partOfName))
            {
                $partOfName = urldecode($partOfName);
            }


            if (isset($parentId) && isset($partOfName))
            {
                ////
            }
            else if (isset($parentId))
            {
                
            }
            else if (isset($partOfName))
            {
                $nameFindString = "%" . $partOfName . "%";
                $chooseParentForAll = $Link->query("SELECT * FROM addres_object WHERE `Уровень` = (SELECT MIN(CAST(`Уровень` AS DECIMAL(65))) FROM addres_object) AND `Актуальность` = 1 AND `Название` LIKE '$nameFindString' LIMIT 10");

                $adresses = [];

                if ($chooseParentForAll) 
                {
                    while ($row = $chooseParentForAll->fetch_assoc()) 
                    {
                        $adresses[] = $row;
                    }
                    $chooseParentForAll->free();

                    $body = [];

                    for ($i = 0; $i < count($adresses); $i++)
                    {
                        $body[] = array(
                            "objectId" => $adresses[$i]["objectid"],
                            "objectGuid"=> $adresses[$i]["objectguid"],
                            "text" => $adresses[$i]["Название типа"] . " " . $adresses[$i]["Название"],
                            "objectLevel" => levelName($adresses[$i]["Уровень"]),
                            "objectLevelText" => levelNameText($adresses[$i]["Уровень"])
                        );
                    }

                    bodyWithRequest("200", $body);
                } 
                else 
                {
                    setHTTPStatus("500", "Ошибка при выборе самого старшего элемента адреса в иерархии". $Link->error);
                }
            }
            else
            {
                goto withoutParams;
            }
        }
        else
        {
            withoutParams:
            $chooseParentForAll = $Link->query("SELECT * FROM addres_object WHERE `Уровень` = (SELECT MIN(CAST(`Уровень` AS DECIMAL(65))) FROM addres_object) AND `Актуальность` = 1 LIMIT 10");

            $adresses = [];

            if ($chooseParentForAll) 
            {
                while ($row = $chooseParentForAll->fetch_assoc()) 
                {
                    $adresses[] = $row;
                }
                $chooseParentForAll->free();

                $body = [];

                for ($i = 0; $i < count($adresses); $i++)
                {
                    $body[] = array(
                        "objectId" => $adresses[$i]["objectid"],
                        "objectGuid"=> $adresses[$i]["objectguid"],
                        "text" => $adresses[$i]["Название типа"] . " " . $adresses[$i]["Название"],
                        "objectLevel" => levelName($adresses[$i]["Уровень"]),
                        "objectLevelText" => levelNameText($adresses[$i]["Уровень"])
                    );

                }

                bodyWithRequest("200", $body);
            } 
            else 
            {
                setHTTPStatus("500", "Ошибка при выборе самого старшего элемента адреса в иерархии". $Link->error);
            }
        }

        mysqli_close($Link);
    }

    
}

function getElementOfAddressOnOneLevel($method, $uriList, $params)
{
    if (isset($uriList[4]))
    {
        setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
    }
    else
    {
        if ($method == "GET")
        {
            findElementsOnOneLevelWithThisParams($params);
        }
        else
        {
            setHTTPStatus("400", "Не тот метод");
        }
    }
}

function addressRequestAnswer($method, $uriList, $params = null)
{
    if (isset($uriList[3]))
    {
        switch ($uriList[3]) {
            case 'search':
                getElementOfAddressOnOneLevel($method, $uriList, $params);
                break;
            case 'chain':
                # code...
                break;
            default:
                setHTTPStatus("404", "Вы отправили запрос на несуществующую часть api");
                break;
        }
    }
    else
    {
        setHTTPStatus("404", "Вы отправили запрос на несуществующую часть api");
    }
}
?>