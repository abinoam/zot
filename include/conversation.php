<?php

/**
 * Render actions localized
 */
function localize_item(&$item){
	
	if ($item['verb']=== ACTIVITY_LIKE || $item['verb']=== ACTIVITY_DISLIKE){

		$r = q("SELECT * from `item`,`contact` WHERE 
				`item`.`contact_id`=`contact`.`id` AND `item`.`uri`='%s';",
				 dbesc($item['parent_uri']));
		if(count($r)==0) return;
		$obj=$r[0];
		
		$author	 = '[url=' . $item['author_link'] . ']' . $item['author_name'] . '[/url]';
		$objauthor =  '[url=' . $obj['author_link'] . ']' . $obj['author_name'] . '[/url]';
		
		switch($obj['verb']){
			case ACTIVITY_POST:
				switch ($obj['object_type']){
					case ACTIVITY_OBJ_EVENT:
						$post_type = t('event');
						break;
					default:
						$post_type = t('status');
				}
				break;
			default:
				if($obj['resource_id']){
					$post_type = t('photo');
					$m=array();	preg_match("/\[url=([^]]*)\]/", $obj['body'], $m);
					$rr['plink'] = $m[1];
				} else {
					$post_type = t('status');
				}
		}
	
		$plink = '[url=' . $obj['plink'] . ']' . $post_type . '[/url]';
                
		switch($item['verb']){
			case ACTIVITY_LIKE :
				$bodyverb = t('%1$s likes %2$s\'s %3$s');
				break;
			case ACTIVITY_DISLIKE:
				$bodyverb = t('%1$s doesn\'t like %2$s\'s %3$s');
				break;
		}
		$item['body'] = sprintf($bodyverb, $author, $objauthor, $plink);
			
	}
	if ($item['verb']=== ACTIVITY_FRIEND){

		if ($item['object_type']=="" || $item['object_type']!== ACTIVITY_OBJ_PERSON) return;

		$Aname = $item['author_name'];
		$Alink = $item['author_link'];
		
		$xmlhead="<"."?xml version='1.0' encoding='UTF-8' ?".">";
		
		$obj = parse_xml_string($xmlhead.$item['object']);
		$links = parse_xml_string($xmlhead."<links>".unxmlify($obj->link)."</links>");
		
		$Bname = $obj->title;
		$Blink = ""; $Bphoto = "";
		foreach ($links->link as $l){
			$atts = $l->attributes();
			switch($atts['rel']){
				case "alternate": $Blink = $atts['href'];
				case "photo": $Bphoto = $atts['href'];
			}
			
		}
		
		$A = '[url=' . $Alink . ']' . $Aname . '[/url]';
		$B = '[url=' . $Blink . ']' . $Bname . '[/url]';
		if ($Bphoto!="") $Bphoto = '[url=' . $Blink . '][img]' . $Bphoto . '[/img][/url]';

		$item['body'] = sprintf( t('%1$s is now friends with %2$s'), $A, $B)."\n\n\n".$Bphoto;

	}
        
}

/**
 * "Render" a conversation or list of items for HTML display.
 * There are two major forms of display:
 *      - Sequential or unthreaded ("New Item View" or search results)
 *      - conversation view
 * The $mode parameter decides between the various renderings and also
 * figures out how to determine page owner and other contextual items 
 * that are based on unique features of the calling module.
 *
 */
function conversation(&$a, $items, $mode, $update) {

	require_once('bbcode.php');

	$profile_owner = 0;
	$page_writeable      = false;

	if($mode === 'network') {
		$profile_owner = local_user();
		$page_writeable = true;
	}

	if($mode === 'profile') {
		$profile_owner = $a->profile['profile_uid'];
		$page_writeable = can_write_wall($a,$profile_owner);
	}

	if($mode === 'notes') {
		$profile_owner = $a->profile['profile_uid'];
		$page_writeable = true;
	}

	if($mode === 'display') {
		$profile_owner = $a->profile['uid'];
		$page_writeable = can_write_wall($a,$profile_owner);
	}

	if($mode === 'community') {
		$profile_owner = 0;
		$page_writeable = false;
	}

	if($update)
		$return_url = $_SESSION['return_url'];
	else
		$return_url = $_SESSION['return_url'] = $a->cmd;

	load_contact_links(local_user());


	$cmnt_tpl    = get_markup_template('comment_item.tpl');
	$like_tpl    = get_markup_template('like.tpl');
	$noshare_tpl = get_markup_template('like_noshare.tpl');
	$tpl         = get_markup_template('wall_item.tpl');
	$wallwall    = get_markup_template('wallwall_item.tpl');
	$droptpl     = get_markup_template('wall_item_drop.tpl');
	$fakedrop    = get_markup_template('wall_fake_drop.tpl');

	$alike = array();
	$dlike = array();
	
	if(count($items)) {

		if($mode === 'network-new' || $mode === 'search' || $mode === 'community') {

			// "New Item View" on network page or search page results 
			// - just loop through the items and format them minimally for display

			$tpl = get_markup_template('search_item.tpl');

			foreach($items as $item) {

				$comment     = '';
				$owner_url   = '';
				$owner_photo = '';
				$owner_name  = '';
				$sparkle     = '';

				if($mode === 'search' || $mode === 'community') {
					if(((activity_match($item['verb'],ACTIVITY_LIKE)) || (activity_match($item['verb'],ACTIVITY_DISLIKE))) 
						&& ($item['id'] != $item['parent']))
						continue;
					$nickname = $item['nickname'];
				}
				else
					$nickname = $a->user['nickname'];
			
				$profile_name   = ((strlen($item['author_name']))   ? $item['author_name']   : $item['name']);

				$sp = false;
				$profile_link = best_link_url($item,$sp);
				if($sp)
					$sparkle = ' sparkle';
				if($profile_link === 'mailbox')
					$profile_link = '';


				$normalised = normalise_link((strlen($item['author_link'])) ? $item['author_link'] : $item['url']);
				if(($normalised != 'mailbox') && (x($a->contacts[$normalised])))
					$profile_avatar = $a->contacts[$normalised]['thumb'];
				else
					$profile_avatar = ((strlen($item['author_avatar'])) ? $item['author_avatar'] : $item['thumb']);

				$location = (($item['location']) ? '<a target="map" title="' . $item['location'] . '" href="http://maps.google.com/?q=' . urlencode($item['location']) . '">' . $item['location'] . '</a>' : '');
				$coord = (($item['coord']) ? '<a target="map" title="' . $item['coord'] . '" href="http://maps.google.com/?q=' . urlencode($item['coord']) . '">' . $item['coord'] . '</a>' : '');
				if($coord) {
					if($location)
						$location .= '<br /><span class="smalltext">(' . $coord . ')</span>';
					else
						$location = '<span class="smalltext">' . $coord . '</span>';
				}

				$drop = '';

				localize_item($item);
				if($mode === 'network-new')
					$t = $droptpl;
				else
					$t = $fakedrop;

				$drop = replace_macros($t,array('$id' => $item['id']));
				$lock = '<div class="wall-item-lock"></div>';
				$star = '';

				$body = prepare_body($item,true);
				
				$o .= replace_macros($tpl,array(
					'$id' => $item['item_id'],
					'$linktitle' => sprintf( t('View %s\'s profile'), $profile_name),
					'$profile_url' => $profile_link,
					'$item_photo_menu' => item_photo_menu($item),
					'$name' => $profile_name,
					'$sparkle' => $sparkle,
					'$lock' => $lock,
					'$thumb' => $profile_avatar,
					'$title' => $item['title'],
					'$body' => $body,
					'$ago' => (($item['app']) ? sprintf( t('%s from %s'),relative_date($item['created']),$item['app']) : relative_date($item['created'])),
					'$location' => $location,
					'$indent' => '',
					'$owner_url' => $owner_url,
					'$owner_photo' => $owner_photo,
					'$owner_name' => $owner_name,
					'$star' => $star,
					'$drop' => $drop,
					'$conv' => '<a href="' . z_path() . '/display/' . $nickname . '/' . $item['id'] . '">' . t('View in context') . '</a>'
				));

			}

			return $o;
		}




		// Normal View


		// Figure out how many comments each parent has
		// (Comments all have gravity of 6)
		// Store the result in the $comments array

		$comments = array();
		foreach($items as $item) {
			if((intval($item['gravity']) == 6) && ($item['id'] != $item['parent'])) {
				if(! x($comments,$item['parent']))
					$comments[$item['parent']] = 1;
				else
					$comments[$item['parent']] += 1;
			}
		}

		// map all the like/dislike activities for each parent item 
		// Store these in the $alike and $dlike arrays

		foreach($items as $item) {
			like_puller($a,$item,$alike,'like');
			like_puller($a,$item,$dlike,'dislike');
		}

		$comments_collapsed = false;
		$blowhard = 0;
		$blowhard_count = 0;

		foreach($items as $item) {

			$comment = '';
			$template = $tpl;
			$commentww = '';
			$sparkle = '';
			$owner_url = $owner_photo = $owner_name = '';

			// We've already parsed out like/dislike for special treatment. We can ignore them now

			if(((activity_match($item['verb'],ACTIVITY_LIKE)) 
				|| (activity_match($item['verb'],ACTIVITY_DISLIKE))) 
				&& ($item['id'] != $item['parent']))
				continue;

			$toplevelpost = (($item['id'] == $item['parent']) ? true : false);


			// Take care of author collapsing and comment collapsing
			// If a single author has more than 3 consecutive top-level posts, squash the remaining ones.
			// If there are more than two comments, squash all but the last 2.

			if($toplevelpost) {

				$item_writeable = (($item['writable'] || $item['self']) ? true : false);

				if($blowhard == $item['cid'] && (! $item['self']) && ($mode != 'profile') && ($mode != 'notes')) {
					$blowhard_count ++;
					if($blowhard_count == 3) {
						$o .= '<div class="icollapse-wrapper fakelink" id="icollapse-wrapper-' . $item['parent'] 
							. '" onclick="openClose(' . '\'icollapse-' . $item['parent'] . '\'); $(\'#icollapse-wrapper-' . $item['parent'] . '\').hide();" >' 
							. t('See more posts like this') . '</div>' . '<div class="icollapse" id="icollapse-' 
							. $item['parent'] . '" style="display: none;" >';
					}
				}
				else {
					$blowhard = $item['cid'];					
					if($blowhard_count >= 3)
						$o .= '</div>';
					$blowhard_count = 0;
				}

				$comments_seen = 0;
				$comments_collapsed = false;
			}
			else
				$comments_seen ++;


			$override_comment_box = ((($page_writeable) && ($item_writeable)) ? true : false);
			$show_comment_box = ((($page_writeable) && ($item_writeable) && ($comments_seen == $comments[$item['parent']])) ? true : false);

			if(($comments[$item['parent']] > 2) && ($comments_seen <= ($comments[$item['parent']] - 2)) && ($item['gravity'] == 6)) {
				if(! $comments_collapsed) {
					$o .= '<div class="ccollapse-wrapper fakelink" id="ccollapse-wrapper-' . $item['parent'] 
						. '" onclick="openClose(' . '\'ccollapse-' . $item['parent'] . '\'); $(\'#ccollapse-wrapper-' . $item['parent'] . '\').hide();" >' 
						. sprintf( t('See all %d comments'), $comments[$item['parent']]) . '</div>'
						. '<div class="ccollapse" id="ccollapse-' . $item['parent'] . '" style="display: none;" >';
					$comments_collapsed = true;
				}
			}
			if(($comments[$item['parent']] > 2) && ($comments_seen == ($comments[$item['parent']] - 1))) {
				$o .= '</div>';
			}

			$redirect_url = z_path() . '/redir/' . $item['cid'] ;

			$lock = ((($item['private']) || (($item['uid'] == local_user()) && (strlen($item['allow_cid']) || strlen($item['allow_gid']) 
				|| strlen($item['deny_cid']) || strlen($item['deny_gid']))))
				? '<div class="wall-item-lock"><img src="images/lock_icon.gif" class="lockview" alt="' . t('Private Message') . '" onclick="lockview(event,' . $item['id'] . ');" /></div>'
				: '<div class="wall-item-lock"></div>');


			// Top-level wall post not written by the wall owner (wall-to-wall)
			// First figure out who owns it. 

			$osparkle = '';

			if(($toplevelpost) && (! $item['self']) && ($mode !== 'profile')) {

				if($item['type'] === 'wall') {

					// On the network page, I am the owner. On the display page it will be the profile owner.
					// This will have been stored in $a->page_contact by our calling page.
					// Put this person on the left of the wall-to-wall notice.

					$owner_url = $a->page_contact['url'];
					$owner_photo = $a->page_contact['thumb'];
					$owner_name = $a->page_contact['name'];
					$template = $wallwall;
					$commentww = 'ww';	
				}
				if(($item['type'] === 'remote') && (strlen($item['owner_link'])) && ($item['owner_link'] != $item['author_link'])) {

					// Could be anybody. 

					$owner_url = $item['owner_link'];
					$owner_photo = $item['owner_avatar'];
					$owner_name = $item['owner_name'];
					$template = $wallwall;
					$commentww = 'ww';
					// If it is our contact, use a friendly redirect link
					if((link_compare($item['owner_link'],$item['url'])) 
						&& ($item['network'] === 'dfrn')) {
						$owner_url = $redirect_url;
						$osparkle = ' sparkle';
					}
				}
			}


			$likebuttons = '';

			if($page_writeable) {
				if($toplevelpost) {
					$likebuttons = replace_macros((($item['private'] || ($profile_owner != local_user())) ? $noshare_tpl : $like_tpl),array(
						'$id' => $item['id'],
						'$likethis' => t("I like this \x28toggle\x29"),
						'$nolike' => t("I don't like this \x28toggle\x29"),
						'$share' => t('Share'),
						'$wait' => t('Please wait') 
					));
				}

				if(($show_comment_box) || (($show_comment_box == false) && ($override_comment_box == false) && ($item['last_child']))) {
					$comment = replace_macros($cmnt_tpl,array(
						'$return_path' => '', 
						'$jsreload' => (($mode === 'display') ? $_SESSION['return_url'] : ''),
						'$type' => (($mode === 'profile') ? 'wall-comment' : 'net-comment'),
						'$id' => $item['item_id'],
						'$parent' => $item['parent'],
						'$profile_uid' =>  $profile_owner,
						'$mylink' => $a->contact['url'],
						'$mytitle' => t('This is you'),
						'$myphoto' => $a->contact['thumb'],
						'$comment' => t('Comment'),
						'$submit' => t('Submit'),
						'$ww' => (($mode === 'network') ? $commentww : '')
					));
				}
			}

			$edpost = (((($profile_owner == local_user()) && ($toplevelpost) && (intval($item['wall']) == 1)) || ($mode === 'notes'))
					? '<a class="editpost icon pencil" href="' . z_path() . '/editpost/' . $item['id'] 
						. '" title="' . t('Edit') . '"></a>'
					: '');


			$drop = '';
			$dropping = false;

			if((intval($item['contact_id']) && $item['contact_id'] == remote_user()) || ($item['uid'] == local_user()))
				$dropping = true;

            $drop = replace_macros((($dropping)? $droptpl : $fakedrop), array('$id' => $item['id'], '$select' => t('Select'), '$delete' => t('Delete')));

			$star = (($profile_owner == local_user() && $toplevelpost) ? '<a href="#" id="starred-' . $item['id'] . '" onclick="dostar(' . $item['id'] . '); return false;" class="star-item icon ' . (($item['starred']) ? 'starred' : 'unstarred') . '" title="' . t('toggle star status')  . '"></a>' : '');


			$photo = $item['photo'];
			$thumb = $item['thumb'];

			// Post was remotely authored.

			$diff_author    = ((link_compare($item['url'],$item['author_link'])) ? false : true);

			$profile_name   = (((strlen($item['author_name']))   && $diff_author) ? $item['author_name']   : $item['name']);

			$sp = false;
			$profile_link = best_link_url($item,$sp);
			if($sp)
				$sparkle = ' sparkle';

			if($profile_link === 'mailbox')
				$profile_link = '';

			$normalised = normalise_link((strlen($item['author_link'])) ? $item['author_link'] : $item['url']);
			if(($normalised != 'mailbox') && (x($a->contacts[$normalised])))
				$profile_avatar = $a->contacts[$normalised]['thumb'];
			else
				$profile_avatar = (((strlen($item['author_avatar'])) && $diff_author) ? $item['author_avatar'] : $thumb);





			$like    = ((x($alike,$item['id'])) ? format_like($alike[$item['id']],$alike[$item['id'] . '-l'],'like',$item['id']) : '');
			$dislike = ((x($dlike,$item['id'])) ? format_like($dlike[$item['id']],$dlike[$item['id'] . '-l'],'dislike',$item['id']) : '');

			$location = (($item['location']) ? '<a target="map" title="' . $item['location'] 
				. '" href="http://maps.google.com/?q=' . urlencode($item['location']) . '">' . $item['location'] . '</a>' : '');
			$coord = (($item['coord']) ? '<a target="map" title="' . $item['coord'] 
				. '" href="http://maps.google.com/?q=' . urlencode($item['coord']) . '">' . $item['coord'] . '</a>' : '');
			if($coord) {
				if($location)
					$location .= '<br /><span class="smalltext">(' . $coord . ')</span>';
				else
					$location = '<span class="smalltext">' . $coord . '</span>';
			}

			$indent = (($toplevelpost) ? '' : ' comment');

			if(strcmp(datetime_convert('UTC','UTC',$item['created']),datetime_convert('UTC','UTC','now - 12 hours')) > 0)
				$indent .= ' shiny'; 

			// 
			localize_item($item);

			// Build the HTML

			$body = prepare_body($item,true);


			$tmp_item = replace_macros($template,array(
				'$id' => $item['item_id'],
				'$linktitle' => sprintf( t('View %s\'s profile'), $profile_name),
				'$olinktitle' => sprintf( t('View %s\'s profile'), $owner_name),
				'$to' => t('to'),
				'$wall' => t('Wall-to-Wall'),
				'$vwall' => t('via Wall-To-Wall:'),
				'$profile_url' => $profile_link,
				'$item_photo_menu' => item_photo_menu($item),
				'$name' => $profile_name,
				'$thumb' => $profile_avatar,
				'$osparkle' => $osparkle,
				'$sparkle' => $sparkle,
				'$title' => $item['title'],
				'$body' => $body,
				'$ago' => ((($item['app']) && ($item['id'] == $item['parent'])) ? sprintf( t('%s from %s'),relative_date($item['created']),$item['app']) : relative_date($item['created'])),
				'$lock' => $lock,
				'$location' => $location,
				'$indent' => $indent,
				'$owner_url' => $owner_url,
				'$owner_photo' => $owner_photo,
				'$owner_name' => $owner_name,
				'$plink' => get_plink($item),
				'$edpost' => $edpost,
				'$star' => $star,
				'$drop' => $drop,
				'$vote' => $likebuttons,
				'$like' => $like,
				'$dislike' => $dislike,
				'$comment' => $comment
			));


			$arr = array('item' => $item, 'output' => $tmp_item);
			call_hooks('display_item', $arr);

			$o .= $arr['output'];

		}
	}


	// if author collapsing is in force but didn't get closed, close it off now.

	if($blowhard_count >= 3)
		$o .= '</div>';

	if($dropping)
		$o .= '<div id="item-delete-selected" class="fakelink" onclick="deleteCheckedItems();"><div id="item-delete-selected-icon" class="icon drophide" title="' . t('Delete Selected Items') . '" onmouseover="imgbright(this);" onmouseout="imgdull(this);" ></div><div id="item-delete-selected-desc" >' .  t('Delete Selected Items') . '</div></div><div id="item-delete-selected-end"></div>';

	return $o;
} 


if(! function_exists('load_contact_links')) {
function load_contact_links($uid) {

	$a = get_app();

	$ret = array();

	if(! $uid || x($a->contacts,'empty'))
		return;

	$r = q("SELECT `id`,`network`,`url`,`thumb` FROM `contact` WHERE `uid` = %d AND `self` = 0 AND `blocked` = 0 ",
			intval($uid)
	);
	if(count($r)) {
		foreach($r as $rr){
			$url = normalise_link($rr['url']);
			$ret[$url] = $rr;
		}
	}
	else 
		$ret['empty'] = true;	
	$a->contacts = $ret;
	return;		
}}


function best_link_url($item,&$sparkle) {

	$a = get_app();

	$best_url = '';
	$sparkle  = false;

	$clean_url = normalise_link($item['author_link']);

	if((local_user()) && (local_user() == $item['uid'])) {
		if(isset($a->contacts) && x($a->contacts,$clean_url)) {
			if($a->contacts[$clean_url]['network'] === NETWORK_DFRN) {
				$best_url = z_path() . '/redir/' . $a->contacts[$clean_url]['id'];
				$sparkle = true;
			}
			else
				$best_url = $a->contacts[$clean_url]['url'];
		}
	}
	if(! $best_url) {
		if(strlen($item['author_link']))
			$best_url = $item['author_link'];
		else
			$best_url = $item['url'];
	}

	return $best_url;
}


if(! function_exists('item_photo_menu')){
function item_photo_menu($item){
	$a = get_app();
	
	if (local_user() && (! count($a->contacts)))
		load_contact_links(local_user());

	$contact_url="";
	$pm_url="";
	$status_link="";
	$photos_link="";
	$posts_link="";

	$sparkle = false;
    $profile_link = best_link_url($item,$sparkle);
	if($profile_link === 'mailbox')
		$profile_link = '';

	if($sparkle) {
		$cid = intval(basename($profile_link));
		$status_link = $profile_link . "?url=status";
		$photos_link = $profile_link . "?url=photos";
		$profile_link = $profile_link . "?url=profile";
		$pm_url = z_path() . '/message/new/' . $cid;
	}
	else {
		if(local_user() && local_user() == $item['uid'] && link_compare($item['url'],$item['author_link'])) {
			$cid = $item['contact_id'];
		}		
		else {
			$cid = 0;
		}
	}
	if(($cid) && (! $item['self'])) {
		$contact_url = z_path() . '/contacts/' . $cid;
		$posts_link = z_path() . '/network/?cid=' . $cid;
	}

	$menu = Array(
		t("View status") => $status_link,
		t("View profile") => $profile_link,
		t("View photos") => $photos_link,		
		t("View recent") => $posts_link, 
		t("Edit contact") => $contact_url,
		t("Send PM") => $pm_url,
	);
	
	
	$args = array($item, &$menu);
	
	call_hooks('item_photo_menu', $args);
	
	$o = "";
	foreach($menu as $k=>$v){
		if ($v!="") $o .= "<li><a href='$v'>$k</a></li>\n";
	}
	return $o;
}}

if(! function_exists('like_puller')) {
function like_puller($a,$item,&$arr,$mode) {

	$url = '';
	$sparkle = '';
	$verb = (($mode === 'like') ? ACTIVITY_LIKE : ACTIVITY_DISLIKE);

	if((activity_match($item['verb'],$verb)) && ($item['id'] != $item['parent'])) {
		$url = $item['author_link'];
		if((local_user()) && (local_user() == $item['uid']) && ($item['network'] === 'dfrn') && (! $item['self']) && (link_compare($item['author_link'],$item['url']))) {
			$url = z_path() . '/redir/' . $item['contact_id'];
			$sparkle = ' class="sparkle" ';
		}
		if(! ((isset($arr[$item['parent'] . '-l'])) && (is_array($arr[$item['parent'] . '-l']))))
			$arr[$item['parent'] . '-l'] = array();
		if(! isset($arr[$item['parent']]))
			$arr[$item['parent']] = 1;
		else	
			$arr[$item['parent']] ++;
		$arr[$item['parent'] . '-l'][] = '<a href="'. $url . '"'. $sparkle .'>' . $item['author_name'] . '</a>';
	}
	return;
}}

// Format the like/dislike text for a profile item
// $cnt = number of people who like/dislike the item
// $arr = array of pre-linked names of likers/dislikers
// $type = one of 'like, 'dislike'
// $id  = item id
// returns formatted text

if(! function_exists('format_like')) {
function format_like($cnt,$arr,$type,$id) {
	$o = '';
	if($cnt == 1)
		$o .= (($type === 'like') ? sprintf( t('%s likes this.'), $arr[0]) : sprintf( t('%s doesn\'t like this.'), $arr[0])) . EOL ;
	else {
		$spanatts = 'class="fakelink" onclick="openClose(\'' . $type . 'list-' . $id . '\');"';
		$o .= (($type === 'like') ? 
					sprintf( t('<span  %1$s>%2$d people</span> like this.'), $spanatts, $cnt)
					 : 
					sprintf( t('<span  %1$s>%2$d people</span> don\'t like this.'), $spanatts, $cnt) ); 
		$o .= EOL ;
		$total = count($arr);
		if($total >= MAX_LIKERS)
			$arr = array_slice($arr, 0, MAX_LIKERS - 1);
		if($total < MAX_LIKERS)
			$arr[count($arr)-1] = t('and') . ' ' . $arr[count($arr)-1];
		$str = implode(', ', $arr);
		if($total >= MAX_LIKERS)
			$str .= sprintf( t(', and %d other people'), $total - MAX_LIKERS );
		$str = (($type === 'like') ? sprintf( t('%s like this.'), $str) : sprintf( t('%s don\'t like this.'), $str));
		$o .= "\t" . '<div id="' . $type . 'list-' . $id . '" style="display: none;" >' . $str . '</div>';
	}
	return $o;
}}


function status_editor($a,$x, $notes_cid = 0) {

	$o = '';
		
	$geotag = (($x['allow_location']) ? get_markup_template('jot_geotag.tpl') : '');

		$tpl = get_markup_template('jot-header.tpl');
	
		$a->page['htmlhead'] .= replace_macros($tpl, array(
			'$baseurl' => z_path(),
			'$geotag' => $geotag,
			'$nickname' => $x['nickname'],
			'$ispublic' => t('Visible to <strong>everybody</strong>'),
			'$linkurl' => t('Please enter a link URL:'),
			'$utubeurl' => t('Please enter a YouTube link:'),
			'$vidurl' => t("Please enter a video\x28.ogg\x29 link/URL:"),
			'$audurl' => t("Please enter an audio\x28.ogg\x29 link/URL:"),
			'$whereareu' => t('Where are you right now?'),
			'$title' => t('Enter a title for this item') 
		));


		$tpl = get_markup_template("jot.tpl");
		
		$jotplugins = '';
		$jotnets = '';

		$mail_disabled = ((function_exists('imap_open') && (! get_config('system','imap_disabled'))) ? 0 : 1);

		$mail_enabled = false;
		$pubmail_enabled = false;

		if(($x['is_owner']) && (! $mail_disabled)) {
			$r = q("SELECT * FROM `mailacct` WHERE `uid` = %d AND `server` != '' LIMIT 1",
				intval(local_user())
			);
			if(count($r)) {
				$mail_enabled = true;
				if(intval($r[0]['pubmail']))
					$pubmail_enabled = true;
			}
		}

		if($mail_enabled) {
	       $selected = (($pubmail_enabled) ? ' checked="checked" ' : '');
			$jotnets .= '<div class="profile-jot-net"><input type="checkbox" name="pubmail_enable"' . $selected . 'value="1" /> '
           	. t("Post to Email") . '</div>';
		}

		call_hooks('jot_tool', $jotplugins);
		call_hooks('jot_networks', $jotnets);

		if($notes_cid)
			$jotnets .= '<input type="hidden" name="contact_allow[]" value="' . $notes_cid .'" />';

		$tpl = replace_macros($tpl,array('$jotplugins' => $jotplugins));	

		$o .= replace_macros($tpl,array(
			'$return_path' => $a->cmd,
			'$action' => 'item',
			'$share' => (($x['button']) ? $x['button'] : t('Share')),
			'$upload' => t('Upload photo'),
			'$attach' => t('Attach file'),
			'$weblink' => t('Insert web link'),
			'$youtube' => t('Insert YouTube video'),
			'$video' => t('Insert Vorbis [.ogg] video'),
			'$audio' => t('Insert Vorbis [.ogg] audio'),
			'$setloc' => t('Set your location'),
			'$noloc' => t('Clear browser location'),
			'$title' => t('Set title'),
			'$wait' => t('Please wait'),
			'$permset' => t('Permission settings'),
			'$ptyp' => (($notes_cid) ? 'note' : 'wall'),
			'$content' => '',
			'$post_id' => '',
			'$baseurl' => z_path(),
			'$defloc' => $x['default_location'],
			'$visitor' => $x['visitor'],
			'$pvisit' => (($notes_cid) ? 'none' : $x['visitor']),
			'$emailcc' => t('CC: email addresses'),
			'$public' => t('Public post'),
			'$jotnets' => $jotnets,
			'$emtitle' => t('Example: bob@example.com, mary@example.com'),
			'$lockstate' => $x['lockstate'],
			'$acl' => $x['acl'],
			'$bang' => $x['bang'],
			'$profile_uid' => $x['profile_uid'],
		));

	return $o;
}
