/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: template/js/interface.js
-----------------------------------------------------
 Назначение: JavaScript Document
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

var interface = {
	wUserBox: (("wUserBox" in localStorage) ? localStorage['wUserBox'] : 180),
	hMessageBox: (("hMessageBox" in localStorage) ? localStorage['hMessageBox'] : 28),
	wTalkDialog: (("wTalkDialog" in localStorage) ? localStorage['wTalkDialog'] : 350),
	hTalkDialog: (("hTalkDialog" in localStorage) ? localStorage['hTalkDialog'] : Math.round($(window).height() / 2)),
	hTalkBox: (("hTalkBox" in localStorage) ? localStorage['hTalkBox'] : 50)
};
var dialogChannel = false;

//-- Заголовок новой вкладки
function tabsHeader(title, cid)
{
	return '<li><a href="#'+title+'"><span id="t'+cid+'"></span>#'+title+'</a></li>';
}

$(document).ready(function()
{
	//-- Верхнее меню
	$("nav.second button#status").button({icons: {primary: "ui-unio-status", secondary: "ui-icon-triangle-1-s"}})
	$("nav.second button#cut").button({icons: {primary: "ui-unio-cut"}, text: false})
	.next().button({icons: {primary: "ui-unio-copy"}, text: false})
	.next().button({icons: {primary: "ui-unio-paste"}, text: false});
	//-- Кнопка: Управление каналами
	$("nav.second button#channels").button({icons: {primary: "ui-unio-channel"}, text: false})
	.click(channelControl)
    .next().button({icons: {primary: "ui-icon-triangle-1-s"}, text: false})
	.parent().buttonset();
	$("#tabs").tabs(
	{
		heightStyle: "fill",
		activate: title_tabs
	});
	$("#userbox").resizable(
	{
		minWidth: 100,
		maxWidth: 300,
		handles: "w",
		resize: function(event, ui)
		{
			$('#leftside').width($('#checkheight').width() - ui.size.width - interface.pAside);
		},
		stop: function(event, ui)
		{
			localStorage['wUserBox'] = ui.size.width;
		}
	});
	$("#messagebox > div").resizable(
	{
		minHeight: 28,
		maxHeight: 100,
		handles: "n",
		resize: function(event, ui)
		{
			$(this).css('top', '');
			$('#messagebox').height(ui.size.height);
			$('#chatbox').height($('#checkheight').height() - ui.size.height - 15);
			$('#tabs').tabs("refresh");
		},
		stop: function(event, ui)
		{
			localStorage['hMessageBox'] = ui.size.height;
		}
	});
	$("#nickbox button:first").button()
	.next().button(
	{
		text: false,
		icons: {primary: "ui-icon-triangle-1-s"}
	})
	.parent().buttonset();	
	
	//-- Подключение
    connect();
	
	$("#dialog-files, #dialog-ufiles").dialog(
	{
		width: "30%",
		autoOpen: false,
		resizable: false,
		position: {at: "right top"},
		show: {effect: "highlight", duration: 2000}
    });
	
	$("#messagebox, #messagebox > div").height(interface.hMessageBox);
	$("aside, #userbox").width(interface.wUserBox);
	interface['pContent'] = parseInt($('#checkheight').css("padding-left"));
	interface['pAside'] = parseInt($('aside').css("right")) + parseInt($('#userbox').css("padding-left"));
	setInterface();
	
	//var audioEl	= $('<audio></audio>');
	//$('body').prepend(audioEl);
	//audio = audioEl.get(0);
	audio = new Audio();
	audio.volume = 0.1;
	audio.autoplay = true;
	
	$('#usersbox').on(
	{
		click: function(e)
		{
			menuTarget = e.target.parentElement.id.substr(5);
		},
		contextmenu: function(e)
		{
			menuTarget = e.target.parentElement.id.substr(5);
			if(menuTarget == user) return true;
			e.preventDefault();
			
			var x = e.pageX;
			var w = $(window).width() - x;
			var y = e.pageY;
			
			$('#usermenu').show();		
			var len = $('#usermenu > ul').outerWidth() - w;
			if(len > 0) x -= len;
			$('#usermenu').css({'left': x, 'top': y})
			$(document).unbind("click").one("click", function()
			{
				$('#usermenu').hide();
			});
		},
		dblclick: function(e)
		{
			menuTarget = e.target.parentElement.id.substr(5);
			if(menuTarget == user) return true;
			
			talk();
		}
	}, 'dd > div');
	$('#filechoose').change(FileSelectHandler);
	
	print("Добро пожаловать в Unio Chat, "+nick+"!", "welcome");
	
	//-- Диалог смены темы
	$('#dialog-topic').dialog(
	{
		width: "40%",
		autoOpen: false,
		resizable: false,
		close: function(){$('#dialog-topic button:first').unbind();}
    });
	//-- Диалог каналов
	$('#dialog-channels').dialog(
	{
		width: 'auto',
		autoOpen: false,
		resizable: false,
		buttons:
		[{
			text: "Закрыть",
			click: function() {$(this).dialog("close");}
		}]
    });
});
