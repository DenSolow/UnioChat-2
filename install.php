<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: install.php
-----------------------------------------------------
 Назначение: Установка модуля (техническая)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

define('WEBCHAT', true);

//-- Подключение файла конфигурации
require 'config.php';

//-- Ïîäêëþ÷åíèå ê MySQL ñåðâåðó
mysql_connect($dbhostname,$dbusername,$dbpassword);
mysql_select_db($database);
mysql_query("SET NAMES UTF8");
mysql_query("SET character_set_client='utf8'");
mysql_query("SET collation_connection='utf8_general_ci'");
mysql_query("SET character_set_results='utf8'");

#########################################################
//-- Создание таблицы chats (Чаты)
#########################################################

$tablename = $prefix . "chats";      // Название таблицы

mysql_query(" create table $tablename (
	idnum int not null auto_increment,
	room INT,
	author INT,
	text TEXT CHARACTER SET utf8,
	date int,
	primary key(idnum)
	) ENGINE = MYISAM;")
	or die(mysql_error());
		
echo "Таблица $tablename успешно создана.<br>";

?>