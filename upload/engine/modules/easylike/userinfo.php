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
 	'userName' => !empty($_REQUEST['user']) ? $db->safesql($_REQUEST['user']) : false,
	'template' => !empty($template) ? $template : 'easylike/default',
	'cachePrefix' => !empty($cachePrefix) ? $cachePrefix : 'news',
	'cacheSuffix' => !empty($cacheSuffix) ? true : false
);
$cacheName = md5(implode('_', $cfg));
$easyLike  = false;
// $easyLike  = dle_cache($cfg['cachePrefix'], $cacheName . $config['skin'], $cfg['cacheSuffix']);
if (!$easyLike) {
	if (file_exists(TEMPLATE_DIR . '/' . $cfg['template'] . '.tpl')) {
		if (!isset($tpl)) {
			$tpl      = new dle_template();
			$tpl->dir = TEMPLATE_DIR;
		} else {
			$tpl->result['easyLike'] = '';
		}
		$tpl->load_template($cfg['template'] . '.tpl');

		$easylike_news = '0';
		$easylike_comments = '0';
		$easylike_all = '0';

		if(strpos($tpl->copy_template, "{easylike_news}") !== false || strpos($tpl->copy_template, "{easylike_all}") !== false) {
			// Запрос в БД на получение всех ID новостей юзера.
			$rowNews = $db->super_query("SELECT id FROM " . PREFIX . "_post WHERE autor = '" . $cfg['userName'] . "'", true);
			if (is_array($rowNews)) {
				$_ids = array();
				foreach ($rowNews as $_id) {
					$_ids[]= $_id['id'];
				}
				if (count($_ids) > 0) {
					$_lid = implode(',', $_ids);
					$news = $db->super_query("SELECT SUM(likes) as userlikes FROM " . PREFIX . "_easylike_count WHERE news_id IN ($_lid)");
					if ($news['userlikes']) {
						$easylike_news = $news['userlikes'];
					}
				}
			}

			$tpl->set('{easylike_news}', $easylike_news);
			$tpl->set('{easylike_news_text}', wordSpan($easylike_news,'лайк||а|ов'));
		}

		if(strpos($tpl->copy_template, "{easylike_comments}") !== false || strpos($tpl->copy_template, "{easylike_all}") !== false) {
			// Запрос в БД на получение всех ID комментариев юзера.
			$rowComments = $db->super_query("SELECT id FROM " . PREFIX . "_comments WHERE autor = '" . $cfg['userName'] . "'", true);
			if (is_array($rowComments)) {
				$_idsc = array();
				foreach ($rowComments as $_id) {
					$_idsc[]= $_id['id'];
				}
				if (count($_idsc) > 0) {
					$_lid = implode(',', $_idsc);
					$comments = $db->super_query("SELECT SUM(likes) as userlikes FROM " . PREFIX . "_easylike_count WHERE comment_id IN ($_lid)");
					if ($comments['userlikes']) {
						$easylike_comments = $comments['userlikes'];
					}
				}
			}
			$tpl->set('{easylike_comments}', $easylike_comments);
			$tpl->set('{easylike_comments_text}', wordSpan($easylike_comments,'лайк||а|ов'));
		}

		if(strpos($tpl->copy_template, "{easylike_all}") !== false) {

			$easylike_all = $easylike_comments + $easylike_news;

			$tpl->set('{easylike_all}', $easylike_all);
			$tpl->set('{easylike_all_text}', wordSpan($easylike_all,'лайк||а|ов'));
		}

		if ($easylike_news > 0) {
			$tpl->set( '[easylike_news]', "" );
			$tpl->set( '[/easylike_news]', "" );
			$tpl->set_block( "'\\[not-easylike_news\\](.*?)\\[/not-easylike_news\\]'si", "" );
		} else {
			$tpl->set( '[not-easylike_news]', "" );
			$tpl->set( '[/not-easylike_news]', "" );
			$tpl->set_block( "'\\[easylike_news\\](.*?)\\[/easylike_news\\]'si", "" );
		}

		if ($easylike_comments > 0) {
			$tpl->set( '[easylike_comments]', "" );
			$tpl->set( '[/easylike_comments]', "" );
			$tpl->set_block( "'\\[not-easylike_comments\\](.*?)\\[/not-easylike_comments\\]'si", "" );
		} else {
			$tpl->set( '[not-easylike_comments]', "" );
			$tpl->set( '[/not-easylike_comments]', "" );
			$tpl->set_block( "'\\[easylike_comments\\](.*?)\\[/easylike_comments\\]'si", "" );
		}

		if ($easylike_all > 0) {
			$tpl->set( '[easylike_all]', "" );
			$tpl->set( '[/easylike_all]', "" );
			$tpl->set_block( "'\\[not-easylike_all\\](.*?)\\[/not-easylike_all\\]'si", "" );
		} else {
			$tpl->set( '[not-easylike_all]', "" );
			$tpl->set( '[/not-easylike_all]', "" );
			$tpl->set_block( "'\\[easylike_all\\](.*?)\\[/easylike_all\\]'si", "" );
		}


		$tpl->compile('easyLike');
		$easyLike = $tpl->result['easyLike'];

		// Создаём кеш
		create_cache($cfg['cachePrefix'], $easyLike, $cacheName . $config['skin'], $cfg['cacheSuffix']);
		$tpl->clear();
	} else {
		$easyLike = '<b style="color:red">Отсутствует файл шаблона: ' . $config['skin'] . '/' . $cfg['template'] . '.tpl</b>';
	}
}
// Выводим результат работы модуля
echo $easyLike;

/**
 * Функция для установки правильного окончания слов
 * @param int $n - число, для которого будет расчитано окончание
 * @param string $words - варианты окончаний для (1 комментарий, 2 комментария, 100 комментариев)
 * @return string - слово с правильным окончанием
 */
function wordSpan($n = 0, $words) {
	$words	= explode('|', $words);
	$n		= intval($n);
	return  $n%10==1&&$n%100!=11?$words[0].$words[1]:($n%10>=2&&$n%10<=4&&($n%100<10||$n%100>=20)?$words[0].$words[2]:$words[0].$words[3]);
}

?>

