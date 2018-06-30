<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: UnioWebHandler.php
-----------------------------------------------------
 Назначение: Обработка данных дочернего процесса
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

isset($UNIOCHAT) or exit;

//пример реализации чата
class UnioWebHandler extends UnioWebDaemon
{
	//private $flud;

	//-- Пользователи
    protected $logins = array();
	//-- Список пользователей
	private $users = array();
	//-- Каналы пользователей
	private $users_ch = array();
	//-- Каналы
	private $channels = array(0 => array());
	//-- Названия каналов
	private $titles = array(0 => 'Main');
	//-- Темы каналов
	private $topics	= array(0 => '');

	//-- ПЕРЕДАЧА: Мастеру о новом пользователе (1), (2)
    protected function onOpen($connectionId, $info)
	{
		//-- ПОИСК УНИКАЛЬНОГО ID ПОЛЬЗОВАТЕЛЯ
		preg_match('/uc_uid=([^;]+)/i', $info['Cookie'], $matches);
		//-- Если не найден или пуст
		$UID = intval($matches[1]);
		//-- Пользователи
		$this->logins[$connectionId] = $info;
		$this->users[$connectionId] = array($info['Ip'], $info['Ip'], $UID, true, '', 0);
		//-- Автовход в #Main - ID = 0
		$this->users_ch[$connectionId] = array(0);
		$this->channels[0][] = $connectionId;
		//-- ПОИСК ПСЕВДОНИМА
		preg_match('/uc_nick=([^;]+)/i', $info['Cookie'], $matches);
		//-- Если найден и не пуст, добавляем
		if(count($matches) > 0 and !empty($matches[1])) $this->users[$connectionId][0] = rawurldecode($matches[1]);
		//-- БРАУЗЕР
		$this->users[$connectionId][4] = isset($info['User-Agent']) ? $this->getbrowser($info['User-Agent']) : $this->getbrowser();
		//-- ПЕРЕДАЧА: Текущему пользователю список пользователей (1)
		$this->sendPacketToClient($connectionId, 1, array($connectionId, $this->users, $this->topics[0]));
		//-- ПЕРЕЛАЧА: Остальным пользователям канала информацию о подключении пользователя (2)
		$this->sendPacketToOther($connectionId, 2, array($connectionId, $this->users[$connectionId]));
    }
	//-- ПЕРЕДАЧА: Пользотель вышел (3)
    protected function onClose($connectionId)
	{
		$key = array_search($connectionId, $this->channels[0]);
		//-- Убираем некоторые элементы массивов и канал Main пользователя
		unset($this->logins[$connectionId], $this->users[$connectionId], $this->users_ch[$connectionId][0], $this->channels[0][$key]);
		//-- Просматриваем каналы
		if(count($this->users_ch[$connectionId]) > 0) foreach($this->users_ch[$connectionId] as $channel)
		{
			$this->channelLeave($connectionId, $channel);
		}
		//-- Убиравем массив с каналами пользователя
		unset($this->users_ch[$connectionId]);
		//-- Отправляем всем о выходе пользователя
		$this->sendPacketToOther($connectionId, 3, $connectionId);
    }

	//-- Вызывается при получении сообщения от клиента
	protected function onMessage($connectionId, $data, $mod)
	{
        if(!strlen($data) or $mod != 'text') return;
		//-- Антифлуд
        /*$time = time();
        if(isset($this->flud[$connectionId]) && $this->flud[$connectionId] == $time) return;
        else $this->flud[$connectionId] = $time;*/
		
		//-- Преобразование данных
		list($type, $data) = explode('|', $data, 2);
		//-- Элементы
		$type = intval($type);
		//-- ПРИЁМ: Команда (16)
		if($type === 16)
		{
			//-- МАСТЕР: Команды (16)
			$this->sendPacketToMaster('command', $data);
			return;
		}
		//-- Создание массива
		switch($type)
		{
			case 0: case 4: case 6: case 12: case 13: case 14: case 17: case 18: case 22: break;
			default:
				$array = explode('|', $data);
				$to = array_shift($array);
		}
		//-- Тип данных
		switch($type)
		{
			//-- Отправка текста, если люди есть в чате
			case 0: 
				list($channelId, $data) = explode('|', $data, 2);
				$send = array($connectionId, $data);
			break;
			//-- Смена ника
			case 4:
				//-- Сменить на новый
				$this->users[$connectionId][0] = $data;
				//-- Оповестить о смене всех
				$send = array($connectionId, $data);
			break;
			//-- Запрос на приём файлов
			case 5:
				//-- Первую переменную переводим в интеджер
				$array[0] = intval($array[0]);
				//-- Добавляет хозяина пакета
				array_unshift($array, $connectionId, '<span class="ui-icon ui-icon-transferthick-e-w" style="float:left;margin:0 7px 20px 0"></span> Пользователь <span class="user">'.$this->users[$connectionId][0].'</span> ожидает отправки <span>'.$array[0].'</span> файла(ов).');
			break;
			//-- Пинг от пользователя
			case 6:
				$this->sendPacketToClient($connectionId, $type);
			return;
			case 7:
				//-- Создаём название директории
				if($array[0] == '1')
				{
					$array[0] = mt_rand();
					//-- ОТПРАВКА: Сообщаем автору пакета, id передачи (8)
					$this->sendPacketToClient($connectionId, 8, $array[0]);
				}
				else $array[0] = 0;
				//-- Добавляет хозяина пакета
				array_unshift($array, $connectionId);
			break;
			case 9:
			case 10:
				//-- Первую переменную переводим в интеджер
				$array[0] = intval($array[0]);
				$array[1] = intval($array[1]);
			break;
			case 12:
				list($to, $uid, $message) = explode('|', $data, 3);
				//-- Если получатель есть, проверяем ключи и
				if(array_key_exists($to, $this->users) and $this->users[$to][2] == $uid)
					//-- Назначаем уникальный UID для зачинщика и добавляем хозяина пакета
					$array = array($connectionId, $this->users[$connectionId][2], $message);
				//-- Иначе, отменяем отправку
				else return;
			break;
			//-- Активность
			case 13:
				$this->users[$connectionId][3] = $data ? true : false;
				//-- Добавляет хозяина пакета
				$send = array($connectionId, intval($data));
			break;
			//-- Смена темы
			case 14:
				list($channelId, $topic) = explode('|', $data, 2);
				//-- Если канала нет, выходим
				if(!array_key_exists($channelId, $this->topics)) return;
				//-- Добавляем автора смены темы
				if($topic != '') $topic .= ' ('.$this->users[$connectionId][0].')';
				//-- Заносим изменения в базу
				$this->topics[$channelId] = $topic;
				//-- Составляем массив для отправки
				$send = array($connectionId, $topic);
			break;
			//-- ПЕРЕДАЧА: Список каналов (17)
			case 17:
				$this->sendPacketToClient($connectionId, $type, $this->titles);
			return;
			//-- Новый канал (18)
			case 18:
				//-- Обработка переменной
				$data = preg_replace('/[^\w\s-\.\:]*/iu', '', $data);
				$data = preg_replace('/\s+/', '_', $data);
				$data = preg_replace('/_+/', '_', $data);
				//-- Уменьшение переменной для исключения регистра
				$find = mb_strtolower($data);
				//-- Поиск дубликата и создание
				if(($key = array_search($find, array_map('mb_strtolower', $this->titles))) === false)
				{
					//-- Длина канала
					if(mb_strlen($data) > 32)
					{
						//-- ПЕРЕДАЧА: Ошибка (19)
						$this->sendPacketToClient($connectionId, 19, 'Недопустимо длинное название канала.');
						return;
					}
					//-- Заполнение массива с каналом
					$this->channels[][] = $connectionId;
					end($this->channels);
					$key = key($this->channels);
					$this->titles[$key] = $data;
					$this->topics[$key] = '';
				}
				//-- Проверка на дублирующий вход
				elseif(in_array($connectionId, $this->channels[$key])) return;
				//-- Добавление в глобальную переменную
				else $this->channels[$key][] = $connectionId;

				//-- Добавление пользователя к каналу
				$this->users_ch[$connectionId][] = $key;
				
				//-- ПЕРЕДАЧА: Текущему пользователю список пользователей (1)
				$this->sendPacketToClient($connectionId, 18, array($data, $this->topics[$key], $this->channels[$key], $key));
				//-- ПЕРЕЛАЧА: Остальным пользователям канала информацию о подключении пользователя (20)
				$this->sendToChannel($key, $connectionId, 20, $connectionId);
			return;
			//-- ПРИЁМ: Выход из канала (19)
			case 19:
				//-- Чистка входящих данных
				$channelId = intval($data);
				//-- Отмена при попытке закрыть #Main
				if($channelId === 0) return;
				//-- Выход
				$this->channelLeave($connectionId, $channelId);
			return;
			//-- ПРИЁМ: Пинг для всех пользователей
			case 22:
				//-- Преобразование в интеджер
				$data = intval($data);
				//-- Сохраняем пинг
				$this->users[$connectionId][5] = $data;
				//-- Добавляет хозяина пакета
				$send = array($connectionId, $data);
			break;
		}
		//-- Тип отправки данных
		if(sizeof($this->logins) > 1) switch($type)
		{
			case 0:
			case 14:
				$this->sendToChannel($channelId, $connectionId, $type, $send);
			break;
			case 4:
			case 13:
			case 22:
				$this->sendPacketToOther($connectionId, $type, $send);
			break;
			case 5:
			case 7:
			case 9:
			case 10:
			case 12:
				//var_dump($type, $array);
				$this->sendPacketToClient($to, $type, $array);
			break;
			case 15:
				$this->sendPacketToClient($to, $type, '<span class="ui-icon ui-icon-cancel" style="float:left;margin:0 7px 20px 0"></span> Пользователь <span class="user">'.$this->users[$connectionId][0].'</span> отменил отправку.');
			break;
			case 11:
				$this->sendPacketToClient($to, $type, $clientID);
			break;
		}
    }

	//-- Вызывается при получении сообщения от мастера
    protected function onMasterMessage($packet)
	{
        $packet = $this->unpack($packet);
		
		//-- Ответ на команду пользователя
		if($packet[0] == 'command')
		{
            //var_dump($packet[1]);
        }
		//-- Создание нового канала
        elseif($packet[0] == 'message')
		{
            $this->sendPacketToClients('message', $packet['data']);
		}
		//-- ПРИЁМ: Запись и вход в чат (1)
		/*if($packet[0] == 'login')
		{
            if ($packet['data']['result']) {
                $this->logins[ $packet['data']['login'] ] = $packet['data']['clientId'];
                $this->sendPacketToClients('login', $packet['data']['login']);
                if (isset($this->clients[ $packet['data']['clientId'] ])) {
                    $this->sendPacketToClient($this->clients[ $packet['data']['clientId'] ], 'message', 'Система: вы вошли в чат под именем ' . $packet['data']['login']);
                }
            } else {
                $this->sendPacketToClient($this->clients[ $packet['data']['clientId'] ], 'message', 'Система: выбранное вами имя занято, попробуйте другое.');
            }
        } elseif ($packet['cmd'] == 'logout') {
            unset($this->logins[$packet['data']['login']]);
            $this->sendPacketToClients('logout', $packet['data']['login']);
        }*/
    }
	//-- Отправляем сообщение на мастер, чтобы он разослал его на все воркеры
    protected function sendPacketToMaster($cmd, $data)
	{
        $this->sendToMaster($this->pack($cmd, $data));
    }
	
	//-- Отправить пользователям (кроме, тип, данные)
	private function sendPacketToOther($connectionId, $cmd, $data)
	{
        $data = $this->pack($cmd, $data);
        foreach($this->clients as $clientId => $client)
            if($clientId != $connectionId) $this->sendToClient($clientId, $data);
    }
	//-- Отправить пользователям (канал, кроме, тип, данные)
	private function sendToChannel($channelId, $connectionId, $cmd, $data)
	{
        $data = json_encode(array('type' => $cmd, 'cid' => $channelId, 'data' => $data));
        foreach($this->channels[$channelId] as $clientId)
			if($clientId != $connectionId) $this->sendToClient($clientId, $data);
    }

    private function sendPacketToClient($connectionId, $type, $data = '')
	{
        $this->sendToClient($connectionId, $this->pack($type, $data));
    }

    public function pack($type, $data)
	{
        return json_encode(array('type' => $type, 'data' => $data));
    }

    public function unpack($data)
	{
        return json_decode($data, true);
    }
	
	//-- Определение браузера
	public function getbrowser($useragent = '')
	{
		if(empty($useragent)) return '??';
		if(stripos($useragent, 'opera') !== false) return 'Opera';
		if(stripos($useragent, 'chrome') !== false) return 'Chrome';
		if(stripos($useragent, 'firefox') !== false) return 'Firefox';
		if(stripos($useragent, 'safari') !== false) return 'Safari';
		if(stripos($useragent, 'msie') !== false or stripos($useragent, 'rv:11.0') !== false) return 'Internet Explorer';
		return '??';
	}
	//-- Выход из канала и закрытие в случии необходимости
	private function channelLeave($connectionId, $channelId)
	{
		//-- Просматриваем каналы
		if(($key = array_search($connectionId, $this->channels[$channelId])) === false) return;
		unset($this->channels[$channelId][$key]);
		//-- Если пользователи кончились, закрываем канал
		if(count($this->channels[$channelId]) == 0) unset($this->channels[$channelId], $this->titles[$channelId], $this->topics[$channelId]);
		//-- ПЕРЕЛАЧА: Остальным пользователям канала о выходе (21)
		else $this->sendToChannel($channelId, $connectionId, 21, $connectionId);
	}
}