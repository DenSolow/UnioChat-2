<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 File: system/ajax/a_transferclean.php
-----------------------------------------------------
 Назначение: Отчистка переданных файлов
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

if(empty($_POST['action']) or $_POST['action'] != "cleanANDclear") exit;

$UNIOCHAT = true;

//-- Подключение файла конфигурации и функций
include '../functions/filesystems.php';

//-- Путь к директории
$folderroot = dirname(__FILE__)."/../../uploads/transfer/";
//-- Сканируем директорию
$scan = scandir($folderroot);
//-- Текущее время
$time = time();
//-- Просмотр директории
foreach($scan as $folder)
{
	if($folder == "." or $folder == "..") continue;
	
	//-- Если это директория
	if(is_dir($folderroot.$folder))
	{
		//-- Время удаления папки
		$timetodel = intval($folder);
		//-- Если папку пора удалять
		if($time > $timetodel) deleteDirectory($folderroot.$folder);
	}
	//-- Иначе удаляем файл
	else unlink($folderroot.$folder);
}

?>