<?php

/**
* K2 Source Class
* @package Highlighter GK5
* @Copyright (C) 2009-2013 Gavick.com
* @ All rights reserved
* @ Joomla! is Free Software
* @ Released under GNU/GPL License : http://www.gnu.org/copyleft/gpl.html
* @version $Revision: 4.0.0 $
**/

// no direct access
defined('_JEXEC') or die('Restricted access');

class NH_GK5_K2_Source {
	// Method to get sources of articles
	static function getSources($config) {
		if($config['data_source'] != 'k2_all') {
			//
			$db = JFactory::getDBO();
			// if source type is section / sections
			$source = false;
			$where1 = '';
			$where2 = '';
			$tag_join= '';
			//
			if( $config['data_source'] == 'k2_tags' ) {
				$tag_join = ' LEFT JOIN #__k2_tags_xref AS tx ON content.id = tx.itemID LEFT JOIN #__k2_tags AS t ON t.id = tx.tagID ';
			}
			//
			if($config['data_source'] == 'k2_categories'){
				$source = $config['k2_categories'];
				$where1 = ' c.id = ';
				$where2 = ' OR c.id = ';
			} else if($config['data_source'] == 'k2_tags') {
	           	$where1 = ' t.id = ';
	           	// adding quotes to tag name
	          	$source = $config['k2_tags'];
	         
	        	if(!is_array($source)) {
	           		$source = array($source);
	        	}
			} else {
				$source = strpos($config['k2_articles'],',') !== false ? explode(',', $config['k2_articles']) : $config['k2_articles'];
				$where1 = ' content.id = ';
				$where2 = ' OR content.id = ';	
			}
			//	
			$where = ''; // initialize WHERE condition
			// generating WHERE condition
			for($i = 0;$i < count($source);$i++){
				if(count($source) == 1) $where .= (is_array($source)) ? $where1.$source[0] : $where1.$source;
				else $where .= ($i == 0) ? $where1.$source[$i] : $where2.$source[$i];		
			}
			//
			$query_name = '
				SELECT 
					c.id AS CID
				FROM 
					#__k2_categories AS c
				LEFT JOIN 
					#__k2_items AS content 
					ON 
					c.id = content.catid 	
				'.$tag_join.' 
				WHERE 
					( '.$where.' ) 
					AND 
					c.published = 1
		        ';	
			// Executing SQL Query
			$db->setQuery($query_name);
	
			// check if some categories was detected
			if($categories = $db->loadObjectList()) {
				$categories_array = array();
				// iterate through all items 
				foreach($categories as $item) {
					if(!in_array($item->CID, $categories_array)) {
						array_push($categories_array, $item->CID);
					}
				}
				//
				return $categories_array;
			} else {
				// when no categories detected
				return null;
			}
		} else {
			return null;
		}
	}
	// Method to get articles in standard mode 
	static function getArticles($categories, $config, $amount) {	
		//
		$sql_where = '';
		$tag_join = '';
		//
		if($categories) {		
			// getting categories ItemIDs
			for($j = 0; $j < count($categories); $j++) {
				$sql_where .= ($j != 0) ? ' OR content.catid = ' . $categories[$j] : ' content.catid = '. $categories[$j];
			}	
		}
		// Overwrite SQL query when user set IDs manually
		if($config['data_source'] == 'k2_articles' && $config['k2_articles'] != ''){
			// initializing variables
			$sql_where = '';
			$ids = explode(',', $config['k2_articles']);
			//
			for($i = 0; $i < count($ids); $i++ ){	
				// linking string with content IDs
				$sql_where .= ($i != 0) ? ' OR content.id = '.$ids[$i] : ' content.id = '.$ids[$i];
			}
		}
		// Overwrite SQL query when user specified tags
		if($config['data_source'] == 'k2_tags' && $config['k2_tags'] != ''){
			// initializing variables
			$sql_where = '';
			$tag_join = ' LEFT JOIN #__k2_tags_xref AS tx ON content.id = tx.itemID LEFT JOIN #__k2_tags AS t ON t.id = tx.tagID ';
			// getting tag
			$sql_where .= ' t.id = '. $config['k2_tags'];
		}
		// Arrays for content
		$content = array();
		$news_amount = 0;
		// Initializing standard Joomla classes and SQL necessary variables
		$db = JFactory::getDBO();
		$access_con = '';
		
		if($config['unauthorized'] == '0') {
			$access_con = ' AND content.access IN ('. implode(',', JFactory::getUser()->authorisedLevels()) .') ';
		}
		$date = JFactory::getDate("now", $config['time_offset']);
		$now  = $date->toSql(true);
		$nullDate = $db->getNullDate();
		// if some data are available
		// when showing only frontpage articles is disabled
		$frontpage_con = '';
		
		if($config['only_frontpage'] == 0 && $config['news_frontpage'] == 0) {
		 	$frontpage_con = ' AND content.featured = 0 ';
		} else if($config['only_frontpage'] == 1) {
			$frontpage_con = ' AND content.featured = 1';
		}
		
		$since_con = '';
		//
		if($config['news_since'] !== '') {
			$since_con = ' AND content.created >= ' . $db->Quote($config['news_since']);
		}
		//
		if($config['news_since'] == '' && $config['news_in'] != '') {
			$since_con = ' AND content.created >= ' . $db->Quote(strftime('%Y-%m-%d 00:00:00', time() - ($config['news_in'] * 24 * 60 * 60)));
		}
		// Ordering string
		$order_options = '';
		// When sort value is random
		if($config['news_sort_value'] == 'random') {
			$order_options = ' RAND() '; 
		}else{ // when sort value is different than random
			$order_options = ' content.'.$config['news_sort_value'].' '.$config['news_sort_order'].' ';
		}	
		// language filters
		$lang_filter = '';
		if (JFactory::getApplication()->getLanguageFilter()) {
			$lang_filter = ' AND content.language in ('.$db->quote(JFactory::getLanguage()->getTag()).','.$db->quote('*').') ';
		}
		
		if($config['data_source'] != 'k2_all') {
			$sql_where = ' AND ( ' . $sql_where . ' ) ';
		}
		// creating SQL query			
		$query_news = '
		SELECT
			content.id AS id,
			content.alias AS alias,
			'.($config['use_title_alias'] ? 'content.alias' : 'content.title').' AS title, 
			content.introtext AS text, 
			content.created AS date, 
			content.publish_up AS date_publish,
			content.hits AS hits,
			content.featured AS frontpage				
		FROM 
			#__k2_items AS content 
			'.$tag_join.'
		WHERE 
			content.published = 1
                '. $access_con .'   
		 		AND ( content.publish_up = '.$db->Quote($nullDate).' OR content.publish_up <= '.$db->Quote($now).' )
				AND ( content.publish_down = '.$db->Quote($nullDate).' OR content.publish_down >= '.$db->Quote($now).' )
			'.$sql_where.'
			'.$lang_filter.'
			'.$frontpage_con.' 
			'.$since_con.'
		ORDER BY 
			'.$order_options.'
		LIMIT
			'.($config['startposition']).','.($amount + (int)$config['time_offset']).';
		';
		// run SQL query
		$db->setQuery($query_news);
		// when exist some results
		if($news = $db->loadAssocList()) {			
			// generating tables of news data
			foreach($news as $item) {	
				$content[] = $item; // store item in array
				$news_amount++;	// news amount
			}
		}
		// generate SQL WHERE condition
		$second_sql_where = '';
		for($i = 0; $i < count($content); $i++) {
			$second_sql_where .= (($i != 0) ? ' OR ' : '') . ' content.id = ' . $content[$i]['id'];
		}
		// second SQL query to get rest of the data and avoid the DISTINCT
		$second_query_news = '
		SELECT
			content.id AS id,
			content.access AS access,
			content.catid AS cid,
			categories.name AS catname, 
			categories.alias AS cat_alias,
			users.email AS author_email,
			content.created_by_alias AS author_alias,
			content.created_by AS author_id,
			content_rating.rating_sum AS rating_sum,
			content_rating.rating_count AS rating_count		
		FROM 
			#__k2_items AS content 
			LEFT JOIN 
				#__k2_categories AS categories 
				ON categories.id = content.catid 
			LEFT JOIN 
				#__users AS users 
				ON users.id = content.created_by 			
			LEFT JOIN 
				#__k2_rating AS content_rating 
				ON content_rating.itemID = content.id
		WHERE 
			'.$second_sql_where.'
		ORDER BY 
			'.$order_options.'
		';
		// run the query
		$db->setQuery($second_query_news);
		// when exist some results
		if($news2 = $db->loadAssocList()) {
			// create the iid array
			$content_id = array();
			// create the content IDs array
			foreach($content as $item) {
				array_push($content_id, $item['id']);
			}
			// generating tables of news data
			foreach($news2 as $item) {						
			    $pos = array_search($item['id'], $content_id);
				// merge the new data to the array of items data
				$content[$pos] = array_merge($content[$pos], (array) $item);
			}
		}
		// the content array
		return $content; 
	}
}

// EOF