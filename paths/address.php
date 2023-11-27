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

function typeForAddnum($type)
{
    switch ($type) {
        case 1:
            return " к. ";
        case 2:
            return " стр. ";
        case 3:
            return " соор. ";
        case 4:
            return " литера ";
        case null:
            return "";
        default:
            return " ";
    }
}

function makeNameForBuilding($building)
{
    return $building["housenum"] . typeForAddnum($building["addtype1"]) . $building["addnum1"] . typeForAddnum($building["addtype2"]) . $building["addnum2"];
}

function functionThatReturnBody($chooseAdressesWithoutBuildings, $chooseBuildings, $body)
{
    if ($chooseAdressesWithoutBuildings)
    {
        $adresses = [];

        while ($row = $chooseAdressesWithoutBuildings->fetch_assoc()) 
        {
            $adresses[] = $row;
        }
        $chooseAdressesWithoutBuildings->free();

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
    }
    else
    {
        setHTTPStatus("500", "Ошибка при поиске элементов, являющихся дочерними, для данного". $Link->error);
    }

    if ($chooseBuildings)
    {
        $adresses = [];

        while ($row = $chooseBuildings->fetch_assoc()) 
        {
            $adresses[] = $row;
        }
        $chooseBuildings->free();

        for ($i = 0; $i < count($adresses); $i++)
        {
            $body[] = array(
                "objectId" => $adresses[$i]["objectid"],
                "objectGuid"=> $adresses[$i]["objectguid"],
                "text" => makeNameForBuilding($adresses[$i]),
                "objectLevel" => "Building",
                "objectLevelText" => "Здание (сооружение)"
            );
        }
    }
    else
    {
        setHTTPStatus("500", "Ошибка при поиске элементов, являющихся дочерними, для данного". $Link->error);
    }

    bodyWithRequest("200", $body);
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

            
            $body = [];

            if (isset($parentId) && isset($partOfName))
            {
                $parentnameWithProcents = "%" . $partOfName . "%";
                $chooseAdressesWithoutBuildings = $Link->query("SELECT DISTINCT addres_object.objectid, addres_object.objectguid, addres_object.`Название`, addres_object.`Название типа`, addres_object.`Уровень` FROM addres_object 
                INNER JOIN administration_hierarchy ON addres_object.objectid = administration_hierarchy.objectid
                WHERE administration_hierarchy.parentobjid = '$parentId' AND addres_object.`Актуальность` = 1 AND addres_object.`Название` LIKE '$parentnameWithProcents'
                ORDER BY `Название`, `Название типа`;");

                $chooseBuildings = $Link->query("SELECT DISTINCT houses.objectid, houses.objectguid, houses.housenum, houses.addnum1, houses.addnum2, houses.housetype, houses.addtype1, houses.addtype2 FROM houses
                INNER JOIN administration_hierarchy ON houses.objectid = administration_hierarchy.objectid
                WHERE administration_hierarchy.parentobjid = '$parentId' AND houses.`Актуальность` = 1 AND houses.housenum LIKE '$parentnameWithProcents'
                ORDER BY housenum, addnum1, addnum2;");

                functionThatReturnBody($chooseAdressesWithoutBuildings, $chooseBuildings, $body);
            }
            else if (isset($parentId))
            {
                $chooseAdressesWithoutBuildings = $Link->query("SELECT DISTINCT addres_object.objectid, addres_object.objectguid, addres_object.`Название`, addres_object.`Название типа`, addres_object.`Уровень` FROM addres_object 
                INNER JOIN administration_hierarchy ON addres_object.objectid = administration_hierarchy.objectid
                WHERE administration_hierarchy.parentobjid = '$parentId' AND addres_object.`Актуальность` = 1
                ORDER BY `Название типа`, `Название`
                LIMIT 10;");

                $chooseBuildings = $Link->query("SELECT DISTINCT houses.objectid, houses.objectguid, houses.housenum, houses.addnum1, houses.addnum2, houses.housetype, houses.addtype1, houses.addtype2 FROM houses
                INNER JOIN administration_hierarchy ON houses.objectid = administration_hierarchy.objectid
                WHERE administration_hierarchy.parentobjid = '$parentId' AND houses.`Актуальность` = 1
                ORDER BY housenum, addnum1, addnum2
                LIMIT 10;");

                functionThatReturnBody($chooseAdressesWithoutBuildings, $chooseBuildings, $body);
            }
            else if (isset($partOfName))
            {
                $nameFindString = "%" . $partOfName . "%";
                $chooseParentForAll = $Link->query("SELECT * FROM addres_object WHERE `Уровень` = (
                    SELECT MIN(CAST(`Уровень` AS DECIMAL(65))) FROM addres_object
                    ) 
                    AND `Актуальность` = 1 AND `Название` LIKE '$nameFindString' LIMIT 10");

                if ($chooseParentForAll) 
                {
                    $adresses = [];

                    while ($row = $chooseParentForAll->fetch_assoc()) 
                    {
                        $adresses[] = $row;
                    }
                    $chooseParentForAll->free();

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

function createChainFromAdresses($params)
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
            $guid = null;

            foreach($params as $nameAndValue)
            {
                $partsOfNameAndValue = explode("=", $nameAndValue);

                if (isset($partsOfNameAndValue[0]) && $partsOfNameAndValue[0] === "objectGuid")
                {
                    $guid = $partsOfNameAndValue[1];
                }
            }

            if (isset($guid))
            {
                if (preg_match('/^[0-9A-Fa-f]{8}\-[0-9A-Fa-f]{4}\-[0-9A-Fa-f]{4}\-[0-9A-Fa-f]{4}\-[0-9A-Fa-f]{12}$/', $guid))
                {
                    $chainWithLastBuilding = $Link->query("SELECT administration_hierarchy.`Путь` FROM houses
                    INNER JOIN administration_hierarchy ON houses.objectid = administration_hierarchy.objectid
                    WHERE objectguid = '$guid' AND houses.`Актуальность` = 1;")->fetch_assoc();

                    $chainWithLastElementOfAdres = $Link->query("SELECT administration_hierarchy.`Путь` FROM addres_object
                    INNER JOIN administration_hierarchy ON addres_object.objectid = administration_hierarchy.objectid
                    WHERE objectguid = '$guid' AND addres_object.`Актуальность` = 1;")->fetch_assoc();

                    $body = [];

                    if ($chainWithLastBuilding)
                    {
                        $objectIds = explode(".", $chainWithLastBuilding["Путь"]);

                        for ($i = 0; $i < count($objectIds)-1; $i++)
                        {
                            $objectId = $objectIds[$i];

                            $adresPart = $Link->query("SELECT objectid, objectguid, `Название`, `Название типа`, `Уровень` FROM addres_object WHERE objectid = '$objectId' AND `Актуальность` = 1;")->fetch_assoc();

                            $body[] = array(
                                "objectId" => $adresPart["objectid"],
                                "objectGuid"=> $adresPart["objectguid"],
                                "text" => $adresPart["Название типа"] . " " . $adresPart["Название"],
                                "objectLevel" => levelName($adresPart["Уровень"]),
                                "objectLevelText" => levelNameText($adresPart["Уровень"])
                            );
                        }

                        $lastObjectId = $objectIds[count($objectIds)-1];

                        $building = $Link->query("SELECT objectid, objectguid, housenum, addnum1, addnum2, housetype, addtype1, addtype2 FROM houses WHERE objectid = '$lastObjectId' AND `Актуальность` = 1;")->fetch_assoc();

                        $body[] = array(
                            "objectId" => $building["objectid"],
                            "objectGuid"=> $building["objectguid"],
                            "text" => makeNameForBuilding($building),
                            "objectLevel" => "Building",
                            "objectLevelText" => "Здание (сооружение)"
                        );
                    }

                    if ($chainWithLastElementOfAdres)
                    {
                        $objectIds = explode(".", $chainWithLastElementOfAdres["Путь"]);
                        
                        foreach ($objectIds as $objectId)
                        {
                            $adresPart = $Link->query("SELECT objectid, objectguid, `Название`, `Название типа`, `Уровень` FROM addres_object WHERE objectid = '$objectId' AND `Актуальность` = 1;")->fetch_assoc();

                            $body[] = array(
                                "objectId" => $adresPart["objectid"],
                                "objectGuid"=> $adresPart["objectguid"],
                                "text" => $adresPart["Название типа"] . " " . $adresPart["Название"],
                                "objectLevel" => levelName($adresPart["Уровень"]),
                                "objectLevelText" => levelNameText($adresPart["Уровень"])
                            );
                        }
                    }

                    bodyWithRequest("200", $body);
                }
                else
                {
                    setHTTPStatus("400", "То, что вы передали, guid-ом не является". $Link->error);
                }
            } 
            else 
            {
                setHTTPStatus("400", "Вы не передали guid адреса". $Link->error);
            }

        }
        else
        {
            setHTTPStatus("400", "Вы не передали guid адреса". $Link->error);
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

function getElementsOfAddressInChain($method, $uriList, $params)
{
    if (isset($uriList[4]))
    {
        setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
    }
    else
    {
        if ($method == "GET")
        {
            createChainFromAdresses($params);
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
                getElementsOfAddressInChain($method, $uriList, $params);
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