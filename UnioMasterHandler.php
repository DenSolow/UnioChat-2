<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: UnioMasterHandler.php
-----------------------------------------------------
 Назначение: Обработка данных главного процесса
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

isset($UNIOCHAT) or exit;

class UnioMasterHandler extends UnioMasterGeneric
{
	//-- Время работы
	private $uptime;
	
	//-- Каналы (ID => Название, Тема)
	protected $channels = array(0 => array('Main', ''));
	//-- Пользователи
   // protected $logins = array();
	
	//-- Вызывается по запуск сервера
	protected function onStart()
	{
		$this->uptime = time();
		error_log('Сервер: Запуск '.UnioMasterServer::$config['class'].' успешно выполнен.');
	}
	//-- Вызывается при получении сообщения от помощника
    protected function onServiceMessage($connectionId, $packet)
	{
        $packet = json_decode($packet, true);

		//-- ПРИЁМ: Команды (16)
		if($packet['type'] == 'command')
		{
			if($packet['data'] == 'uptime') 
			{
				$output = time() - $this->uptime;
				
				//-- ПЕРЕДАЧА: Команды (16)
				$this->sendPacketToCurrent($connectionId, 'command', $output);
			}
		}
		/*return;
        if($packet[0] == 'message')
		{
            $this->sendPacketToOther($connectionId, 'message', $packet[1]);
        }
		//-- ПРИЁМ: Нового пользователя (1)
		elseif($packet[0] == 'login')
		{
			//-- Запись в массив пользователей
			$logins[$packet[1]['id']] = $packet[1]['info'];
			//-- ПЕРЕДАЧА: Пользователь записан (1)
			$this->_write($connectionId, json_encode(array('login', $packet[1])));
            if (in_array($login, $this->logins)) {
                $packet[1]['result'] = false;
                $this->sendPacketToCurrent($connectionId, 'login', $packet[1]);
            } else {
                $this->logins[] = $login;
                $packet[1]['result'] = true;
                $this->sendPacketToCurrent($connectionId, 'login', $packet[1]);
                $packet[1]['clientId'] = -1;
                $this->sendPacketToOther($connectionId, 'login', $packet[1]);
            }
        }
		elseif($packet[0] == 'logout')
		{
            $login = $packet[1]['login'];
            unset($this->logins[array_search($login, $this->logins)]);
            $this->sendPacketToOther($connectionId, 'logout', $packet[1]);
        }*/
    }

    public function sendPacketToCurrent($connectionId, $cmd, $data)
	{
        $this->_write($connectionId, json_encode(array($cmd, $data)), "\n");
    }

    public function sendPacketToOther($connectionId, $cmd, $data)
	{
        $data = json_encode(array($cmd, $data));
		//-- Исключаем элемент массива //-- !!! Возможна замена
		//var_dump($this->services);
		//-- Пересылаем данные во все помошники
        foreach($this->services as $workerId => $worker)
            if($workerId !== $connectionId) $this->_write($workerId, $data);
    }
}