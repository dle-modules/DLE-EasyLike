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

if (!defined('DATALIFEENGINE')) die("Go fuck yourself!");
$cfg  = array(
    'newsId' => !empty($news_id) ? (int)$news_id : false,
    'commentId' => !empty($comment_id) ? (int)$comment_id : false,
);
if ($cfg['newsId'] || $cfg['commentId']) {
	if ($cfg['newsId']) {
		$id = $cfg['newsId'];
		$col = 'news_id';
	}
	if ($cfg['commentId']) {
		$id = $cfg['commentId'];
		$col = 'comment_id';
	}
	// Запрос в БД
	$row = $db->super_query("SELECT news_id, likes FROM " . PREFIX . "_easylike_count WHERE {$col} = '" . $id . "'");

	$likeCount = ($row['likes']) ? $row['likes'] : false ;

	// Условие для вывода лайков
	if ($likeCount) {
		// Нсли лайки есть - выводим их количество
		$easyLike = '<span class="easylike_count" data-' . $col . '="' . $id . '" data-count="' . $likeCount . '">' . $likeCount . '</span>';
	} else {
		// Если нет - выводим информацию о том, что их нет (ну или тупо нолик можно подставить)
		$easyLike = '<span class="easylike_count nolikes" data-' . $col . '="' . $id . '" data-count="0">0</span>';
	}

	// Выводим результат работы модуля
	echo $easyLike;
}
?>
