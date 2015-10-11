<?php
/*
=============================================================================
Easy Like - модуль организации системы лайков для DLE
=============================================================================
Автор:   ПафНутиЙ
URL:     http://pafnuty.name/
twitter: https://twitter.com/pafnuty_name
google+: http://gplus.to/pafnuty
email:   pafnuty10@gmail.com
=============================================================================
 */
// Настройки модуля лайков

$easylikeConfig = array(
	'not_send_email' => array(
		'groups_id' => array(
			// Перечисляем через запятую ID групп, для которых будет отключено email уведомление о лайке

			1,
			25,

			// Перечисляем через запятую ID групп, для которых будет отключено email уведомление о лайке
		),
		'users'     => array(
			// Перечисляем через запятую логины юзеров, для которых будет отключено email уведомление о лайке

			'ПафНутиЙ',
			'bot',
			'guest',

			// Перечисляем через запятую логины юзеров, для которых будет отключено email уведомление о лайке
		),
	),
);
?>