<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 File: stop-restart-utilities.php
-----------------------------------------------------
 Target: Ajax stop/restart the chat
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

$UNIOCHAT = true;

include 'config.php';

//-- Action
$action = 'stop';
//-- Путь к файлам
$path = __DIR__.'/';

if(PHP_OS == 'WINNT')
{
	pclose(popen('start /B '.$cfg['win_path'].' '.$path.'master.php '.$action, 'r'));
	pclose(popen('start /B '.$cfg['win_path'].' '.$path.'worker.php '.$action, 'r'));
}
else
{
	exec('nohup php '.$path.'master.php '.$action.' > /dev/null 2>&1 &');
	exec('nohup php '.$path.'worker.php '.$action.' > /dev/null 2>&1 &');
}

?>