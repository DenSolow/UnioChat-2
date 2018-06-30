<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: index.php
-----------------------------------------------------
 Назначение: Движок
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

isset($UNIOCHAT) or exit;

/*$dbhostname = '127.0.0.1';// Адрес
$dbusername = 'root';	  // Логин
$dbpassword = '';		  // Пароль
$database = 'p2pChat';  // Имя бызы
$prefix = 'pc_';		  // Префикс*/

//-- Кодировка
mb_internal_encoding('UTF-8');

//-- Параметры
$cfg = array(
	'gzip'			=> 1,
	'rus'			=> false,
	'path'			=> '/UnioChat2/',
	'site_url'		=> 'OMSite/',
	'host'			=> 'localhost', //-- CHANGE THIS TO YOU DOMAIN
	
	//-- Starts chat
	'win_path'		=>	'c:/php7/php'
);

?>