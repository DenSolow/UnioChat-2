<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: UnioWebGeneric.php
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

isset($UNIOCHAT) or exit;

abstract class UnioWebGeneric
{
    const SOCKET_BUFFER_SIZE = 1024;
    const MAX_SOCKET_BUFFER_SIZE = 10240;
    const MAX_SOCKETS = 1000;
    const SOCKET_MESSAGE_DELIMITER = "\n";
	const TIMEOUT = 5;
    protected $clients = array();
    protected $_server = null;
    protected $_master = null;
    protected $_read = array();//буферы чтения
    protected $_write = array();//буферы записи

    public function start()
	{
        $this->onStart();

        while(true)
		{
            //-- Подготавливаем массив всех сокетов, которые нужно обработать
            $read = $this->clients;

            if($this->_server) $read[] = $this->_server;
            if($this->_master) $read[] = $this->_master;

            if(!$read) return;

            $write = array();

            if($this->_write)
			{
                foreach ($this->_write as $connectionId => $buffer) 
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
					//-- На WebServer пришёл запрос от нового клиента
                    if($this->_server == $client)
					{ 
                        if((count($this->clients) < self::MAX_SOCKETS) && ($client = @stream_socket_accept($this->_server, self::TIMEOUT)))
						{
                            stream_set_blocking($client, 0);
                            $clientId = intval($client);
                            $this->clients[$clientId] = $client;
                            $this->_onOpen($clientId);
                        }
                    } 
					//-- Приём сообщения
					else
					{
                        $connectionId = intval($client);
						//-- Сообщение от мастера
						if($this->_master == $client)
						{
							//-- Было закрыто соединение
                            if(is_null($this->_read($connectionId)))
							{
                                $this->close($connectionId);
                                continue;
                            }
							//-- Вызываем пользовательский сценарий
                            while($data = $this->_readFromBuffer($connectionId))
							{
                                $this->onMasterMessage($data); 
                            }
                        }
						//-- Сообщение от WebSocket
						else
						{
							//-- Cоединение было закрыто или превышен размер буфера
                            if(!$this->_read($connectionId))
							{ 
                                $this->close($connectionId);
                                continue;
                            }
                            $this->_onMessage($connectionId);
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

        if(isset($this->clients[$connectionId])) unset($this->clients[$connectionId]);
        elseif($this->getConnectionById($connectionId) == $this->_server)unset($this->_server);
        elseif($this->getConnectionById($connectionId) == $this->_master) unset($this->_master);

        unset($this->_write[$connectionId]);
        unset($this->_read[$connectionId]);
    }
	/**
		* WebSocket: Закрытие соединения.
		*
		* @param integer $status  http://tools.ietf.org/html/rfc6455#section-7.4
		* @param string  $message A closing message, max 125 bytes.
	*/
	protected function _close($connectionId, $status = 1000, $message = 'ttfn')
	{
		$status_binstr = sprintf('%016b', $status);
    	$status_str = '';
    	foreach(str_split($status_binstr, 8) as $binstr) $status_str .= chr(bindec($binstr));
		$this->_write($connectionId, $this->_encode($status_str . $message, 'close'));
		
		//$this->is_closing = true;
		//$response = $this->_decode($connectionId); // Receiving a close frame will close the socket now.
		//var_dump($response);
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

        if(!strlen($data)) return;
		//-- Добавляем полученные данные в буфер чтения
        @$this->_read[$connectionId] .= $data;
        return strlen($this->_read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }

    protected function getConnectionById($connectionId)
	{
        if(isset($this->clients[$connectionId])) return $this->clients[$connectionId];
        if(intval($this->_server) == $connectionId) return $this->_server;
        if(intval($this->_master) == $connectionId) return $this->_master;
    }

    abstract protected function _onOpen($connectionId);

    abstract protected function _onMessage($connectionId);

    abstract protected function onServiceMessage($connectionId, $data);

    abstract protected function onMasterMessage($data);

    abstract protected function onServiceOpen($connectionId);

    abstract protected function onServiceClose($connectionId);
}