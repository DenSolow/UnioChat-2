<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: UnioMasterServer.php
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

isset($UNIOCHAT) or exit;

class UnioMasterServer
{
	public static $config;
	
    public function __construct($config)
	{
        self::$config = $config;
    }

    public function start()
	{
		if(file_exists(self::$config['pid']))
		{
			$pid = @file_get_contents(self::$config['pid']);
			if($this->isProcessRunning($pid))
			{
				error_log('Ошибка: process '.self::$config['class'].' already started.');
				exit;
			}
			//-- The process cleaned
			else
			{
				error_log('Внимание: процесс '.self::$config['class'].' отмечен, как запущенный, но не найден в системе и будет запущен заного...');
				unlink(self::$config['pid']);
			}
		}
		//-- Если не требуется создавать сервера, выходим
        if(empty(self::$config['localsocket'])) exit("error: config: !websocket && !localsocket && !master\r\n");

        $service = null;

		//-- Создаём локального мастера сокет для управления сокетами
        if(isset(self::$config['localsocket']))
		{
			//$context = stream_context_create();
			
			// local_cert must be in PEM format
			//stream_context_set_option($context, 'tls', 'local_cert', 'avtoelektro_su.crt');
			//stream_context_set_option($context, 'tls', 'local_pk', 'private.key');
			
          //  $service = @stream_socket_server(self::$config['localsocket'], $errorNumber, $errorString, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
		  	$service = @stream_socket_server(self::$config['localsocket'], $errorNumber, $errorString);
			if(!$service)
			{
				error_log("error: stream_socket_master: $errorString ($errorNumber)");
				exit;
			}
            stream_set_blocking($service, 0);
        }
		//-- Создаём файл для блокировки дубликата сокетов
		file_put_contents(self::$config['pid'], getmypid());
		//-- Классы
		$master = new self::$config['class']($service);
		$master->start();
    }

    public function stop()
	{
        $pid = @file_get_contents(self::$config['pid']);
        if($pid) 
		{			
			//-- Linux
			if(!$this->isWindowsOS()) $result = posix_kill($pid, SIGTERM);
			//-- Windows
			else
			{
				$info = exec('taskkill /F /PID '.$pid, $output, $return);
				$result = $return === 0 ? true : false;
			}
			//-- Успех закрытия
            if($result)
			{
				unlink(self::$config['pid']);
				error_log('Сервер: Остановка '.self::$config['class'].' успешно выполнена.');
			}
			else
			{
				$error = isset($output) ? 'Не удаётся найти процесс "'.$pid.'".' : posix_get_last_error();
				error_log(self::$config['class'].': '.$error);
			}
        }
		else
		{
			error_log('Ошибка: already stopped.');
        }
    }

    public function restart()
	{
        $pid = @file_get_contents(self::$config['pid']);
        if($pid)
		{
            $this->stop();
        }

        $this->start();
		
		error_log('Сервер: Перезапуск '.self::$config['class'].' успешно выполнен.');
    }
	
	//-- Is windows OS
	private function isWindowsOS()
	{
		return (stripos(PHP_OS, 'WIN') === false) ? false : true;
	}
	//-- Run a command
	private function executeInSystem($command)
	{
		$handle = popen($command, 'r');
		$output = stream_get_contents($handle);
		pclose($handle);

		return $output;
	}
	//-- Is process running
	private function isProcessRunning($pid, $processName = null)
	{
		$windowsCommand = 'tasklist';
		$windowsRegexp = '\s+' . $pid . '\s+';

		$linuxCommand = 'ps -A';
		$linuxRegexp = '^\s*' . $pid . '\s+';

		if(!empty($processName))
		{
			$windowsRegexp = '^' . $processName . '\S+' . $windowsRegexp;
			$linuxCommand = $linuxCommand . " | grep " . escapeshellarg($processName);
		}
		//-- Linux
		if(!$this->isWindowsOS())
		{
			$command = $linuxCommand;
			$regexp = $linuxRegexp;
		}
		//-- Windows
		else
		{
			$command = $windowsCommand;
			$regexp = $windowsRegexp;
		}

		$regexp = "/$regexp/i";

		$outputs = $this->executeInSystem($command);
		$outputs = preg_split("/[\n\r]+/i", $outputs);
		
		//-- If the process found
		foreach($outputs as $output) if(preg_match($regexp, $output) === 1)	return true;

		return false;
	}
}