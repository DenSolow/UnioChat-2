<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 File: system/ajax/a_chat_get.php
-----------------------------------------------------
 Target: Ajax get a chat
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

if(empty($_POST['action'])) exit(json_encode(array(false, 'Error on incoming data.')));

$UNIOCHAT = true;

include '../../config.php';

//-- Путь к файлам
$path = __DIR__.'/../../';

if(PHP_OS == 'WINNT')
{
	pclose(popen('start /B '.$cfg['win_path'].' '.$path.'master.php start', 'r'));
	pclose(popen('start /B '.$cfg['win_path'].' '.$path.'worker.php start', 'r'));
}
else
{
	exec('nohup php '.$path.'master.php start > /dev/null 2>&1 &');
	exec('nohup php '.$path.'worker.php start > /dev/null 2>&1 &');
}

echo json_encode(array(true));

?>