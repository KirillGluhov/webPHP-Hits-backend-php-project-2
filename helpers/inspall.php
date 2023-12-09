<?php

function startCreatingDBIfNotExists()
{
    global $config;

    $connection = mysqli_connect($config['db_host'], $config['db_username'], $config['db_password']);

    if ($connection->connect_error)
    {
        setHTTPStatus("500", "Ошибка подключении к СУБД". $connection->error);
    }
    else
    {
        $isExistDB = $connection->query("SELECT SCHEMA_NAME
        FROM information_schema.SCHEMATA
        WHERE SCHEMA_NAME = 'blog';");

        if ($isExistDB->num_rows == 0)
        {
            $create = $connection->query("CREATE DATABASE IF NOT EXISTS `blog`;");

            if ($connection->query("CREATE DATABASE IF NOT EXISTS `blog`;") != true)
            {
                setHTTPStatus("500", "Ошибка при создании СУБД". $connection->error);
            }
            else
            {
                $connectionToDB = mysqli_connect("127.0.0.1", "root", "kirillgluhov", "blog");

                if ($connectionToDB->connect_error)
                {
                    setHTTPStatus("500", "Ошибка подключении к БД". $connectionToDB->error);
                }
                else
                {
                    $createAddressObject = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`addres_object` (
                    `Идентификатор` bigint NOT NULL,
                    `objectid` bigint NOT NULL,
                    `objectguid` varchar(45) NOT NULL,
                    `Название` text NOT NULL,
                    `Название типа` text,
                    `Уровень` text NOT NULL,
                    `Актуальность` int DEFAULT NULL,
                    `Активность` int DEFAULT NULL,
                    PRIMARY KEY (`Идентификатор`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createAdministrationHierarchy = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`administration_hierarchy` (
                    `Идентификатор` bigint NOT NULL,
                    `objectid` bigint DEFAULT NULL,
                    `parentobjid` bigint DEFAULT NULL,
                    `regioncode` text,
                    `areacode` text,
                    `citycode` text,
                    `placecode` text,
                    `plancode` text,
                    `streetcode` text,
                    `Активность` int DEFAULT NULL,
                    `Путь` text,
                    PRIMARY KEY (`Идентификатор`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createComment = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`comment` (
                    `id` varchar(45) NOT NULL,
                    `createTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `Содержимое` text,
                    `modifiedDate` timestamp NULL DEFAULT NULL,
                    `deleteDate` timestamp NULL DEFAULT NULL,
                    `authorId` varchar(45) NOT NULL,
                    `subcomments` int NOT NULL DEFAULT '0',
                    `postId` varchar(45) NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `Автор` (`authorId`),
                    KEY `Пост` (`postId`),
                    CONSTRAINT `Автор` FOREIGN KEY (`authorId`) REFERENCES `user` (`Идентификатор пользователя`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `Пост` FOREIGN KEY (`postId`) REFERENCES `post` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createCommentHierarchy = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`comment_hierarchy` (
                    `commentId` varchar(45) NOT NULL,
                    `parentId` varchar(45) DEFAULT NULL,
                    `Путь` text NOT NULL,
                    PRIMARY KEY (`commentId`),
                    CONSTRAINT `Комм` FOREIGN KEY (`commentId`) REFERENCES `comment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createCommunity = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`community` (
                    `id` varchar(45) NOT NULL,
                    `Дата создания` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `Название` varchar(255) NOT NULL,
                    `Описание` text,
                    `Закрытость` tinyint NOT NULL,
                    `Число подписчиков` int NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `id_UNIQUE` (`id`),
                    UNIQUE KEY `Название_UNIQUE` (`Название`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createHouses = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`houses` (
                    `Идентификатор` bigint NOT NULL,
                    `objectid` bigint NOT NULL,
                    `objectguid` varchar(45) NOT NULL,
                    `housenum` text,
                    `addnum1` text,
                    `addnum2` text,
                    `housetype` int DEFAULT NULL,
                    `addtype1` int DEFAULT NULL,
                    `addtype2` int DEFAULT NULL,
                    `Актуальность` int DEFAULT NULL,
                    `Активность` int DEFAULT NULL,
                    PRIMARY KEY (`Идентификатор`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createLike = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`like` (
                    `userId` varchar(45) NOT NULL,
                    `postId` varchar(45) NOT NULL,
                    PRIMARY KEY (`userId`,`postId`),
                    KEY `Пост postId` (`postId`),
                    CONSTRAINT `userId, польз` FOREIGN KEY (`userId`) REFERENCES `user` (`Идентификатор пользователя`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `Пост postId` FOREIGN KEY (`postId`) REFERENCES `post` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createPost = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`post` (
                    `id` varchar(45) NOT NULL,
                    `Время создания` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `Заголовок` text NOT NULL,
                    `Описание` text NOT NULL,
                    `Время чтения` int NOT NULL,
                    `Изображение` text,
                    `authorId` varchar(45) NOT NULL,
                    `communityId` varchar(45) DEFAULT NULL,
                    `addressId` varchar(45) DEFAULT NULL,
                    `Число лайков` varchar(45) NOT NULL DEFAULT '0',
                    `Число комментариев` varchar(45) NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `id_UNIQUE` (`id`),
                    KEY `Id автора_idx` (`authorId`),
                    KEY `Id сообщества_idx` (`communityId`),
                    CONSTRAINT `Id автора` FOREIGN KEY (`authorId`) REFERENCES `user` (`Идентификатор пользователя`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `Id сообщества` FOREIGN KEY (`communityId`) REFERENCES `community` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createTag = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`tag` (
                    `id` varchar(45) NOT NULL,
                    `Время создания` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `Название` text NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `id_UNIQUE` (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createTagPost = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`tag-post` (
                    `tagId` varchar(45) NOT NULL,
                    `postId` varchar(45) NOT NULL,
                    PRIMARY KEY (`tagId`,`postId`),
                    KEY `Пост у тега` (`postId`),
                    CONSTRAINT `Пост у тега` FOREIGN KEY (`postId`) REFERENCES `post` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `Тег` FOREIGN KEY (`tagId`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createToken = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`token` (
                    `Значение токена` varchar(512) NOT NULL,
                    `Действительно до` datetime DEFAULT NULL,
                    `Идентификатор пользователя` varchar(45) NOT NULL,
                    PRIMARY KEY (`Значение токена`),
                    KEY `Владелец токена_idx` (`Идентификатор пользователя`),
                    CONSTRAINT `Владелец токена` FOREIGN KEY (`Идентификатор пользователя`) REFERENCES `user` (`Идентификатор пользователя`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createUser = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`user` (
                    `ФИО` varchar(255) NOT NULL,
                    `День рождения` date DEFAULT NULL,
                    `Пол` enum('Male','Female') NOT NULL,
                    `Телефон` varchar(18) DEFAULT NULL,
                    `Email` varchar(255) NOT NULL,
                    `Пароль` varchar(255) NOT NULL,
                    `Дата создания` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `Идентификатор пользователя` varchar(45) NOT NULL,
                    PRIMARY KEY (`Идентификатор пользователя`),
                    UNIQUE KEY `Email_UNIQUE` (`Email`),
                    UNIQUE KEY `Идентификатор пользователя_UNIQUE` (`Идентификатор пользователя`),
                    KEY `Автор_idx` (`ФИО`,`Идентификатор пользователя`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    $createUserCommunity = $connection->query("
                    CREATE TABLE IF NOT EXISTS `blog`.`user-community` (
                    `userId` varchar(45) NOT NULL,
                    `communityId` varchar(45) NOT NULL,
                    `Роль` enum('Administrator','Subscriber') NOT NULL,
                    `Уникальный идентификатор` int unsigned NOT NULL AUTO_INCREMENT,
                    PRIMARY KEY (`Уникальный идентификатор`),
                    UNIQUE KEY `Уникальный идентификатор_UNIQUE` (`Уникальный идентификатор`),
                    KEY `User-Community_idx` (`communityId`),
                    KEY `Community-User_idx` (`userId`),
                    CONSTRAINT `Community-User` FOREIGN KEY (`userId`) REFERENCES `user` (`Идентификатор пользователя`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `User-Community` FOREIGN KEY (`communityId`) REFERENCES `community` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

                    



                    $triggerCommentInsert = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `comment_AFTER_INSERT` AFTER INSERT ON `blog`.`comment` FOR EACH ROW BEGIN
                    DECLARE comments INT;
                    
                    SELECT COUNT(*) INTO comments FROM `comment` WHERE `comment`.postId = NEW.postId;
                    
                    UPDATE post SET `Число комментариев` = comments WHERE post.id = NEW.postId;
                    END;;
                    DELIMITER ;");

                    $triggerComment = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `comment_AFTER_UPDATE` AFTER UPDATE ON `comment` FOR EACH ROW BEGIN
                    DECLARE comments INT;
                    
                    SELECT COUNT(*) INTO comments FROM `comment` WHERE `comment`.postId = NEW.postId;
                    
                    UPDATE post SET `Число комментариев` = comments WHERE post.id = NEW.postId;
                    END;;
                    DELIMITER ;");

                    $triggerCommentDelete = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `comment_AFTER_DELETE` AFTER DELETE ON `comment` FOR EACH ROW BEGIN
                    DECLARE comments INT;
                    
                    SELECT COUNT(*) INTO comments FROM `comment` WHERE `comment`.postId = OLD.postId;
                    
                    UPDATE post SET `Число комментариев` = comments WHERE post.id = OLD.postId;
                    END;;
                    DELIMITER ;");

                    $triggerCommentHierarchyInsert = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `comment_hierarchy_AFTER_INSERT` AFTER INSERT ON `comment_hierarchy` FOR EACH ROW BEGIN
                    DECLARE subcomments INT;
                    
                    SELECT COUNT(*) INTO subcomments FROM `comment_hierarchy` WHERE `comment_hierarchy`.parentId = NEW.parentId;
                    
                    UPDATE `comment` SET `subcomments` = subcomments WHERE `comment`.id = NEW.parentId;
                    END;;
                    DELIMITER ;");

                    $triggerCommentHierarchyUpdate = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `comment_hierarchy_AFTER_UPDATE` AFTER UPDATE ON `comment_hierarchy` FOR EACH ROW BEGIN
                    DECLARE subcomments INT;
                    
                    SELECT COUNT(*) INTO subcomments FROM `comment_hierarchy` WHERE `comment_hierarchy`.parentId = NEW.parentId;
                    
                    UPDATE `comment` SET `subcomments` = subcomments WHERE `comment`.id = NEW.parentId;
                    END;;
                    DELIMITER ;");

                    $triggerCommentHierarchyDelete = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `comment_hierarchy_AFTER_DELETE` AFTER DELETE ON `comment_hierarchy` FOR EACH ROW BEGIN
                    DECLARE subcomments INT;
                    
                    SELECT COUNT(*) INTO subcomments FROM `comment_hierarchy` WHERE `comment_hierarchy`.parentId = OLD.parentId;
                    
                    UPDATE `comment` SET `subcomments` = subcomments WHERE `comment`.id = OLD.parentId;
                    END;;
                    DELIMITER ;");

                    $triggerLikeInsert = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `like_AFTER_INSERT` AFTER INSERT ON `like` FOR EACH ROW BEGIN
                    DECLARE likes INT;
                    
                    SELECT COUNT(*) INTO likes FROM `like` WHERE `like`.postId = NEW.postId;
                    
                    UPDATE post SET `Число лайков` = likes WHERE post.id = NEW.postId;
                    END;;
                    DELIMITER ;");

                    $triggerLikeUpdate = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `like_AFTER_UPDATE` AFTER UPDATE ON `like` FOR EACH ROW BEGIN
                    DECLARE likes INT;
                    
                    SELECT COUNT(*) INTO likes FROM `like` WHERE `like`.postId = NEW.postId;
                    
                    UPDATE post SET `Число лайков` = likes WHERE post.id = NEW.postId;
                    END;;
                    DELIMITER ;");

                    $triggerLikeDelete = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `like_AFTER_DELETE` AFTER DELETE ON `like` FOR EACH ROW BEGIN
                    DECLARE likes INT;
                    
                    SELECT COUNT(*) INTO likes FROM `like` WHERE `like`.postId = OLD.postId;
                    
                    UPDATE post SET `Число лайков` = likes WHERE post.id = OLD.postId;
                    END;;
                    DELIMITER ;");

                    $triggerTagInsert = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `tag_BEFORE_INSERT` BEFORE INSERT ON `tag` FOR EACH ROW BEGIN
                    SET NEW.id = UUID();
                    END;;
                    DELIMITER ;");

                    $triggerUserCommunityInsert = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `user-community_AFTER_INSERT` AFTER INSERT ON `user-community` FOR EACH ROW BEGIN
                    DECLARE subscribers INT;
                    
                    SELECT COUNT(*) INTO subscribers FROM `user-community` WHERE `user-community`.communityId = NEW.communityId AND `user-community`.`Роль` = 'Subscriber';
                    
                    UPDATE community SET `Число подписчиков` = subscribers WHERE community.id = NEW.communityId;
                    END;;
                    DELIMITER ;");

                    $triggerUserCommunityUpdate = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `user-community_AFTER_UPDATE` AFTER UPDATE ON `user-community` FOR EACH ROW BEGIN
                    DECLARE subscribers INT;
                    
                    SELECT COUNT(*) INTO subscribers FROM `user-community` WHERE `user-community`.communityId = NEW.communityId AND `user-community`.`Роль` = 'Subscriber';
                    
                    UPDATE community SET `Число подписчиков` = subscribers WHERE community.id = NEW.communityId;
                    END;;
                    DELIMITER ;");

                    $triggerUserCommunityDelete = $connection->query("
                    DELIMITER ;;
                    CREATE TRIGGER `user-community_AFTER_DELETE` AFTER DELETE ON `user-community` FOR EACH ROW BEGIN
                    DECLARE subscribers INT;
                    
                    SELECT COUNT(*) INTO subscribers FROM `user-community` WHERE `user-community`.communityId = OLD.communityId AND `user-community`.`Роль` = 'Subscriber';
                    
                    UPDATE community SET `Число подписчиков` = subscribers WHERE community.id = OLD.communityId;
                    END;;
                    DELIMITER ;");

                    $dataTag = $connection->query("INSERT INTO `tag` VALUES ('19904a10-8ece-11ee-8353-d8bbc1fa25b1','2023-11-29 15:43:53','18+'),('19905ce1-8ece-11ee-8353-d8bbc1fa25b1','2023-11-29 15:43:53','IT'),('19906abb-8ece-11ee-8353-d8bbc1fa25b1','2023-11-29 15:43:53','Приколы'),('1990762c-8ece-11ee-8353-d8bbc1fa25b1','2023-11-29 15:43:53','Интернет');");
                }

                mysqli_close($connectionToDB);
            }
        }
    }

    mysqli_close($connection);
}



?>