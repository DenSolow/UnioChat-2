<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: UnioMasterGeneric.php
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

isset($UNIOCHAT) or exit;

abstract class UnioMasterGeneric
{
    const SOCKET_BUFFER_SIZE = 1024;
    const MAX_SOCKET_BUFFER_SIZE = 10240;
    const MAX_SOCKETS = 1000;
    const SOCKET_MESSAGE_DELIMITER = "\n";
	const TIMEOUT = 5;
    protected $services = array();
    protected $_service = null;
    protected $_read = array();//буферы чтения
    protected $_write = array();//буферы записи
	protected $pid;

    public function __construct($service)
	{
        $this->_service = $service;
        $this->pid = getmypid();
    }

    public function start()
	{
        $this->onStart();

        while(true)
		{
            //-- Подготавливаем массив всех сокетов, которые нужно обработать
            $read = $this->services;

            if($this->_service) $read[] = $this->_service;

            if(!$read) return;

            $write = array();

            if($this->_write)
			{
                foreach($this->_write as $connectionId => $buffer) 
				//var_export($buffer);
                    if($buffer) $write[] = $this->getConnectionById($connectionId);
            }

            $except = $read;

			//-- Обновляем массив сокетов, которые можно обработать
            stream_select($read, $write, $except, null);

			//-- Пришли данные от подключенных клиентов
            if($read)
			{
                foreach($read as $client)
				{
					//-- На MasterServer пришёл запрос от нового клиента
					if($this->_service == $client)
					{ 
                        if((count($this->services) < self::MAX_SOCKETS) && ($client = @stream_socket_accept($this->_service, self::TIMEOUT)))
						{
                            stream_set_blocking($client, 0);
                            $clientId = intval($client);
                            $this->services[$clientId] = $client;
                            $this->onServiceOpen($clientId);
                        }
                    } 
					//-- Приём сообщения
					else
					{
                        $connectionId = intval($client);
						//-- Сообщение от помощников
                        if(in_array($client, $this->services))
						{
							//-- Было закрыто соединение
                            if($this->_read($connectionId) === false)
							{ 
                                $this->close($connectionId);
                                continue;
                            }
							//-- Вызываем пользовательский сценарий
                            while($data = $this->_readFromBuffer($connectionId))
							{
                                $this->onServiceMessage($connectionId, $data); 
                            }
                        }
                    }
                }
            }

			//-- Проверяем, что мы его ещё не закрыли во время чтения
            if($write) foreach($write as $client) if(is_resource($client)) $this->_sendBuffer($client);

            if($except) foreach($except as $client) $this->_onError(intval($client));
        }
    }

    protected function _onError($connectionId)
	{
        error_log("An error has occurred: $connectionId");
    }

    protected function close($connectionId)
	{
        @fclose($this->getConnectionById($connectionId));

        if(isset($this->services[$connectionId])) unset($this->services[$connectionId]);
        elseif($this->getConnectionById($connectionId) == $this->_service) unset($this->_service);

        unset($this->_write[$connectionId]);
        unset($this->_read[$connectionId]);
    }
	
    protected function _write($connectionId, $data, $delimiter = '')
	{
        @$this->_write[$connectionId] .=  $data . $delimiter;
    }

    protected function _sendBuffer($connect)
	{
        $connectionId = intval($connect);
        $written = fwrite($connect, $this->_write[$connectionId], self::SOCKET_BUFFER_SIZE);
        $this->_write[$connectionId] = substr($this->_write[$connectionId], $written);
    }

    protected function _readFromBuffer($connectionId)
	{
        $data = '';

        if(false !== ($pos = strpos($this->_read[$connectionId], self::SOCKET_MESSAGE_DELIMITER)))
		{
            $data = substr($this->_read[$connectionId], 0, $pos);
            $this->_read[$connectionId] = substr($this->_read[$connectionId], $pos + strlen(self::SOCKET_MESSAGE_DELIMITER));
        }

        return $data;
    }

    protected function _read($connectionId)
	{
        $data = fread($this->getConnectionById($connectionId), self::SOCKET_BUFFER_SIZE);

        if(!strlen($data)) return false;
		//-- Добавляем полученные данные в буфер чтения
        @$this->_read[$connectionId] .= $data;
        return strlen($this->_read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }

    protected function getConnectionById($connectionId)
	{
        if(isset($this->services[$connectionId])) return $this->services[$connectionId];
        if(intval($this->_service) == $connectionId) return $this->_service;
    }

	abstract protected function onStart();
    abstract protected function onServiceMessage($connectionId, $data);
    protected function onServiceOpen($connectionId) {}
    protected function onServiceClose($connectionId) {}
}