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

// Ввсякие обязательные штуки для ajax DLE
@error_reporting ( E_ALL ^ E_WARNING ^ E_NOTICE );
@ini_set ( 'display_errors', true );
@ini_set ( 'html_errors', false );
@ini_set ( 'error_reporting', E_ALL ^ E_WARNING ^ E_NOTICE );
$count = (int)$_REQUEST['count'];
$news_id = (int)$_REQUEST['news_id'];
$comment_id = (int)$_REQUEST['comment_id'];

if( $news_id < 1 && $comment_id < 1) die( "Hacking attempt!" );

define( 'DATALIFEENGINE', true );
define( 'ROOT_DIR', substr( dirname(  __FILE__ ), 0, -12 ) );
define( 'ENGINE_DIR', ROOT_DIR . '/engine' );

include ENGINE_DIR . '/data/config.php';
include ENGINE_DIR . '/modules/easylike/easylike_config.php';

if( $config['http_home_url'] == "" ) {
	$config['http_home_url'] = explode( "engine/ajax/easylike.php", $_SERVER['PHP_SELF'] );
	$config['http_home_url'] = reset( $config['http_home_url'] );
	$config['http_home_url'] = "http://" . $_SERVER['HTTP_HOST'] . $config['http_home_url'];
}

require_once ENGINE_DIR . '/classes/mysql.php';
require_once ENGINE_DIR . '/data/dbconfig.php';
require_once ENGINE_DIR . '/modules/functions.php';
if ($config['version_id'] > 9.6) {
	dle_session();
} else {
	@session_start();
}


// Финт со скином, чтобы не сабмитить его в форме.
$_REQUEST['skin'] = totranslit($config['skin'], false, false);

if( $_REQUEST['skin'] == "" OR !@is_dir( ROOT_DIR . '/templates/' . $_REQUEST['skin'] ) ) {
	die( "Hacking attempt!" );
}

$user_group = get_vars( "usergroup" );
if( ! $user_group ) {
	$user_group = array ();
	$db->query( "SELECT * FROM " . USERPREFIX . "_usergroups ORDER BY id ASC" );
	while ( $row = $db->get_row() ) {
		$user_group[$row['id']] = array ();
		foreach ( $row as $key => $value ) $user_group[$row['id']][$key] = stripslashes($value);
	}
	set_vars( "usergroup", $user_group );
	$db->free();
}
require_once ENGINE_DIR . '/modules/sitelogin.php';

// Проверяем возможность голосования
if(!$is_logged) $member_id['user_group'] = 5;
if(!$user_group[$member_id['user_group']]['allow_rating']) die(":-(");
// Получаем IP посетителя
$ip = $db->safesql($_SERVER['REMOTE_ADDR']);

// Определяем переменные в запрос.
if ($is_logged) {
	// Если юзер авторизован
	$name = $db->safesql($member_id['name']);
	$where = "user_name = '{$member_id['name']}'";
} else {
	// Если неавторизован
	$name = "";
	$where = "ip ='{$ip}'";
}

// Проверяем лайки у новости или комментария
if ($news_id > 0) {
	$likes = $db->super_query( "SELECT news_id, likes FROM " . PREFIX . "_easylike_count WHERE news_id = $news_id ");
	$like_id = $likes['news_id'];
	$col = 'news_id';
	$id = $news_id;
	$is_comment = false;
}
if ($comment_id > 0) {
	$likes = $db->super_query( "SELECT comment_id, likes FROM " . PREFIX . "_easylike_count WHERE comment_id = $comment_id ");
	$like_id = $likes['comment_id'];
	$col = 'comment_id';
	$id = $comment_id;
	$is_comment = true;
}

if (!$likes['likes']) {
	$likes['likes'] = 0;
}

$notSendNotify = false;

if (in_array($member_id['name'], $easylikeConfig['not_send_email']['users']) || in_array($member_id['user_group'], $easylikeConfig['not_send_email']['groups_id'])) {
	$notSendNotify = true;
}

if (count(explode('.', $ip)) == 4 ) {
	// Если получен IP посетителя - работаем.
	if (!$like_id) {
		// Если записи о лайках нет - добавим.
		$db->query("INSERT INTO " . PREFIX . "_easylike_count ($col, likes) VALUES ($id, '1')");
		$easyLike = setLog($col, $likes['likes'], $id, $name, $ip);
		if(!$notSendNotify) {
			sendNotify($id, $name, $is_comment);
		}
	} else {
		// Если запись есть, то проверяем, не лайкал ли этот посетитель.
		$select = "SELECT {$col} FROM " . PREFIX . "_easylike_log WHERE {$col} = $id AND {$where}";
		$row = $db->super_query($select);

		if (!$row[$col]) {
			// Если не лайкал - работаем.
			$db->query("UPDATE " . PREFIX . "_easylike_count SET likes=likes+1 WHERE {$col} ='".$id."'");
			$easyLike = setLog($col, $likes['likes'], $id, $name, $ip);
			if(!$notSendNotify) {
				sendNotify($id, $name, $is_comment);
			}
		} else {
			// Если лайкал - шлём ему привет :).
			$easyLike = ':-)';
		}
	}
} else {
	// Если IP не получили (бывает такое с ipv6 :)) - показываем грустный смайлик.
	$easyLike = 'bad ip';
}

$db->close();

@header( "Content-type: text/html; charset=" . $config['charset'] );
echo $easyLike;



/**
 * Записываем даные в лог
 * @param string  $col
 * @param integer $count
 * @param integer $id
 * @param string  $name
 * @param string  $ip
 */
function setLog($col = 'news_id', $count = 0, $id = 1, $name = '', $ip = '') {
	global $config, $db;

	$db->query( "INSERT INTO " . PREFIX . "_easylike_log ($col, user_name, ip) values ('$id', '{$name}', '$ip')" );
	if ($config['version_id'] > 9.4) {
		if (($config['allow_alt_url'] && $config['allow_alt_url'] != 'no') && !$config['seo_type']) {
			$cprefix = 'full_';
		} else {
			if ($col == 'comment_id') {
				$row = $db->super_query("SELECT post_id FROM ".PREFIX."_comments WHERE id='{$id}'");
				$id = $row['news_id'];
			}
			$cprefix = 'full_'.$id;
		}
		clear_cache(array('news_', 'rss', $cprefix));
	} else {
		clear_cache();
	}


	return $count + 1;
}


/**
 * Отправляем уведомление на почту
 * @param  integer $id         ID новости или комментария
 * @param  boolean $is_comment Если комментарий - нужно поставить true
 * @return  отправка почты
 */
function sendNotify($id = 0, $member_name = 'Гость', $is_comment = false) {
	global $config, $db;

	$id = (int)$id;
	$member_name = ($member_name == '') ? 'Гость' : $member_name ;

	if ($is_comment) {
		$getName = $db->super_query("SELECT post_id, autor FROM ".PREFIX."_comments WHERE id='{$id}'");
	} else {
		$getName = $db->super_query("SELECT autor FROM ".PREFIX."_post WHERE id='{$id}'");
	}
	$userName = $db->safesql($getName['autor']);


	$ml = $db->super_query("SELECT email, name, allow_mail FROM ".USERPREFIX."_users WHERE name='{$userName}'");

	if($ml['allow_mail']) {
		include_once ENGINE_DIR . '/classes/mail.class.php';
		$mail = new dle_mail($config, true);

		if($config['allow_alt_url'] && $config['allow_alt_url'] != 'no') {
			$user_link = $config['http_home_url'] . "user/" . urlencode($member_name) . "/";
		} else {
			$user_link = $config['http_home_url'] . "?subaction=userinfo&amp;user=" . urlencode($member_name);
		}
		$userLinkText = ($member_name == 'Гость') ? "<b>{$member_name}</b>" : "<a href=\"{$user_link}\" target=\"_blank\">{$member_name}</a>";

		// Специально продублировал код для комментария и новости, что бы была возможно сть задавать соё оформление для этих писем.
		if ($is_comment) {
			$mail_subj = $member_name." лайкнул Ваш комментарий!";
			$mail_text = <<<HTML
			<p>Привет, <b>{$ml['name']}</b>!</p>
			<p>Пользователь {$userLinkText} лайкнул Ваш <a href="{$config['http_home_url']}?newsid={$getName['post_id']}#comment-id-{$id}" target="_blank">комментарий</a>.</p>
			<p>---------------------------------------------</p>
			<p><small>&ndash; С наилучшими пожеланиями, администрация <a href="{$config['http_home_url']}" target="_blank">{$config['home_title']}</a></small></p>
HTML;
		} else {
			$mail_subj = $member_name." лайкнул Вашу новость!";
			$mail_text = <<<HTML
			<p>Привет, <b>{$ml['name']}</b>!</p>
			<p>Пользователь {$userLinkText} лайкнул Вашу <a href="{$config['http_home_url']}?newsid={$id}" target="_blank">новость</a>.</p>
			<p>---------------------------------------------</p>
			<p><small>&ndash; С наилучшими пожеланиями, администрация <a href="{$config['http_home_url']}" target="_blank">{$config['home_title']}</a></small></p>
HTML;
		}

		$mail->send($ml['email'], $mail_subj, $mail_text );
	}
}

?>