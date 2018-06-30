/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: template/js/engine.js
-----------------------------------------------------
 Назначение: JavaScript Document
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

var ajaxDir = baseUrl+'system/ajax/', title = document.title,
	socket = false, recconect = false, transferid = false, transfer = false, transfer_image = false, active = true, topic_stop = false, channelRefresh = false, muted = ('sMuted' in localStorage) ? true : false,
	user = 0, file_to = 0, menuTarget = 0, channelTarget = 0, icon_blink = 0, ping = 0,
	nick = $.cookie('uc_nick'),
	files = [], xhr = [], users = [], topics = {}, uids = {}, fileText = ['Принять', 'Отмена', 'Отправить', 'Закрыть'], talkText = [' - Разговор', 'Восстанавливаем соединение...'], topicText = ['сменил тему', 'очистил тему'];
var audio;

//-- ИНТЕРВАЛ: ПИНГА КАЖДЫЕ 5 МИНУТ = 300000 миллисекунд (6)
setInterval(pingRequest, 300000);
//-- ПОДКЛЮЧЕНИЕ
function connect()
{
	if(socket === false)
	{
		try
		{
			socket = new WebSocket("ws://"+serverUrl+":8168");
			status(socket.readyState);
			
			socket.onopen = function(msg)
			{
				status(socket.readyState);
				//-- ОТПРАВКА: Автовход (18)
				channelAutoIn();
				//-- ОТПРАВКА: Активность окна (13)
				socket.send('13|'+(active ? 1 : 0));
				//-- ОТПРАВКА: ПИНГА через 5 секунду = 5000 (6)
				setTimeout(pingRequest, 5000);
			};
			socket.onmessage = function(msg)
			{
				var t = typeof(msg.data);
				if(t == "string")
				{
					var o = $.parseJSON(msg.data);
					switch(o.type)
					{
						//-- Сообщение
						case 0:
							text = text_do(o.cid, o.data[1], o.data[0]);
							if(!text) return;
							message(o.cid, o.data[0], text);
							break;
						//-- Список пользователей и тем
						case 1:	userslist(o.data); break;
						//-- Пользователь вошёл
						case 2:	userslist_add(o.data); break;
						//-- Пользователь вышел
						case 3: userslist_del(o.data); break;
						//-- Пользователь сменил ник
						case 4:	user_nick(o.data[0], o.data[1]); break;
						//-- Запрос на приём файлов
						case 5:	files_incoming(o.data);	break;
						//-- Приём ответа на Ping
						case 6: pongResponse(); break;
						//-- Вопрос о приёме файлов
						case 15:
							$('#dialog-files').unbind('dialogclose').dialog({buttons:[{text: fileText[3],	click: function(){$(this).dialog("close");}}]}).find('> p').html(o.data);
							transfer = false;
						break;
						//-- Согласие приёма файлов
						case 7:	files_agree(o.data[0], o.data[1]); break;
						//-- ID передачи
						case 8:	transferid = o.data; break;
						//-- Прогресс передачи файла
						case 9:	file_progress(o.data[0], o.data[1]); break;
						//-- Завершение передачи файла
						case 10: file_finish(o.data); break;
						//-- Звуковой сигнал
						case 11: beep_in(o.data); break;
						//-- Разговор
						case 12: talk_recive(o.data); break;
						//-- Активность
						case 13: activity(o.data[0], o.data[1]); break;
						//-- Смена темы:
						case 14: topic_recive(o.cid, o.data[0], o.data[1]); break;
						//-- Список каналов
						case 17: channelsRefresh(o.data); break;
						//-- Ответ о входе в канал
						case 18: channelEnter(o.data); break;
						//-- Ошибка
						case 19: error_text(o.data); break;
						//-- Пользователь вошёл в канал
						case 20: user_enter(o.cid, o.data); break;
						//-- Пользователь вышел из канала
						case 21: user_del(o.cid, o.data); break;
						//-- Пинг от пользователя
						case 22: pingSet(o.data[0], o.data[1]); break;
					}
				}
				else if(t == 'object')
				{
					var file = msg.data;
					console.log(file);
				}
			};
			socket.onclose = function(msg)
			{
				status(socket.readyState);
				if(this.readyState === 3) socket = false;
				$('#usersbox dd').remove();
				$('#uMain dt span > span').text('0');
				if(msg.code === 3001)
				{
					print("Уже открыта одна копия чата. Пожалуйста, проверьте свои вкладки.", "error");
					return;
				}
				reconnect();
			};
		}
		catch(ex) {reconnect();}
	}
}
//-- СИСТЕМА: Переподключение
function reconnect()
{
	print("Не удаётся подключиться к сетевому сервису. Возможно, из-за неполадок с интернет соединением.", "error");
	
	$.post(
		ajaxDir + 'a_chat_start.php',
		{'action': 'start'},
		function(result)
		{
			//console.log(result)
		},
		'json'
	)
	.fail(function(data) {console.log(data.responseText);});
	
	setTimeout(connect, 20000);
}
//-- ОТПРАВКА: Сообщения в чат (0)
function message_send(e)
{
	//-- Последнее введённое сообщение
	if(e.target.value == '' && e.keyCode == 38 && 'lastSend' in localStorage)
	{
		e.target.value = localStorage['lastSend'];
		return false;
	}
	//-- Отправка по нажатию Enter
	if(e.shiftKey || e.keyCode != 13) return true;

	var txt = e.target;
	var msg = $.trim($(txt).val());

	if(msg == '') return false;
	
	//-- Получение ID активной вкладки
	var channelId = channelGetId();
	
	localStorage['lastSend'] = txt.value;
	$(txt).val('').focus();
	//-- ОТПРАВКА: Команда (16)
	if(msg.charAt(0) == '/')
	{
		socket.send('16|'+msg.substring(1));
		return false;
	}
	socket.send('0|'+channelId+'|'+msg);
	msg = text_do(channelId, msg);
	if(msg) print(escapeHtml('<'+nick+'> ')+msg, 'self', channelId);

	return false;
}
//-- ПРИЁМ: Сообщения в чат (0)
function message(cid, uid, text)
{
	text = escapeHtml('<'+users[uid][0]+'> ')+text;
	print(text, 'message', cid);
	soundPlay('chat_line');
	icon_toogle();
	tabBlink(cid);
}
//-- ФУНКЦИОНАЛ: Мигать на вкладке канала
function tabBlink(cid)
{
	//-- Получение ID активной вкладки
	var channelId = channelGetId();
	//-- Добавляем мигающий значёк на кладку
	if(cid != channelId) $('span#t'+cid).addClass('blink');
}
//-- ФУНКЦИОНАЛ: Убрать мигание
function tabBlinkStop(cid)
{
	if($('span#t'+cid).hasClass('blink')) $('span#t'+cid).removeClass('blink');
}
//-- ФУНКЦИОНАЛ: Подготовка текста
function text_do(cid, text, uid)
{
	uid = uid || false;
	//-- Проверка на ссылку
	var regexp = /^https?:\/\/[^\ ]+$/i;
	text = $.trim(text);
	if(!regexp.test(text)) return escapeHtml(text).replace(/(https?:\/\/[^\ ]+)/ig, '<a href="$1"  target="_blank">$1</a>');
	//-- Проверка на Ютуб
	var youtube = text.match(/youtu.be\/(.+)|youtube.com\/watch\?[^\ ]*v=([^&]+|$)/i);
	if(youtube !== null)
	{
		var output = (youtube[1] === undefined) ? youtube[2] : youtube[1];
		return '<br /><iframe id="ytplayer" type="text/html" width="640" height="360" src="https://www.youtube.com/embed/'+output+'" frameborder="0" allowfullscreen>';
	}
	//-- Проверка на Coub
	var coub = text.match(/coub.com\/view\/([^\/]+|$)/i);
	if(coub !== null)
	{
		return '<br /><iframe id="coubVideo" src="http://coub.com/embed/'+coub[1]+'?muted=false&autostart=false&originalSize=false&hideTopBar=true&startWithHD=true" allowfullscreen="true" frameborder="0" width="640" height="360"></iframe>';
	}
	//-- Проверка на Vimeo
	regexp = /vimeo.com/i;
	if(regexp.test(text))
	{
		//-- Проверка на три вида ссылок
		var vimeo = text.match(/vimeo.com\/groups\/(?:[^\/]+)\/videos\/([^\/]+|$)/i);
		if(vimeo === null)
		{
			vimeo = text.match(/vimeo.com\/channels\/(?:[^\/]+)\/([^\/]+|$)/i);
			if(vimeo === null) vimeo = text.match(/vimeo.com\/([^\/]+|$)/i);
		}
		if(vimeo !== null)
		{	
			return '<br /><iframe src="https://player.vimeo.com/video/'+vimeo[1]+'" width="640" height="360" frameborder="0" allowfullscreen></iframe>';
		}
	}
	//-- Проверка на изображение и музыку
	if(!uid)
	{
		print(escapeHtml('<'+nick+'> ')+' <a href="'+text+'" target="_blank" class="images">'+text+'</a>', 'self', cid);
		var href = text;
	}
	
	$("<img/>").attr("src", text)
	.load(function()
	{
		var w = Math.round($(window).width() * 0.5);
		var width = this.width;
		var height = this.height;
		if(width > w)
		{
			var k = width/height;
			width = w;
			height = Math.floor(width/k);
		}
		text = '<br /><a href="'+text+'" target="_blank"><img src="'+text+'" width="'+width+'" height="'+height+'" /></a>';
		uid ? message(cid, uid, text) : text_replace(cid, href, text);
		//-- Чистка памяти
		$(this).remove();
	})
	.error(function()
	{
		var tempAudio = new Audio();
		$(tempAudio).bind(
		{
			'loadedmetadata': function(e)
			{
				text = '<audio src="'+text+'" controls></audio>';
				uid ? message(cid, uid, text) : text_replace(cid, href, text);
			},
			'error': function()
			{
				text = '<a href="'+text+'" target="_blank">'+text+'</a>';
				uid ? message(cid, uid, text) : text_replace(cid, href);
			}
		});
		tempAudio.src = text;
		//-- Чистка памяти
		$(this).remove();
	});
	return false;
}
//-- ФУНКЦИОНАЛ: Замена ссылки на картинку
function text_replace(cid, href, text)
{
	text = text || false;
	
	$('a.images[href="'+href+'"]').each(function(index, a)
	{
        if(text)
		{
			$(a).replaceWith(text);
			$('.chat[data-channel-id="'+cid+'"]').scrollTop($('.chat[data-channel-id="'+cid+'"]').scrollTop() + 9999);
		}
		else $(a).removeAttr('class');
    });
}
//-- ПРИЁМ: Список пользователей и тема #Main (1)
function userslist(users)
{
	var list = '', count = 0;
	user = users[0];
	//-- Список пользователей
	$.each(users[1], function(id, val)
	{
		list += user_format(id, val);
		count++;
    });
	//-- #Main
	var topic = users[2];
	$('#u0').append(list).find('dt > span').text(count);
	//-- Тема
	topic_change(topic, 0);
}

//-- ПРИЁМ: Пользователь вошёл (2)
function userslist_add(user)
{
	$('#u0').append(user_format(user[0], user[1]));
	var count = $('#u0 > dd').length;
	$('#u0 dt > span').text(count);
	
	print("Пользователь "+escapeHtml(user[1][0])+" зашёл в чат", "network");
	soundPlay('join_network');
}
//-- ПРИЁМ: Пользователь вошёл в канал (20)
function user_enter(cid, user)
{
	$('#u'+cid).append(user_format(user));
	var count = $('#u'+cid+' > dd').length;
	$('#u'+cid+' dt > span').text(count);
	
	print("Пользователь "+escapeHtml(users[user][0])+" присоединился к каналу", "network", cid);
	soundPlay('join_network');
	tabBlink(cid);
}
//-- ПРИЁМ: Пользователь вышел (3)
function userslist_del(user)
{
	$('#u0 #user-'+user).remove();
	var count = $('#u0 > dd').length;
	$('#u0 dt > span').text(count);
	
	print("Пользователь "+escapeHtml(users[user][0])+" вышел из Unio Chat", "network");
	soundPlay('leave_network');
	if(uids[users[user][2]] == user) delete uids[users[user][2]];
	delete users[user];
	if(menuTarget == user) menuTarget = 0;
}

//-- ОТПРАВКА: Смена ника (4)
function nick_change(value)
{
	value = $.trim(value);
	if(nick != value && value != '')
	{
		socket.send('4|'+value);
		$.cookie('uc_nick', value, {expires: 365, path: '/'});
		user_nick(user, value);
		nick = value;
		$('#nick').blur();
	}
}
//-- ПРИЁМ: Смена ника (4)
function user_nick(uid, nick)
{
	var old_nick = users[uid][0];
	users[uid][0] = nick;
	$('#usersbox > dl > dd#user-'+uid+' div:last-child').text(nick);
	print(escapeHtml(old_nick+' сменил псевдоним на '+nick), 'nickchange');
}
//-- ОТПРАВКА: ПИНГА (6)
function pingRequest()
{
	if(socket)
	{
		socket.send('6|');
		ping = new Date().getTime();
	}
}
//-- ПРИЁМ: Ответ на пинг (6)
//-- ОТПРАВКА: Пинга другим пользователям (22)
function pongResponse()
{
	users[user][5] = new Date().getTime() - ping;
	$('dd#user-'+user).replaceWith(user_format(user));
	socket.send('22|'+users[user][5]);
}
//-- ПРИЁМ: Пинг от пользователя (22)
function pingSet(user, ping)
{
	users[user][5] = ping;
	$('dd#user-'+user).replaceWith(user_format(user));
}
//-- ФУНКЦИОНАЛ: Отправка в окно чата
function print(text, style, cid)
{
	style = style || 'self';
	var channel = (typeof cid === 'undefined') ? $('#Main') : $('div.chat[data-channel-id="'+cid+'"]');
	var time = new Date();
	var minutes = time.getMinutes();
	var timestamp = '['+time.getHours()+':'+((minutes < 10) ? '0'+minutes : minutes)+'] ';
	
	$(channel).append('<p class="'+style+'">'+timestamp+text+'</p>').scrollTop($(channel).scrollTop() + 9999);
}
//-- ФУНКЦИОНАЛ: Шаблон пользователя
function user_format(id, array)
{
	if(typeof array !== 'undefined')
	{
		users[id] = array;
		uids[array[2]] = parseInt(id);
	}
	var opticity = users[id][3] ? '' : ' style="opacity:0.5"';
	return '<dd id="user-'+id+'"><div'+opticity+'></div><div title="= Информация о пользователе = \nПсевдоним: '+users[id][0]+' \nIP: '+users[id][1]+' \nUID: '+users[id][2]+' \nБраузер: '+users[id][4]+' \nPing: '+users[id][5]+' мс">'+users[id][0]+'</div></dd>';
}
//-- СИСТЕМА: Выход (НЕ НАЗНАЧЕНО)
function quit()
{
	conlose.log("Goodbye!");
	socket.close();
	socket = false;
}
//-- ОТПРАВКА: Запрос на передачу файлов (5), Отмена передачи (15)
function FileSelectHandler(e)
{
	var to = menuTarget;
	if(to == user || to === false) return;

	transfer = true;
	files = e.target.files || e.originalEvent.dataTransfer.files;
	var meta = "", fileslist = "";
	
	// process all File objects
	$.each(files, function(key, file)
	{
		//-- Мета данные
		if(meta != "") meta += '|';
		meta += file.name+'|'+file.size;
		//-- Составление списка файлов
		fileslist += files_list(key, file.name, file.size);
	});
	//-- Настройка и открытие диалога
	$('#dialog-ufiles .fileslist').html(fileslist).next().html('Ожидание ответа от получателя...');
	$("#dialog-ufiles").dialog({buttons:[{text: fileText[1], click: function(){$(this).dialog("close");}}]}).dialog("open")
	.bind("dialogclose", function()
	{
		socket.send('15|'+to);
		files_cancel();
	});
	//-- Отправка запроса на передачу файлов
	socket.send('5|'+to+'|'+files.length+'|'+meta);
}
//-- ФУНКЦИОНАЛ: Отмена передачи
function files_cancel()
{
	if(xhr.length > 0)
	{
		$.each(xhr, function()
		{
			this.abort();
		});
	}
	transfer = false;
	files = [];
	$("#dialog-ufiles").unbind("dialogclose");
}
//-- ФУНКЦИОНАЛ: Создание списка файлов
function files_list(id, name, size)
{
	return '<div class="file-'+id+'"><div>'+name+'</div><progress max="100" value="0"></progress></div>';
}
//-- ПРИЁМ: Запрос на передачу файлов (5)
//-- ОТПРАВКА: Ответ о передачи (7)
function files_incoming(data)
{
	transfer = true;
	//-- От кого и сколько
	$("#dialog-files > p").html(data[1]);
	//-- Счётчик
	var q = 3,	
		fileslist = "";

	for(var i=0;i<data[2];i++)
	{
		files[i] = true;
		fileslist += files_list(i, data[q], data[q+1]);
		q+=2;
	}

	$("#dialog-files .fileslist").html(fileslist);
	$("#dialog-files").dialog(
	{
		buttons:
		[
			{text: fileText[0],	click: function()
			{
				$(this).unbind("dialogclose").dialog({buttons:[{text: fileText[1], click: function(){$(this).dialog("close");}}]}).one("dialogclose", function()
				{
					socket.send('7|'+data[0]+'|0');
					transfer = false;
				});
				socket.send('7|'+data[0]+'|1');
				//-- Информация о приёме
				$("#dialog-files > p").html('<span class="ui-icon ui-icon-cancel" style="float:left;margin:0 7px 20px 0"></span> Приём файлов от пользователя '+users[data[0]][0]+'.');
			}},
			{text: fileText[1], click: function(){$(this).dialog("close");}}
		]
	})
	.dialog("open")
	.one("dialogclose", function()
	{
		socket.send('7|'+data[0]+'|0');
	});
	soundPlay('file-transfer');
}
//-- ПРИЁМ: Ответ о передачи (7)
//-- ОТПРАВКА: Отмена передачи (15), Прогресса передачи файла (9), Завершение передачи файла (10)
function files_agree(from, approve)
{
	//-- Отправка файлов
	if(approve)
	{
		$("#dialog-ufiles").unbind("dialogclose").bind("dialogclose", function()
		{
			socket.send('15|'+from);
			files_cancel();
		})
		//-- Информация о начале отправки
		.find('.statuc').html('Передача файлов...');
		$.each(files, function(key, file)
		{
			var fd = new FormData();
			xhr[key] = new XMLHttpRequest();
			xhr[key].open('POST', 'upload.php', true);
			  
			xhr[key].upload.onprogress = function(e)
			{
				if(e.lengthComputable)
				{
					var percentComplete = Math.round((e.loaded / e.total) * 100);
					$('#dialog-ufiles .file-'+key+'>progress').val(percentComplete);
					socket.send('9|'+from+'|'+key+'|'+percentComplete);
				}
			};
			xhr[key].onload = function()
			{
				if(this.status == 200)
				{
					if($('#dialog-ufiles .file-'+key+'>progress').val() != 100) $('#dialog-ufiles .file-'+key+'>progress').val(100);
					var o = $.parseJSON(this.response);
					socket.send('10|'+from+'|'+key+'|1|'+o[0]+'|'+o[1]);
				}
				else socket.send('10|'+from+'|'+key+'|0');
				
				xhr.splice(key, 1);
				delete files[key];
				
				$('#dialog-ufiles .file-'+key).slideUp('slow', function()
				{
					$(this).remove();
					if($('#dialog-ufiles > .fileslist > div').length == 0)
					{
						files_cancel();
						$('#dialog-ufiles').dialog("close");
						$.post(ajaxDir+'a_transferclean.php', {action: "cleanANDclear"}).error(function(data) {alert(data.responseText);});
					}
				});				
			};
			
			fd.append("transferid", approve);
			fd.append("file", file);
			xhr[key].send(fd);
		});
		//socket.send(BinaryPack.pack([1, from, files[0]]));
	}
	else
	{
		$("#dialog-ufiles").dialog({buttons:[{text: fileText[3], click: function(){$(this).dialog("close");}}]}).find('.status').html('Получатель отклонил запрос.');
		files_cancel();
	}
}
//-- ПРИЁМ: Проценты загрузки файлов (9)
function file_progress(key, value)
{
	$('#dialog-files .file-'+key+'>progress').val(value);
}
//-- ПРИЁМ: Ссылки на скачивание файлов (10)
function file_finish(data)
{
	files.splice(0, 1);
	
	if(data[1])
	{
		var element = $('#dialog-files .file-'+data[0]);
		var filename = $(element).children().first().text();
		var blank = (navigator.userAgent.indexOf("Firefox") === -1) ? '' : ' target="_blank"';
		
		if($('#dialog-files .file-'+data[0]+'>progress').val() != 100) file_progress(data[0], 100);
		$(element).append('<a href="download.php?transferid='+transferid+'&transferroot='+data[2]+'&transferfile='+encodeURIComponent(filename)+'&transfertype='+data[3]+'"'+blank+'>Скачать</a>');
	}
	if(files.length === 0)
	{
		$("#dialog-files").dialog({buttons:[{text: fileText[3],	click: function(){$(this).dialog("close");}}]}).find('> p').html('<span class="ui-icon ui-icon-check" style="float:left;margin:0 7px 20px 0"></span> Приём файлов завершён.');
		soundPlay('file-transfer-done');
	}
}
//-- ОТПРАВКА: Звуковой сигнал (11)
function beep()
{
	if(menuTarget <= 0) return false;
	
	print("Звуковой сигнал для "+escapeHtml(users[menuTarget][0])+" отправлен", "nickchange");
	socket.send("11|"+menuTarget);
}
//-- ПРИЁМ: Звуковой сигнал (11)
function beep_in(from)
{
	print("Получен звуковой сигнал от "+escapeHtml(users[menuTarget][0]), "nickchange");
	soundPlay('beep');	
}
//-- ФУНКЦИОНАЛ: Создание или открытие диалога
function talk(uid)
{
	if(menuTarget <= 0) return false;
	
	var sound = uid ? true : false;
	uid = uid || users[menuTarget][2];
	var element = $('#talk-'+uid);
	
	if($(element).length > 0)
	{
		if(!$(element).dialog("isOpen"))
		{
			$(element).dialog({show: "highlight"}).dialog("open");
			if(sound) soundPlay('message');
		}
		else if(!active) soundPlay('message');
		return element;
	}
	var target = menuTarget;
	
	$('#dialog-talk').clone().appendTo("body").attr('id', 'talk-'+uid)
	.dialog(
	{
		title: users[menuTarget][0]+talkText[0],
		width: interface.wTalkDialog,
		height: interface.hTalkDialog,
		resize: function()
		{
			talk_resize($(this));
		},
		resizeStop: function(event, ui)
		{
			localStorage['wTalkDialog'] = ui.size.width;
			localStorage['hTalkDialog'] = ui.size.height;
		}
	})
	var element = $('#talk-'+uid);
	
	$(element).show()
	.find('.mainbox').height(interface.hTalkBox).resizable(
	{
		minHeight: 20,
		maxHeight: 100,
		handles: "n",
		resize: function(event, ui)
		{
			$(this).css('top', '');
			talk_resize($(this).parent());
		},
		stop: function(event, ui)
		{
			localStorage['hTalkBox'] = ui.size.height;
		}
	});
	talk_resize(element);
	talk_events(element, target, uid);
	$('button:last', element).click(function(){$(element).dialog("close");});
	if(sound) soundPlay('message');
	icon_toogle();

	return element;
}
//-- ФУНКЦИОНАЛ: События на нажатие
function talk_events(element, target, uid)
{
	$('textarea', element).unbind().keydown(function(e)
	{
		if(!e.ctrlKey || e.keyCode != 13) return true;
		
		talk_send(target, uid, $(this).val());
	});
	$('button:first', element).unbind().click(function()
	{
		talk_send(target, uid, $(this).parent().parent().find('textarea').val());
	});
}
//-- ФУНКЦИОНАЛ: Размеры в окне разговора
function talk_resize(element)
{
	$('.talkbox', element).height($(element).height() - $('.mainbox', element).outerHeight(true) - $('footer', element).outerHeight(true));
}
//-- ОТПРАВКА: Сообщения в разговор (12)
function talk_send(to, uid, text)
{
	text = $.trim(text);
	if(text == '')
	{
		$('textarea', element).val('').focus();
		return false;
	}
	
	var element = $('#talk-'+uid);

	if(users[to] === undefined || users[to][2] != uid)
	{
		$('.talkbox', element).append('<p class="recover">'+talkText[1]+'</p>');

		if(uids[uid] === undefined) return false;
		
		to = uids[uid];
		talk_events(element, to, uid);
	}

	talk_print(element, text, 0);
	$('textarea', element).val("").focus();
	socket.send('12|'+to+'|'+uid+'|'+text);
}
//-- ПРИЁМ: Сообщения в разговор (12)
function talk_recive(data)
{
	menuTarget = data[0];
	var element = talk(data[1]);
	talk_print(element, data[2], data[0]);
	icon_toogle();
}
//-- ПРИЁМ: Активность
function activity(user, active)
{
	var opacity = active ? 1 : 0.5;
	$('#usersbox #user-'+user+' div:first-child').fadeTo(0, opacity);
}
//-- ФУНКЦИОНАЛ: Стили и формат сообщения
function talk_print(element, text, from)
{
	var time = new Date();
	var minutes = time.getMinutes();
	var timestamp = '['+time.getHours()+':'+((minutes < 10) ? '0'+minutes : minutes)+'] ';
	if(from)
	{
		style = "from";
		var author = users[from][0];
	}
	else
	{
		style = "me";
		var author = nick;
	}
	text = escapeHtml(text).replace(/(https?:\/\/[^\ ]+)/ig, '<a href="$1"  target="_blank">$1</a>');
	
	$('.talkbox', element).append('<p class="'+style+'">'+timestamp+' '+author+':</p><p>'+text+'</p>').scrollTop($('.talkbox', element).scrollTop() + 9999);
}
//-- ФУНКЦИОНАЛ: Открытие диалога сообщения
function status(type)
{
	var status = '';
	switch(type)
	{
		case 0: status = 'Подключение...'; break;
		case 1: status = 'Готов.'; break;
		case 3: status = 'Отключён.'; break;
	}
	$('.footer span').text(status);
}
//-- ОТПРАВКА: Смена темы (14)
function topic_submit()
{
	if(!topic_stop)
	{
		$('#dialog-topic').dialog('close');
		
		var cid = channelGetId();
		var topic = $.trim($('#dialog-topic input').val());
		
		socket.send('14|'+cid+'|'+topic);
		if(topic != '') topic += ' ('+nick+')';
		topic_recive(cid, user, topic);
	}
}
//-- ПРИЁМ: Смена темы (14)
function topic_recive(cid, user, topic)
{
	if(!topic_stop)
	{
		var button = $('#dialog-topic button:first');
		$(button).prop('disabled', topic_stop);
		topic_stop = true;
		setTimeout(function()
		{
			topic_stop = false;
			$(button).prop('disabled', topic_stop);
		}, 60000);
	}
	
	var text = users[user][0]+' ';
	text += (topic == '') ? topicText[1] : topicText[0]+': "'+topic+'"';
	print(text, 'topic', cid);
	soundPlay('topic_change');
	icon_toogle();
	tabBlink(cid);
	//-- Запоминаем новую тему канала
	topics[cid] = topic;
	//-- Смена заголовка активного окна
	if(channelGetId() == cid)
	{
		var channelTitle = $('div.chat[data-channel-id="'+cid+'"]').attr('id');
		if(topic != '') channelTitle += ': ';
		document.title = '[#'+channelTitle+topic+'] - '+title;
	}
}
//-- ФУНКЦИОНАЛ: Действите с вкладками
function title_tabs(event, ui)
{
	//-- Убираем мигающие значки
	var cid = channelGetId(ui.newTab.index());
	tabBlinkStop(cid);
	//-- Заголовок окна
	var channelTitle = $('div.chat[data-channel-id="'+cid+'"]').attr('id');
	if(topics[cid] != '') channelTitle += ': ';
	document.title = '[#'+channelTitle+topics[cid]+'] - '+title;
	//topic_change(ui.panel.selector.substr(1));
}
//-- ФУНКЦИОНАЛ: Смена заголовка
function topic_change(topic, cid)
{
	topics[cid] = topic;
	var channelTitle = $('div.chat[data-channel-id="'+cid+'"]').attr('id');
	if(topic != '')
	{
		channelTitle += ': ';
		print('Текущая тема канала: "'+escapeHtml(topic)+'"', 'topic', cid);
		soundPlay('topic_change');
	}
	if(channelGetId() == cid) document.title = '[#'+channelTitle+topic+'] - '+title;
}
//-- ФУНКЦИОНАЛ: Открытие диалога смены темы
function topic_dialog()
{
	var tab = $('#tabs').tabs('option', 'active');
	var channel = $('#tabs div.chat:eq('+tab+')').attr('id');
	var cid = channelGetId(tab);
	var topic = (cid in topics) ? topics[cid] : '';
	
	$('#dialog-topic').dialog(
	{
		title: '#'+channel
	}).dialog('open')
	.find('button:first').prop('disabled', topic_stop);
	$('#dialog-topic input').val(topic).select();
}
//-- ФУНКЦИОНАЛ: Включение/Отключение звука
function mute_toogle()
{
	if(muted)
	{
		muted = false;
		localStorage.removeItem('sMuted');
	}
	else
	{
		muted = true;
		localStorage['sMuted'] = true;
	}
}
function icon_set(url)
{
	$('link[rel="shortcut icon"]').replaceWith('<link rel="shortcut icon" type="image/x-icon" href="'+url+'" />');
}
function icon_toogle()
{
	if(active || icon_blink) return;

	var info = 'template/icons/chat.ico';
	var toogle = true;
	
	icon_blink = setInterval(function()
	{
		if(toogle)
		{
			icon_set(info);
			toogle = false;
		}
		else
		{
			icon_set('favicon.ico');
			toogle = true;
		}
	}, 1000);
}
function icon_normal()
{
	if(!icon_blink) return;
	
	icon_set('favicon.ico');
	clearInterval(icon_blink);
	icon_blink = 0;
}
function soundPlay(name)
{
	if(!muted) audio.src = baseUrl+'sounds/'+name+'.wav';
}
function setInterface()
{
	var height = $('#checkheight').height();
	$('#leftside').width($('#checkheight').width() - $('#userbox').width() - interface.pAside);
	$('#chatbox').height(height - $('#messagebox').innerHeight() - interface.pContent);
	$('#userbox').height(height);
	$('#usersbox').height(height - $('#userbox > form').innerHeight() - 2)
	$('#tabs').tabs("refresh");
}
function escapeHtml(unsafe)
{
	return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;").replace(/\n{5,}|\n/g, "<br />");
}
function chatline_nick()
{
	if(menuTarget <= 0 || !(menuTarget in users)) return false;
	
	var message = users[menuTarget][0]+': '+$('#message').val();
	$('#message').val(message).focus();
}
function chatbox_clean()
{
	var tab = $('#tabs').tabs('option', 'active');
	$('#tabs div.chat:eq('+tab+')').empty();
}
//-- ФУНКЦИОНАЛ: Информация в окне канала
function channel_info(text)
{
	$('#channel-info').stop().slideDown().find('span:last').html(text).parent().parent().delay(5000).slideUp();
	transfer_image = false;
	channelTarget = 0;
}
//-- ОТПРАВКА: Изображения в канал (1)
function channel_image(e)
{
	if(transfer_image) return false;
	
	transfer_image = true;
	var cid = channelTarget;
	files = e.target.files || e.originalEvent.dataTransfer.files;
	
	if(files.length > 1) return channel_info('Отправляйте по одному файлу.');
	if(files[0].type.indexOf('image') === -1 && files[0].type.indexOf('audio') === -1) return channel_info('Разрешены только изображения и музыка.');
	//-- Показ прогресс бара
	$('#channel-progress').slideDown().find('progress').val(0);
	//-- Отправка файлов
	var fd = new FormData();
	var xhr = new XMLHttpRequest();
	xhr.open('POST', 'upload_image.php', true);
			  
	xhr.upload.onprogress = function(e)
	{
		if(e.lengthComputable)
		{
			var percentComplete = Math.round((e.loaded / e.total) * 100);
			$('#channel-progress > progress').val(percentComplete);
		}
	};
	xhr.onload = function()
	{	
		$('#channel-progress').slideUp();
		if(this.status == 200)
		{
			//-- Если при этом всё равно появилась ошибка
			if(this.response.indexOf('<b>Warning</b>:') > -1) return channel_info(this.response);
			var result = $.parseJSON(this.response);
			if(result[0])
			{
				msg = result[1];
				msg += encodeURI(files[0].name);
				
				socket.send('0|'+cid+'|'+msg);
				msg = text_do(cid, msg);
				if(msg) print(escapeHtml('<'+nick+'> ')+msg, 'self', cid);
				
				transfer_image = false;
				channelTarget = 0;
			}
			else return channel_info(result[1]);
		}
		else return channel_info('Ошибка при отправки файла.');
	};
	
	fd.append('file', files[0]);
	xhr.send(fd);
}

$(document).bind(
{
	keydown: function(e)
	{
		//-- Открыть каналы
		if(e.keyCode == 113) $('#dialog-chennals').dialog('open');
		if(!e.ctrlKey && !e.shiftKey) return;

		if(e.ctrlKey)
			switch(e.keyCode)
			{
				case 89: chatline_nick(); return false;
			}
		if(e.shiftKey)
		{
			switch(e.keyCode)
			{
				case 27: chatbox_clean(); return false;
			}
		}
	},
	dragover: function(e)
	{
		e.stopPropagation();
		e.preventDefault();
		
		//-- Окно чата
		if(e.target.className.indexOf('chat') >= 0)
		{
			$('#'+e.target.id).addClass('files');
		}
		else if(e.target.parentNode.className.indexOf('chat') >= 0)
		{
			$('#'+e.target.parentNode.id).addClass('files');
		}
		//-- Окно пользователей
		else if(e.target.localName == 'dd' && e.target.offsetParent.id == 'userbox')
		{
			if(transfer) return;
			if(e.target.id.substr(5) != user) e.target.className = "files";
		}
		else if(e.target.parentNode.localName == 'dd' && e.target.parentNode.offsetParent.id == 'userbox')
		{
			if(transfer) return;
			if(e.target.parentNode.id.substr(5) != user) e.target.parentNode.className = "files";
		}
	},
	dragleave: function(e)
	{
		e.stopPropagation();
		e.preventDefault();
		//-- Окно канала
		if(e.target.className.indexOf('chat') >= 0)
			$('#'+e.target.id).removeClass('files');		
		else if(e.target.parentNode.className.indexOf('chat') >= 0)
			$('#'+e.target.parentNode.id).removeClass('files');
		//-- Окно пользователей
		else if(e.target.localName == 'dd' && e.target.offsetParent.id == 'userbox')
			e.target.className = "";
		else if(e.target.parentNode.localName == 'dd' && e.target.parentNode.offsetParent.id == 'userbox')
			e.target.parentNode.className = "";
	},
	drop: function(e)
	{
		e.stopPropagation();
		e.preventDefault();
		
		$('div.files').each(function(){$(this).removeClass('files');});
		$('dd.files').each(function(){$(this).removeAttr('class');});
		
		var type = 0;
		
		//-- Окно канала
		//console.log(e)
		if(e.target.className.indexOf('chat') >= 0)
		{
			type = 1;
			channelTarget = e.target.dataset.channelId;
		}
		else if(e.target.parentNode.className.indexOf('chat') >= 0)
		{
			type = 1;
			channelTarget = e.target.parentNode.dataset.channelId;
		}
		//-- Окно пользователей
		else if(e.target.localName == 'dd' && e.target.offsetParent.id == 'userbox')
		{
			if(transfer) return;
			type = 2;
			menuTarget = e.target.id.substr(5);
		}
		else if(e.target.parentNode.localName == 'dd' && e.target.parentNode.offsetParent.id == 'userbox')
		{
			if(transfer) return;
			type = 2;
			menuTarget = e.target.parentNode.id.substr(5);
		}
		//-- Тип отправки
		if(type == 1) channel_image(e);
		else if(type == 2) FileSelectHandler(e);
	}
});
$(window).bind(
{
	resize: function(e)
	{
		if(!e.target.tagName) setInterface();
	},
	//-- ОТПРАВКА: Активность окна (13)
	focus: function()
	{
		if(socket) socket.send('13|1');
		activity(user, 1);
		active = true;
		icon_normal();
	},
	blur: function()
	{
		if(socket) socket.send('13|0');
		activity(user, 0);
		active = false;
	},
	beforeunload: function(){return 'Вы хотите выйти из Unio Chat?';}
});

function idCorrect(id)
{
	return id.replace( /(:|\.)/g, "\\$1" );
}
/* Вставка картинки из буфера обмена
document.onpaste = function(event){
  var items = (event.clipboardData || event.originalEvent.clipboardData).items;
  console.log(JSON.stringify(items)); // will give you the mime types
  var blob = items[0].getAsFile();
  var reader = new FileReader();
  reader.onload = function(event){
    console.log(event.target.result)}; // data url!
  reader.readAsDataURL(blob);
}

setInterval(function()
{
	console.log(users)
}, 5000);*/