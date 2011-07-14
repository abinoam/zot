<?php


function format_event_html($ev) {

	require_once('include/bbcode.php');

	if(! ((is_array($ev)) && count($ev)))
		return '';

	$bd_format = t('l F d, Y \@ g:i A') ; // Friday January 18, 2011 @ 8 AM

	$o = '<div class="vevent">' . "\r\n";

	$o .= '<p class="description event-description">' . bbcode($ev['desc']) .  '</p>' . "\r\n";

	$o .= '<p class="event-start">' . t('Starts:') . ' <abbr class="dtstart" title="'
		. datetime_convert('UTC','UTC',$ev['start'], (($ev['adjust']) ? ATOM_TIME : 'Y-m-d\TH:i:s' ))
		. '" >' 
		. (($ev['adjust']) ? day_translate(datetime_convert('UTC', date_default_timezone_get(), 
			$ev['start'] , $bd_format ))
			:  day_translate(datetime_convert('UTC', 'UTC', 
			$ev['start'] , $bd_format)))
		. '</abbr></p>' . "\r\n";

	if(! $ev['nofinish'])
		$o .= '<p class="event-end" >' . t('Finishes:') . ' <abbr class="dtend" title="'
			. datetime_convert('UTC','UTC',$ev['finish'], (($ev['adjust']) ? ATOM_TIME : 'Y-m-d\TH:i:s' ))
			. '" >' 
			. (($ev['adjust']) ? day_translate(datetime_convert('UTC', date_default_timezone_get(), 
				$ev['finish'] , $bd_format ))
				:  day_translate(datetime_convert('UTC', 'UTC', 
				$ev['finish'] , $bd_format )))
			. '</abbr></p>'  . "\r\n";

	if(strlen($ev['location']))
		$o .= '<p class="event-location"> ' . t('Location:') . ' <span class="location">' 
			. bbcode($ev['location']) 
			. '</span></p>' . "\r\n";

	$o .= '</div>' . "\r\n";
	return $o;
}


function parse_event($h) {

	require_once('include/Scrape.php');
	require_once('library/HTMLPurifier.auto.php');
	require_once('include/html2bbcode');

	$h = '<html><body>' . $h . '</body></html>';

	$ret = array();

	$dom = HTML5_Parser::parse($h);

	if(! $dom)
 		return $ret;

	$items = $dom->getElementsByTagName('*');

	foreach($items as $item) {
		if(attribute_contains($item->getAttribute('class'), 'vevent')) {
			$level2 = $item->getElementsByTagName('*');
			foreach($level2 as $x) {
				if(attribute_contains($x->getAttribute('class'),'dtstart') && $x->getAttribute('title')) {
					$ret['start'] = $x->getAttribute('title');
					if(! strpos($ret['start'],'Z'))
						$ret['adjust'] = true;
				}
				if(attribute_contains($x->getAttribute('class'),'dtend') && $x->getAttribute('title'))
					$ret['finish'] = $x->getAttribute('title');

				if(attribute_contains($x->getAttribute('class'),'description'))
					$ret['desc'] = $x->textContent;
				if(attribute_contains($x->getAttribute('class'),'location'))
					$ret['location'] = $x->textContent;
			}
		}
	}

	// sanitise

	if((x($ret,'desc')) && ((strpos($ret['desc'],'<') !== false) || (strpos($ret['desc'],'>') !== false))) {
		$config = HTMLPurifier_Config::createDefault();
		$config->set('Cache.DefinitionImpl', null);
		$purifier = new HTMLPurifier($config);
		$ret['desc'] = html2bbcode($purifier->purify($ret['desc']));
	}

	if((x($ret,'location')) && ((strpos($ret['location'],'<') !== false) || (strpos($ret['location'],'>') !== false))) {
		$config = HTMLPurifier_Config::createDefault();
		$config->set('Cache.DefinitionImpl', null);
		$purifier = new HTMLPurifier($config);
		$ret['location'] = html2bbcode($purifier->purify($ret['location']));
	}

	if(x($ret,'start'))
		$ret['start'] = datetime_convert('UTC','UTC',$ret['start']);
	if(x($ret,'finish'))
		$ret['finish'] = datetime_convert('UTC','UTC',$ret['finish']);

	return $ret;
}


function format_event_bbcode($ev) {

	$o = '';

	if($ev['desc'])
		$o .= '[event-description]' . $ev['desc'] . '[/event-description]';

	if($ev['start'])
		$o .= '[event-start]' . $ev['start'] . '[/event-start]';

	if(($ev['finish']) && (! $ev['nofinish']))
		$o .= '[event-finish]' . $ev['finish'] . '[/event-finish]';
 
	if($ev['location'])
		$o .= '[event-location]' . $ev['location'] . '[/event-location]';

	if($ev['adjust'])
		$o .= '[event-adjust]' . $ev['adjust'] . '[/event-adjust]';


	return $o;

}

function bbtovcal($s) {
	$o = '';
	$ev = bbtoevent($s);
	if($ev['desc'])
		$o = format_event_html($ev);
	return $o;
}


function bbtoevent($s) {

	$ev = array();

	$match = '';
	if(preg_match("/\[event\-description\](.*?)\[\/event\-description\]/is",$s,$match))
		$ev['desc'] = $match[1];
	$match = '';
	if(preg_match("/\[event\-start\](.*?)\[\/event\-start\]/is",$s,$match))
		$ev['start'] = $match[1];
	$match = '';
	if(preg_match("/\[event\-finish\](.*?)\[\/event\-finish\]/is",$s,$match))
		$ev['finish'] = $match[1];
	$match = '';
	if(preg_match("/\[event\-location\](.*?)\[\/event\-location\]/is",$s,$match))
		$ev['location'] = $match[1];
	$match = '';
	if(preg_match("/\[event\-adjust\](.*?)\[\/event\-adjust\]/is",$s,$match))
		$ev['adjust'] = $match[1];
	$match = '';
	$ev['nofinish'] = (($ev['start'] && (! $ev['finish'])) ? 1 : 0);
	return $ev;

}


function sort_by_date($a) {

	usort($a,'ev_compare');
	return $a;
}


function ev_compare($a,$b) {

	$date_a = (($a['adjust']) ? datetime_convert('UTC',date_default_timezone_get(),$a['start']) : $a['start']);
	$date_b = (($b['adjust']) ? datetime_convert('UTC',date_default_timezone_get(),$b['start']) : $b['start']);

	if($date_a === $date_b)
		return strcasecmp($a['desc'],$b['desc']);
	
	return strcmp($date_a,$date_b);
}



function event_store($arr) {

	require_once('include/datetime.php');
	require_once('include/items.php');
	require_once('include/bbcode.php');

	$a = get_app();

	$arr['created'] = (($arr['created']) ? $arr['created'] : datetime_convert());
	$arr['edited']  = (($arr['edited']) ? $arr['edited'] : datetime_convert());
	$arr['type']    = (($arr['type']) ? $arr['type'] : 'event' );	
	$arr['cid']     = ((intval($arr['cid'])) ? intval($arr['cid']) : 0);
	$arr['uri']     = (x($arr,'uri') ? $arr['uri'] : item_new_uri($a->get_hostname(),$arr['uid']));

	if($arr['cid'])
		$c = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($arr['cid']),
			intval($arr['uid'])
		);
	else
		$c = q("SELECT * FROM `contact` WHERE `self` = 1 AND `uid` = %d LIMIT 1",
			intval($arr['uid'])
		);

	if(count($c))
		$contact = $c[0];


	// Existing event being modified

	if($arr['id']) {

		// has the event actually changed?

		$r = q("SELECT * FROM `event` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($arr['id']),
			intval($arr['uid'])
		);
		if((! count($r)) || ($r[0]['edited'] === $arr['edited'])) {

			// Nothing has changed. Grab the item id to return.

			$r = q("SELECT * FROM `item` WHERE `event_id` = %d AND `uid` = %d LIMIT 1",
				intval($arr['id']),
				intval($arr['uid'])
			);
			return((count($r)) ? $r[0]['id'] : 0);
		}

		// The event changed. Update it.

		$r = q("UPDATE `event` SET
			`edited` = '%s',
			`start` = '%s',
			`finish` = '%s',
			`desc` = '%s',
			`location` = '%s',
			`type` = '%s',
			`adjust` = %d,
			`nofinish` = %d,
			`allow_cid` = '%s',
			`allow_gid` = '%s',
			`deny_cid` = '%s',
			`deny_gid` = '%s'
			WHERE `id` = %d AND `uid` = %d LIMIT 1",

			dbesc($arr['edited']),
			dbesc($arr['start']),
			dbesc($arr['finish']),
			dbesc($arr['desc']),
			dbesc($arr['location']),
			dbesc($arr['type']),
			intval($arr['adjust']),
			intval($arr['nofinish']),
			dbesc($arr['allow_cid']),
			dbesc($arr['allow_gid']),
			dbesc($arr['deny_cid']),
			dbesc($arr['deny_gid']),
			intval($arr['id']),
			intval($arr['uid'])
		);
		$r = q("SELECT * FROM `item` WHERE `event_id` = %d AND `uid` = %d LIMIT 1",
			intval($arr['id']),
			intval($arr['uid'])
		);
		if(count($r)) {
			$object = '<object><type>' . xmlify(ACTIVITY_OBJ_EVENT) . '</type><title></title><id>' . xmlify($arr['uri']) . '</id>';
			$object .= '<content>' . xmlify(format_event_bbcode($arr)) . '</content>';
			$object .= '</object>' . "\n";


			q("UPDATE `item` SET `body` = '%s', `object` = '%s', `allow_cid` = '%s', `allow_gid` = '%s', `deny_cid` = '%s', `deny_gid` = '%s', `edited` = '%s' WHERE `id` = %d AND `uid` = %d LIMIT 1",
				dbesc(format_event_bbcode($arr)),
				dbesc($object),
				dbesc($arr['allow_cid']),
				dbesc($arr['allow_gid']),
				dbesc($arr['deny_cid']),
				dbesc($arr['deny_gid']),
				dbesc($arr['edited']),
				intval($r[0]['id']),
				intval($arr['uid'])
			);

			return $r[0]['id'];
		}
		else
			return 0;
	}
	else {

		// New event. Store it. 

		$r = q("INSERT INTO `event` ( `uid`,`cid`,`uri`,`created`,`edited`,`start`,`finish`,`desc`,`location`,`type`,
			`adjust`,`nofinish`,`allow_cid`,`allow_gid`,`deny_cid`,`deny_gid`)
			VALUES ( %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, '%s', '%s', '%s', '%s' ) ",
			intval($arr['uid']),
			intval($arr['cid']),
			dbesc($arr['uri']),
			dbesc($arr['created']),
			dbesc($arr['edited']),
			dbesc($arr['start']),
			dbesc($arr['finish']),
			dbesc($arr['desc']),
			dbesc($arr['location']),
			dbesc($arr['type']),
			intval($arr['adjust']),
			intval($arr['nofinish']),
			dbesc($arr['allow_cid']),
			dbesc($arr['allow_gid']),
			dbesc($arr['deny_cid']),
			dbesc($arr['deny_gid'])

		);

		$r = q("SELECT * FROM `event` WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
			dbesc($arr['uri']),
			intval($arr['uid'])
		);
		if(count($r))
			$event = $r[0];

		$item_arr = array();

		$item_arr['uid']           = $arr['uid'];
		$item_arr['contact_id']    = $arr['cid'];
		$item_arr['uri']           = $arr['uri'];
		$item_arr['parent_uri']    = $arr['uri'];
		$item_arr['type']          = 'activity';
		$item_arr['wall']          = (($arr['cid']) ? 0 : 1);
		$item_arr['contact_id']    = $contact['id'];
		$item_arr['owner_name']    = $contact['name'];
		$item_arr['owner_link']    = $contact['url'];
		$item_arr['owner_avatar']  = $contact['thumb'];
		$item_arr['author_name']   = $contact['name'];
		$item_arr['author_link']   = $contact['url'];
		$item_arr['author_avatar'] = $contact['thumb'];
		$item_arr['title']         = '';
		$item_arr['allow_cid']     = $str_contact_allow;
		$item_arr['allow_gid']     = $str_group_allow;
		$item_arr['deny_cid']      = $str_contact_deny;
		$item_arr['deny_gid']      = $str_group_deny;
		$item_arr['last_child']    = 1;
		$item_arr['visible']       = 1;
		$item_arr['verb']          = ACTIVITY_POST;
		$item_arr['object_type']   = ACTIVITY_OBJ_EVENT;

		$item_arr['body']          = format_event_bbcode($event);


		$item_arr['object'] = '<object><type>' . xmlify(ACTIVITY_OBJ_EVENT) . '</type><title></title><id>' . xmlify($uri) . '</id>';
		$item_arr['object'] .= '<content>' . xmlify(format_event_bbcode($event)) . '</content>';
		$item_arr['object'] .= '</object>' . "\n";

		$item_id = item_store($item_arr);

		$r = q("SELECT * FROM `user` WHERE `uid` = %d LIMIT 1",
			intval($arr['uid'])
		);
		if(count($r))
			$plink = z_path() . '/display/' . $r[0]['nickname'] . '/' . $item_id;


		if($item_id) {
			q("UPDATE `item` SET `plink` = '%s', `event_id` = %d  WHERE `uid` = %d AND `id` = %d LIMIT 1",
				dbesc($plink),
				intval($event['id']),
				intval($arr['uid']),
				intval($item_id)
			);
		}

		return $item_id;
	}
}
