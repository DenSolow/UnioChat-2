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

//-- Локальное хранилище каналов
if('channels' in localStorage) var channels = $.parseJSON(localStorage['channels']);
else
{
	var channels = [{'title': 'Main', 'check': true}];
	channelsSave();
}

//-- ФУНКЦИОНАЛ: Получение ID активной вкладки
function channelGetId(tab)
{
	tab = tab || $('#tabs').tabs('option', 'active');
	return parseInt($('div.chat:eq('+tab+')').attr('data-channel-id'));
}
//-- ФУНКЦИОНАЛ: Управление каналами
function channelControl()
{
	var localChannels = $('#dialog-channels').dialog('open')
	.find('.window:eq(0) > div');
	//-- Активирум функционал
	if(!dialogChannel)
	{
		//-- Кнопка "Копировать"
		$('#dialog-channels .btnCopy').click(function()
		{
			channelCopy();
		})
		//-- Кнопка "Войти"
		$('#dialog-channels .btnEnter').click(function()
		{
			channelIn();
		})
		//-- Кнопка "Покинуть"
		$('#dialog-channels .btnLeave').click(function()
		{
			//-- Перебираем выбранные
			$('#dialog-channels .second .selected').each(function()
			{
				//-- Выходим из канала
				channelLeave($(idCorrect($(this).text())).attr('data-channel-id'));
            });
		})
		//-- Кнопка "Добавить"
		$('#dialog-channels .btnAdd').click(function()
		{
			channelUnselect();
			$(this).prop('disabled', true);
			channelEdit($(localChannels).append('<div><input type="checkbox"><input type="text" readonly="readonly" size="2" value="#"></div>').find('input:last'));
			return false;
		});
		//-- Кнопка "Удалить"
		$('#dialog-channels .btnRemove').click(function()
		{
			//-- Выключаем кнопку "Удалить", "Войти"
			$('#dialog-channels .btnRemove, #dialog-channels .btnEnter').prop('disabled', true);
			//-- Перебираем выбранные
			$('#dialog-channels .first .selected').each(function()
			{
				//-- Удаляем из базы
				channelRemove($(this).val().substr(1));
				//-- Удаляем визуально
				$(this).parent().remove();
            });
		});
		//-- Выделение локальных каналов
		$('#dialog-channels .first').on({
			'click': function()
			{
				//-- Если Main
				if($(this).val().toLowerCase() == '#main') return false;
				//-- Если есть выделения
				if($('#dialog-channels .first .selected').length > 0) $('#dialog-channels .first .selected').removeClass('selected');
				var element = this;
				$(this).addClass('selected');
				$('#dialog-channels .first').off('click.selectOff').one('click.selectOff', channelUnselect);
				//-- Активируем кнопку "Удалить", "Войти"
				$('#dialog-channels .btnRemove, #dialog-channels .btnEnter').prop('disabled', false);
				return false;
			},
			'dblclick': function()
			{
				//-- Если Main
				if($(this).val().toLowerCase() == '#main') return false;
				channelIn();
			}
		}, 'div > input:last-child');
		//-- Выбор локальных каналов для автовхода
		$('#dialog-channels .first').on('change', 'div > input:first-child', function()
		{
			$(this).prop('checked', channelAutostart($(this).next().val(), $(this).is(':checked')));
		});
		//-- Выделение серверных каналов
		$('#dialog-channels .second').on({
			'click': function()
			{
				//-- Если Main
				if($(this).text().toLowerCase() == '#main') return false;
				//-- Если есть выделения
				if($('#dialog-channels .second .selected').length > 0) $('#dialog-channels .second .selected').removeClass('selected');
				var element = this;
				$(this).addClass('selected');
				$('#dialog-channels .second').off('click.secondOff').one('click.secondOff', channelServerUnselect);
				//-- Активируем кнопки "Копировать", "Покинуть"
				$('#dialog-channels .btnCopy, #dialog-channels .btnLeave').prop('disabled', false);
				return false;
			},
			'dblclick': function()
			{
				$('#tabs').tabs({active:channelIndex($(this).text().substr(1))});
				channelCopy();
			}
		}, 'div');
		//-- Запрет на повторное назначение
		dialogChannel = true;
	}
	//-- Чистка списков
	$(localChannels).empty();
	//-- Выключаем кнопку "Удалить", "Войти"
	$('#dialog-channels .btnRemove, #dialog-channels .btnEnter').prop('disabled', true);
	//-- Генерация локального списка
	var check, size;
	$.each(channels, function(id, channel)
	{
		check = channel.check ? ' checked' : '';
		size = channel.title.length + 1;
		$(localChannels).append('<div><input type="checkbox"'+check+'><input type="text" readonly="readonly" size="'+size+'" value="#'+channel.title+'"></div>');
	});
	//-- Генерация серверного списка
	channelsRefreshQuery();
}
//-- ФУНКЦИОНАЛ: Копирование канала
function channelCopy()
{
	//-- Перебираем выбранные
	$('#dialog-channels .second .selected').each(function()
	{
		//-- Добавляем канал
		var title = $(this).text().substr(1);
		if(!channelsSearch(title))
		{
			channelCreate(title);
			size = title.length + 1;
			$('#dialog-channels .window:eq(0) > div').append('<div><input type="checkbox"><input type="text" readonly="readonly" size="'+size+'" value="#'+title+'"></div>');
		}
	});
}
//-- ФУНКЦИОНАЛ: Найти индекс канала
function channelIndex(channel)
{
	channel = idCorrect(channel);
	return $('#tabs .chat').index($('#'+channel));
}
//-- ФУНКЦИОНАЛ: Создание локального канала
function channelCreate(title)
{
	channels[channels.length] = {'title': title, 'check': false};
	channelsSave();
}
//-- ФУНКЦИОНАЛ: Удаление локального канала
function channelRemove(title)
{
	var title = title.toLowerCase();
	if(title == 'main') return;
	$.each(channels, function(key, channel)
	{
		if(channel.title.toLowerCase() == title)
		{
			channels.splice(key, 1);
			return false;
		}
	});
	channelsSave();
}
//-- ФУНКЦИОНАЛ: Сохранение каналов в локальном хранилище
function channelsSave()
{
	localStorage['channels'] = JSON.stringify(channels);
}
//-- ФУНКЦИОНАЛ: Установка/Снятие галочки для автовхода
function channelAutostart(title, checked)
{
	var title = title.toLowerCase().substr(1);
	if(title == 'main') return true;
	$.each(channels, function(key, channel)
	{
		if(channel.title.toLowerCase() == title)
		{
			channels[key].check = checked;
			return false;
		}
	});
	channelsSave();
	return checked;
}
//-- ФУНКЦИОНАЛ: Сброс выбора локальных каналов
function channelUnselect()
{
	var selected = $('#dialog-channels .first .selected');
	if($(selected).length > 0)
	{
		$(selected).removeClass('selected');
		$('#dialog-channels .btnRemove, #dialog-channels .btnEnter').prop('disabled', true);
	}
}
//-- ФУНКЦИОНАЛ: Сброс выбора серверных каналов
function channelServerUnselect()
{
	$('#dialog-channels .second .selected').removeClass('selected');
	$('#dialog-channels .btnCopy, #dialog-channels .btnLeave').prop('disabled', true);	
}
//-- ФУНКЦИОНАЛ: Редактирование канала
function channelEdit(element)
{
	//-- Старое название без знака "#"
	var oldTitle = $(element).val().substr(1);
	
	$(element).addClass('edit').prop('readonly', false).focus().select()
	.on('mouseenter', function()
	{
		$(document).off('click');
		$(this).one('mouseleave', function(){$(document).one('click', function(){channelEditDone(oldTitle);});});
	});
	$(document).one('click', function(){channelEditDone(oldTitle);});
	
	//-- При нажатии Enter
	$(element).bind({
		keydown: function(e)
		{
			if(e.keyCode != 13) return;
			channelEditDone(oldTitle);
			$(document).off('click');
		},
		keyup: function()
		{
			//-- Размер input
			$(element).attr('size', $(element).val().length + 1);
		}
	});
	return false;
}
//-- ФУНКЦИОНАЛ: Завершение редактирования
function channelEditDone(oldTitle)
{
	//-- Удаление выключено
	var remove = false;
	//-- Заголовок
	var title = $.trim($('#dialog-channels input.edit').val()).replace(/[^\s\w-\.\:]*/ig, '');
	title = title.replace(/\s+/g, '_').replace(/_+/g, '_');
	//-- Есть ли первая решётка
	if(title.charAt(0) != '#') title = '#' + title;
	//-- Обрезаем строку и решётку
	title = title.substr(1, 31);
	//-- Новый канал
	if(oldTitle == '')
	{
		if(title == '' || channelsSearch(title)) remove = true;
		//-- Добавление
		else
		{
			$('#dialog-channels input.edit').val('#' + title);
			channelCreate(title);
		}
	}
	//-- Редактирование
	else
	{
		if(title !== oldTitle)
		{
			channelRemove(oldTitle);
			//-- Если канал уже есть
			if(channelsSearch(title)) remove = true;
			else channelCreate(title);
		}
	}
	$('#dialog-channels input.edit').unbind();
	if(remove) $('#dialog-channels input.edit').parent().remove();
	else $('#dialog-channels input.edit').prop('readonly', true).removeClass('edit');
	//-- Возращаем функционал кнопки "Добавить"
	$('#dialog-channels .btnAdd').prop('disabled', false);
}
//-- ФУНКЦИОНАЛ: Поиск в массиве
function channelsSearch(title)
{
	var title = title.toLowerCase();
	var found = false;
	$.each(channels, function()
	{
		if(this.title.toLowerCase() == title) 
		{
			found = true;
			return false;
		}
	});
	return found;
}

//-- ОТПРАВКА: Запрос всех каналов с сервера (17)
function channelsRefreshQuery()
{
	if(channelRefresh) return;
	channelRefresh = true;
	var serverChannels = $('#dialog-channels .window:eq(1) > div');
	$(serverChannels).empty();
	socket.send('17|');
	channelServerUnselect();
}
//-- ОТПРАВКА: Вход в канал (18)
function channelIn()
{
	$('#dialog-channels .first .selected').each(function()
	{
		//-- Проверка на повторный вход
		var value = $(this).val().substr(1);
		if($('#'+idCorrect(value)).length > 0) return;
		socket.send("18|"+value);
	});
}
//-- ОТПРАВКА: Автовход в каналы (18)
function channelAutoIn()
{
	if(recconect)
	{
		$('div.chat').each(function()
		{
            socket.send('18|'+$(this).attr('id'));
        });
	}
	else if('channels' in localStorage)
	{
		recconect = true;
		var channels = $.parseJSON(localStorage['channels']);
		$.each(channels, function()
		{
			//-- Если отмечен для автовхода
			if(this.check) socket.send('18|'+this.title);
		});
	}
}
//-- ОТПРАВКА: Выход из канала (19)
function channelLeave(cid)
{
	//-- Отмена при попытке закрыть #Main
	if(parseInt(cid) === 0) return;
	//-- Отправка на сервер
	socket.send("19|"+cid);
	//-- Закрытие вкладки
	var tabId = $('div.chat[data-channel-id="'+cid+'"]').remove().attr("aria-labelledby");
    $("ul.channel > li[aria-labelledby='"+tabId+"']").remove();
    $('#tabs').tabs('refresh');
	//-- Закрытие списка пользователей
	$('#u'+cid).remove();
	//-- Обновление списка каналов
	if($('#dialog-channels').dialog('isOpen')) channelsRefreshQuery();
	//-- Удаляем темы канала
	delete topics[cid];
}

//-- ПРИЁМ: Вывод списка каналов сервера (17)
function channelsRefresh(channelsList)
{
	var serverChannels = $('#dialog-channels .window:eq(1) > div');
	
	$.each(channelsList, function()
	{
		$(serverChannels).append('<div>#'+this+'</div>');
	});
	
	channelRefresh = false;
}
//-- ПРИЁМ: Вход в канал (18)
function channelEnter(data)
{
	var list = '', count = 0;
	var channel = data[0],
		topic = data[1],
		users = data[2],
		cid = data[3];
	//-- Создаём новую вкладку, если не создана
	if($('#'+idCorrect(channel)).length === 0)
	{
		$('#tabs .channel').append(tabsHeader(channel, cid));
		$('#tabs').append('<div id="'+channel+'" class="chat" data-channel-id="'+cid+'"></div>').tabs('refresh');
		//-- Если диалог каналов открыт, иначе без активации вкладок
		if($('#dialog-channels').dialog('isOpen')) $('#tabs').tabs({active:channelIndex(channel)});
		//-- Создаём боковое меню с пользователями
		$('#usersbox').append('<dl id="u'+cid+'"><dt>#'+channel+' (<span>0</span>)</dt></dl>');
		//-- Успешное создание
		print('Сейчас разговариваем в #'+channel, 'welcome', cid);
	}
	//-- Заменяем ID канала
	else
	{
		//-- Находим предыдущее ID
		channel = idCorrect(channel);
		var oldId = $('#'+channel).attr('data-channel-id');
		//-- Удаляем тему старого ID
		delete topics[oldId];
		//-- Обновляем новые ID
		$('#'+channel).attr('data-channel-id', cid);
		$('#u'+oldId).attr('id', 'u'+cid);
	}
	//-- Список пользователей
	$.each(users, function()
	{
		list += user_format(this);
		count++;
    });
	$('#u'+cid).append(list).find('dt > span').text(count);

	//-- Тема
	topic_change(topic, cid);
	//-- Если открыт диалог каналов
	if($('#dialog-channels').dialog('isOpen')) channelsRefreshQuery();
}
//-- ПРИЁМ: Пользователь вышел из канала (21)
function user_del(cid, user)
{
	$('#u'+cid+' #user-'+user).remove();
	var count = $('#u'+cid+' > dd').length;
	$('#u'+cid+' dt > span').text(count);

	print("Пользователь "+escapeHtml(users[user][0])+" вышел из канала", "network", cid);
	soundPlay('leave_network');
	tabBlink(cid);
}