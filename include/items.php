<?php

require_once('bbcode.php');
require_once('oembed.php');
require_once('include/salmon.php');

function get_feed_for(&$a, $dfrn_id, $owner_nick, $last_update, $direction = 0) {


	// default permissions - anonymous user

	if(! strlen($owner_nick))
		killme();

	$sql_extra = " AND `allow_cid` = '' AND `allow_gid` = '' AND `deny_cid`  = '' AND `deny_gid`  = '' ";

	$r = q("SELECT `contact`.*, `user`.`uid` AS `user_uid`, `user`.`nickname`, `user`.`timezone`
		FROM `contact` LEFT JOIN `user` ON `user`.`uid` = `contact`.`uid`
		WHERE `contact`.`self` = 1 AND `user`.`nickname` = '%s' LIMIT 1",
		dbesc($owner_nick)
	);

	if(! count($r))
		killme();

	$owner = $r[0];
	$owner_id = $owner['user_uid'];
	$owner_nick = $owner['nickname'];

	$birthday = feed_birthday($owner_id,$owner['timezone']);

	if(strlen($dfrn_id)) {

		$sql_extra = '';
		switch($direction) {
			case (-1):
				$sql_extra = sprintf(" AND `issued_id` = '%s' ", dbesc($dfrn_id));
				$my_id = $dfrn_id;
				break;
			case 0:
				$sql_extra = sprintf(" AND `issued_id` = '%s' AND `duplex` = 1 ", dbesc($dfrn_id));
				$my_id = '1:' . $dfrn_id;
				break;
			case 1:
				$sql_extra = sprintf(" AND `dfrn_id` = '%s' AND `duplex` = 1 ", dbesc($dfrn_id));
				$my_id = '0:' . $dfrn_id;
				break;
			default:
				return false;
				break; // NOTREACHED
		}

		$r = q("SELECT * FROM `contact` WHERE `blocked` = 0 AND `pending` = 0 AND `contact`.`uid` = %d $sql_extra LIMIT 1",
			intval($owner_id)
		);

		if(! count($r))
			killme();

		$contact = $r[0];
		$groups = init_groups_visitor($contact['id']);

		if(count($groups)) {
			for($x = 0; $x < count($groups); $x ++) 
				$groups[$x] = '<' . intval($groups[$x]) . '>' ;
			$gs = implode('|', $groups);
		}
		else
			$gs = '<<>>' ; // Impossible to match 

		$sql_extra = sprintf(" 
			AND ( `allow_cid` = '' OR     `allow_cid` REGEXP '<%d>' ) 
			AND ( `deny_cid`  = '' OR NOT `deny_cid`  REGEXP '<%d>' ) 
			AND ( `allow_gid` = '' OR     `allow_gid` REGEXP '%s' )
			AND ( `deny_gid`  = '' OR NOT `deny_gid`  REGEXP '%s') 
		",
			intval($contact['id']),
			intval($contact['id']),
			dbesc($gs),
			dbesc($gs)
		);
	}

	if($dfrn_id === '' || $dfrn_id === '*')
		$sort = 'DESC';
	else
		$sort = 'ASC';

	if(! strlen($last_update))
		$last_update = 'now -30 days';

	$check_date = datetime_convert('UTC','UTC',$last_update,'Y-m-d H:i:s');

	$r = q("SELECT `item`.*, `item`.`id` AS `item_id`, 
		`contact`.`name`, `contact`.`photo`, `contact`.`url`, 
		`contact`.`name_date`, `contact`.`uri_date`, `contact`.`avatar_date`,
		`contact`.`thumb`, `contact`.`dfrn_id`, `contact`.`self`, 
		`contact`.`id` AS `contact_id`, `contact`.`uid` AS `contact-uid`
		FROM `item` LEFT JOIN `contact` ON `contact`.`id` = `item`.`contact_id`
		WHERE `item`.`uid` = %d AND `item`.`visible` = 1 AND `item`.`parent` != 0 
		AND `item`.`wall` = 1 AND `contact`.`blocked` = 0 AND `contact`.`pending` = 0
		AND ( `item`.`edited` > '%s' OR `item`.`changed` > '%s' )
		$sql_extra
		ORDER BY `parent` %s, `created` ASC LIMIT 0, 300",
		intval($owner_id),
		dbesc($check_date),
		dbesc($check_date),
		dbesc($sort)
	);

	// Will check further below if this actually returned results.
	// We will provide an empty feed if that is the case.

	$items = $r;

	$feed_template = get_markup_template('atom_feed.tpl');

	$atom = '';

	$hubxml = feed_hublinks();

	$salmon = feed_salmonlinks($owner_nick);

	$atom .= replace_macros($feed_template, array(
		'$version'      => xmlify(FRIENDIKA_VERSION),
		'$feed_id'      => xmlify(z_path() . '/profile/' . $owner_nick),
		'$feed_title'   => xmlify($owner['name']),
		'$feed_updated' => xmlify(datetime_convert('UTC', 'UTC', 'now' , ATOM_TIME)) ,
		'$hub'          => $hubxml,
		'$salmon'       => $salmon,
		'$name'         => xmlify($owner['name']),
		'$profile_page' => xmlify($owner['url']),
		'$photo'        => xmlify($owner['photo']),
		'$thumb'        => xmlify($owner['thumb']),
		'$picdate'      => xmlify(datetime_convert('UTC','UTC',$owner['avatar_date'] . '+00:00' , ATOM_TIME)) ,
		'$uridate'      => xmlify(datetime_convert('UTC','UTC',$owner['uri_date']    . '+00:00' , ATOM_TIME)) ,
		'$namdate'      => xmlify(datetime_convert('UTC','UTC',$owner['name_date']   . '+00:00' , ATOM_TIME)) , 
		'$birthday'     => ((strlen($birthday)) ? '<dfrn:birthday>' . xmlify($birthday) . '</dfrn:birthday>' : '')
	));

	call_hooks('atom_feed', $atom);

	if(! count($items)) {

		call_hooks('atom_feed_end', $atom);

		$atom .= '</feed>' . "\r\n";
		return $atom;
	}

	foreach($items as $item) {

		// public feeds get html, our own nodes use bbcode

		if($dfrn_id === '') {
			$type = 'html';
		}
		else {
			$type = 'text';
		}

		$atom .= atom_entry($item,$type,null,$owner,true);
	}

	call_hooks('atom_feed_end', $atom);

	$atom .= '</feed>' . "\r\n";

	return $atom;
}


function construct_verb($item) {
	if($item['verb'])
		return $item['verb'];
	return ACTIVITY_POST;
}

function construct_activity_object($item) {

	if($item['object']) {
		$o = '<as:object>' . "\r\n";
		$r = parse_xml_string($item['object'],false);


		if(! $r)
			return '';
		if($r->type)
			$o .= '<as:object_type>' . xmlify($r->type) . '</as:object_type>' . "\r\n";
		if($r->id)
			$o .= '<id>' . xmlify($r->id) . '</id>' . "\r\n";
		if($r->title)
			$o .= '<title>' . xmlify($r->title) . '</title>' . "\r\n";
		if($r->link) {
			if(substr($r->link,0,1) === '<') {
				// patch up some facebook "like" activity objects that got stored incorrectly
				// for a couple of months prior to 9-Jun-2011 and generated bad XML.
				// we can probably remove this hack here and in the following function in a few months time.
				if(strstr($r->link,'&') && (! strstr($r->link,'&amp;')))
					$r->link = str_replace('&','&amp;', $r->link);
				$r->link = preg_replace('/\<link(.*?)\"\>/','<link$1"/>',$r->link);
				$o .= $r->link;
			}					
			else
				$o .= '<link rel="alternate" type="text/html" href="' . xmlify($r->link) . '" />' . "\r\n";
		}
		if($r->content)
			$o .= '<content type="html" >' . xmlify(bbcode($r->content)) . '</content>' . "\r\n";
		$o .= '</as:object>' . "\r\n";
		return $o;
	}

	return '';
} 

function construct_activity_target($item) {

	if($item['target']) {
		$o = '<as:target>' . "\r\n";
		$r = parse_xml_string($item['target'],false);
		if(! $r)
			return '';
		if($r->type)
			$o .= '<as:object_type>' . xmlify($r->type) . '</as:object_type>' . "\r\n";
		if($r->id)
			$o .= '<id>' . xmlify($r->id) . '</id>' . "\r\n";
		if($r->title)
			$o .= '<title>' . xmlify($r->title) . '</title>' . "\r\n";
		if($r->link) {
			if(substr($r->link,0,1) === '<') {
				if(strstr($r->link,'&') && (! strstr($r->link,'&amp;')))
					$r->link = str_replace('&','&amp;', $r->link);
				$r->link = preg_replace('/\<link(.*?)\"\>/','<link$1"/>',$r->link);
				$o .= $r->link;
			}					
			else
				$o .= '<link rel="alternate" type="text/html" href="' . xmlify($r->link) . '" />' . "\r\n";
		}
		if($r->content)
			$o .= '<content type="html" >' . xmlify(bbcode($r->content)) . '</content>' . "\r\n";
		$o .= '</as:target>' . "\r\n";
		return $o;
	}

	return '';
} 




function get_atom_elements($feed,$item) {

	require_once('library/HTMLPurifier.auto.php');
	require_once('include/html2bbcode.php');

	$best_photo = array();

	$res = array();

	$author = $item->get_author();
	if($author) { 
		$res['author_name'] = unxmlify($author->get_name());
		$res['author_link'] = unxmlify($author->get_link());
	}
	else {
		$res['author_name'] = unxmlify($feed->get_title());
		$res['author_link'] = unxmlify($feed->get_permalink());
	}
	$res['uri'] = unxmlify($item->get_id());
	$res['title'] = unxmlify($item->get_title());
	$res['body'] = unxmlify($item->get_content());
	$res['plink'] = unxmlify($item->get_link(0));

	// look for a photo. We should check media size and find the best one,
	// but for now let's just find any author photo

	$rawauthor = $item->get_item_tags(SIMPLEPIE_NAMESPACE_ATOM_10,'author');

	if($rawauthor && $rawauthor[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link']) {
		$base = $rawauthor[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link'];
		foreach($base as $link) {
			if(! $res['author_avatar']) {
				if($link['attribs']['']['rel'] === 'photo' || $link['attribs']['']['rel'] === 'avatar')
					$res['author_avatar'] = unxmlify($link['attribs']['']['href']);
			}
		}
	}			

	$rawactor = $item->get_item_tags(NAMESPACE_ACTIVITY, 'actor');

	if($rawactor && activity_match($rawactor[0]['child'][NAMESPACE_ACTIVITY]['object_type'][0]['data'],ACTIVITY_OBJ_PERSON)) {
		$base = $rawactor[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link'];
		if($base && count($base)) {
			foreach($base as $link) {
				if($link['attribs']['']['rel'] === 'alternate' && (! $res['author_link']))
					$res['author_link'] = unxmlify($link['attribs']['']['href']);
				if(! $res['author_avatar']) {
					if($link['attribs']['']['rel'] === 'avatar' || $link['attribs']['']['rel'] === 'photo')
						$res['author_avatar'] = unxmlify($link['attribs']['']['href']);
				}
			}
		}
	}

	// No photo/profile-link on the item - look at the feed level

	if((! (x($res,'author_link'))) || (! (x($res,'author_avatar')))) {
		$rawauthor = $feed->get_feed_tags(SIMPLEPIE_NAMESPACE_ATOM_10,'author');
		if($rawauthor && $rawauthor[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link']) {
			$base = $rawauthor[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link'];
			foreach($base as $link) {
				if($link['attribs']['']['rel'] === 'alternate' && (! $res['author_link']))
					$res['author_link'] = unxmlify($link['attribs']['']['href']);
				if(! $res['author_avatar']) {
					if($link['attribs']['']['rel'] === 'photo' || $link['attribs']['']['rel'] === 'avatar')
						$res['author_avatar'] = unxmlify($link['attribs']['']['href']);
				}
			}
		}			

		$rawactor = $feed->get_feed_tags(NAMESPACE_ACTIVITY, 'subject');

		if($rawactor && activity_match($rawactor[0]['child'][NAMESPACE_ACTIVITY]['object_type'][0]['data'],ACTIVITY_OBJ_PERSON)) {
			$base = $rawactor[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link'];

			if($base && count($base)) {
				foreach($base as $link) {
					if($link['attribs']['']['rel'] === 'alternate' && (! $res['author_link']))
						$res['author_link'] = unxmlify($link['attribs']['']['href']);
					if(! (x($res,'author_avatar'))) {
						if($link['attribs']['']['rel'] === 'avatar' || $link['attribs']['']['rel'] === 'photo')
							$res['author_avatar'] = unxmlify($link['attribs']['']['href']);
					}
				}
			}
		}
	}

	$apps = $item->get_item_tags(NAMESPACE_STATUSNET,'notice_info');
	if($apps && $apps[0]['attribs']['']['source']) {
		$res['app'] = strip_tags(unxmlify($apps[0]['attribs']['']['source']));
		if($res['app'] === 'web')
			$res['app'] = 'OStatus';
	}		   

	/**
	 * If there's a copy of the body content which is guaranteed to have survived mangling in transit, use it.
	 */

	$have_real_body = false;

	$rawenv = $item->get_item_tags(NAMESPACE_DFRN, 'env');
	if($rawenv) {
		$have_real_body = true;
		$res['body'] = $rawenv[0]['data'];
		$res['body'] = str_replace(array(' ',"\t","\r","\n"), array('','','',''),$res['body']);
		// make sure nobody is trying to sneak some html tags by us
		$res['body'] = notags(base64url_decode($res['body']));
	}

	$maxlen = get_max_import_size();
	if($maxlen && (strlen($res['body']) > $maxlen))
		$res['body'] = substr($res['body'],0, $maxlen);

	// It isn't certain at this point whether our content is plaintext or html and we'd be foolish to trust 
	// the content type. Our own network only emits text normally, though it might have been converted to 
	// html if we used a pubsubhubbub transport. But if we see even one html tag in our text, we will
	// have to assume it is all html and needs to be purified.

	// It doesn't matter all that much security wise - because before this content is used anywhere, we are 
	// going to escape any tags we find regardless, but this lets us import a limited subset of html from 
	// the wild, by sanitising it and converting supported tags to bbcode before we rip out any remaining 
	// html.

	if((strpos($res['body'],'<') !== false) || (strpos($res['body'],'>') !== false)) {

		$res['body'] = preg_replace('#<object[^>]+>.+?' . 'http://www.youtube.com/((?:v|cp)/[A-Za-z0-9\-_=]+).+?</object>#s',
			'[youtube]$1[/youtube]', $res['body']);

		$res['body'] = preg_replace('#<iframe[^>].+?' . 'http://www.youtube.com/embed/([A-Za-z0-9\-_=]+).+?</iframe>#s',
			'[youtube]$1[/youtube]', $res['body']);

		$res['body'] = oembed_html2bbcode($res['body']);

		$config = HTMLPurifier_Config::createDefault();
		$config->set('Cache.DefinitionImpl', null);

		// we shouldn't need a whitelist, because the bbcode converter
		// will strip out any unsupported tags.
		// $config->set('HTML.Allowed', 'p,b,a[href],i'); 

		$purifier = new HTMLPurifier($config);
		$res['body'] = $purifier->purify($res['body']);

		$res['body'] = html2bbcode($res['body']);
	}

	$allow = $item->get_item_tags(NAMESPACE_DFRN,'comment-allow');
	if($allow && $allow[0]['data'] == 1)
		$res['last_child'] = 1;
	else
		$res['last_child'] = 0;

	$private = $item->get_item_tags(NAMESPACE_DFRN,'private');
	if($private && $private[0]['data'] == 1)
		$res['private'] = 1;
	else
		$res['private'] = 0;

	$extid = $item->get_item_tags(NAMESPACE_DFRN,'extid');
	if($extid && $extid[0]['data'])
		$res['extid'] = $extid[0]['data'];

	$rawlocation = $item->get_item_tags(NAMESPACE_DFRN, 'location');
	if($rawlocation)
		$res['location'] = unxmlify($rawlocation[0]['data']);


	$rawcreated = $item->get_item_tags(SIMPLEPIE_NAMESPACE_ATOM_10,'published');
	if($rawcreated)
		$res['created'] = unxmlify($rawcreated[0]['data']);


	$rawedited = $item->get_item_tags(SIMPLEPIE_NAMESPACE_ATOM_10,'updated');
	if($rawedited)
		$res['edited'] = unxmlify($rawedited[0]['data']);

	if((x($res,'edited')) && (! (x($res,'created'))))
		$res['created'] = $res['edited']; 

	if(! $res['created'])
		$res['created'] = $item->get_date('c');

	if(! $res['edited'])
		$res['edited'] = $item->get_date('c');


	// Disallow time travelling posts

	$d1 = strtotime($res['created']);
	$d2 = strtotime($res['edited']);
	$d3 = strtotime('now');

	if($d1 > $d3)
		$res['created'] = datetime_convert();
	if($d2 > $d3)
		$res['edited'] = datetime_convert();

	$rawowner = $item->get_item_tags(NAMESPACE_DFRN, 'owner');
	if($rawowner[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['name'][0]['data'])
		$res['owner_name'] = unxmlify($rawowner[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['name'][0]['data']);
	elseif($rawowner[0]['child'][NAMESPACE_DFRN]['name'][0]['data'])
		$res['owner_name'] = unxmlify($rawowner[0]['child'][NAMESPACE_DFRN]['name'][0]['data']);
	if($rawowner[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['uri'][0]['data'])
		$res['owner_link'] = unxmlify($rawowner[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['uri'][0]['data']);
	elseif($rawowner[0]['child'][NAMESPACE_DFRN]['uri'][0]['data'])
		$res['owner_link'] = unxmlify($rawowner[0]['child'][NAMESPACE_DFRN]['uri'][0]['data']);

	if($rawowner[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link']) {
		$base = $rawowner[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link'];

		foreach($base as $link) {
			if(! $res['owner_avatar']) {
				if($link['attribs']['']['rel'] === 'photo' || $link['attribs']['']['rel'] === 'avatar')			
					$res['owner_avatar'] = unxmlify($link['attribs']['']['href']);
			}
		}
	}

	$rawgeo = $item->get_item_tags(NAMESPACE_GEORSS,'point');
	if($rawgeo)
		$res['coord'] = unxmlify($rawgeo[0]['data']);


	$rawverb = $item->get_item_tags(NAMESPACE_ACTIVITY, 'verb');

	// select between supported verbs

	if($rawverb) {
		$res['verb'] = unxmlify($rawverb[0]['data']);
	}

	// translate OStatus unfollow to activity streams if it happened to get selected
		
	if((x($res,'verb')) && ($res['verb'] === 'http://ostatus.org/schema/1.0/unfollow'))
		$res['verb'] = ACTIVITY_UNFOLLOW;


	$cats = $item->get_categories();
	if($cats) {
		$tag_arr = array();
		foreach($cats as $cat) {
			$term = $cat->get_term();
			if(! $term)
				$term = $cat->get_label();
			$scheme = $cat->get_scheme();
			if($scheme && $term && stristr($scheme,'X-DFRN:'))
				$tag_arr[] = substr($scheme,7,1) . '[url=' . unxmlify(substr($scheme,9)) . ']' . unxmlify($term) . '[/url]';
			elseif($term)
				$tag_arr[] = notags(trim($term));
		}
		$res['tag'] =  implode(',', $tag_arr);
	}

	$attach = $item->get_enclosures();
	if($attach) {
		$att_arr = array();
		foreach($attach as $att) {
			$len   = intval($att->get_length());
			$link  = str_replace(array(',','"'),array('%2D','%22'),notags(trim(unxmlify($att->get_link()))));
			$title = str_replace(array(',','"'),array('%2D','%22'),notags(trim(unxmlify($att->get_title()))));
			$type  = str_replace(array(',','"'),array('%2D','%22'),notags(trim(unxmlify($att->get_type()))));
			if(strpos($type,';'))
				$type = substr($type,0,strpos($type,';'));
			if((! $link) || (strpos($link,'http') !== 0))
				continue;

			if(! $title)
				$title = ' ';
			if(! $type)
				$type = 'application/octet-stream';

			$att_arr[] = '[attach]href="' . $link . '" size="' . $len . '" type="' . $type . '" title="' . $title . '"[/attach]'; 
		}
		$res['attach'] = implode(',', $att_arr);
	}

	$rawobj = $item->get_item_tags(NAMESPACE_ACTIVITY, 'object');

	if($rawobj) {
		$res['object'] = '<object>' . "\n";
		if($rawobj[0]['child'][NAMESPACE_ACTIVITY]['object_type'][0]['data']) {
			$res['object_type'] = $rawobj[0]['child'][NAMESPACE_ACTIVITY]['object_type'][0]['data'];
			$res['object'] .= '<type>' . $rawobj[0]['child'][NAMESPACE_ACTIVITY]['object_type'][0]['data'] . '</type>' . "\n";
		}	
		if($rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['id'][0]['data'])
			$res['object'] .= '<id>' . $rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['id'][0]['data'] . '</id>' . "\n";
		if($rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link'])
			$res['object'] .= '<link>' . encode_rel_links($rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link']) . '</link>' . "\n";
		if($rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['title'][0]['data'])
			$res['object'] .= '<title>' . $rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['title'][0]['data'] . '</title>' . "\n";
		if($rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['content'][0]['data']) {
			$body = $rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['content'][0]['data'];
			if(! $body)
				$body = $rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['summary'][0]['data'];
			// preserve a copy of the original body content in case we later need to parse out any microformat information, e.g. events
			$res['object'] .= '<orig>' . xmlify($body) . '</orig>' . "\n";
			if((strpos($body,'<') !== false) || (strpos($body,'>') !== false)) {

				$body = preg_replace('#<object[^>]+>.+?' . 'http://www.youtube.com/((?:v|cp)/[A-Za-z0-9\-_=]+).+?</object>#s',
					'[youtube]$1[/youtube]', $body);

		$res['body'] = preg_replace('#<iframe[^>].+?' . 'http://www.youtube.com/embed/([A-Za-z0-9\-_=]+).+?</iframe>#s',
			'[youtube]$1[/youtube]', $res['body']);


				$config = HTMLPurifier_Config::createDefault();
				$config->set('Cache.DefinitionImpl', null);

				$purifier = new HTMLPurifier($config);
				$body = $purifier->purify($body);
				$body = html2bbcode($body);
			}

			$res['object'] .= '<content>' . $body . '</content>' . "\n";
		}

		$res['object'] .= '</object>' . "\n";
	}

	$rawobj = $item->get_item_tags(NAMESPACE_ACTIVITY, 'target');

	if($rawobj) {
		$res['target'] = '<target>' . "\n";
		if($rawobj[0]['child'][NAMESPACE_ACTIVITY]['object_type'][0]['data']) {
			$res['target'] .= '<type>' . $rawobj[0]['child'][NAMESPACE_ACTIVITY]['object_type'][0]['data'] . '</type>' . "\n";
		}	
		if($rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['id'][0]['data'])
			$res['target'] .= '<id>' . $rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['id'][0]['data'] . '</id>' . "\n";

		if($rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link'])
			$res['target'] .= '<link>' . encode_rel_links($rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link']) . '</link>' . "\n";
		if($rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['title'][0]['data'])
			$res['target'] .= '<title>' . $rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['title'][0]['data'] . '</title>' . "\n";
		if($rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['content'][0]['data']) {
			$body = $rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['content'][0]['data'];
			if(! $body)
				$body = $rawobj[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['summary'][0]['data'];
			// preserve a copy of the original body content in case we later need to parse out any microformat information, e.g. events
			$res['object'] .= '<orig>' . xmlify($body) . '</orig>' . "\n";
			if((strpos($body,'<') !== false) || (strpos($body,'>') !== false)) {

				$body = preg_replace('#<object[^>]+>.+?' . 'http://www.youtube.com/((?:v|cp)/[A-Za-z0-9\-_=]+).+?</object>#s',
					'[youtube]$1[/youtube]', $body);

		$res['body'] = preg_replace('#<iframe[^>].+?' . 'http://www.youtube.com/embed/([A-Za-z0-9\-_=]+).+?</iframe>#s',
			'[youtube]$1[/youtube]', $res['body']);

				$config = HTMLPurifier_Config::createDefault();
				$config->set('Cache.DefinitionImpl', null);

				$purifier = new HTMLPurifier($config);
				$body = $purifier->purify($body);
				$body = html2bbcode($body);
			}

			$res['target'] .= '<content>' . $body . '</content>' . "\n";
		}

		$res['target'] .= '</target>' . "\n";
	}

	$arr = array('feed' => $feed, 'item' => $item, 'result' => $res);

	call_hooks('parse_atom', $arr);

	return $res;
}

function encode_rel_links($links) {
	$o = '';
	if(! ((is_array($links)) && (count($links))))
		return $o;
	foreach($links as $link) {
		$o .= '<link ';
		if($link['attribs']['']['rel'])
			$o .= 'rel="' . $link['attribs']['']['rel'] . '" ';
		if($link['attribs']['']['type'])
			$o .= 'type="' . $link['attribs']['']['type'] . '" ';
		if($link['attribs']['']['href'])
			$o .= 'href="' . $link['attribs']['']['href'] . '" ';
		if( (x($link['attribs'],NAMESPACE_MEDIA)) && $link['attribs'][NAMESPACE_MEDIA]['width'])
			$o .= 'media:width="' . $link['attribs'][NAMESPACE_MEDIA]['width'] . '" ';
		if( (x($link['attribs'],NAMESPACE_MEDIA)) && $link['attribs'][NAMESPACE_MEDIA]['height'])
			$o .= 'media:height="' . $link['attribs'][NAMESPACE_MEDIA]['height'] . '" ';
		$o .= ' />' . "\n" ;
	}
	return xmlify($o);
}

function item_store($arr,$force_parent = false) {

	if($arr['gravity'])
		$arr['gravity'] = intval($arr['gravity']);
	elseif($arr['parent_uri'] == $arr['uri'])
		$arr['gravity'] = 0;
	elseif(activity_match($arr['verb'],ACTIVITY_POST))
		$arr['gravity'] = 6;
	else      
		$arr['gravity'] = 6;   // extensible catchall

	if(! x($arr,'type'))
		$arr['type']      = 'remote';

	// Shouldn't happen but we want to make absolutely sure it doesn't leak from a plugin.

	if((strpos($arr['body'],'<') !== false) || (strpos($arr['body'],'>') !== false)) 
		$arr['body'] = strip_tags($arr['body']);


	$arr['wall']          = ((x($arr,'wall'))          ? intval($arr['wall'])                : 0);
	$arr['uri']           = ((x($arr,'uri'))           ? notags(trim($arr['uri']))           : random_string());
	$arr['extid']         = ((x($arr,'extid'))         ? notags(trim($arr['extid']))         : '');
	$arr['author_name']   = ((x($arr,'author_name'))   ? notags(trim($arr['author_name']))   : '');
	$arr['author_link']   = ((x($arr,'author_link'))   ? notags(trim($arr['author_link']))   : '');
	$arr['author_avatar'] = ((x($arr,'author_avatar')) ? notags(trim($arr['author_avatar'])) : '');
	$arr['owner_name']    = ((x($arr,'owner_name'))    ? notags(trim($arr['owner_name']))    : '');
	$arr['owner_link']    = ((x($arr,'owner_link'))    ? notags(trim($arr['owner_link']))    : '');
	$arr['owner_avatar']  = ((x($arr,'owner_avatar'))  ? notags(trim($arr['owner_avatar']))  : '');
	$arr['created']       = ((x($arr,'created') !== false) ? datetime_convert('UTC','UTC',$arr['created']) : datetime_convert());
	$arr['edited']        = ((x($arr,'edited')  !== false) ? datetime_convert('UTC','UTC',$arr['edited'])  : datetime_convert());
	$arr['received']      = datetime_convert();
	$arr['changed']       = datetime_convert();
	$arr['title']         = ((x($arr,'title'))         ? notags(trim($arr['title']))         : '');
	$arr['location']      = ((x($arr,'location'))      ? notags(trim($arr['location']))      : '');
	$arr['coord']         = ((x($arr,'coord'))         ? notags(trim($arr['coord']))         : '');
	$arr['last_child']    = ((x($arr,'last_child'))    ? intval($arr['last_child'])          : 0 );
	$arr['visible']       = ((x($arr,'visible') !== false) ? intval($arr['visible'])         : 1 );
	$arr['deleted']       = 0;
	$arr['parent_uri']    = ((x($arr,'parent_uri'))    ? notags(trim($arr['parent_uri']))    : '');
	$arr['verb']          = ((x($arr,'verb'))          ? notags(trim($arr['verb']))          : '');
	$arr['object_type']   = ((x($arr,'object_type'))   ? notags(trim($arr['object_type']))   : '');
	$arr['object']        = ((x($arr,'object'))        ? trim($arr['object'])                : '');
	$arr['target_type']   = ((x($arr,'target_type'))   ? notags(trim($arr['target_type']))   : '');
	$arr['target']        = ((x($arr,'target'))        ? trim($arr['target'])                : '');
	$arr['plink']         = ((x($arr,'plink'))         ? notags(trim($arr['plink']))         : '');
	$arr['allow_cid']     = ((x($arr,'allow_cid'))     ? trim($arr['allow_cid'])             : '');
	$arr['allow_gid']     = ((x($arr,'allow_gid'))     ? trim($arr['allow_gid'])             : '');
	$arr['deny_cid']      = ((x($arr,'deny_cid'))      ? trim($arr['deny_cid'])              : '');
	$arr['deny_gid']      = ((x($arr,'deny_gid'))      ? trim($arr['deny_gid'])              : '');
	$arr['private']       = ((x($arr,'private'))       ? intval($arr['private'])             : 0 );
	$arr['body']          = ((x($arr,'body'))          ? trim($arr['body'])                  : '');
	$arr['tag']           = ((x($arr,'tag'))           ? notags(trim($arr['tag']))           : '');
	$arr['attach']        = ((x($arr,'attach'))        ? notags(trim($arr['attach']))        : '');
	$arr['app']           = ((x($arr,'app'))           ? notags(trim($arr['app']))           : '');

	if($arr['parent_uri'] === $arr['uri']) {
		$parent_id = 0;
		$allow_cid = $arr['allow_cid'];
		$allow_gid = $arr['allow_gid'];
		$deny_cid  = $arr['deny_cid'];
		$deny_gid  = $arr['deny_gid'];
	}
	else { 

		// find the parent and snarf the item id and ACL's
		// and anything else we need to inherit

		$r = q("SELECT * FROM `item` WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
			dbesc($arr['parent_uri']),
			intval($arr['uid'])
		);

		if(count($r)) {

			// is the new message multi-level threaded?
			// even though we don't support it now, preserve the info
			// and re-attach to the conversation parent.

			if($r[0]['uri'] != $r[0]['parent_uri']) {
				$arr['thr_parent'] = $arr['parent_uri'];
				$arr['parent_uri'] = $r[0]['parent_uri'];
			}

			$parent_id      = $r[0]['id'];
			$parent_deleted = $r[0]['deleted'];
			$allow_cid      = $r[0]['allow_cid'];
			$allow_gid      = $r[0]['allow_gid'];
			$deny_cid       = $r[0]['deny_cid'];
			$deny_gid       = $r[0]['deny_gid'];
			$arr['wall']    = $r[0]['wall'];
		}
		else {

			// Allow one to see reply tweets from status.net even when
			// we don't have or can't see the original post.

			if($force_parent) {
				logger('item_store: $force_parent=true, reply converted to top-level post.');
				$parent_id = 0;
				$arr['thr_parent'] = $arr['parent_uri'];
				$arr['parent_uri'] = $arr['uri'];
				$arr['gravity'] = 0;
			}
			else {
				logger('item_store: item parent was not found - ignoring item');
				return 0;
			}
		}
	}

	call_hooks('post_remote',$arr);

	dbesc_array($arr);

	logger('item_store: ' . print_r($arr,true), LOGGER_DATA);

	$r = dbq("INSERT INTO `item` (`" 
			. implode("`, `", array_keys($arr)) 
			. "`) VALUES ('" 
			. implode("', '", array_values($arr)) 
			. "')" );

	// find the item we just created

	$r = q("SELECT `id` FROM `item` WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
		$arr['uri'],           // already dbesc'd
		intval($arr['uid'])
	);
	if(! count($r)) {
		// This is not good, but perhaps we encountered a rare race/cache condition, so back off and try again. 
		sleep(3);
		$r = q("SELECT `id` FROM `item` WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
			$arr['uri'],           // already dbesc'd
			intval($arr['uid'])
		);
	}

	if(count($r)) {
		$current_post = $r[0]['id'];
		logger('item_store: created item ' . $current_post);
	}
	else {
		logger('item_store: could not locate created item');
		return 0;
	}

	if((! $parent_id) || ($arr['parent_uri'] === $arr['uri']))	
		$parent_id = $current_post;

 	if(strlen($allow_cid) || strlen($allow_gid) || strlen($deny_cid) || strlen($deny_gid))
		$private = 1;
	else
		$private = $arr['private']; 

	// Set parent id - and also make sure to inherit the parent's ACL's.

	$r = q("UPDATE `item` SET `parent` = %d, `allow_cid` = '%s', `allow_gid` = '%s',
		`deny_cid` = '%s', `deny_gid` = '%s', `private` = %d, `deleted` = %d WHERE `id` = %d LIMIT 1",
		intval($parent_id),
		dbesc($allow_cid),
		dbesc($allow_gid),
		dbesc($deny_cid),
		dbesc($deny_gid),
		intval($private),
		intval($parent_deleted),
		intval($current_post)
	);

	/**
	 * If this is now the last_child, force all _other_ children of this parent to *not* be last_child
	 */

	if($arr['last_child']) {
		$r = q("UPDATE `item` SET `last_child` = 0 WHERE `parent_uri` = '%s' AND `uid` = %d AND `id` != %d",
			dbesc($arr['uri']),
			intval($arr['uid']),
			intval($current_post)
		);
	}

	return $current_post;
}

function get_item_contact($item,$contacts) {
	if(! count($contacts) || (! is_array($item)))
		return false;
	foreach($contacts as $contact) {
		if($contact['id'] == $item['contact_id']) {
			return $contact;
			break; // NOTREACHED
		}
	}
	return false;
}


function dfrn_deliver($owner,$contact,$atom, $dissolve = false) {

	$a = get_app();

	if((! strlen($contact['issued_id'])) && (! $contact['duplex']) && (! ($owner['page_flags'] == PAGE_COMMUNITY)))
		return 3;

	$idtosend = $orig_id = (($contact['dfrn_id']) ? $contact['dfrn_id'] : $contact['issued_id']);

	if($contact['duplex'] && $contact['dfrn_id'])
		$idtosend = '0:' . $orig_id;
	if($contact['duplex'] && $contact['issued_id'])
		$idtosend = '1:' . $orig_id;		

	$rino = ((function_exists('mcrypt_encrypt')) ? 1 : 0);

	$rino_enable = get_config('system','rino_encrypt');

	if(! $rino_enable)
		$rino = 0;

	$url = $contact['notify'] . '&dfrn_id=' . $idtosend . '&dfrn_version=' . DFRN_PROTOCOL_VERSION . (($rino) ? '&rino=1' : '');

	logger('dfrn_deliver: ' . $url);

	$xml = fetch_url($url);

	$curl_stat = $a->get_curl_code();
	if(! $curl_stat)
		return(-1); // timed out

	logger('dfrn_deliver: ' . $xml);

	if(! $xml)
		return 3;

	if(strpos($xml,'<?xml') === false) {
		logger('dfrn_deliver: no valid XML returned');
		logger('dfrn_deliver: returned XML: ' . $xml, LOGGER_DATA);
		return 3;
	}

	$res = parse_xml_string($xml);

	if((intval($res->status) != 0) || (! strlen($res->challenge)) || (! strlen($res->dfrn_id)))
		return (($res->status) ? $res->status : 3);

	$postvars     = array();
	$sent_dfrn_id = hex2bin((string) $res->dfrn_id);
	$challenge    = hex2bin((string) $res->challenge);
	$dfrn_version = (float) (($res->dfrn_version) ? $res->dfrn_version : 2.0);
	$rino_allowed = ((intval($res->rino) === 1) ? 1 : 0);

	$final_dfrn_id = '';


	if(($contact['duplex'] && strlen($contact['pubkey'])) || ($owner['page_flags'] == PAGE_COMMUNITY)) {
		openssl_public_decrypt($sent_dfrn_id,$final_dfrn_id,$contact['pubkey']);
		openssl_public_decrypt($challenge,$postvars['challenge'],$contact['pubkey']);
	}
	else {
		openssl_private_decrypt($sent_dfrn_id,$final_dfrn_id,$contact['prvkey']);
		openssl_private_decrypt($challenge,$postvars['challenge'],$contact['prvkey']);
	}

	$final_dfrn_id = substr($final_dfrn_id, 0, strpos($final_dfrn_id, '.'));

	if(strpos($final_dfrn_id,':') == 1)
		$final_dfrn_id = substr($final_dfrn_id,2);

	if($final_dfrn_id != $orig_id) {
		logger('dfrn_deliver: wrong dfrn_id.');
		// did not decode properly - cannot trust this site 
		return 3;
	}

	$postvars['dfrn_id']      = $idtosend;
	$postvars['dfrn_version'] = DFRN_PROTOCOL_VERSION;
	if($dissolve)
		$postvars['dissolve'] = '1';


	if((($contact['rel']) && ($contact['rel'] != REL_FAN) && (! $contact['blocked'])) || ($owner['page_flags'] == PAGE_COMMUNITY)) {
		$postvars['data'] = $atom;
		$postvars['perm'] = 'rw';
	}
	else {
		$postvars['data'] = str_replace('<dfrn:comment-allow>1','<dfrn:comment-allow>0',$atom);
		$postvars['perm'] = 'r';
	}

	if($rino && $rino_allowed && (! $dissolve)) {
		$key = substr(random_string(),0,16);
		$data = bin2hex(aes_encrypt($postvars['data'],$key));
		$postvars['data'] = $data;
		logger('rino: sent key = ' . $key);	


		if($dfrn_version >= 2.1) {	
			if(($contact['duplex'] && strlen($contact['pubkey'])) || ($owner['page_flags'] == PAGE_COMMUNITY)) {
				openssl_public_encrypt($key,$postvars['key'],$contact['pubkey']);
			}
			else {
				openssl_private_encrypt($key,$postvars['key'],$contact['prvkey']);
			}
		}
		else {
			if(($contact['duplex'] && strlen($contact['prvkey'])) || ($owner['page_flags'] == PAGE_COMMUNITY)) {
				openssl_private_encrypt($key,$postvars['key'],$contact['prvkey']);
			}
			else {
				openssl_public_encrypt($key,$postvars['key'],$contact['pubkey']);
			}
		}

		logger('md5 rawkey ' . md5($postvars['key']));

		$postvars['key'] = bin2hex($postvars['key']);
	}

	logger('dfrn_deliver: ' . "SENDING: " . print_r($postvars,true), LOGGER_DATA);

	$xml = post_url($contact['notify'],$postvars);

	logger('dfrn_deliver: ' . "RECEIVED: " . $xml, LOGGER_DATA);

	$curl_stat = $a->get_curl_code();
	if((! $curl_stat) || (! strlen($xml)))
		return(-1); // timed out

	if(strpos($xml,'<?xml') === false) {
		logger('dfrn_deliver: phase 2: no valid XML returned');
		logger('dfrn_deliver: phase 2: returned XML: ' . $xml, LOGGER_DATA);
		return 3;
	}

	$res = parse_xml_string($xml);

	return $res->status; 
}


/**
 *
 * consume_feed - process atom feed and update anything/everything we might need to update
 *
 * $xml = the (atom) feed to consume - RSS isn't as fully supported but may work for simple feeds.
 *
 * $importer = the contact_record (joined to user_record) of the local user who owns this relationship.
 *             It is this person's stuff that is going to be updated.
 * $contact =  the person who is sending us stuff. If not set, we MAY be processing a "follow" activity
 *             from an external network and MAY create an appropriate contact record. Otherwise, we MUST 
 *             have a contact record.
 * $hub = should we find a hub declation in the feed, pass it back to our calling process, who might (or 
 *        might not) try and subscribe to it.
 *
 */

function consume_feed($xml,$importer,&$contact, &$hub, $datedir = 0, $secure_feed = false) {

	require_once('library/simplepie/simplepie.inc');

	$feed = new SimplePie();
	$feed->set_raw_data($xml);
	if($datedir)
		$feed->enable_order_by_date(true);
	else
		$feed->enable_order_by_date(false);
	$feed->init();

	if($feed->error())
		logger('consume_feed: Error parsing XML: ' . $feed->error());

	$permalink = $feed->get_permalink();

	// Check at the feed level for updated contact name and/or photo

	$name_updated  = '';
	$new_name = '';
	$photo_timestamp = '';
	$photo_url = '';
	$birthday = '';

	$hubs = $feed->get_links('hub');

	if(count($hubs))
		$hub = implode(',', $hubs);

	$rawtags = $feed->get_feed_tags( SIMPLEPIE_NAMESPACE_ATOM_10, 'author');
	if($rawtags) {
		$elems = $rawtags[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10];
		if($elems['name'][0]['attribs'][NAMESPACE_DFRN]['updated']) {
			$name_updated = $elems['name'][0]['attribs'][NAMESPACE_DFRN]['updated'];
			$new_name = $elems['name'][0]['data'];
		} 
		if((x($elems,'link')) && ($elems['link'][0]['attribs']['']['rel'] === 'photo') && ($elems['link'][0]['attribs'][NAMESPACE_DFRN]['updated'])) {
			$photo_timestamp = datetime_convert('UTC','UTC',$elems['link'][0]['attribs'][NAMESPACE_DFRN]['updated']);
			$photo_url = $elems['link'][0]['attribs']['']['href'];
		}

		if((x($rawtags[0]['child'], NAMESPACE_DFRN)) && (x($rawtags[0]['child'][NAMESPACE_DFRN],'birthday'))) {
			$birthday = datetime_convert('UTC','UTC', $rawtags[0]['child'][NAMESPACE_DFRN]['birthday'][0]['data']);
		}
	}

	if((is_array($contact)) && ($photo_timestamp) && (strlen($photo_url)) && ($photo_timestamp > $contact['avatar_date'])) {
		logger('consume_feed: Updating photo for ' . $contact['name']);
		require_once("Photo.php");
		$photo_failure = false;
		$have_photo = false;

		$r = q("SELECT `resource_id` FROM `photo` WHERE `contact_id` = %d AND `uid` = %d LIMIT 1",
			intval($contact['id']),
			intval($contact['uid'])
		);
		if(count($r)) {
			$resource_id = $r[0]['resource_id'];
			$have_photo = true;
		}
		else {
			$resource_id = photo_new_resource();
		}
			
		$img_str = fetch_url($photo_url,true);
		$img = new Photo($img_str);
		if($img->is_valid()) {
			if($have_photo) {
				q("DELETE FROM `photo` WHERE `resource_id` = '%s' AND `contact_id` = %d AND `uid` = %d",
					dbesc($resource_id),
					intval($contact['id']),
					intval($contact['uid'])
				);
			}
				
			$img->scaleImageSquare(175);
				
			$hash = $resource_id;
			$r = $img->store($contact['uid'], $contact['id'], $hash, basename($photo_url), 'Contact Photos', 4);
				
			$img->scaleImage(80);
			$r = $img->store($contact['uid'], $contact['id'], $hash, basename($photo_url), 'Contact Photos', 5);

			$img->scaleImage(48);
			$r = $img->store($contact['uid'], $contact['id'], $hash, basename($photo_url), 'Contact Photos', 6);

			$a = get_app();

			q("UPDATE `contact` SET `avatar_date` = '%s', `photo` = '%s', `thumb` = '%s', `micro` = '%s'  
				WHERE `uid` = %d AND `id` = %d LIMIT 1",
				dbesc(datetime_convert()),
				dbesc(z_path() . '/photo/' . $hash . '-4.jpg'),
				dbesc(z_path() . '/photo/' . $hash . '-5.jpg'),
				dbesc(z_path() . '/photo/' . $hash . '-6.jpg'),
				intval($contact['uid']),
				intval($contact['id'])
			);
		}
	}

	if((is_array($contact)) && ($name_updated) && (strlen($new_name)) && ($name_updated > $contact['name_date'])) {
		q("UPDATE `contact` SET `name` = '%s', `name_date` = '%s' WHERE `uid` = %d AND `id` = %d LIMIT 1",
			dbesc(notags(trim($new_name))),
			dbesc(datetime_convert()),
			intval($contact['uid']),
			intval($contact['id'])
		);
	}

	if(strlen($birthday)) {
		if(substr($birthday,0,4) != $contact['bdyear']) {
			logger('consume_feed: updating birthday: ' . $birthday);

			/**
			 *
			 * Add new birthday event for this person
			 *
			 * $bdtext is just a readable placeholder in case the event is shared
			 * with others. We will replace it during presentation to our $importer
			 * to contain a sparkle link and perhaps a photo. 
			 *
			 */
			 
			$bdtext = t('Birthday:') . ' [url=' . $contact['url'] . ']' . $contact['name'] . '[/url]' ;


			$r = q("INSERT INTO `event` (`uid`,`cid`,`created`,`edited`,`start`,`finish`,`desc`,`type`)
				VALUES ( %d, %d, '%s', '%s', '%s', '%s', '%s', '%s' ) ",
				intval($contact['uid']),
			 	intval($contact['id']),
				dbesc(datetime_convert()),
				dbesc(datetime_convert()),
				dbesc(datetime_convert('UTC','UTC', $birthday)),
				dbesc(datetime_convert('UTC','UTC', $birthday . ' + 1 day ')),
				dbesc($bdtext),
				dbesc('birthday')
			);
			

			// update bdyear

			q("UPDATE `contact` SET `bdyear` = '%s' WHERE `uid` = %d AND `id` = %d LIMIT 1",
				dbesc(substr($birthday,0,4)),
				intval($contact['uid']),
				intval($contact['id'])
			);

			// This function is called twice without reloading the contact
			// Make sure we only create one event. This is why &$contact 
			// is a reference var in this function

			$contact['bdyear'] = substr($birthday,0,4);
		}

	}


	// process any deleted entries

	$del_entries = $feed->get_feed_tags(NAMESPACE_TOMB, 'deleted-entry');
	if(is_array($del_entries) && count($del_entries)) {
		foreach($del_entries as $dentry) {
			$deleted = false;
			if(isset($dentry['attribs']['']['ref'])) {
				$uri = $dentry['attribs']['']['ref'];
				$deleted = true;
				if(isset($dentry['attribs']['']['when'])) {
					$when = $dentry['attribs']['']['when'];
					$when = datetime_convert('UTC','UTC', $when, 'Y-m-d H:i:s');
				}
				else
					$when = datetime_convert('UTC','UTC','now','Y-m-d H:i:s');
			}
			if($deleted && is_array($contact)) {
				$r = q("SELECT * FROM `item` WHERE `uri` = '%s' AND `uid` = %d AND `contact_id` = %d LIMIT 1",
					dbesc($uri),
					intval($importer['uid']),
					intval($contact['id'])
				);
				if(count($r)) {
					$item = $r[0];

					if(! $item['deleted'])
						logger('consume_feed: deleting item ' . $item['id'] . ' uri=' . $item['uri'], LOGGER_DEBUG);

					if($item['uri'] == $item['parent_uri']) {
						$r = q("UPDATE `item` SET `deleted` = 1, `edited` = '%s', `changed` = '%s',
							`body` = '', `title` = ''
							WHERE `parent_uri` = '%s' AND `uid` = %d",
							dbesc($when),
							dbesc(datetime_convert()),
							dbesc($item['uri']),
							intval($importer['uid'])
						);
					}
					else {
						$r = q("UPDATE `item` SET `deleted` = 1, `edited` = '%s', `changed` = '%s',
							`body` = '', `title` = '' 
							WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
							dbesc($when),
							dbesc(datetime_convert()),
							dbesc($uri),
							intval($importer['uid'])
						);
						if($item['last_child']) {
							// ensure that last_child is set in case the comment that had it just got wiped.
							q("UPDATE `item` SET `last_child` = 0, `changed` = '%s' WHERE `parent_uri` = '%s' AND `uid` = %d ",
								dbesc(datetime_convert()),
								dbesc($item['parent_uri']),
								intval($item['uid'])
							);
							// who is the last child now? 
							$r = q("SELECT `id` FROM `item` WHERE `parent_uri` = '%s' AND `type` != 'activity' AND `deleted` = 0 AND `uid` = %d 
								ORDER BY `created` DESC LIMIT 1",
									dbesc($item['parent_uri']),
									intval($importer['uid'])
							);
							if(count($r)) {
								q("UPDATE `item` SET `last_child` = 1 WHERE `id` = %d LIMIT 1",
									intval($r[0]['id'])
								);
							}
						}	
					}
				}	
			}
		}
	}

	// Now process the feed

	if($feed->get_item_quantity()) {		

		logger('consume_feed: feed item count = ' . $feed->get_item_quantity());

        // in inverse date order
		if ($datedir)
			$items = array_reverse($feed->get_items());
		else
			$items = $feed->get_items();


		foreach($items as $item) {

			$is_reply = false;		
			$item_id = $item->get_id();
			$rawthread = $item->get_item_tags( NAMESPACE_THREAD,'in-reply-to');
			if(isset($rawthread[0]['attribs']['']['ref'])) {
				$is_reply = true;
				$parent_uri = $rawthread[0]['attribs']['']['ref'];
			}

			if(($is_reply) && is_array($contact)) {

				// Have we seen it? If not, import it.
	
				$item_id  = $item->get_id();
				$datarray = get_atom_elements($feed,$item);

				if(! x($datarray,'author_name'))
					$datarray['author_name'] = $contact['name'];
				if(! x($datarray,'author_link'))
					$datarray['author_link'] = $contact['url'];
				if(! x($datarray,'author_avatar'))
					$datarray['author_avatar'] = $contact['thumb'];


				$r = q("SELECT `uid`, `last_child`, `edited`, `body` FROM `item` WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
					dbesc($item_id),
					intval($importer['uid'])
				);

				// Update content if 'updated' changes

				if(count($r)) {
					if((x($datarray,'edited') !== false) && (datetime_convert('UTC','UTC',$datarray['edited']) !== $r[0]['edited'])) {  
						$r = q("UPDATE `item` SET `body` = '%s', `edited` = '%s' WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
							dbesc($datarray['body']),
							dbesc(datetime_convert('UTC','UTC',$datarray['edited'])),
							dbesc($item_id),
							intval($importer['uid'])
						);
					}

					// update last_child if it changes

					$allow = $item->get_item_tags( NAMESPACE_DFRN, 'comment-allow');
					if(($allow) && ($allow[0]['data'] != $r[0]['last_child'])) {
						$r = q("UPDATE `item` SET `last_child` = 0, `changed` = '%s' WHERE `parent_uri` = '%s' AND `uid` = %d",
							dbesc(datetime_convert()),
							dbesc($parent_uri),
							intval($importer['uid'])
						);
						$r = q("UPDATE `item` SET `last_child` = %d , `changed` = '%s'  WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
							intval($allow[0]['data']),
							dbesc(datetime_convert()),
							dbesc($item_id),
							intval($importer['uid'])
						);
					}
					continue;
				}

				$force_parent = false;
				if($contact['network'] === 'stat') {
					$force_parent = true;
					if(strlen($datarray['title']))
						unset($datarray['title']);
					$r = q("UPDATE `item` SET `last_child` = 0, `changed` = '%s' WHERE `parent_uri` = '%s' AND `uid` = %d",
						dbesc(datetime_convert()),
						dbesc($parent_uri),
						intval($importer['uid'])
					);
					$datarray['last_child'] = 1;
				}

				if(($contact['network'] === 'feed') || (! strlen($contact['notify']))) {
					// one way feed - no remote comment ability
					$datarray['last_child'] = 0;
				}
				$datarray['parent_uri'] = $parent_uri;
				$datarray['uid'] = $importer['uid'];
				$datarray['contact_id'] = $contact['id'];
				if((activity_match($datarray['verb'],ACTIVITY_LIKE)) || (activity_match($datarray['verb'],ACTIVITY_DISLIKE))) {
					$datarray['type'] = 'activity';
					$datarray['gravity'] = GRAVITY_LIKE;
				}

				$r = item_store($datarray,$force_parent);
				continue;
			}

			else {

				// Head post of a conversation. Have we seen it? If not, import it.

				$item_id  = $item->get_id();

				$datarray = get_atom_elements($feed,$item);

				if(is_array($contact)) {
					if(! x($datarray,'author_name'))
						$datarray['author_name'] = $contact['name'];
					if(! x($datarray,'author_link'))
						$datarray['author_link'] = $contact['url'];
					if(! x($datarray,'author_avatar'))
						$datarray['author_avatar'] = $contact['thumb'];
				}

				if((x($datarray,'object_type')) && ($datarray['object_type'] === ACTIVITY_OBJ_EVENT)) {
					$ev = bbtoevent($datarray['body']);
					if(x($ev,'desc') && x($ev,'start')) {
						$ev['uid'] = $importer['uid'];
						$ev['uri'] = $item_id;
						$ev['edited'] = $datarray['edited'];

						if(is_array($contact))
							$ev['cid'] = $contact['id'];
						$r = q("SELECT * FROM `event` WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
							dbesc($item_id),
							intval($importer['uid'])
						);
						if(count($r))
							$ev['id'] = $r[0]['id'];
						$xyz = event_store($ev);
						continue;
					}
				}

				$r = q("SELECT `uid`, `last_child`, `edited`, `body` FROM `item` WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
					dbesc($item_id),
					intval($importer['uid'])
				);

				// Update content if 'updated' changes

				if(count($r)) {
					if((x($datarray,'edited') !== false) && (datetime_convert('UTC','UTC',$datarray['edited']) !== $r[0]['edited'])) {  
						$r = q("UPDATE `item` SET `body` = '%s', `edited` = '%s' WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
							dbesc($datarray['body']),
							dbesc(datetime_convert('UTC','UTC',$datarray['edited'])),
							dbesc($item_id),
							intval($importer['uid'])
						);
					}

					// update last_child if it changes

					$allow = $item->get_item_tags( NAMESPACE_DFRN, 'comment-allow');
					if($allow && $allow[0]['data'] != $r[0]['last_child']) {
						$r = q("UPDATE `item` SET `last_child` = %d , `changed` = '%s' WHERE `uri` = '%s' AND `uid` = %d LIMIT 1",
							intval($allow[0]['data']),
							dbesc(datetime_convert()),
							dbesc($item_id),
							intval($importer['uid'])
						);
					}
					continue;
				}

				if(activity_match($datarray['verb'],ACTIVITY_FOLLOW)) {
					logger('consume-feed: New follower');
					new_follower($importer,$contact,$datarray,$item);
					return;
				}
				if(activity_match($datarray['verb'],ACTIVITY_UNFOLLOW))  {
					lose_follower($importer,$contact,$datarray,$item);
					return;
				}
				if(! is_array($contact))
					return;

				if($contact['network'] === 'stat' || stristr($permalink,'twitter.com')) {
					if(strlen($datarray['title']))
						unset($datarray['title']);
					$datarray['last_child'] = 1;
				}

				if(($contact['network'] === 'feed') || (! strlen($contact['notify']))) {
					// one way feed - no remote comment ability
					$datarray['last_child'] = 0;
				}

				// This is my contact on another system, but it's really me.
				// Turn this into a wall post.

				if($contact['remote_self'])
					$datarray['wall'] = 1;

				$datarray['parent_uri'] = $item_id;
				$datarray['uid'] = $importer['uid'];
				$datarray['contact_id'] = $contact['id'];
				$r = item_store($datarray);
				continue;

			}
		}
	}
}

function new_follower($importer,$contact,$datarray,$item) {
	$url = notags(trim($datarray['author_link']));
	$name = notags(trim($datarray['author_name']));
	$photo = notags(trim($datarray['author_avatar']));

	$rawtag = $item->get_item_tags(NAMESPACE_ACTIVITY,'actor');
	if($rawtag && $rawtag[0]['child'][NAMESPACE_POCO]['preferredUsername'][0]['data'])
		$nick = $rawtag[0]['child'][NAMESPACE_POCO]['preferredUsername'][0]['data'];

	if(is_array($contact)) {
		if($contact['network'] == 'stat' && $contact['rel'] == REL_FAN) {
			$r = q("UPDATE `contact` SET `rel` = %d WHERE `id` = %d AND `uid` = %d LIMIT 1",
				intval(REL_BUD),
				intval($contact['id']),
				intval($importer['uid'])
			);
		}

		// send email notification to owner?
	}
	else {
	
		// create contact record

		$r = q("INSERT INTO `contact` ( `uid`, `created`, `url`, `name`, `nick`, `photo`, `network`, `rel`, 
			`blocked`, `readonly`, `pending`, `writable` )
			VALUES ( %d, '%s', '%s', '%s', '%s', '%s', '%s', %d, 0, 0, 1, 1 ) ",
			intval($importer['uid']),
			dbesc(datetime_convert()),
			dbesc($url),
			dbesc($name),
			dbesc($nick),
			dbesc($photo),
			dbesc('stat'),
			intval(REL_VIP)
		);
		$r = q("SELECT `id` FROM `contact` WHERE `uid` = %d AND `url` = '%s' AND `pending` = 1 AND `rel` = %d LIMIT 1",
				intval($importer['uid']),
				dbesc($url),
				intval(REL_VIP)
		);
		if(count($r))
				$contact_record = $r[0];

		// create notification	
		$hash = random_string();

		if(is_array($contact_record)) {
			$ret = q("INSERT INTO `intro` ( `uid`, `contact_id`, `blocked`, `knowyou`, `hash`, `datetime`)
				VALUES ( %d, %d, 0, 0, '%s', '%s' )",
				intval($importer['uid']),
				intval($contact_record['id']),
				dbesc($hash),
				dbesc(datetime_convert())
			);
		}
		$r = q("SELECT * FROM `user` WHERE `uid` = %d LIMIT 1",
			intval($importer['uid'])
		);
		$a = get_app();
		if(count($r)) {
			if(($r[0]['notify_flags'] & NOTIFY_INTRO) && ($r[0]['page_flags'] == PAGE_NORMAL)) {
				$email_tpl = get_intltext_template('follow_notify_eml.tpl');
				$email = replace_macros($email_tpl, array(
					'$requestor' => ((strlen($name)) ? $name : t('[Name Withheld]')),
					'$url' => $url,
					'$myname' => $r[0]['username'],
					'$siteurl' => z_path(),
					'$sitename' => $a->config['sitename']
				));
				$res = mail($r[0]['email'], 
					t("You have a new follower at ") . $a->config['sitename'],
					$email,
					'From: ' . t('Administrator') . '@' . $_SERVER['SERVER_NAME'] . "\n"
					. 'Content-type: text/plain; charset=UTF-8' . "\n"
					. 'Content-transfer-encoding: 8bit' );
			
			}
		}
	}
}

function lose_follower($importer,$contact,$datarray,$item) {

	if(($contact['rel'] == REL_BUD) || ($contact['rel'] == REL_FAN)) {
		q("UPDATE `contact` SET `rel` = %d WHERE `id` = %d LIMIT 1",
			intval(REL_FAN),
			intval($contact['id'])
		);
	}
	else {
		contact_remove($contact['id']);
	}
}


function subscribe_to_hub($url,$importer,$contact) {

	if(is_array($importer)) {
		$r = q("SELECT `nickname` FROM `user` WHERE `uid` = %d LIMIT 1",
			intval($importer['uid'])
		);
	}
	if(! count($r))
		return;

	$push_url = get_config('system','url') . '/pubsub/' . $r[0]['nickname'] . '/' . $contact['id'];

	// Use a single verify token, even if multiple hubs

	$verify_token = ((strlen($contact['hub_verify'])) ? $contact['hub_verify'] : random_string());

	$params= 'hub.mode=subscribe&hub.callback=' . urlencode($push_url) . '&hub.topic=' . urlencode($contact['poll']) . '&hub.verify=async&hub.verify_token=' . $verify_token;

	logger('subscribe_to_hub: subscribing ' . $contact['name'] . ' to hub ' . $url . ' with verifier ' . $verify_token);

	if(! strlen($contact['hub_verify'])) {
		$r = q("UPDATE `contact` SET `hub_verify` = '%s' WHERE `id` = %d LIMIT 1",
			dbesc($verify_token),
			intval($contact['id'])
		);
	}

	post_url($url,$params);			
	return;

}


function atom_author($tag,$name,$uri,$h,$w,$photo) {
	$o = '';
	if(! $tag)
		return $o;
	$name = xmlify($name);
	$uri = xmlify($uri);
	$h = intval($h);
	$w = intval($w);
	$photo = xmlify($photo);


	$o .= "<$tag>\r\n";
	$o .= "<name>$name</name>\r\n";
	$o .= "<uri>$uri</uri>\r\n";
	$o .= '<link rel="photo"  type="image/jpeg" media:width="' . $w . '" media:height="' . $h . '" href="' . $photo . '" />' . "\r\n";
	$o .= '<link rel="avatar" type="image/jpeg" media:width="' . $w . '" media:height="' . $h . '" href="' . $photo . '" />' . "\r\n";

	call_hooks('atom_author', $o);

	$o .= "</$tag>\r\n";
	return $o;
}

function atom_entry($item,$type,$author,$owner,$comment = false) {

	$a = get_app();

	if($item['deleted'])
		return '<at:deleted-entry ref="' . xmlify($item['uri']) . '" when="' . xmlify(datetime_convert('UTC','UTC',$item['edited'] . '+00:00',ATOM_TIME)) . '" />' . "\r\n";


	if($item['allow_cid'] || $item['allow_gid'] || $item['deny_cid'] || $item['deny_gid'])
		$body = fix_private_photos($item['body'],$owner['uid']);
	else
		$body = $item['body'];


	$o = "\r\n\r\n<entry>\r\n";

	if(is_array($author))
		$o .= atom_author('author',$author['name'],$author['url'],80,80,$author['thumb']);
	else
		$o .= atom_author('author',(($item['author_name']) ? $item['author_name'] : $item['name']),(($item['author_link']) ? $item['author_link'] : $item['url']),80,80,(($item['author_avatar']) ? $item['author_avatar'] : $item['thumb']));
	if(strlen($item['owner_name']))
		$o .= atom_author('dfrn:owner',$item['owner_name'],$item['owner_link'],80,80,$item['owner_avatar']);

	if($item['parent'] != $item['id'])
		$o .= '<thr:in-reply-to ref="' . xmlify($item['parent_uri']) . '" type="text/html" href="' .  xmlify(z_path() . '/display/' . $owner['nickname'] . '/' . $item['id']) . '" />' . "\r\n";

	$o .= '<id>' . xmlify($item['uri']) . '</id>' . "\r\n";
	$o .= '<title>' . xmlify($item['title']) . '</title>' . "\r\n";
	$o .= '<published>' . xmlify(datetime_convert('UTC','UTC',$item['created'] . '+00:00',ATOM_TIME)) . '</published>' . "\r\n";
	$o .= '<updated>' . xmlify(datetime_convert('UTC','UTC',$item['edited'] . '+00:00',ATOM_TIME)) . '</updated>' . "\r\n";
	$o .= '<dfrn:env>' . base64url_encode($body, true) . '</dfrn:env>' . "\r\n";
	$o .= '<content type="' . $type . '" >' . xmlify(($type === 'html') ? bbcode($body) : $body) . '</content>' . "\r\n";
	$o .= '<link rel="alternate" type="text/html" href="' . xmlify(z_path() . '/display/' . $owner['nickname'] . '/' . $item['id']) . '" />' . "\r\n";
	if($comment)
		$o .= '<dfrn:comment-allow>' . intval($item['last_child']) . '</dfrn:comment-allow>' . "\r\n";

	if($item['location']) {
		$o .= '<dfrn:location>' . xmlify($item['location']) . '</dfrn:location>' . "\r\n";
		$o .= '<poco:address><poco:formatted>' . xmlify($item['location']) . '</poco:formatted></poco:address>' . "\r\n";
	}

	if($item['coord'])
		$o .= '<georss:point>' . xmlify($item['coord']) . '</georss:point>' . "\r\n";

	if(($item['private']) || strlen($item['allow_cid']) || strlen($item['allow_gid']) || strlen($item['deny_cid']) || strlen($item['deny_gid']))
		$o .= '<dfrn:private>1</dfrn:private>' . "\r\n";

	if($item['extid'])
		$o .= '<dfrn:extid>' . $item['extid'] . '</dfrn:extid>' . "\r\n";

	if($item['app'])
		$o .= '<statusnet:notice_info local_id="' . $item['id'] . '" source="' . $item['app'] . '" ></statusnet:notice_info>';
	$verb = construct_verb($item);
	$o .= '<as:verb>' . xmlify($verb) . '</as:verb>' . "\r\n";
	$actobj = construct_activity_object($item);
	if(strlen($actobj))
		$o .= $actobj;
	$actarg = construct_activity_target($item);
	if(strlen($actarg))
		$o .= $actarg;

	$tags = item_getfeedtags($item);
	if(count($tags)) {
		foreach($tags as $t) {
			$o .= '<category scheme="X-DFRN:' . xmlify($t[0]) . ':' . xmlify($t[1]) . '" term="' . xmlify($t[2]) . '" />' . "\r\n";
		}
	}

	$o .= item_getfeedattach($item);

	$mentioned = get_mentions($item);
	if($mentioned)
		$o .= $mentioned;
	
	call_hooks('atom_entry', $o);

	$o .= '</entry>' . "\r\n";
	
	return $o;
}

function fix_private_photos($s,$uid) {
	$a = get_app();
	logger('fix_private_photos');

	if(preg_match("/\[img\](.*?)\[\/img\]/is",$s,$matches)) {
		$image = $matches[1];
		logger('fix_private_photos: found photo ' . $image);
		if(stristr($image ,z_path() . '/photo/')) {
			$i = basename($image);
			$i = str_replace('.jpg','',$i);
			$x = strpos($i,'-');
			if($x) {
				$res = substr($i,$x+1);
				$i = substr($i,0,$x);
				$r = q("SELECT * FROM `photo` WHERE `resource_id` = '%s' AND `scale` = %d AND `uid` = %d",
					dbesc($i),
					intval($res),
					intval($uid)
				);
				if(count($r)) {
					logger('replacing photo');
					$s = str_replace($image, 'data:image/jpg;base64,' . base64_encode($r[0]['data']), $s);
				}
			}
			logger('fix_private_photos: replaced: ' . $s, LOGGER_DATA);
		}	
	}
	return($s);
}



function item_getfeedtags($item) {
	$ret = array();
	$matches = false;
	$cnt = preg_match_all('|\#\[url\=(.*?)\](.*?)\[\/url\]|',$item['tag'],$matches);
	if($cnt) {
		for($x = 0; $x < count($matches); $x ++) {
			if($matches[1][$x])
				$ret[] = array('#',$matches[1][$x], $matches[2][$x]);
		}
	}
	$matches = false; 
	$cnt = preg_match_all('|\@\[url\=(.*?)\](.*?)\[\/url\]|',$item['tag'],$matches);
	if($cnt) {
		for($x = 0; $x < count($matches); $x ++) {
			if($matches[1][$x])
				$ret[] = array('#',$matches[1][$x], $matches[2][$x]);
		}
	} 
	return $ret;
}

function item_getfeedattach($item) {
	$ret = '';
	$arr = explode(',',$item['attach']);
	if(count($arr)) {
		foreach($arr as $r) {
			$matches = false;
			$cnt = preg_match('|\[attach\]href=\"(.*?)\" size=\"(.*?)\" type=\"(.*?)\" title=\"(.*?)\"\[\/attach\]|',$r,$matches);
			if($cnt) {
				$ret .= '<link rel="enclosure" href="' . xmlify($matches[1]) . '" type="' . xmlify($matches[3]) . '" ';
				if(intval($matches[2]))
					$ret .= 'size="' . intval($matches[2]) . '" ';
				if($matches[4] !== ' ')
					$ret .= 'title="' . xmlify(trim($matches[4])) . '" ';
				$ret .= ' />' . "\r\n";
			}
		}
	}
	return $ret;
}


	
function item_expire($uid,$days) {

	if((! $uid) || (! $days))
		return;

	$r = q("SELECT * FROM `item` 
		WHERE `uid` = %d 
		AND `created` < UTC_TIMESTAMP() - INTERVAL %d DAY 
		AND `id` = `parent` 
		AND `deleted` = 0",
		intval($uid),
		intval($days)
	);

	if(! count($r))
		return;
 
	logger('expire: # items=' . count($r) );

	foreach($r as $item) {

		// Only expire posts, not photos and photo comments

		if(strlen($item['resource_id']))
			continue;

		$r = q("UPDATE `item` SET `deleted` = 1, `edited` = '%s', `changed` = '%s' WHERE `id` = %d LIMIT 1",
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			intval($item['id'])
		);

		// kill the kids

		$r = q("UPDATE `item` SET `deleted` = 1, `edited` = '%s', `changed` = '%s' WHERE `parent_uri` = '%s' AND `uid` = %d ",
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			dbesc($item['parent_uri']),
			intval($item['uid'])
		);

	}

	proc_run('php',"include/notifier.php","expire","$uid");

}


function drop_items($items) {
	$uid = 0;

	if(count($items)) {
		foreach($items as $item) {
			$owner = drop_item($item,false);
			if($owner && ! $uid)
				$uid = $owner;
		}
	}

	// multiple threads may have been deleted, send an expire notification

	if($uid)
		proc_run('php',"include/notifier.php","expire","$uid");
}


function drop_item($id,$interactive = true) {

	$a = get_app();

	// locate item to be deleted

	$r = q("SELECT * FROM `item` WHERE `id` = %d LIMIT 1",
		intval($id)
	);

	if(! count($r)) {
		if(! $interactive)
			return 0;
		notice( t('Item not found.') . EOL);
		goaway(z_path() . '/' . $_SESSION['return_url']);
	}

	$item = $r[0];

	$owner = $item['uid'];

	// check if logged in user is either the author or owner of this item

	if((local_user() == $item['uid']) || (remote_user() == $item['contact_id'])) {

		// delete the item

		$r = q("UPDATE `item` SET `deleted` = 1, `body` = '', `edited` = '%s', `changed` = '%s' WHERE `id` = %d LIMIT 1",
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			intval($item['id'])
		);

		// If item is a link to a photo resource, nuke all the associated photos 
		// (visitors will not have photo resources)
		// This only applies to photos uploaded from the photos page. Photos inserted into a post do not
		// generate a resource_id and therefore aren't intimately linked to the item. 

		if(strlen($item['resource_id'])) {
			q("DELETE FROM `photo` WHERE `resource_id` = '%s' AND `uid` = %d ",
				dbesc($item['resource_id']),
				intval($item['uid'])
			);
			// ignore the result
		}

		// If item is a link to an event, nuke the event record.

		if(intval($item['event_id'])) {
			q("DELETE FROM `event` WHERE `id` = %d AND `uid` = %d LIMIT 1",
				intval($item['event_id']),
				intval($item['uid'])
			);
			// ignore the result
		}


		// If it's the parent of a comment thread, kill all the kids

		if($item['uri'] == $item['parent_uri']) {
			$r = q("UPDATE `item` SET `deleted` = 1, `edited` = '%s', `changed` = '%s', `body` = '' 
				WHERE `parent_uri` = '%s' AND `uid` = %d ",
				dbesc(datetime_convert()),
				dbesc(datetime_convert()),
				dbesc($item['parent_uri']),
				intval($item['uid'])
			);
			// ignore the result
		}
		else {
			// ensure that last_child is set in case the comment that had it just got wiped.
			q("UPDATE `item` SET `last_child` = 0, `changed` = '%s' WHERE `parent_uri` = '%s' AND `uid` = %d ",
				dbesc(datetime_convert()),
				dbesc($item['parent_uri']),
				intval($item['uid'])
			);
			// who is the last child now? 
			$r = q("SELECT `id` FROM `item` WHERE `parent_uri` = '%s' AND `type` != 'activity' AND `deleted` = 0 AND `uid` = %d ORDER BY `edited` DESC LIMIT 1",
				dbesc($item['parent_uri']),
				intval($item['uid'])
			);
			if(count($r)) {
				q("UPDATE `item` SET `last_child` = 1 WHERE `id` = %d LIMIT 1",
					intval($r[0]['id'])
				);
			}	
		}
		$drop_id = intval($item['id']);
			
		// send the notification upstream/downstream as the case may be

		if(! $interactive)
			return $owner;

		proc_run('php',"include/notifier.php","drop","$drop_id");
		goaway(z_path() . '/' . $_SESSION['return_url']);
		//NOTREACHED
	}
	else {
		if(! $interactive)
			return 0;
		notice( t('Permission denied.') . EOL);
		goaway(z_path() . '/' . $_SESSION['return_url']);
		//NOTREACHED
	}
	
}