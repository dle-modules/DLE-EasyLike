<?php
/*
=============================================================================
Easy Like - ������ ����������� ������� ������ ��� DLE
=============================================================================
�����:   ��������
URL:     http://pafnuty.name/
twitter: https://twitter.com/pafnuty_name
google+: http://gplus.to/pafnuty
email:   pafnuty10@gmail.com
=============================================================================
*/

// ������� ������������ ����� ��� ajax DLE
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


// ���� �� ������, ����� �� ��������� ��� � �����.
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

// ��������� ����������� �����������
if(!$is_logged) $member_id['user_group'] = 5;
if(!$user_group[$member_id['user_group']]['allow_rating']) die(":-(");
// �������� IP ����������
$ip = $db->safesql($_SERVER['REMOTE_ADDR']);

// ���������� ���������� � ������.
if($is_logged) {
	// ���� ���� �����������
	$name = $db->safesql($member_id['name']);
	$where = "user_name = '{$member_id['name']}'";
} else {
	// ���� �������������
	$name = "guest_user";
	$where = "ip ='{$ip}'";
}

// ��������� ����� � �������
$id = $db->super_query( "SELECT news_id FROM " . PREFIX . "_easylike_count WHERE news_id = $news_id ");

if (count(explode('.', $ip)) == 4) {
	// ���� ������� IP ���������� - ��������.
	if (!$id['news_id']) {
		// ���� ������ � ������ ��� - �������.
		$db->query("INSERT INTO " . PREFIX . "_easylike_count (news_id, likes) VALUES ($news_id, '1')");
		$easyLike = setLog($count, $news_id, $name, $ip);
	} else {
		// ���� ������ ����, �� ���������, �� ������ �� ���� ����������.
		$select = "SELECT news_id FROM " . PREFIX . "_easylike_log WHERE news_id = $news_id AND {$where}";
		$row = $db->super_query($select);

		if (!$row['news_id']) {
			// ���� �� ������ - ��������.
			$db->query("UPDATE " . PREFIX . "_easylike_count SET likes=likes+1 WHERE news_id ='".$news_id."'");
			$easyLike = setLog($count, $news_id, $name, $ip);
		} else {
			// ���� ������ - ��� ��� ������ :).
			$easyLike = ':-)';
		}
	}
} else {
	// ���� IP �� �������� (������ ����� � ipv6 :)) - ���������� �������� �������.
	$easyLike = ':-(';
}

$db->close();

@header( "Content-type: text/html; charset=" . $config['charset'] );
echo $easyLike;

/**
 * ���������� ����� � ���
 * @param integer $count
 * @param integer $news_id
 * @param string  $name
 * @param string  $ip
 */
function setLog($count = 0, $news_id = 1, $name = 'guest_user', $ip = '127.0.0.1') {
	global $config, $db;

	$db->query( "INSERT INTO " . PREFIX . "_easylike_log (news_id, user_name, ip) values ('$news_id', '{$name}', '$ip')" );
	if (($config['allow_alt_url'] && $config['allow_alt_url'] != 'no') AND !$config['seo_type']) {
		$cprefix = 'full_';
	} else {
		$cprefix = 'full_'.$news_id;
	}
	clear_cache(array('news_', 'rss', $cprefix));

	return $count + 1;
}
?>