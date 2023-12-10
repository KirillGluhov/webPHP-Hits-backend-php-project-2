<?php
include_once "helpers/headers.php";
require_once 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;

global $Link;

function checkName($name)
{
    if (gettype($name) == "string" && strlen($name) >= 1)
    {
        return 1;
    }
    else if (gettype($name) == "string")
    {
        return 2;
    }

    return 0;
}

function checkPassword($password)
{
    if (gettype($password) == "string" && strlen($password) >= 6 && preg_match('/\d/', $password))
    {
        return 1;
    }
    else if (gettype($password) == "string" && strlen($password) >= 6)
    {
        return 2;
    }
    else if (gettype($password) == "string" && preg_match('/\d/', $password))
    {
        return 3;
    }
    else if (gettype($password) == 'string')
    {
        return 4;
    }

    return 0;
}

function checkEmail($email)
{
    if (gettype($email) == 'string' && filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        return 1;
    }
    else if (gettype($email) == 'string')
    {
        return 2;
    }

    return 0;
}

function checkEmailExtended($email, $Link, $userId)
{
    if (gettype($email) == 'string' && filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $isFreeEmail = $Link->query("SELECT `Email` FROM user WHERE user.`Email` = '$email' AND user.`Идентификатор пользователя` <> '$userId'");

        if ($isFreeEmail) 
        {
            $rowCount = $isFreeEmail->num_rows;
        
            if ($rowCount < 1) 
            {
                return 1;
            } 
            else 
            {
                setHTTPStatus("400", "Email " . $email . " занят");
                return 4;
            }
        } 
        else 
        {
            setHTTPStatus("500", "Ошибка при получении данных из БД");
            return 3;
        }
        

    }
    else if (gettype($email) == 'string')
    {
        return 2;
    }

    return 0;
}

function checkGender($gender)
{
    if (gettype($gender) == 'string' && ($gender == "Male" || $gender == "Female"))
    {
        return 1;
    }
    else if (gettype($gender) == "string")
    {
        return 2;
    }

    return 0;
}

function checkBirthday($dateTimeString)
{
    if (preg_match('/\d{4}-\d{2}-\d{2}/', $dateTimeString, $matches)) 
    {
        $dateTime = DateTime::createFromFormat('Y-m-d', $matches[0]);
        $minDate = DateTime::createFromFormat('Y-m-d', '1900-01-01');
        $now = new DateTime();

        if ($dateTime->format("Y") >= $minDate->format("Y") && $dateTime->format("Y-m-d") <= $now->format("Y-m-d"))
        {
            return 1;
        }

        return 0;
    } 
    else 
    {
        return 2;
    }

}

function getBirthDay($stringWithDate)
{
    if (preg_match('/\d{4}-\d{2}-\d{2}/', $stringWithDate, $matches))
    {
        return $matches[0];
    }
}

function checkPhoneNumber($phoneNumber)
{
    if (preg_match('/^\\+7 \\(\\d{3}\\) \\d{3}-\\d{2}-\\d{2}$/', $phoneNumber) && gettype($phoneNumber) == 'string') 
    {
        return 1;
    }
    else if (gettype($phoneNumber) == 'string')
    {
        return 2;
    }

    return 0;
}

function saveUser($body)
{
    if (isset($body)) 
    {
        if (isset($body["fullName"]) && isset($body["password"]) && isset($body["email"]) && isset($body["gender"]))
        {
            $flagFullName = checkName($body["fullName"]);
            $flagPassword = checkPassword($body["password"]);
            $flagEmail = checkEmail($body["email"]);
            $flagGender = checkGender($body["gender"]);

            $flagBirthDate = 1;
            $flagPhoneNumber = 1;

            if (isset($body["birthDate"]))
            {
                $flagBirthDate = checkBirthday($body["birthDate"]);
            }

            if (isset($body["phoneNumber"]))
            {
                $flagPhoneNumber = checkPhoneNumber($body["phoneNumber"]);
            }

            $errors = array();

            if ($flagFullName != 1)
            {
                if ($flagFullName == 0)
                {
                    $errors["fullName"] = ["Неправильный тип данных"];
                }
                else if ($flagFullName == 2)
                {
                    $errors["fullName"] = ["Длина должна быть не меньше 1"];
                }
            }

            if ($flagPassword != 1)
            {
                if ($flagPassword == 0)
                {
                    $errors["password"] = ["Неправильный тип данных"];
                }
                else if ($flagPassword == 2)
                {
                    $errors["password"] = ["В пароле должна быть хотя бы одна цифра"];
                }
                else if ($flagPassword == 3)
                {
                    $errors["password"] = ["Длина пароля должна быть не меньше 6"];
                }
                else if ($flagPassword == 4)
                {
                    $errors["password"] = ["В пароле должна быть хотя бы одна цифра", "Длина пароля должна быть не меньше 6"];
                }
            }

            if ($flagEmail != 1)
            {
                if ($flagEmail == 0)
                {
                    $errors["email"] = ["Неправильный тип данных"];
                }
                else if ($flagEmail == 2)
                {
                    $errors["email"] = ["Электронная почта не может быть такой"];
                }
            }

            if ($flagGender != 1)
            {
                if ($flagGender == 0)
                {
                    $errors["gender"] = ["Неправильный тип данных"];
                }
                else if ($flagGender == 2)
                {
                    $errors["gender"] = ["Нет такого пола"];
                }
            }

            if ($flagBirthDate != 1)
            {
                if ($flagBirthDate == 0)
                {
                    $errors["birthDate"] = ["Дата не можеет быть раньше 1900 года или позже текущего времени"];
                }
                else if ($flagBirthDate == 2)
                {
                    $errors["birthDate"] = ["Неправильный тип данных"];
                }
            }

            if ($flagPhoneNumber != 1)
            {
                if ($flagPhoneNumber == 0)
                {
                    $errors["phoneNumber"] = ["Неправильный тип данных"];
                }
                else if ($flagPhoneNumber == 2)
                {
                    $errors["phoneNumber"] = ["Неправильный формат номера телефона"];
                }

            }

            if ($flagFullName * $flagPassword * $flagEmail * $flagGender * $flagBirthDate * $flagPhoneNumber == 1)
            {
                $fullName = $body["fullName"];
                $password = $body["password"];
                $email = $body["email"];
                $gender = $body["gender"];
                $birthDate = (isset($body["birthDate"]) ? $body["birthDate"] : null);
                $phoneNumber = (isset($body["phoneNumber"]) ? $body["phoneNumber"] : null);

                $dateOfBirthday = null;

                if ($birthDate !== null)
                {
                    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $birthDate);

                    if (!$dateTime)
                    {
                        $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $birthDate);

                        if (!$dateTime)
                        {
                            $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:s.u', $birthDate);

                            if (!$dateTime)
                            {
                                $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:s', $birthDate);

                                if (!$dateTime)
                                {
                                    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $birthDate);

                                    if (!$dateTime)
                                    {
                                        $dateTime = DateTime::createFromFormat('Y-m-d', $birthDate);
                                    }
                                }
                            }
                        }
                    }

                    $dateOfBirthday = $dateTime->format("Y-m-d");
                }

                global $config;

                $Link = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password'], $config['db_name']);

                if (!$Link)
                {
                    setHTTPStatus("500", "Ошибка соединения с БД " .mysqli_connect_error());
                    exit;
                }
                else
                {
                    $now = time();
                    $currentTime = new DateTime();

                    $uuid = Uuid::uuid4()->toString();

                    
                    $currentTime->modify('+1 hour');
                    $expirationTime = $currentTime->format('Y-m-d H:i:s');


                    $password = hash('sha256', $password . $uuid);
                    $mainPartOfTokenJWT = array(
                        "iss" => "Blog.API",
                        "aud" => "Blog.API",
                        "nbf" => $now,
                        "iat" => $now,
                        "exp" => $now + 3600,
                        "nameId" => $uuid
                    );

                    $token = JWT::encode($mainPartOfTokenJWT, $config['secret_key'], 'HS256');

                    $isUserExist = $Link->query("SELECT `Email` FROM user WHERE user.`Email` = '$email' ")->fetch_assoc();

                    if (isset($isUserExist["Email"]))
                    {
                        setHTTPStatus("400", "Пользователь с электронной почтой " . $email . " уже существует");
                    }
                    else
                    {
                        $userInsertResult = null;

                        if ($dateOfBirthday !== null && $phoneNumber !== null)
                        {
                            $userInsertResult = $Link->query("INSERT INTO user(`ФИО`, `Пароль`, `Email`, `Пол`, `День рождения`, `Телефон`, `Идентификатор пользователя`) VALUES('$fullName', '$password', '$email', '$gender', '$dateOfBirthday', '$phoneNumber', '$uuid')");
                        }
                        else if ($phoneNumber !== null)
                        {
                            $userInsertResult = $Link->query("INSERT INTO user(`ФИО`, `Пароль`, `Email`, `Пол`, `Телефон`, `Идентификатор пользователя`) VALUES('$fullName', '$password', '$email', '$gender', '$phoneNumber', '$uuid')");
                        }
                        else if ($dateOfBirthday !== null)
                        {
                            $userInsertResult = $Link->query("INSERT INTO user(`ФИО`, `Пароль`, `Email`, `Пол`, `День рождения`, `Идентификатор пользователя`) VALUES('$fullName', '$password', '$email', '$gender', '$dateOfBirthday', '$uuid')");
                        }
                        else
                        {
                            $userInsertResult = $Link->query("INSERT INTO user(`ФИО`, `Пароль`, `Email`, `Пол`, `Идентификатор пользователя`) VALUES('$fullName', '$password', '$email', '$gender', '$uuid')");
                        }
                        

                        if (!$userInsertResult)
                        {
                            setHTTPStatus("500", "Ошибка при добавлении пользователя " .$Link->error);
                        }
                        else
                        {
                            $userID = $Link->query("SELECT `Идентификатор пользователя` FROM user WHERE user.`Email` = '$email'")->fetch_assoc();;
                            $userId = $userID["Идентификатор пользователя"];

                            $tokenInsertResult = $Link->query("INSERT INTO token(`Значение токена`, `Идентификатор пользователя`, `Действительно до`) VALUES('$token', '$userId', '$expirationTime')");

                            if (!$tokenInsertResult)
                            {
                                setHTTPStatus("500", "Ошибка при вставке токена". $Link->error);
                            }
                            else
                            {
                                echo json_encode(['token' => $token]);
                            }
                        }
                    }

                    mysqli_close($Link);
                    
                }
            }
            else
            {
                setHTTPStatus("400", "Неккоректные данные пользователя", $errors);
            }

        }
        else
        {
            setHTTPStatus("400", "Вы не передали минимальные требуемые данные для регистрации пользователя");
        }
        
    }
    else
    {
        setHTTPStatus("400", "Вы не передали данные для регистрации пользователя");
    }
}

function findUser($body)
{
    if (isset($body))
    {
        if (isset($body["email"]) && isset($body["password"]))
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
                $email = $body["email"];
                $password = $body["password"];

                $isUserExist = $Link->query("SELECT `Идентификатор пользователя` FROM user WHERE user.`Email` = '$email' ")->fetch_assoc();

                if (isset($isUserExist["Идентификатор пользователя"]))
                {
                    $userId = $isUserExist["Идентификатор пользователя"];
                    $password = $password = hash('sha256', $password . $userId);

                    $passwordFromDB = $Link->query("SELECT `Пароль` FROM user WHERE user.`Идентификатор пользователя` = '$userId'")->fetch_assoc();

                    if (isset($passwordFromDB["Пароль"]))
                    {
                        if ($passwordFromDB["Пароль"] == $password)
                        {
                            $now = time();
                            $currentTime = new DateTime();
                            
                            $currentTime->modify('+1 hour');
                            $expirationTime = $currentTime->format('Y-m-d H:i:s');

                            $mainPartOfTokenJWT = array(
                                "iss" => "Blog.API",
                                "aud" => "Blog.API",
                                "nbf" => $now,
                                "iat" => $now,
                                "exp" => $now + 3600,
                                "nameId" => $userId
                            );
        
                            $token = JWT::encode($mainPartOfTokenJWT, $config['secret_key'], 'HS256');

                            $tokenInsertResult = $Link->query("INSERT INTO token(`Значение токена`, `Идентификатор пользователя`, `Действительно до`) VALUES('$token', '$userId', '$expirationTime')");

                            if (!$tokenInsertResult)
                            {
                                setHTTPStatus("500", "Ошибка при вставке токена". $Link->error);
                            }
                            else
                            {
                                echo json_encode(['token' => $token]);
                            }
                        }
                        else
                        {
                            setHTTPStatus("400", "Пароль неправильный");
                        }
                    }
                    else
                    {
                        setHTTPStatus("500", "Почему-то у пользователя нет пароля");
                    }
                }
                else
                {
                    setHTTPStatus("400", "Пользователя с электронной почтой " . $email . " не существует");
                }
            }

            mysqli_close($Link);
        }
        else
        {
            setHTTPStatus("400", "Вы не передали минимальные требуемые данные для авторизации пользователя");
        }
    }
    else
    {
        setHTTPStatus("400", "Вы не передали данные для авторизации пользователя");
    }
}

function logoutUserWithThisToken($token)
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
                    $deleteUserToken = $Link->query("DELETE FROM token WHERE `Значение токена` = '$token'");
                        
                    if ($deleteUserToken)
                    {
                        $deletedRows = $Link->affected_rows;

                        if ($deletedRows > 0)
                        {
                            setHTTPStatus("200", "Пользователь успешно вышел");
                        }
                        else
                        {
                            setHTTPStatus("500", "Почему-то выйти не удалось. Возможно ошибка со стороны БД. Вот текст ошибки: " . $Link->error);
                        }
                    }
                    else
                    {
                        setHTTPStatus("500", "Запрос не дошёл к БД");
                    }
                }
                else
                {
                    setHTTPStatus("401", "Токен не подходит ни одному пользователю");
                }
            }
            else
            {
                setHTTPStatus("500", "Запрос не дошёл к БД");
            }
        }

        mysqli_close($Link);
    }
    else
    {
        setHTTPStatus("401", "Вы не передали данные для того, чтобы идентифицировать пользователя, из аккаунта которого вы собираетесь выйти");
    }
}

function getProfile($token)
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
                    $profile = $Link->query("SELECT * FROM user WHERE user.`Идентификатор пользователя` = '$userId'")->fetch_assoc();

                    $dateAndTime = explode(" ", $profile["Дата создания"]);

                    $body = [
                        "id" => $profile["Идентификатор пользователя"],
                        "createTime" => $dateAndTime[0] . "T" . $dateAndTime[1],
                        "fullName" => $profile["ФИО"],
                        "birthDate" => (isset($profile["День рождения"]) ? ($profile["День рождения"]) : null),
                        "gender" => $profile["Пол"],
                        "email" => $profile["Email"],
                        "phoneNumber" => (isset($profile["Телефон"]) ? $profile["Телефон"] : null)
                    ];

                    bodyWithRequest ("200", $body);


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
        }

        mysqli_close($Link);
    }
    else
    {
        setHTTPStatus("401", "Вы не передали данные для того, чтобы предоставить вам профиль пользователя");
    }
}

function changeUserProfile($token, $body)
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

                    if (isset($body["email"]) && isset($body["fullName"]) && isset($body["gender"]))
                    {
                        $email = $body["email"];
                        $fullName = $body["fullName"];
                        $gender = $body["gender"];

                        $birthDate = null;
                        $phoneNumber = null;

                        $flagIsCorrectEmail = checkEmailExtended($email, $Link, $userId);
                        $flagIsCorrectFullName = checkName($fullName);
                        $flagIsCorrectGender = checkGender($gender);

                        $flagIsCorrectBirthDate = 1;
                        $flagIsCorrectPhoneNumber = 1;

                        if (isset($body["birthDate"]))
                        {
                            $birthDate = getBirthDay($body["birthDate"]);
                            $flagIsCorrectBirthDate = checkBirthday($birthDate);
                        }

                        if (isset($body["phoneNumber"]))
                        {
                            $phoneNumber = $body["phoneNumber"];
                            $flagIsCorrectPhoneNumber = checkPhoneNumber($phoneNumber);
                        }

                        $errors = array();

                        if ($flagIsCorrectEmail * $flagIsCorrectFullName * $flagIsCorrectGender * $flagIsCorrectBirthDate * $flagIsCorrectPhoneNumber == 1)
                        {
                            $userInsertResult = null;

                            if ($birthDate != null && $phoneNumber != null)
                            {
                                $updateProfileData = $Link->query("UPDATE user SET `Email` = '$email', `ФИО` = '$fullName', `Пол` = '$gender', `День рождения` = '$birthDate', `Телефон` = '$phoneNumber' WHERE `Идентификатор пользователя` = '$userId'");
                            }
                            else if ($birthDate != null)
                            {
                                $updateProfileData = $Link->query("UPDATE user SET `Email` = '$email', `ФИО` = '$fullName', `Пол` = '$gender', `День рождения` = '$birthDate' WHERE `Идентификатор пользователя` = '$userId'");
                            }
                            else if ($phoneNumber != null)
                            {
                                $updateProfileData = $Link->query("UPDATE user SET `Email` = '$email', `ФИО` = '$fullName', `Пол` = '$gender', `Телефон` = '$phoneNumber' WHERE `Идентификатор пользователя` = '$userId'");
                            }
                            else
                            {
                                $updateProfileData = $Link->query("UPDATE user SET `Email` = '$email', `ФИО` = '$fullName', `Пол` = '$gender' WHERE `Идентификатор пользователя` = '$userId'");
                            }

                            if ($userInsertResult)
                            {
                                setHTTPStatus("500", "Ошибка при изменении данных пользователя " .$Link->error);
                            }
                            else
                            {
                                bodyWithRequest("200", null);
                            }
                        }
                        else
                        {
                            if ($flagIsCorrectEmail != 1)
                            {
                                if ($flagIsCorrectEmail == 0)
                                {
                                    $errors["email"] = ["Неправильный тип данных"];
                                }
                                else if ($flagIsCorrectEmail == 2)
                                {
                                    $errors["email"] = ["Электронная почта не может быть такой"];
                                }
                            }

                            if ($flagIsCorrectFullName != 1)
                            {
                                if ($flagIsCorrectFullName == 0)
                                {
                                    $errors["fullName"] = ["Неправильный тип данных"];
                                }
                                else if ($flagIsCorrectFullName == 2)
                                {
                                    $errors["fullName"] = ["Длина должна быть не меньше 1"];
                                }
                            }

                            if ($flagIsCorrectGender != 1)
                            {
                                if ($flagIsCorrectGender == 0)
                                {
                                    $errors["gender"] = ["Неправильный тип данных"];
                                }
                                else if ($flagIsCorrectGender == 2)
                                {
                                    $errors["gender"] = ["Нет такого пола"];
                                }
                            }

                            if ($flagIsCorrectBirthDate != 1)
                            {
                                if ($flagIsCorrectBirthDate == 0)
                                {
                                    $errors["birthDate"] = ["Дата не можеет быть раньше 1900 года или позже текущего времени"];
                                }
                                else if ($flagIsCorrectBirthDate == 2)
                                {
                                    $errors["birthDate"] = ["Неправильный тип данных"];
                                }
                            }

                            if ($flagIsCorrectPhoneNumber != 1)
                            {
                                if ($flagIsCorrectPhoneNumber == 0)
                                {
                                    $errors["phoneNumber"] = ["Неправильный тип данных"];
                                }
                                else if ($flagIsCorrectPhoneNumber == 2)
                                {
                                    $errors["phoneNumber"] = ["Неправильный формат номера телефона"];
                                }

                            }

                            setHTTPStatus("400", "Неккоректные данные пользователя", $errors);
                        }

                    }
                    else
                    {
                        setHTTPStatus("400", "Для изменения профиля пользователя необходимо передать ФИО, электронную почту и пол");
                    }
                }
                else
                {
                    setHTTPStatus("401", "Токен не подходит ни одному пользователю");
                }
            }
            else
            {
                setHTTPStatus("401", "Токен не подходит ни одному пользователю");
            }
        }

        mysqli_close($Link);
    }
    else
    {
        setHTTPStatus("401", "Вы не передали данные для того, чтобы изменить профиль пользователя");
    }
}

function registerUser($method, $uriList, $body)
{
    if (isset($uriList[4]))
    {
        setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
    }
    else
    {
        if ($method == "POST")
        {
            saveUser($body);
        }
        else
        {
            setHTTPStatus("400", "Не тот метод");
        }
    }
}

function loginUser($method, $uriList, $body)
{
    if (isset($uriList[4]))
    {
        setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
    }
    else
    {
        if ($method == "POST")
        {
            findUser($body);
        }
        else
        {
            setHTTPStatus("400", "Не тот метод");
        }
    }
}

function logoutUser($method, $uriList, $token)
{
    if (isset($uriList[4]))
    {
        setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
    }
    else
    {
        if ($method == "POST")
        {
            logoutUserWithThisToken($token);
        }
        else
        {
            setHTTPStatus("400", "Не тот метод");
        }
    }
}

function changeOrGiveProfile($method, $uriList, $token, $body=null)
{
    if (isset($uriList[4]))
    {
        setHTTPStatus("400", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
    }
    else
    {
        if ($method == "GET")
        {
            getProfile($token);
        }
        else if ($method == "PUT")
        {
            changeUserProfile($token, $body);
        }
        else
        {
            setHTTPStatus("400", "Не тот метод");
        }
    }
}

function userRequestAnswer($method, $uriList, $body = null, $params = null, $token = null)
{
    if (isset($uriList[3]))
    {
        switch ($uriList[3]) {
            case "register":
                registerUser($method, $uriList, $body);
                break;
            case "login":
                loginUser($method, $uriList, $body);
                break;
            case "logout":
                logoutUser($method, $uriList, $token);
                break;
            case "profile":
                changeOrGiveProfile($method, $uriList, $token, $body);
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