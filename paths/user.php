<?php
include_once "helpers/headers.php";

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
    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $dateTimeString);
    $minDate = DateTime::createFromFormat('Y-m-d', '1900-01-01');
    $now = new DateTime();

    if ($dateTime !== false)
    {
        if ($dateTime->format("Y") >= $minDate->format("Y") &&
        $dateTime->format("Y-m-d\TH:i:s\Z") <= $now->format("Y-m-d\TH:i:s\Z"))
        {
            return 1;
        }

        return 0;
    }
    else
    {
        $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $dateTimeString);

        if ($dateTime !== false)
        {
            if ($dateTime->format("Y") >= $minDate->format("Y") &&
             $dateTime->format("Y-m-d\TH:i:s\Z") <= $now->format("Y-m-d\TH:i:s\Z"))
            {
                return 1;
            }
        
            return 0;
        }

        return 2;
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
                    $errors["birthDate"] = ["Неправильный тип данных"];
                }
                else if ($flagBirthDate == 2)
                {
                    $errors["birthDate"] = ["Дата не можеет быть раньше 1900 года или позже текущего времени"];
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


                $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $birthDate);
                $dateOfBirthday = $dateTime->format("Y-m-d");

                $Link = mysqli_connect("127.0.0.1", "root", "kirillgluhov", "blog");

                if (!$Link)
                {
                    setHTTPStatus("500", "Ошибка соединения с БД " .mysqli_connect_error());
                    exit;
                }
                else
                {
                    $token = bin2hex(random_bytes(64));
                    $password = password_hash($password, PASSWORD_DEFAULT);
                    $isUserExist = $Link->query("SELECT `Email` FROM user WHERE user.`Email` = '$email' ")->fetch_assoc();

                    if (isset($isUserExist["Email"]))
                    {
                        setHTTPStatus("400", "Пользователь с электронной почтой " . $email . " уже существует");
                    }
                    else
                    {
                        $userInsertResult = $Link->query("INSERT INTO user(`ФИО`, `Пароль`, `Email`, `Пол`, `День рождения`, `Телефон`) VALUES('$fullName', '$password', '$email', '$gender', '$dateOfBirthday', '$phoneNumber')");

                        if (!$userInsertResult)
                        {
                            setHTTPStatus("500", "Ошибка при добавлении пользователя " .$Link->error);
                        }
                        else
                        {
                            $userID = $Link->query("SELECT `Номер пользователя` FROM user WHERE user.`Email` = '$email'")->fetch_assoc();;
                            $userId = $userID["Номер пользователя"];

                            $currentTime = new DateTime();
                            $currentTime->modify('+1 hour');

                            $now = $currentTime->format('Y-m-d H:i:s');

                            $tokenInsertResult = $Link->query("INSERT INTO token(`Значение токена`, `Номер пользователя`, `Действительно до`) VALUES('$token', '$userId', '$now')");

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

function registerUser($method, $uriList, $body)
{
    if (isset($uriList[4]))
    {
        setHTTPStatus("404", "Вы написали слишком длинный запрос (есть лишняя часть запроса)");
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

function userRequestAnswer($method, $uriList, $body = null, $params = null, $token = null)
{
    if (isset($uriList[3]))
    {
        switch ($uriList[3]) {
            case "register":
                registerUser($method, $uriList, $body);
                break;
            case "login":
                # code...
                break;
            case "logout":
                # code...
                break;
            case "profile":
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

//mysqli_close($Link);

?>