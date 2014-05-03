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
$news_id = (int)$_REQUEST['id'];
if( $news_id < 1 ) die( "Hacking attempt!" );

define( 'DATALIFEENGINE', true );
define( 'ROOT_DIR', substr( dirname(  __FILE__ ), 0, -12 ) );
define( 'ENGINE_DIR', ROOT_DIR . '/engine' );

include ENGINE_DIR . '/data/config.php';

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

if( $config["lang_" . $config['skin']] ) {
	if ( file_exists( ROOT_DIR . '/language/' . $config["lang_" . $config['skin']] . '/website.lng' ) ) {
		include_once ROOT_DIR . '/language/' . $config["lang_" . $config['skin']] . '/website.lng';
	} else die("Language file not found");
} else {
	include_once ROOT_DIR . '/language/' . $config['langs'] . '/website.lng';
}

$config['charset'] = ($lang['charset'] != '') ? $lang['charset'] : $config['charset'];
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
if($is_logged) {
	// Если юзер авторизован
	$name = $db->safesql($member_id['name']);
	$where = "user_name = '{$member_id['name']}'";
} else {
	// Если неавторизован
	$name = "";
	$where = "ip ='{$ip}'";
}

// Проверяем лайки у новости
$likes = $db->super_query( "SELECT news_id, likes FROM " . PREFIX . "_easylike_count WHERE news_id = $news_id ");
if (!$likes['likes']) {
	$likes['likes'] = 0;
}

if (count(explode('.', $ip)) == 4) {
	// Если получен IP посетителя - работаем.
	if (!$likes['news_id']) {
		// Если записи о лайках нет - добавим.
		$db->query("INSERT INTO " . PREFIX . "_easylike_count (news_id, likes) VALUES ($news_id, '1')");
		$easyLike = setLog($likes['likes'], $news_id, $name, $ip);
	} else {
		// Если запись есть, то проверяем, не лайкал ли этот посетитель.
		$select = "SELECT news_id FROM " . PREFIX . "_easylike_log WHERE news_id = $news_id AND {$where}";
		$row = $db->super_query($select);

		if (!$row['news_id']) {
			// Если не лайкал - работаем.
			$db->query("UPDATE " . PREFIX . "_easylike_count SET likes=likes+1 WHERE news_id ='".$news_id."'");
			$easyLike = setLog($likes['likes'], $news_id, $name, $ip);
		} else {
			// Если лайкал - шлём ему привет :).
			$easyLike = ':-)';
		}
	}
} else {
	// Если IP не получили (бывает такое с ipv6 :)) - показываем грустный смайлик.
	$easyLike = ':-(';
}

$db->close();

@header( "Content-type: text/html; charset=" . $config['charset'] );
echo $easyLike;

/**
 * Записываем даные в лог
 * @param integer $count
 * @param integer $news_id
 * @param string  $name
 * @param string  $ip
 */
function setLog($count = 0, $news_id = 1, $name = '', $ip = '') {
	global $config, $db;

	$db->query( "INSERT INTO " . PREFIX . "_easylike_log (news_id, user_name, ip) values ('$news_id', '{$name}', '$ip')" );
	if ($config['version_id'] > 9.4) {
		if (($config['allow_alt_url'] && $config['allow_alt_url'] != 'no') && !$config['seo_type']) {
			$cprefix = 'full_';
		} else {
			$cprefix = 'full_'.$news_id;
		}
		clear_cache(array('news_', 'rss', $cprefix));
	} else {
		clear_cache();
	}


	return $count + 1;
}
?>