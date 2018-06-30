<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: system/functions/uniochat.php
-----------------------------------------------------
 Назначение: Функции юнио чата
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

isset($UNIOCHAT) or exit;

//-- Определение браузера
function getbrowser($useragent = "")
{
	if(empty($useragent)) return "??";
	if(stripos($useragent, "opera") !== false) return "Opera";
	if(stripos($useragent, "chrome") !== false) return "Chrome";
	if(stripos($useragent, "firefox") !== false) return "Firefox";
	if(stripos($useragent, "safari") !== false) return "Safari";
	if(stripos($useragent, "msie") !== false) return "Internet Explorer";
	return "??";
}

?>