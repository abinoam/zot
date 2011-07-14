<?php

/**
 *
 * This is the POST destination for most all locally posted
 * text stuff. This function handles status, wall-to-wall status, 
 * local comments, and remote coments - that are posted on this site 
 * (as opposed to being delivered in a feed).
 * All of these become an "item" which is our basic unit of 
 * information.
 * Posts that originate externally or do not fall into the above 
 * posting categories go through item_store() instead of this function. 
 *
 */  

function item_post(&$a) {

	if((! local_user()) && (! remote_user()))
		return;

	require_once('include/security.php');

	$uid = local_user();

	if(x($_POST,'dropitems')) {
		require_once('include/items.php');
		$arr_drop = explode(',',$_POST['dropitems']);
		drop_items($arr_drop);
		$json = array('success' => 1);
		echo json_encode($json);
		killme();
	}

	call_hooks('post_local_start', $_POST);

	$parent = ((x($_POST,'parent')) ? intval($_POST['parent']) : 0);

	$parent_item = null;
	$parent_contact = null;

	if($parent) {
		$r = q("SELECT * FROM `item` WHERE `id` = %d LIMIT 1",
			intval($parent)
		);
		if(! count($r)) {
			notice( t('Unable to locate original post.') . EOL);
			if(x($_POST,'return')) 
				goaway(z_path() . "/" . $_POST['return'] );
			killme();
		}
		$parent_item = $r[0];
		if($parent_item['contact_id'] && $uid) {
			$r = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
				intval($parent_item['contact_id']),
				intval($uid)
			);
			if(count($r))
				$parent_contact = $r[0];
		}
	}

	$profile_uid = ((x($_POST,'profile_uid')) ? intval($_POST['profile_uid']) : 0);
	$post_id     = ((x($_POST['post_id']))    ? intval($_POST['post_id'])     : 0);
	$app         = ((x($_POST['source']))     ? strip_tags($_POST['source'])  : '');

	if(! can_write_wall($a,$profile_uid)) {
		notice( t('Permission denied.') . EOL) ;
		if(x($_POST,'return')) 
			goaway(z_path() . "/" . $_POST['return'] );
		killme();
	}


	// is this an edited post?

	$orig_post = null;

	if($post_id) {
		$i = q("SELECT * FROM `item` WHERE `uid` = %d AND `id` = %d LIMIT 1",
			intval($profile_uid),
			intval($post_id)
		);
		if(! count($i))
			killme();
		$orig_post = $i[0];
	}

	$user = null;

	$r = q("SELECT * FROM `user` WHERE `uid` = %d LIMIT 1",
		intval($profile_uid)
	);
	if(count($r))
		$user = $r[0];
	
	if($orig_post) {
		$str_group_allow   = $orig_post['allow_gid'];
		$str_contact_allow = $orig_post['allow_cid'];
		$str_group_deny    = $orig_post['deny_gid'];
		$str_contact_deny  = $orig_post['deny_cid'];
		$title             = $orig_post['title'];
		$location          = $orig_post['location'];
		$coord             = $orig_post['coord'];
		$verb              = $orig_post['verb'];
		$emailcc           = $orig_post['emailcc'];
		$app			   = $orig_post['app'];

		$body              = escape_tags(trim($_POST['body']));
		$private           = $orig_post['private'];
		$pubmail_enable    = $orig_post['pubmail'];
	}
	else {
		$str_group_allow   = perms2str($_POST['group_allow']);
		$str_contact_allow = perms2str($_POST['contact_allow']);
		$str_group_deny    = perms2str($_POST['group_deny']);
		$str_contact_deny  = perms2str($_POST['contact_deny']);
		$title             = notags(trim($_POST['title']));
		$location          = notags(trim($_POST['location']));
		$coord             = notags(trim($_POST['coord']));
		$verb              = notags(trim($_POST['verb']));
		$emailcc           = notags(trim($_POST['emailcc']));

		$body              = escape_tags(trim($_POST['body']));
		$private = ((strlen($str_group_allow) || strlen($str_contact_allow) || strlen($str_group_deny) || strlen($str_contact_deny)) ? 1 : 0);

		if(($parent_item) && 
			(($parent_item['private']) 
				|| strlen($parent_item['allow_cid']) 
				|| strlen($parent_item['allow_gid']) 
				|| strlen($parent_item['deny_cid']) 
				|| strlen($parent_item['deny_gid'])
			)) {
			$private = 1;
		}
	
		$pubmail_enable    = ((x($_POST,'pubmail_enable') && intval($_POST['pubmail_enable']) && (! $private)) ? 1 : 0);

		if(! strlen($body)) {
			info( t('Empty post discarded.') . EOL );
			if(x($_POST,'return')) 
				goaway(z_path() . "/" . $_POST['return'] );
			killme();
		}
	}

	// get contact info for poster

	$author = null;
	$self   = false;

	if(($_SESSION['uid']) && ($_SESSION['uid'] == $profile_uid)) {
		$self = true;
		$r = q("SELECT * FROM `contact` WHERE `uid` = %d AND `self` = 1 LIMIT 1",
			intval($_SESSION['uid'])
		);
	}
	else {
		if((x($_SESSION,'visitor_id')) && (intval($_SESSION['visitor_id']))) {
			$r = q("SELECT * FROM `contact` WHERE `id` = %d LIMIT 1",
				intval($_SESSION['visitor_id'])
			);
		}
	}

	if(count($r)) {
		$author = $r[0];
		$contact_id = $author['id'];
	}

	// get contact info for owner
	
	if($profile_uid == $_SESSION['uid']) {
		$contact_record = $author;
	}
	else {
		$r = q("SELECT * FROM `contact` WHERE `uid` = %d AND `self` = 1 LIMIT 1",
			intval($profile_uid)
		);
		if(count($r))
			$contact_record = $r[0];
	}

	$post_type = notags(trim($_POST['type']));

	if($post_type === 'net-comment') {
		if($parent_item !== null) {
			if($parent_item['wall'] == 1)
				$post_type = 'wall-comment';
			else
				$post_type = 'remote-comment';
		}
	}

	/**
	 *
	 * When a photo was uploaded into the message using the (profile wall) ajax 
	 * uploader, The permissions are initially set to disallow anybody but the
	 * owner from seeing it. This is because the permissions may not yet have been
	 * set for the post. If it's private, the photo permissions should be set
	 * appropriately. But we didn't know the final permissions on the post until
	 * now. So now we'll look for links of uploaded messages that are in the
	 * post and set them to the same permissions as the post itself.
	 *
	 */

	$match = null;

	if(preg_match_all("/\[img\](.*?)\[\/img\]/",$body,$match)) {
		$images = $match[1];
		if(count($images)) {
			foreach($images as $image) {
				if(! stristr($image,z_path() . '/photo/'))
					continue;
				$image_uri = substr($image,strrpos($image,'/') + 1);
				$image_uri = substr($image_uri,0, strpos($image_uri,'-'));
				if(! strlen($image_uri))
					continue;
				$srch = '<' . intval($profile_uid) . '>';
				$r = q("SELECT `id` FROM `photo` WHERE `allow_cid` = '%s' AND `allow_gid` = '' AND `deny_cid` = '' AND `deny_gid` = ''
					AND `resource_id` = '%s' AND `uid` = %d LIMIT 1",
					dbesc($srch),
					dbesc($image_uri),
					intval($profile_uid)
				);
				if(! count($r))
					continue;
 

				$r = q("UPDATE `photo` SET `allow_cid` = '%s', `allow_gid` = '%s', `deny_cid` = '%s', `deny_gid` = '%s'
					WHERE `resource_id` = '%s' AND `uid` = %d AND `album` = '%s' ",
					dbesc($str_contact_allow),
					dbesc($str_group_allow),
					dbesc($str_contact_deny),
					dbesc($str_group_deny),
					dbesc($image_uri),
					intval($profile_uid),
					dbesc( t('Wall Photos'))
				);
 
			}
		}
	}


	/**
	 * Next link in any attachment references we find in the post.
	 */

	$match = false;

	if(preg_match_all("/\[attachment\](.*?)\[\/attachment\]/",$body,$match)) {
		$attaches = $match[1];
		if(count($attaches)) {
			foreach($attaches as $attach) {
				$r = q("SELECT * FROM `attach` WHERE `uid` = %d AND `id` = %d LIMIT 1",
					intval($profile_uid),
					intval($attach)
				);				
				if(count($r)) {
					$r = q("UPDATE `attach` SET `allow_cid` = '%s', `allow_gid` = '%s', `deny_cid` = '%s', `deny_gid` = '%s'
						WHERE `uid` = %d AND `id` = %d LIMIT 1",
						intval($profile_uid),
						intval($attach)
					);
				}
			}
		}
	}

	/**
	 * Fold multi-line [code] sequences
	 */

	$body = preg_replace('/\[\/code\]\s*\[code\]/m',"\n",$body); 

	/**
	 * Look for any tags and linkify them
	 */

	$str_tags = '';
	$inform   = '';


	$tags = get_tags($body);

	/**
	 * add a statusnet style reply tag if the original post was from there
	 * and we are replying, and there isn't one already
	 */

	if(($parent_contact) && ($parent_contact['network'] === 'stat') 
		&& ($parent_contact['nick']) && (! in_array('@' . $parent_contact['nick'],$tags))) {
		$body = '@' . $parent_contact['nick'] . ' ' . $body;
		$tags[] = '@' . $parent_contact['nick'];
	}		

	if(count($tags)) {
		foreach($tags as $tag) {
			if(isset($profile))
				unset($profile);
			if(strpos($tag,'#') === 0) {
				if(strpos($tag,'[url='))
					continue;
				$basetag = str_replace('_',' ',substr($tag,1));
				$body = str_replace($tag,'#[url=' . z_path() . '/search?search=' . rawurlencode($basetag) . ']' . $basetag . '[/url]',$body);
				if(strlen($str_tags))
					$str_tags .= ',';
				$str_tags .= '#[url=' . z_path() . '/search?search=' . rawurlencode($basetag) . ']' . $basetag . '[/url]';
				continue;
			}
			if(strpos($tag,'@') === 0) {
				if(strpos($tag,'[url='))
					continue;
				$stat = false;
				$name = substr($tag,1);
				if((strpos($name,'@')) || (strpos($name,'http://'))) {
					$newname = $name;
					$links = @lrdd($name);
					if(count($links)) {
						foreach($links as $link) {
							if($link['@attributes']['rel'] === 'http://webfinger.net/rel/profile-page')
                    			$profile = $link['@attributes']['href'];
							if($link['@attributes']['rel'] === 'salmon') {
								if(strlen($inform))
									$inform .= ',';
                    			$inform .= 'url:' . str_replace(',','%2c',$link['@attributes']['href']);
							}
						}
					}
				}
				else {
					$newname = $name;
					$alias = '';
					if(strstr($name,'_') || strstr($name,' ')) {
						$newname = str_replace('_',' ',$name);
						$r = q("SELECT * FROM `contact` WHERE `name` = '%s' AND `uid` = %d LIMIT 1",
							dbesc($newname),
							intval($profile_uid)
						);
					}
					else {
						$r = q("SELECT * FROM `contact` WHERE `nick` = '%s' AND `uid` = %d LIMIT 1",
							dbesc($name),
							intval($profile_uid)
						);
					}
					if(count($r)) {
						$profile = $r[0]['url'];
						if($r[0]['network'] === 'stat') {
							$newname = $r[0]['nick'];
							$stat = true;
							if($r[0]['alias'])
								$alias = $r[0]['alias'];
						}
						else
							$newname = $r[0]['name'];
						if(strlen($inform))
							$inform .= ',';
						$inform .= 'cid:' . $r[0]['id'];
					}
				}
				if($profile) {
					$body = str_replace('@' . $name, '@' . '[url=' . $profile . ']' . $newname	. '[/url]', $body);
					$profile = str_replace(',','%2c',$profile);
					if(strlen($str_tags))
						$str_tags .= ',';
					$str_tags .= '@[url=' . $profile . ']' . $newname	. '[/url]';

					// Status.Net seems to require the numeric ID URL in a mention if the person isn't 
					// subscribed to you. But the nickname URL is OK if they are. Grrr. We'll tag both. 

					if(strlen($alias)) {
						if(strlen($str_tags))
							$str_tags .= ',';
						$str_tags .= '@[url=' . $alias . ']' . $newname	. '[/url]';
					}
				}
			}
		}
	}

	$attachments = '';
	$match = false;

	if(preg_match_all('/(\[attachment\]([0-9]+)\[\/attachment\])/',$body,$match)) {
		foreach($match[2] as $mtch) {
			$r = q("SELECT `id`,`filename`,`filesize`,`filetype` FROM `attach` WHERE `uid` = %d AND `id` = %d LIMIT 1",
				intval($profile_uid),
				intval($mtch)
			);
			if(count($r)) {
				if(strlen($attachments))
					$attachments .= ',';
				$attachments .= '[attach]href="' . z_path() . '/attach/' . $r[0]['id'] . '" size="' . $r[0]['filesize'] . '" type="' . $r[0]['filetype'] . '" title="' . (($r[0]['filename']) ? $r[0]['filename'] : ' ') . '"[/attach]'; 
			}
			$body = str_replace($match[1],'',$body);
		}
	}

	$wall = 0;

	if($post_type === 'wall' || $post_type === 'wall-comment')
		$wall = 1;

	if(! strlen($verb))
		$verb = ACTIVITY_POST ;

	$gravity = (($parent) ? 6 : 0 );
 
	$notify_type = (($parent) ? 'comment-new' : 'wall-new' );

	$uri = item_new_uri($a->get_hostname(),$profile_uid);

	$datarray = array();
	$datarray['uid']           = $profile_uid;
	$datarray['type']          = $post_type;
	$datarray['wall']          = $wall;
	$datarray['gravity']       = $gravity;
	$datarray['contact_id']    = $contact_id;
	$datarray['owner_name']    = $contact_record['name'];
	$datarray['owner_link']    = $contact_record['url'];
	$datarray['owner_avatar']  = $contact_record['thumb'];
	$datarray['author_name']   = $author['name'];
	$datarray['author_link']   = $author['url'];
	$datarray['author_avatar'] = $author['thumb'];
	$datarray['created']       = datetime_convert();
	$datarray['edited']        = datetime_convert();
	$datarray['received']      = datetime_convert();
	$datarray['changed']       = datetime_convert();
	$datarray['uri']           = $uri;
	$datarray['title']         = $title;
	$datarray['body']          = $body;
	$datarray['app']           = $app;
	$datarray['location']      = $location;
	$datarray['coord']         = $coord;
	$datarray['tag']           = $str_tags;
	$datarray['inform']        = $inform;
	$datarray['verb']          = $verb;
	$datarray['allow_cid']     = $str_contact_allow;
	$datarray['allow_gid']     = $str_group_allow;
	$datarray['deny_cid']      = $str_contact_deny;
	$datarray['deny_gid']      = $str_group_deny;
	$datarray['private']       = $private;
	$datarray['pubmail']       = $pubmail_enable;
	$datarray['attach']        = $attachments;

	/**
	 * These fields are for the convenience of plugins...
	 * 'self' if true indicates the owner is posting on their own wall
	 * If parent is 0 it is a top-level post.
	 */

	$datarray['parent']        = $parent;
	$datarray['self']          = $self;
	$datarray['prvnets']       = $user['prvnets'];

	if($orig_post)
		$datarray['edit']      = true;

	call_hooks('post_local',$datarray);


	if($orig_post) {
		$r = q("UPDATE `item` SET `body` = '%s', `edited` = '%s' WHERE `id` = %d AND `uid` = %d LIMIT 1",
			dbesc($body),
			dbesc(datetime_convert()),
			intval($post_id),
			intval($profile_uid)
		);

		proc_run('php', "include/notifier.php", 'edit_post', "$post_id");
		if((x($_POST,'return')) && strlen($_POST['return'])) {
			logger('return: ' . $_POST['return']);
			goaway(z_path() . "/" . $_POST['return'] );
		}
		killme();
	}
	else
		$post_id = 0;


	$r = q("INSERT INTO `item` (`uid`,`type`,`wall`,`gravity`,`contact_id`,`owner_name`,`owner_link`,`owner_avatar`, 
		`author_name`, `author_link`, `author_avatar`, `created`, `edited`, `received`, `changed`, `uri`, `title`, `body`, `app`, `location`, `coord`, 
		`tag`, `inform`, `verb`, `allow_cid`, `allow_gid`, `deny_cid`, `deny_gid`, `private`, `pubmail`, `attach` )
		VALUES( %d, '%s', %d, %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, '%s' )",
		intval($datarray['uid']),
		dbesc($datarray['type']),
		intval($datarray['wall']),
		intval($datarray['gravity']),
		intval($datarray['contact_id']),
		dbesc($datarray['owner_name']),
		dbesc($datarray['owner_link']),
		dbesc($datarray['owner_avatar']),
		dbesc($datarray['author_name']),
		dbesc($datarray['author_link']),
		dbesc($datarray['author_avatar']),
		dbesc($datarray['created']),
		dbesc($datarray['edited']),
		dbesc($datarray['received']),
		dbesc($datarray['changed']),
		dbesc($datarray['uri']),
		dbesc($datarray['title']),
		dbesc($datarray['body']),
		dbesc($datarray['app']),
		dbesc($datarray['location']),
		dbesc($datarray['coord']),
		dbesc($datarray['tag']),
		dbesc($datarray['inform']),
		dbesc($datarray['verb']),
		dbesc($datarray['allow_cid']),
		dbesc($datarray['allow_gid']),
		dbesc($datarray['deny_cid']),
		dbesc($datarray['deny_gid']),
		intval($datarray['private']),
		intval($datarray['pubmail']),
		dbesc($datarray['attach'])
	);

	$r = q("SELECT `id` FROM `item` WHERE `uri` = '%s' LIMIT 1",
		dbesc($datarray['uri']));
	if(count($r)) {
		$post_id = $r[0]['id'];
		logger('mod_item: saved item ' . $post_id);

		if($parent) {

			// This item is the last leaf and gets the comment box, clear any ancestors
			$r = q("UPDATE `item` SET `last_child` = 0, `changed` = '%s' WHERE `parent` = %d ",
				dbesc(datetime_convert()),
				intval($parent)
			);

			// Inherit ACL's from the parent item.

			$r = q("UPDATE `item` SET `allow_cid` = '%s', `allow_gid` = '%s', `deny_cid` = '%s', `deny_gid` = '%s', `private` = %d
				WHERE `id` = %d LIMIT 1",
				dbesc($parent_item['allow_cid']),
				dbesc($parent_item['allow_gid']),
				dbesc($parent_item['deny_cid']),
				dbesc($parent_item['deny_gid']),
				intval($parent_item['private']),
				intval($post_id)
			);

			// Send a notification email to the conversation owner, unless the owner is me and I wrote this item
			if(($user['notify_flags'] & NOTIFY_COMMENT) && ($contact_record != $author)) {
				push_lang($user['language']);
				require_once('bbcode.php');
				$from = $author['name'];

				// name of the automated email sender
				$msg['notificationfromname']	= stripslashes($datarray['author_name']);;
				// noreply address to send from
				$msg['notificationfromemail']	= t('noreply') . '@' . $a->get_hostname();				

				// text version
				// process the message body to display properly in text mode
				$msg['textversion']
					= html_entity_decode(strip_tags(bbcode(stripslashes($datarray['body']))), ENT_QUOTES, 'UTF-8');
				
				// html version
				// process the message body to display properly in text mode
				$msg['htmlversion']	
					= html_entity_decode(bbcode(stripslashes(str_replace(array("\\r\\n", "\\r","\\n\\n" ,"\\n"), "<br />\n",$datarray['body']))));

				// load the template for private message notifications
				$tpl = get_intltext_template('cmnt_received_html_body_eml.tpl');
				$email_html_body_tpl = replace_macros($tpl,array(
					'$username'     => $user['username'],
					'$sitename'		=> $a->config['sitename'],				// name of this site
					'$siteurl'		=> z_path(),					// descriptive url of this site
					'$thumb'		=> $author['thumb'],					// thumbnail url for sender icon
					'$email'		=> $importer['email'],					// email address to send to
					'$url'			=> $author['url'],						// full url for the site
					'$from'			=> $from,								// name of the person sending the message
					'$body'			=> $msg['htmlversion'],					// html version of the message
					'$display'		=> z_path() . '/display/' . $user['nickname'] . '/' . $post_id,
				));
			
				// load the template for private message notifications
				$tpl = get_intltext_template('cmnt_received_text_body_eml.tpl');
				$email_text_body_tpl = replace_macros($tpl,array(
					'$username'     => $user['username'],
					'$sitename'		=> $a->config['sitename'],				// name of this site
					'$siteurl'		=> z_path(),					// descriptive url of this site
					'$thumb'		=> $author['thumb'],					// thumbnail url for sender icon
					'$email'		=> $importer['email'],					// email address to send to
					'$url'			=> $author['url'],						// profile url for the author
					'$from'			=> $from,								// name of the person sending the message
					'$body'			=> $msg['textversion'],					// text version of the message
					'$display'		=> z_path() . '/display/' . $user['nickname'] . '/' . $post_id,
				));

				// use the EmailNotification library to send the message
				require_once("include/EmailNotification.php");
				EmailNotification::sendTextHtmlEmail(
					$msg['notificationfromname'],
					t("Administrator@") . $a->get_hostname(),
					t("noreply") . '@' . $a->get_hostname(),
					$user['email'],
					sprintf( t('%s commented on an item at %s'), $from , $a->config['sitename']),
					$email_html_body_tpl,
					$email_text_body_tpl
				);

				pop_lang();
			}
		}
		else {
			$parent = $post_id;

			// let me know if somebody did a wall-to-wall post on my profile

			if(($user['notify_flags'] & NOTIFY_WALL) && ($contact_record != $author)) {
				push_lang($user['language']);
				require_once('bbcode.php');
				$from = $author['name'];
							
				// name of the automated email sender
				$msg['notificationfromname']	= $from;
				// noreply address to send from
				$msg['notificationfromemail']	= t('noreply') . '@' . $a->get_hostname();				

				// text version
				// process the message body to display properly in text mode
				$msg['textversion']
					= html_entity_decode(strip_tags(bbcode(stripslashes($datarray['body']))), ENT_QUOTES, 'UTF-8');
				
				// html version
				// process the message body to display properly in text mode
				$msg['htmlversion']	
					= html_entity_decode(bbcode(stripslashes(str_replace(array("\\r\\n", "\\r","\\n\\n" ,"\\n"), "<br />\n",$datarray['body']))));

				// load the template for private message notifications
				$tpl = load_view_file('view/wall_received_html_body_eml.tpl');
				$email_html_body_tpl = replace_macros($tpl,array(
					'$username'     => $user['username'],
					'$sitename'		=> $a->config['sitename'],				// name of this site
					'$siteurl'		=> z_path(),					// descriptive url of this site
					'$thumb'		=> $author['thumb'],					// thumbnail url for sender icon
					'$url'			=> $author['url'],						// full url for the site
					'$from'			=> $from,								// name of the person sending the message
					'$body'			=> $msg['htmlversion'],					// html version of the message
					'$display'		=> z_path() . '/display/' . $user['nickname'] . '/' . $post_id,
				));
			
				// load the template for private message notifications
				$tpl = load_view_file('view/wall_received_text_body_eml.tpl');
				$email_text_body_tpl = replace_macros($tpl,array(
					'$username'     => $user['username'],
					'$sitename'		=> $a->config['sitename'],				// name of this site
					'$siteurl'		=> z_path(),					// descriptive url of this site
					'$thumb'		=> $author['thumb'],					// thumbnail url for sender icon
					'$url'			=> $author['url'],						// full url for the site
					'$from'			=> $from,								// name of the person sending the message
					'$body'			=> $msg['textversion'],					// text version of the message
					'$display'		=> z_path() . '/display/' . $user['nickname'] . '/' . $post_id,
				));

				// use the EmailNotification library to send the message
				require_once("include/EmailNotification.php");
				EmailNotification::sendTextHtmlEmail(
					$msg['notificationfromname'],
					t("Administrator@") . $a->get_hostname(),
					t("noreply") . '@' . $a->get_hostname(),
					$user['email'],
					sprintf( t('%s posted to your profile wall at %s') , $from , $a->config['sitename']),
					$email_html_body_tpl,
					$email_text_body_tpl
				);
				pop_lang();
			}
		}

		$r = q("UPDATE `item` SET `parent` = %d, `parent_uri` = '%s', `plink` = '%s', `changed` = '%s', `last_child` = 1, `visible` = 1
			WHERE `id` = %d LIMIT 1",
			intval($parent),
			dbesc(($parent == $post_id) ? $uri : $parent_item['uri']),
			dbesc(z_path() . '/display/' . $user['nickname'] . '/' . $post_id),
			dbesc(datetime_convert()),
			intval($post_id)
		);

		// photo comments turn the corresponding item visible to the profile wall
		// This way we don't see every picture in your new photo album posted to your wall at once.
		// They will show up as people comment on them.

		if(! $parent_item['visible']) {
			$r = q("UPDATE `item` SET `visible` = 1 WHERE `id` = %d LIMIT 1",
				intval($parent_item['id'])
			);
		}
	}
	else {
		logger('mod_item: unable to retrieve post that was just stored.');
		notify( t('System error. Post not saved.'));
		goaway(z_path() . "/" . $_POST['return'] );
		// NOTREACHED
	}

	proc_run('php', "include/notifier.php", $notify_type, "$post_id");

	$datarray['id']    = $post_id;
	$datarray['plink'] = z_path() . '/display/' . $user['nickname'] . '/' . $post_id;

	call_hooks('post_local_end', $datarray);

	if(strlen($emailcc) && $profile_uid == local_user()) {
		$erecips = explode(',', $emailcc);
		if(count($erecips)) {
			foreach($erecips as $recip) {
				$addr = trim($recip);
				if(! strlen($addr))
					continue;
				$disclaimer = '<hr />' . sprintf( t('This message was sent to you by %s, a member of the Friendika social network.'),$a->user['username']) 
					. '<br />';
				$disclaimer .= sprintf( t('You may visit them online at %s'), z_path() . '/profile/' . $a->user['nickname']) . EOL;
				$disclaimer .= t('Please contact the sender by replying to this post if you do not wish to receive these messages.') . EOL; 

				$subject  = '[Friendika]' . ' ' . sprintf( t('%s posted an update.'),$a->user['username']);
				$headers  = 'From: ' . $a->user['username'] . ' <' . $a->user['email'] . '>' . "\n";
				$headers .= 'MIME-Version: 1.0' . "\n";
				$headers .= 'Content-Type: text/html; charset=UTF-8' . "\n";
				$headers .= 'Content-Transfer-Encoding: 8bit' . "\n\n";
				$link = '<a href="' . z_path() . '/profile/' . $a->user['nickname'] . '"><img src="' . $author['thumb'] . '" alt="' . $a->user['username'] . '" /></a><br /><br />';
				$html    = prepare_body($datarray);
				$message = '<html><body>' . $link . $html . $disclaimer . '</body></html>';
				@mail($addr, $subject, $message, $headers);
			}
		}
	}

	logger('post_complete');
	if((x($_POST,'return')) && strlen($_POST['return'])) {
		logger('return: ' . $_POST['return']);
		goaway(z_path() . "/" . $_POST['return'] );
	}
	if($_POST['api_source'])
		return;
	$json = array('success' => 1);
	if(x($_POST,'jsreload') && strlen($_POST['jsreload']))
		$json['reload'] = z_path() . '/' . $_POST['jsreload'];

	logger('post_json: ' . print_r($json,true), LOGGER_DEBUG);

	echo json_encode($json);
	killme();
	// NOTREACHED
}





function item_content(&$a) {

	if((! local_user()) && (! remote_user()))
		return;

	require_once('include/security.php');

	if(($a->argc == 3) && ($a->argv[1] === 'drop') && intval($a->argv[2])) {
		require_once('include/items.php');
		drop_item($a->argv[2]);
	}
}
