<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: master.php
-----------------------------------------------------
 Назначение: Главный процесс чата
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

ini_set('log_errors', 1);
//-- Кодировка
mb_internal_encoding('UTF-8');
ini_set('error_log', __DIR__.'/log.txt');

if(empty($argv[1]) || !in_array($argv[1], array('start', 'stop', 'restart'))) exit("there is no option (start|stop|restart)\r\n");

$config = array(
    'class'	=> 'UnioMasterHandler',
    'pid'	=> __DIR__.'/tmp/unio_master.pid',
    //'websocket' => 'tcp://127.0.0.1:8000',
    'localsocket' => 'tcp://127.0.0.1:8167',
    //'master' => 'tcp://127.0.0.1:8020',
    //'eventDriver' => 'event',
);

//-- Пути подключения классов
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);
//-- Функция автоподключения классов
spl_autoload_register(function($class)
{
	$UNIOCHAT = true;
	include $class . '.php';
});

$UnioMasterServer = new UnioMasterServer($config);
//-- Запуск с командой
call_user_func(array($UnioMasterServer, $argv[1]));

error_log('Ошибка: already stopped.');
