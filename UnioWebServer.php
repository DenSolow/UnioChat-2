<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: UnioWebServer.php
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

isset($UNIOCHAT) or exit;

class UnioWebServer
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
        if(empty(self::$config['websocket']) && empty(self::$config['master'])) exit("error: config: !websocket && !master\r\n");

        $server = $master = null;

		//-- Открываем серверный сокет для браузеров
        if(isset(self::$config['websocket']))
		{
			//-- FIRST VARIANT	
			/*$context = stream_context_create();
			
			// local_cert must be in PEM format
			stream_context_set_option($context, 'ssl', 'local_cert', __DIR__.'/avtoelektro_su.crt');
			stream_context_set_option($context, 'ssl', 'local_pk', __DIR__.'/private.key');
			// Pass Phrase (password) of private key
			//stream_context_set_option($context, 'tls', 'passphrase', 'u!2D(&]k');*/
			
			//-- WORKS VARIANT
			/*$options = array(
			'ssl' => array(
				//'peer_name' => 'avtoelektro.su',
				//'verify_peer' => false,
				'local_cert' => __DIR__.'/avtoelektro_su.crt',
				'local_pk' => __DIR__.'/private.key',
				//'disable_compression' => true,
				//'SNI_enabled' => true,
				//'ciphers' => 'EDH+aRSA+AESGCM:EDH+aRSA+AES:EECDH+aRSA+AESGCM:EECDH+aRSA+AES:-SHA:ECDHE-RSA-AES256-SHA:ECDHE-RSA-AES128-SHA:RSA+AESGCM:RSA+AES+SHA256:RSA+AES+SHA:DES-CBC3-SHA:DHE-RSA-AES256-SHA:DHE-RSA-AES128-SHA'
				//'ciphers' => 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:ECDHE-RSA-RC4-SHA:ECDHE-ECDSA-RC4-SHA:AES128:AES256:RC4-SHA:HIGH:!aNULL:!eNULL:!EXPORT:!DES:!3DES:!MD5:!PSK',
			),
		);
		$context = stream_context_create($options);*/

			//-- Usual
			$server = @stream_socket_server(self::$config['websocket'], $errorNumber, $errorString);
			//-- Transport Layer Security (TLS)
           // $server = @stream_socket_server(self::$config['websocket'], $errorNumber, $errorString, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
            if(!$server)
			{
				error_log("error: stream_socket_worker: $errorString ($errorNumber)");
				exit;
			}
			//stream_set_blocking($server, true);
			//$ret = stream_socket_enable_crypto($server, false);
			//$ret = stream_socket_enable_crypto($server, true, STREAM_CRYPTO_METHOD_SSLv23_SERVER);
            stream_set_blocking($server, 0);
			
			//error_log(var_export($ret, true));
        }
		//-- Подключаемся к мастеру для обработки сообщений от скриптов
        if(isset(self::$config['master']))
		{
			//$context = stream_context_create();
			
			// local_cert must be in PEM format
			//stream_context_set_option($context, 'tls', 'local_cert', 'avtoelektro_su.crt');
			//stream_context_set_option($context, 'tls', 'local_pk', 'private.key');
			
            //$master = @stream_socket_client(self::$config['master'], $errorNumber, $errorString, ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT, $context);
			$master = @stream_socket_client(self::$config['master'], $errorNumber, $errorString);
            if(!$master)
			{
				error_log("error: stream_socket_client: $errorString ($errorNumber)");
				exit;
			}
			stream_set_blocking($master, 0);
        }
		//-- Создаём файл для блокировки дубликата сокетов
		file_put_contents(self::$config['pid'], getmypid());
		//-- Классы
		$worker = new self::$config['class']($server, $master);
		$worker->start();
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
				error_log('Сервер: Остановка '.self::$config['class'].' успешно выполнена.');
			}
			else
			{
				$error = isset($output) ? 'Не удаётся найти процесс "'.$pid.'".' : posix_get_last_error();
				error_log(self::$config['class'].': '.$error);
			}
			//-- Удаляем файл процесса
			unlink(self::$config['pid']);
            /*sleep(1);
            posix_kill($pid, SIGKILL);
            sleep(1);
            if ($websocket = @stream_socket_client (self::$config['websocket'], $errno, $errstr)) {
                stream_socket_shutdown($websocket, STREAM_SHUT_RDWR);
            }

            if (!empty(self::$config['localsocket'])) {
                if ($localsocket = stream_socket_client (self::$config['localsocket'], $errno, $errstr)) {
                    stream_socket_shutdown($localsocket, STREAM_SHUT_RDWR);
                }
            }*/
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
			//-- Если WebSocket
			//UnioWebGeneric::stop();
			/*if(WebsocketGeneric::$_server)
			{
				foreach($worker->clients as $clientId => $client) $worker->_close($clientId);
			}*/
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