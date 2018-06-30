<?php
/*
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- 
 UnioChat Engine
-----------------------------------------------------
 Copyright (c) 2012,2018 Create New Unlimited
-----------------------------------------------------
 Author: Denis Solokhin (http://densolow.com)
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
 Файл: template/main.php
-----------------------------------------------------
 Назначение: Шаблон главной страницы
-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
*/

isset($UNIOCHAT) or exit;

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Unio Chat 2.0 Final</title>

<link rel="stylesheet" type="text/css" href="<?= $cfg['site_url']?>css/smoothness/jquery-ui.min.css" media="screen" />
<link rel="stylesheet" type="text/css" href="<?= TPL_DIR ?>css/style.css?2" media="all">
<link rel="shortcut icon" href="favicon.ico" />

<script type="text/javascript" src="<?= $cfg['site_url']?>js/jquery.min.js"></script>
<script type="text/javascript" src="<?= $cfg['site_url']?>js/jquery-ui.min.js"></script>
<script type="text/javascript" src="<?= $cfg['site_url']?>js/jquery.cookie.js"></script>
<script type="text/javascript">
var baseUrl = "<?= $cfg['path'] ?>";
var serverUrl = "<?= $cfg['host'] ?>";
</script>
<script type="text/javascript" src="<?= TPL_DIR ?>js/engine.js"></script>
<script type="text/javascript" src="<?= TPL_DIR ?>js/channel.js"></script>
<script type="text/javascript" src="<?= TPL_DIR ?>js/interface.js"></script>
<script type="text/javascript" src="<?= TPL_DIR ?>js/filetransfer.js"></script>
<script type="text/javascript" src="<?= TPL_DIR ?>js/FileSaver.js"></script>
</head>
<body>
<table id="UnioChat" class="box" cellspacing="0" cellpadding="0">
<tr height="64">
	<td valign="top">
    <nav class="first">
    	<ul id="menubox" class="menubox">
            <li>Разговор
            <ul>
                <li onClick="topic_dialog()">Смена темы...</li>
            </ul>
            </li>
            <li>Правка
            <ul>
                <li onClick="chatbox_clean()">Очистить чат</li>
            </ul>
            </li>
            <li>Настройка
            <ul>
                <li onClick="mute_toogle()">Выключить/Включить звук</li>
            </ul>
            </li>
        </ul>
    </nav>
    <nav class="second">
		<div><button id="status" disabled>Активен</button></div>
    	<div><button id="cut" title="Вырезать (Ctrl+X)"></button><button id="copy" title="Копировать (Ctrl+C)"></button><button id="paste" title="Вставить (Ctrl+V)"></button></div>
        <div><div><button id="channels" title="Каналы (F2)"></button><button disabled></button></div></div>
    </nav>
    </td>
</tr>
<tr>
	<td id="checkheight" valign="top" style="padding:5px;background-color:#f0f0f0;">
    <div id="content">
    
    <div id="leftside">
        <div id="chatbox">
            <div id="tabs">
                <ul class="channel">
                    <li><a href="#Main"><span id="t0"></span>#Main</a></li>
                </ul>
                <div id="Main" class="chat" data-channel-id="0">
                </div>
            </div>
            
            <div id="channel-info" class="ui-state-highlight ui-corner-all"><p><span class="ui-icon ui-icon-info" style="float:left;margin 0 7px 0 0;"></span><span></span></p></div>
            <div id="channel-progress" class="ui-state-highlight ui-corner-all"><progress max="100" value="0"></progress></div>
        </div>
        <div id="messagebox">
            <div><textarea id="message" onKeyDown="return message_send(event)" maxlength="960"></textarea></div>
        </div>
    </div>
    <aside>
        <div id="userbox">
        <form onSubmit="nick_change(nick.value);return false" action="">
       	<div><label for="nick">Псевдоним:</label></div>
        <div id="nickbox"><div><div><button type="submit"><img src="<?= TPL_DIR ?>images/change-nick.png" /></button><button disabled>Изменить псевдоним</button></div></div><input id="nick" name="nick" type="text" value="<?= $nick ?>" maxlength="35" /></div>
        <div style="margin-top:9px;overflow:hidden;white-space:nowrap">Пользователи в сети:</div>
        </form>
        <div id="usersbox">
        <dl id="u0">
            <dt>#Main (<span>0</span>)</dt>
        </dl>
        </div>
        </div>
	</aside>

    </div>
    </td>
</tr>
<tr class="footer">
    <td>
    <span>Готов.</span>
    <div>&copy; <a href="http://densolow.com" target="_blank" title="Create New Unlimited (Author: Den Solow)">C.N.U.</a>, <?= date('Y') ?></div>
    </td>
</tr>
</table>
<div id="dialog-ufiles" title="Исходящие файлы" class="dialog">
<div class="fileslist"></div>
<div class="status"></div>
</div>
<div id="dialog-files" title="Входящие файлы" class="dialog">
<p></p>
<div class="fileslist"></div>
</div>
<div id="dialog-talk" class="dialog-talk dialog">
	<div class="talkbox"></div>
    <div class="mainbox"><textarea maxlength="960"></textarea></div>
    <footer><div class="statusbox">Готов.</div><button>Отправить</button><button>Закрыть</button></footer>
</div>
<div id="usermenu" class="menubox dialog">
<ul>
	<li onClick="talk()"><strong>Сообщение...</strong></li>
    <li onClick="chatline_nick()">Обратиться в чате<div>Ctrl+Y</div></li>
    <li onClick="beep()">Сигнал</li>
    <li onClick="$('#filechoose').click()">Отправить файл(ы)...</li>
</ul>
</div>
<div id="dialog-topic" class="dialog">
<form onSubmit="topic_submit();return false" action="">
<p><strong>Тема канала:</strong></p>
<div class="form"><input type="text" maxlength="500" /></div>
<div class="buttons"><button type="submit">ОК</button><button type="button" onClick="$('#dialog-topic').dialog('close')">Отмена</button></div>
</form>
</div>
<div id="dialog-channels" title="Управление каналами" class="dialog">
<table style="width:100%;margin-top:10px">
<tr class="header">
    <td>Список Ваших каналов:</td>
    <td>&nbsp;</td>
    <td>Созданные каналы:</td>
</tr>
<tr>
	<td class="window"><div class="first" title="Выделить канал для удаления и входа."></div></td>
    <td class="buttons"><div><button class="btnCopy" disabled>&lt;- Копировать</button></div>
    <div style="margin-top:27px"><button class="btnEnter" disabled>Войти -&gt;</button></div>
    <div style="margin-top:7px"><button class="btnLeave" disabled>Покинуть [X]</button></div></td>
    <td class="window"><div class="second" title="Выделить канал для копирования и выхода."></div></td>
</tr>
<tr>
	<td><button class="btnAdd">Добавить ...</button> <button class="btnRemove" disabled>Удалить</button></td>
    <td>&nbsp;</td>
    <td><button onclick="channelsRefreshQuery()"><span class="ui-icon ui-icon-refresh" style="float:left;margin-right:.3em;"></span>Обновить список</button></td>
</tr>
</table>
<div style="border:1px solid #d5dfe5;border-radius:3px;padding:4px;margin-top:5px;"><label><input type="checkbox" disabled> Разрешить пользователям просматривать список Ваших каналов.</label></div>
<div class="ui-widget"><div class="ui-state-highlight ui-corner-all" style="margin-top: 20px; padding: 0 .7em;"><p><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span> Отмеченные каналы будут автоматически созданы при старте.</p></div></div>
</div>

<div style="width:0;height:0;overflow:hidden"><input id="filechoose" type="file" multiple /></div>
</body>
</html>