<?php

require_once('include/security.php');
require_once('include/bbcode.php');
require_once('include/items.php');


function like_content(&$a) {

	if(! local_user() && ! remote_user()) {
		return;
	}

	$verb = notags(trim($_GET['verb']));

	if(! $verb)
		$verb = 'like';


	switch($verb) {
		case 'like':
			$activity = ACTIVITY_LIKE;
			break;
		case 'dislike':
			$activity = ACTIVITY_DISLIKE;
			break;
		case 'unlike':
			$activity = ACTIVITY_LIKE;
			break;
		case 'undislike':
			$activity = ACTIVITY_DISLIKE;
			break;
		default:
			return;
			break;
	}


	$item_id = (($a->argc > 1) ? notags(trim($a->argv[1])) : 0);

	logger('like: verb ' . $verb . ' item ' . $item_id);


	$r = q("SELECT * FROM `item` WHERE ( `id` = '%s' OR `uri` = '%s') AND `id` = `parent` LIMIT 1",
		dbesc($item_id),
		dbesc($item_id)
	);

	if(! $item_id || (! count($r))) {
		logger('like: no item ' . $item_id);
		return;
	}

	$item = $r[0];

	$owner_uid = $item['uid'];

	if(! can_write_wall($a,$owner_uid)) {
		return;
	}

	$r = q("SELECT `contact`.*, `user`.`nickname` FROM `contact` LEFT JOIN `user` ON `contact`.`uid` = `user`.`uid`
		WHERE `contact`.`self` = 1 AND `contact`.`uid` = %d LIMIT 1",
		intval($owner_uid)
	);
	if(count($r))
		$owner = $r[0];

	if(! $owner) {
		logger('like: no owner');
		return;
	}


	if((local_user()) && (local_user() == $owner_uid)) {
		$contact = $owner;
	}
	else {
		$r = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($_SESSION['visitor_id']),
			intval($owner_uid)
		);
		if(count($r))
			$contact = $r[0];
	}
	if(! $contact) {
		return;
	}

	$r = q("SELECT `id` FROM `item` WHERE `verb` = '%s' AND `deleted` = 0 
		AND `contact_id` = %d AND ( `parent` = '%s' OR `parent_uri` = '%s') LIMIT 1",
		dbesc($activity),
		intval($contact['id']),
		dbesc($item_id),
		dbesc($item_id)
	);
	if(count($r)) {
		// Already voted, undo it
		$r = q("UPDATE `item` SET `deleted` = 1, `changed` = '%s' WHERE `id` = %d LIMIT 1",
			dbesc(datetime_convert()),
			intval($r[0]['id'])
		);

		proc_run('php',"include/notifier.php","like","$post_id");
		return;
	}

	$uri = item_new_uri($a->get_hostname(),$owner_uid);

	$post_type = (($item['resource_id']) ? t('photo') : t('status'));
	$objtype = (($item['resource_id']) ? ACTIVITY_OBJ_PHOTO : ACTIVITY_OBJ_NOTE ); 
	$link = xmlify('<link rel="alternate" type="text/html" href="' . z_path() . '/display/' . $owner['nickname'] . '/' . $item['id'] . '" />' . "\n") ;
	$body = $item['body'];

	$obj = <<< EOT

	<object>
		<type>$objtype</type>
		<local>1</local>
		<id>{$item['uri']}</id>
		<link>$link</link>
		<title></title>
		<content>$body</content>
	</object>
EOT;
	if($verb === 'like')
		$bodyverb = t('%1$s likes %2$s\'s %3$s');
	if($verb === 'dislike')
		$bodyverb = t('%1$s doesn\'t like %2$s\'s %3$s');

	if(! isset($bodyverb))
			return; 

	$arr = array();

	$arr['uri'] = $uri;
	$arr['uid'] = $owner_uid;
	$arr['contact_id'] = $contact['id'];
	$arr['type'] = 'activity';
	$arr['wall'] = 1;
	$arr['gravity'] = GRAVITY_LIKE;
	$arr['parent'] = $item['id'];
	$arr['parent_uri'] = $item['uri'];
	$arr['owner_name'] = $owner['name'];
	$arr['owner_link'] = $owner['url'];
	$arr['owner_avatar'] = $owner['thumb'];
	$arr['author_name'] = $contact['name'];
	$arr['author_link'] = $contact['url'];
	$arr['author_avatar'] = $contact['thumb'];
	
	$ulink = '[url=' . $contact['url'] . ']' . $contact['name'] . '[/url]';
	$alink = '[url=' . $item['author_link'] . ']' . $item['author_name'] . '[/url]';
	$plink = '[url=' . z_path() . '/display/' . $owner['nickname'] . '/' . $item['id'] . ']' . $post_type . '[/url]';
	$arr['body'] =  sprintf( $bodyverb, $ulink, $alink, $plink );

	$arr['verb'] = $activity;
	$arr['object_type'] = $objtype;
	$arr['object'] = $obj;
	$arr['allow_cid'] = $item['allow_cid'];
	$arr['allow_gid'] = $item['allow_gid'];
	$arr['deny_cid'] = $item['deny_cid'];
	$arr['deny_gid'] = $item['deny_gid'];
	$arr['visible'] = 1;
	$arr['unseen'] = 1;
	$arr['last_child'] = 0;

	$post_id = item_store($arr);	

	if(! $item['visible']) {
		$r = q("UPDATE `item` SET `visible` = 1 WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($item['id']),
			intval($owner_uid)
		);
	}			

	$arr['id'] = $post_id;

	call_hooks('post_local_end', $arr);

	proc_run('php',"include/notifier.php","like","$post_id");

	return; // NOTREACHED
}