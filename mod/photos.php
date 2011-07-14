<?php
require_once('include/Photo.php');
require_once('include/items.php');
require_once('include/acl_selectors.php');
require_once('include/bbcode.php');
require_once('include/security.php');

function photos_init(&$a) {


	if((get_config('system','block_public')) && (! local_user()) && (! remote_user())) {
		return;
	}
	$o = '';

	if($a->argc > 1) {
		$nick = $a->argv[1];
		$r = q("SELECT * FROM `user` WHERE `nickname` = '%s' AND `blocked` = 0 LIMIT 1",
			dbesc($nick)
		);

		if(! count($r))
			return;

		$a->data['user'] = $r[0];

		$sql_extra = permissions_sql($a->data['user']['uid']);

		$albums = q("SELECT distinct(`album`) AS `album` FROM `photo` WHERE `uid` = %d $sql_extra ",
			intval($a->data['user']['uid'])
		);

		if(count($albums)) {
			$a->data['albums'] = $albums;

			$o .= '<h4><a href="' . z_path() . '/profile/' . $a->data['user']['nickname'] . '">' . $a->data['user']['username'] . '</a></h4>';
			$o .= '<h4>' . '<a href="' . z_path() . '/photos/' . $a->data['user']['nickname'] . '">' . t('Photo Albums') . '</a></h4>';
		
			$o .= '<ul>';
			foreach($albums as $album) {

				// don't show contact photos. We once trasnlated this name, but then you could still access it under
				// a different language setting. Now we store the name in English and check in English (and translated for legacy albums).

				if((! strlen($album['album'])) || ($album['album'] === 'Contact Photos') || ($album['album'] === t('Contact Photos')))
					continue;
				$o .= '<li>' . '<a href="photos/' . $a->argv[1] . '/album/' . bin2hex($album['album']) . '" />' . $album['album'] . '</a></li>'; 
			}
			$o .= '</ul>';
		}

		if(! x($a->page,'aside'))
			$a->page['aside'] = '';
		$a->page['aside'] .= $o;


		$a->page['htmlhead'] .= "<script> var ispublic = '" . t('everybody') . "';" ;

		$a->page['htmlhead'] .= <<< EOT

		$(document).ready(function() {

			$('#contact_allow, #contact_deny, #group_allow, #group_deny').change(function() {
				var selstr;
				$('#contact_allow option:selected, #contact_deny option:selected, #group_allow option:selected, #group_deny option:selected').each( function() {
					selstr = $(this).text();
					$('#jot-perms-icon').removeClass('unlock').addClass('lock');
					$('#jot-public').hide();
				});
				if(selstr == null) { 
					$('#jot-perms-icon').removeClass('lock').addClass('unlock');
					$('#jot-public').show();
				}

			}).trigger('change');

		});

		</script>
EOT;
	}

	return;
}



function photos_post(&$a) {

	logger('mod/photos.php: photos_post(): begin' , 'LOGGER_DEBUG');

	foreach($_REQUEST AS $key => $val) {
		logger('mod/photos.php: photos_post(): $_REQUEST key: ' . $key . ' val: ' . $val , 'LOGGER_DEBUG');
	}

	foreach($_FILES AS $key => $val) {
		logger('mod/photos.php: photos_post(): $_FILES key: ' . $key . ' val: ' . $val , 'LOGGER_DEBUG');
	}

	$can_post  = false;
	$visitor   = 0;

	$page_owner_uid = $a->data['user']['uid'];
	$community_page = (($a->data['user']['page_flags'] == PAGE_COMMUNITY) ? true : false);

	if((local_user()) && (local_user() == $page_owner_uid))
		$can_post = true;
	else {
		if($community_page && remote_user()) {
			$r = q("SELECT `uid` FROM `contact` WHERE `blocked` = 0 AND `pending` = 0 AND `id` = %d AND `uid` = %d LIMIT 1",
				intval(remote_user()),
				intval($page_owner_uid)
			);
			if(count($r)) {
				$can_post = true;
				$visitor = remote_user();
			}
		}
	}

	if(! $can_post) {
		notice( t('Permission denied.') . EOL );
		killme();
	}

	$r = q("SELECT `contact`.*, `user`.`nickname` FROM `contact` LEFT JOIN `user` ON `user`.`uid` = `contact`.`uid` 
		WHERE `user`.`uid` = %d AND `self` = 1 LIMIT 1",
		intval($page_owner_uid)
	);

	if(! count($r)) {
		notice( t('Contact information unavailable') . EOL);
		logger('photos_post: unable to locate contact record for page owner. uid=' . $page_owner_uid);
		killme();
	}

	$owner_record = $r[0];	


	if(($a->argc > 3) && ($a->argv[2] === 'album')) {
		$album = hex2bin($a->argv[3]);

		if($album === t('Profile Photos') || $album === 'Contact Photos' || $album === t('Contact Photos')) {
			goaway(z_path() . '/' . $_SESSION['photo_return']);
			return; // NOTREACHED
		}

		$r = q("SELECT count(*) FROM `photo` WHERE `album` = '%s' AND `uid` = %d",
			dbesc($album),
			intval($page_owner_uid)
		);
		if(! count($r)) {
			notice( t('Album not found.') . EOL);
			goaway(z_path() . '/' . $_SESSION['photo_return']);
			return; // NOTREACHED
		}

		$newalbum = notags(trim($_POST['albumname']));
		if($newalbum != $album) {
			q("UPDATE `photo` SET `album` = '%s' WHERE `album` = '%s' AND `uid` = %d",
				dbesc($newalbum),
				dbesc($album),
				intval($page_owner_uid)
			);
			$newurl = str_replace(bin2hex($album),bin2hex($newalbum),$_SESSION['photo_return']);
			goaway(z_path() . '/' . $newurl);
			return; // NOTREACHED
		}


		if($_POST['dropalbum'] == t('Delete Album')) {

			$res = array();

			// get the list of photos we are about to delete

			if($visitor) {
				$r = q("SELECT distinct(`resource_id`) as `rid` FROM `photo` WHERE `contact_id` = %d AND `uid` = %d AND `album` = '%s'",
					intval($visitor),
					intval($page_owner_uid),
					dbesc($album)
				);
			}
			else {
				$r = q("SELECT distinct(`resource_id`) as `rid` FROM `photo` WHERE `uid` = %d AND `album` = '%s'",
					intval(local_user()),
					dbesc($album)
				);
			}
			if(count($r)) {
				foreach($r as $rr) {
					$res[] = "'" . dbesc($rr['rid']) . "'" ;
				}
			}
			else {
				goaway(z_path() . '/' . $_SESSION['photo_return']);
				return; // NOTREACHED
			}

			$str_res = implode(',', $res);

			// remove the associated photos

			q("DELETE FROM `photo` WHERE `resource_id` IN ( $str_res ) AND `uid` = %d",
				intval($page_owner_uid)
			);

			// find and delete the corresponding item with all the comments and likes/dislikes

			$r = q("SELECT `parent_uri` FROM `item` WHERE `resource_id` IN ( $str_res ) AND `uid` = %d",
				intval($page_owner_uid)
			);
			if(count($r)) {
				foreach($r as $rr) {
					q("UPDATE `item` SET `deleted` = 1, `changed` = '%s' WHERE `parent_uri` = '%s' AND `uid` = %d",
						dbesc(datetime_convert()),
						dbesc($rr['parent_uri']),
						intval($page_owner_uid)
					);

					$drop_id = intval($rr['id']);

					// send the notification upstream/downstream as the case may be

					if($rr['visible'])
						proc_run('php',"include/notifier.php","drop","$drop_id");
				}
			}
		}
		goaway(z_path() . '/photos/' . $a->data['user']['nickname']);
		return; // NOTREACHED
	}

	if(($a->argc > 2) && (x($_POST,'delete')) && ($_POST['delete'] == t('Delete Photo'))) {

		// same as above but remove single photo

		if($visitor) {
			$r = q("SELECT `id`, `resource_id` FROM `photo` WHERE `contact_id` = %d AND `uid` = %d AND `resource_id` = '%s' LIMIT 1",
				intval($visitor),
				intval($page_owner_uid),
				dbesc($a->argv[2])
			);
		}
		else {
			$r = q("SELECT `id`, `resource_id` FROM `photo` WHERE `uid` = %d AND `resource_id` = '%s' LIMIT 1",
				intval(local_user()),
				dbesc($a->argv[2])
			);
		}
		if(count($r)) {
			q("DELETE FROM `photo` WHERE `uid` = %d AND `resource_id` = '%s'",
				intval($page_owner_uid),
				dbesc($r[0]['resource_id'])
			);
			$i = q("SELECT * FROM `item` WHERE `resource_id` = '%s' AND `uid` = %d LIMIT 1",
				dbesc($r[0]['resource_id']),
				intval($page_owner_uid)
			);
			if(count($i)) {
				q("UPDATE `item` SET `deleted` = 1, `edited` = '%s', `changed` = '%s' WHERE `parent_uri` = '%s' AND `uid` = %d",
					dbesc(datetime_convert()),
					dbesc(datetime_convert()),
					dbesc($i[0]['uri']),
					intval($page_owner_uid)
				);

				$url = z_path();
				$drop_id = intval($i[0]['id']);

				if($i[0]['visible'])
					proc_run('php',"include/notifier.php","drop","$drop_id");
			}
		}

		goaway(z_path() . '/' . $_SESSION['photo_return']);
		return; // NOTREACHED
	}

	if(($a->argc > 2) && ((x($_POST,'desc') !== false) || (x($_POST,'newtag') !== false)) || (x($_POST,'albname') !== false)) {

		$desc        = ((x($_POST,'desc'))    ? notags(trim($_POST['desc']))    : '');
		$rawtags     = ((x($_POST,'newtag'))  ? notags(trim($_POST['newtag']))  : '');
		$item_id     = ((x($_POST,'item_id')) ? intval($_POST['item_id'])       : 0);
		$albname     = ((x($_POST,'albname')) ? notags(trim($_POST['albname'])) : '');
		$str_group_allow   = perms2str($_POST['group_allow']);
		$str_contact_allow = perms2str($_POST['contact_allow']);
		$str_group_deny    = perms2str($_POST['group_deny']);
		$str_contact_deny  = perms2str($_POST['contact_deny']);

		$resource_id = $a->argv[2];

		if(! strlen($albname))
			$albname = datetime_convert('UTC',date_default_timezone_get(),'now', 'Y');
		

		$p = q("SELECT * FROM `photo` WHERE `resource_id` = '%s' AND `uid` = %d ORDER BY `scale` DESC",
			dbesc($resource_id),
			intval($page_owner_uid)
		);
		if(count($p)) {
			$r = q("UPDATE `photo` SET `desc` = '%s', `album` = '%s', `allow_cid` = '%s', `allow_gid` = '%s', `deny_cid` = '%s', `deny_gid` = '%s' WHERE `resource_id` = '%s' AND `uid` = %d",
				dbesc($desc),
				dbesc($albname),
				dbesc($str_contact_allow),
				dbesc($str_group_allow),
				dbesc($str_contact_deny),
				dbesc($str_group_deny),
				dbesc($resource_id),
				intval($page_owner_uid)
			);
		}

		/* Don't make the item visible if the only change was the album name */

		$visibility = 0;
		if($p[0]['desc'] !== $desc || strlen($rawtags))
			$visibility = 1;
		
		if(! $item_id) {

			// Create item container

			$title = '';
			$basename = basename($filename);
			$uri = item_new_uri($a->get_hostname(),$page_owner_uid);

			$arr = array();

			$arr['uid']           = $page_owner_uid;
			$arr['uri']           = $uri;
			$arr['parent_uri']    = $uri; 
			$arr['type']          = 'photo';
			$arr['wall']          = 1;
			$arr['resource_id']   = $p[0]['resource_id'];
			$arr['contact_id']    = $owner_record['id'];
			$arr['owner_name']    = $owner_record['name'];
			$arr['owner_link']    = $owner_record['url'];
			$arr['owner_avatar']  = $owner_record['thumb'];
			$arr['author_name']   = $owner_record['name'];
			$arr['author_link']   = $owner_record['url'];
			$arr['author_avatar'] = $owner_record['thumb'];
			$arr['title']         = $title;
			$arr['allow_cid']     = $p[0]['allow_cid'];
			$arr['allow_gid']     = $p[0]['allow_gid'];
			$arr['deny_cid']      = $p[0]['deny_cid'];
			$arr['deny_gid']      = $p[0]['deny_gid'];
			$arr['last_child']    = 1;
			$arr['visible']       = $visibility;
			
			$arr['body']          = '[url=' . z_path() . '/photos/' . $a->data['user']['nickname'] . '/image/' . $p[0]['resource_id'] . ']' 
						. '[img]' . z_path() . '/photo/' . $p[0]['resource_id'] . '-' . $p[0]['scale'] . '.jpg' . '[/img]' 
						. '[/url]';
		
			$item_id = item_store($arr);

		}

		if($item_id) {
			$r = q("SELECT * FROM `item` WHERE `id` = %d AND `uid` = %d LIMIT 1",
				intval($item_id),
				intval($page_owner_uid)
			);
		}
		if(count($r)) {
			$old_tag    = $r[0]['tag'];
			$old_inform = $r[0]['inform'];
		}

		if(strlen($rawtags)) {

			$str_tags = '';
			$inform   = '';

			// if the new tag doesn't have a namespace specifier (@foo or #foo) give it a hashtag

			$x = substr($rawtags,0,1);
			if($x !== '@' && $x !== '#')
				$rawtags = '#' . $rawtags;

			$taginfo = array();
			$tags = get_tags($rawtags);

			if(count($tags)) {
				foreach($tags as $tag) {
					if(isset($profile))
						unset($profile);
					if(strpos($tag,'@') === 0) {
						$name = substr($tag,1);
						if((strpos($name,'@')) || (strpos($name,'http://'))) {
							$newname = $name;
							$links = @lrdd($name);
							if(count($links)) {
								foreach($links as $link) {
									if($link['@attributes']['rel'] === 'http://webfinger.net/rel/profile-page')
        		            			$profile = $link['@attributes']['href'];
									if($link['@attributes']['rel'] === 'salmon') {
										$salmon = '$url:' . str_replace(',','%sc',$link['@attributes']['href']);
										if(strlen($inform))
											$inform .= ',';
                    					$inform .= $salmon;
									}
								}
							}
							$taginfo[] = array($newname,$profile,$salmon);
						}
						else {
							$newname = $name;
							if(strstr($name,'_') || strstr($name,' ')) {
								$newname = str_replace('_',' ',$name);
								$r = q("SELECT * FROM `contact` WHERE `name` = '%s' AND `uid` = %d LIMIT 1",
									dbesc($newname),
									intval($page_owner_uid)
								);
							}
							else {
								$r = q("SELECT * FROM `contact` WHERE `nick` = '%s' AND `uid` = %d LIMIT 1",
									dbesc($name),
									intval($page_owner_uid)
								);
							}
							if(count($r)) {
								$newname = $r[0]['name'];
								$profile = $r[0]['url'];
								$notify = 'cid:' . $r[0]['id'];
								if(strlen($inform))
									$inform .= ',';
								$inform .= $notify;
							}
						}
						if($profile) {
							if(substr($notify,0,4) === 'cid:')
								$taginfo[] = array($newname,$profile,$notify,$r[0],'@[url=' . str_replace(',','%2c',$profile) . ']' . $newname	. '[/url]');
							else
								$taginfo[] = array($newname,$profile,$notify,null,$str_tags .= '@[url=' . $profile . ']' . $newname	. '[/url]');
							if(strlen($str_tags))
								$str_tags .= ',';
							$profile = str_replace(',','%2c',$profile);
							$str_tags .= '@[url=' . $profile . ']' . $newname	. '[/url]';
						}
					}
				}
			}

			$newtag = $old_tag;
			if(strlen($newtag) && strlen($str_tags)) 
				$newtag .= ',';
			$newtag .= $str_tags;

			$newinform = $old_inform;
			if(strlen($newinform) && strlen($inform))
				$newinform .= ',';
			$newinform .= $inform;

			$r = q("UPDATE `item` SET `tag` = '%s', `inform` = '%s', `edited` = '%s', `changed` = '%s' WHERE `id` = %d AND `uid` = %d LIMIT 1",
				dbesc($newtag),
				dbesc($newinform),
				dbesc(datetime_convert()),
				dbesc(datetime_convert()),
				intval($item_id),
				intval($page_owner_uid)
			);

			$best = 0;
			foreach($p as $scales) {
				if(intval($scales['scale']) == 2) {
					$best = 2;
					break;
				}
				if(intval($scales['scale']) == 4) {
					$best = 4;
					break;
				}
			}

			if(count($taginfo)) {
				foreach($taginfo as $tagged) {
		
					$uri = item_new_uri($a->get_hostname(),$page_owner_uid);

					$arr = array();

					$arr['uid']           = $page_owner_uid;
					$arr['uri']           = $uri;
					$arr['parent_uri']    = $uri;
					$arr['type']          = 'activity';
					$arr['wall']          = 1;
					$arr['contact_id']    = $owner_record['id'];
					$arr['owner_name']    = $owner_record['name'];
					$arr['owner_link']    = $owner_record['url'];
					$arr['owner_avatar']  = $owner_record['thumb'];
					$arr['author_name']   = $owner_record['name'];
					$arr['author_link']   = $owner_record['url'];
					$arr['author_avatar'] = $owner_record['thumb'];
					$arr['title']         = '';
					$arr['allow_cid']     = $p[0]['allow_cid'];
					$arr['allow_gid']     = $p[0]['allow_gid'];
					$arr['deny_cid']      = $p[0]['deny_cid'];
					$arr['deny_gid']      = $p[0]['deny_gid'];
					$arr['last_child']    = 1;
					$arr['visible']       = 1;
					$arr['verb']          = ACTIVITY_TAG;
					$arr['object_type']   = ACTIVITY_OBJ_PERSON;
					$arr['target_type']   = ACTIVITY_OBJ_PHOTO;
					$arr['tag']           = $tagged[4];
					$arr['inform']        = $tagged[2];

					$arr['body']          = '[url=' . $tagged[1] . ']' . $tagged[0] . '[/url]' . ' ' . t('was tagged in a') . ' ' . '[url=' . z_path() . '/photos/' . $owner_record['nickname'] . '/image/' . $p[0]['resource_id'] . ']' . t('photo') . '[/url]' . ' ' . t('by') . ' ' . '[url=' . $owner_record['url'] . ']' . $owner_record['name'] . '[/url]' ;
					$arr['body'] .= "\n\n" . '[url=' . z_path() . '/photos/' . $owner_record['nickname'] . '/image/' . $p[0]['resource_id'] . ']' . '[img]' . z_path() . "/photo/" . $p[0]['resource_id'] . '-' . $best . '.jpg' . '[/img][/url]' . "\n" ;

					$arr['object'] = '<object><type>' . ACTIVITY_OBJ_PERSON . '</type><title>' . $tagged[0] . '</title><id>' . $tagged[1] . '/' . $tagged[0] . '</id>';
					$arr['object'] .= '<link>' . xmlify('<link rel="alternate" type="text/html" href="' . $tagged[1] . '" />' . "\n");
					if($tagged[3])
						$arr['object'] .= xmlify('<link rel="photo" type="image/jpeg" href="' . $tagged[3]['photo'] . '" />' . "\n");
					$arr['object'] .= '</link></object>' . "\n";

					$arr['target'] = '<target><type>' . ACTIVITY_OBJ_PHOTO . '</type><title>' . $p[0]['desc'] . '</title><id>'
						. z_path() . '/photos/' . $owner_record['nickname'] . '/image/' . $p[0]['resource_id'] . '</id>';
					$arr['target'] .= '<link>' . xmlify('<link rel="alternate" type="text/html" href="' . z_path() . '/photos/' . $owner_record['nickname'] . '/image/' . $p[0]['resource_id'] . '" />' . "\n" . '<link rel="preview" type="image/jpeg" href="' . z_path() . "/photo/" . $p[0]['resource_id'] . '-' . $best . '.jpg' . '" />') . '</link></target>';

					$item_id = item_store($arr);
					if($item_id) {
						q("UPDATE `item` SET `plink` = '%s' WHERE `uid` = %d AND `id` = %d LIMIT 1",
							dbesc(z_path() . '/display/' . $owner_record['nickname'] . '/' . $item_id),
							intval($page_owner_uid),
							intval($item_id)
						);

						proc_run('php',"include/notifier.php","tag","$item_id");
					}
				}

			}

		}
		goaway(z_path() . '/' . $_SESSION['photo_return']);
		return; // NOTREACHED
	}


	/**
	 * default post action - upload a photo
	 */

	call_hooks('photo_post_init', $_POST);

	/**
	 * Determine the album to use
	 */

	$album    = notags(trim($_REQUEST['album']));
	$newalbum = notags(trim($_REQUEST['newalbum']));

	logger('mod/photos.php: photos_post(): album= ' . $album . ' newalbum= ' . $newalbum , 'LOGGER_DEBUG');

	if(! strlen($album)) {
		if(strlen($newalbum))
			$album = $newalbum;
		else
			$album = datetime_convert('UTC',date_default_timezone_get(),'now', 'Y');
	}

	/**
	 *
	 * We create a wall item for every photo, but we don't want to
	 * overwhelm the data stream with a hundred newly uploaded photos.
	 * So we will make the first photo uploaded to this album in the last several hours
	 * visible by default, the rest will become visible over time when and if
	 * they acquire comments, likes, dislikes, and/or tags 
	 *
	 */

	$r = q("SELECT * FROM `photo` WHERE `album` = '%s' AND `uid` = %d AND `created` > UTC_TIMESTAMP() - INTERVAL 3 HOUR ",
		dbesc($album),
		intval($page_owner_uid)
	);
	if((! count($r)) || ($album == t('Profile Photos')))
		$visible = 1;
	else
		$visible = 0;

	$str_group_allow   = perms2str(((is_array($_REQUEST['group_allow']))   ? $_REQUEST['group_allow']   : explode(',',$_REQUEST['group_allow'])));
	$str_contact_allow = perms2str(((is_array($_REQUEST['contact_allow'])) ? $_REQUEST['contact_allow'] : explode(',',$_REQUEST['contact_allow'])));
	$str_group_deny    = perms2str(((is_array($_REQUEST['group_deny']))    ? $_REQUEST['group_deny']    : explode(',',$_REQUEST['group_deny'])));
	$str_contact_deny  = perms2str(((is_array($_REQUEST['contact_deny']))  ? $_REQUEST['contact_deny']  : explode(',',$_REQUEST['contact_deny'])));

	$ret = array('src' => '', 'filename' => '', 'filesize' => 0);

	call_hooks('photo_post_file',$ret);

	if(x($ret,'src') && x($ret,'filesize')) {
		$src      = $ret['src'];
		$filename = $ret['filename'];
		$filesize = $ret['filesize'];
	}
	else {
		$src        = $_FILES['userfile']['tmp_name'];
		$filename   = basename($_FILES['userfile']['name']);
		$filesize   = intval($_FILES['userfile']['size']);
	}


	logger('photos: upload: received file: ' . $filename . ' as ' . $src . ' ' . $filesize . ' bytes', LOGGER_DEBUG);

	$maximagesize = get_config('system','maximagesize');

	if(($maximagesize) && ($filesize > $maximagesize)) {
		notice( t('Image exceeds size limit of ') . $maximagesize . EOL);
		@unlink($src);
		$foo = 0;
		call_hooks('photo_post_end',$foo);
		return;
	}

	if(! $filesize) {
		notice( t('Image file is empty.') . EOL);
		@unlink($src);
		$foo = 0;
		call_hooks('photo_post_end',$foo);
		return;
	}

	logger('mod/photos.php: photos_post(): loading the contents of ' . $src , 'LOGGER_DEBUG');

	$imagedata = @file_get_contents($src);
	$ph = new Photo($imagedata);

	if(! $ph->is_valid()) {
		logger('mod/photos.php: photos_post(): unable to process image' , 'LOGGER_DEBUG');
		notice( t('Unable to process image.') . EOL );
		@unlink($src);
		$foo = 0;
		call_hooks('photo_post_end',$foo);
		killme();
	}

	@unlink($src);

	$width  = $ph->getWidth();
	$height = $ph->getHeight();

	$smallest = 0;

	$photo_hash = photo_new_resource();

	$r = $ph->store($page_owner_uid, $visitor, $photo_hash, $filename, $album, 0 , 0, $str_contact_allow, $str_group_allow, $str_contact_deny, $str_group_deny);

	if(! $r) {
		logger('mod/photos.php: photos_post(): image store failed' , 'LOGGER_DEBUG');
		notice( t('Image upload failed.') . EOL );
		killme();
	}

	if($width > 640 || $height > 640) {
		$ph->scaleImage(640);
		$ph->store($page_owner_uid, $visitor, $photo_hash, $filename, $album, 1, 0, $str_contact_allow, $str_group_allow, $str_contact_deny, $str_group_deny);
		$smallest = 1;
	}

	if($width > 320 || $height > 320) {
		$ph->scaleImage(320);
		$ph->store($page_owner_uid, $visitor, $photo_hash, $filename, $album, 2, 0, $str_contact_allow, $str_group_allow, $str_contact_deny, $str_group_deny);
		$smallest = 2;
	}
	
	$basename = basename($filename);
	$uri = item_new_uri($a->get_hostname(), $page_owner_uid);

	// Create item container

	$arr = array();

	$arr['uid']           = $page_owner_uid;
	$arr['uri']           = $uri;
	$arr['parent_uri']    = $uri;
	$arr['type']          = 'photo';
	$arr['wall']          = 1;
	$arr['resource_id']   = $photo_hash;
	$arr['contact_id']    = $owner_record['id'];
	$arr['owner_name']    = $owner_record['name'];
	$arr['owner_link']    = $owner_record['url'];
	$arr['owner_avatar']  = $owner_record['thumb'];
	$arr['author_name']   = $owner_record['name'];
	$arr['author_link']   = $owner_record['url'];
	$arr['author_avatar'] = $owner_record['thumb'];
	$arr['title']         = '';
	$arr['allow_cid']     = $str_contact_allow;
	$arr['allow_gid']     = $str_group_allow;
	$arr['deny_cid']      = $str_contact_deny;
	$arr['deny_gid']      = $str_group_deny;
	$arr['last_child']    = 1;
	$arr['visible']       = $visible;
	$arr['body']          = '[url=' . z_path() . '/photos/' . $owner_record['nickname'] . '/image/' . $photo_hash . ']' 
				. '[img]' . z_path() . "/photo/{$photo_hash}-{$smallest}.jpg" . '[/img]' 
				. '[/url]';

	$item_id = item_store($arr);

	if($item_id) {
		q("UPDATE `item` SET `plink` = '%s' WHERE `uid` = %d AND `id` = %d LIMIT 1",
			dbesc(z_path() . '/display/' . $owner_record['nickname'] . '/' . $item_id),
			intval($page_owner_uid),
			intval($item_id)
		);
	}
	
	if($visible) 
		proc_run('php', "include/notifier.php", 'wall-new', $item_id);

	call_hooks('photo_post_end',intval($item_id));

	// addon uploaders should call "killme()" [e.g. exit] within the photo_post_end hook
	// if they do not wish to be redirected

	goaway(z_path() . '/' . $_SESSION['photo_return']);
	// NOTREACHED
}



function photos_content(&$a) {

	// URLs:
	// photos/name
	// photos/name/upload
	// photos/name/album/xxxxx
	// photos/name/album/xxxxx/edit
	// photos/name/image/xxxxx
	// photos/name/image/xxxxx/edit


	if((get_config('system','block_public')) && (! local_user()) && (! remote_user())) {
		notice( t('Public access denied.') . EOL);
		return;
	}


	require_once('include/bbcode.php');
	require_once('include/security.php');
	require_once('include/conversation.php');

	if(! x($a->data,'user')) {
		notice( t('No photos selected') . EOL );
		return;
	}

	$_SESSION['photo_return'] = $a->cmd;

	//
	// Parse arguments 
	//

	if($a->argc > 3) {
		$datatype = $a->argv[2];
		$datum = $a->argv[3];
	}
	elseif(($a->argc > 2) && ($a->argv[2] === 'upload'))
		$datatype = 'upload';
	else
		$datatype = 'summary';

	if($a->argc > 4)
		$cmd = $a->argv[4];
	else
		$cmd = 'view';

	//
	// Setup permissions structures
	//

	$can_post       = false;
	$visitor        = 0;
	$contact        = null;
	$remote_contact = false;

	$owner_uid = $a->data['user']['uid'];

	$community_page = (($a->data['user']['page_flags'] == PAGE_COMMUNITY) ? true : false);

	if((local_user()) && (local_user() == $owner_uid))
		$can_post = true;
	else {
		if($community_page && remote_user()) {
			$r = q("SELECT `uid` FROM `contact` WHERE `blocked` = 0 AND `pending` = 0 AND `id` = %d AND `uid` = %d LIMIT 1",
				intval(remote_user()),
				intval($owner_uid)
			);
			if(count($r)) {
				$can_post = true;
				$contact  = $r[0];
				$remote_contact = true;
				$visitor = remote_user();
			}
		}
	}

	// perhaps they're visiting - but not a community page, so they wouldn't have write access

	if(remote_user() && (! $visitor)) {
		$contact_id = $_SESSION['visitor_id'];
		$groups = init_groups_visitor($contact_id);
		$r = q("SELECT * FROM `contact` WHERE `blocked` = 0 AND `pending` = 0 AND `id` = %d AND `uid` = %d LIMIT 1",
			intval(remote_user()),
			intval($owner_uid)
		);
		if(count($r)) {
			$contact = $r[0];
			$remote_contact = true;
		}
	}

	if(! $remote_contact) {
		if(local_user()) {
			$contact_id = $_SESSION['cid'];
			$contact = $a->contact;
		}
	}

	if($a->data['user']['hidewall'] && (local_user() != $owner_uid) && (! $remote_contact)) {
		notice( t('Access to this item is restricted.') . EOL);
		return;
	}

	$sql_extra = permissions_sql($owner_uid,$remote_contact,$groups);

	$o = "";

	// tabs
	$tpl = get_markup_template('profile_tabs.tpl');
	$_is_owner = (local_user() && (local_user() == $owner_uid));
	$o .= replace_macros($tpl,array(
		'$url' => z_path() . '/profile/' .$a->data['user']['nickname'],
		'$phototab' => z_path() . '/photos/' . $a->data['user']['nickname'],
		'$status' => t('Status'),
		'$profile' => t('Profile'),
		'$photos' => t('Photos'),
		'$events' => (($_is_owner) ? t('Events') : ''),
		'$notes' => (($_is_owner) ? 	t('Personal Notes') : ''),
		'$activetab' => "photos",
	));	

	//
	// dispatch request
	//


	if($datatype === 'upload') {
		if(! ($can_post)) {
			notice( t('Permission denied.'));
			return;
		}
		$albumselect = '<select id="photos-upload-album-select" name="album" size="4">';

		$albumselect .= '<option value="" selected="selected" >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>';
		if(count($a->data['albums'])) {
			foreach($a->data['albums'] as $album) {
				if(($album['album'] === '') || ($album['album'] === 'Contact Photos') || ($album['album'] === t('Contact Photos')))
					continue;
				$albumselect .= '<option value="' . $album['album'] . '">' . $album['album'] . '</option>';
			}
		}

		$celeb = ((($a->user['page_flags'] == PAGE_SOAPBOX) || ($a->user['page_flags'] == PAGE_COMMUNITY)) ? true : false);

		$albumselect .= '</select>';

		$uploader = '';

		$ret = array('post_url' => z_path() . '/photos/' . $a->data['user']['nickname'],
				'addon_text' => $uploader,
				'default_upload' => true);


		call_hooks('photo_upload_form',$ret);

		$default_upload = '<input type="file" name="userfile" /> 	<div class="photos-upload-submit-wrapper" >
		<input type="submit" name="submit" value="' . t('Submit') . '" id="photos-upload-submit" /> </div>';


 

		$tpl = get_markup_template('photos_upload.tpl');
		$o .= replace_macros($tpl,array(
			'$pagename' => t('Upload Photos'),
			'$sessid' => session_id(),
			'$nickname' => $a->data['user']['nickname'],
			'$newalbum' => t('New album name: '),
			'$existalbumtext' => t('or existing album name: '),
			'$albumselect' => $albumselect,
			'$permissions' => t('Permissions'),
			'$aclselect' => (($visitor) ? '' : populate_acl($a->user, $celeb)),
			'$uploader' => $ret['addon_text'],
			'$default' => (($ret['default_upload']) ? $default_upload : ''),
			'$uploadurl' => $ret['post_url']

		));

		return $o; 
	}

	if($datatype === 'album') {

		$album = hex2bin($datum);

		$r = q("SELECT `resource_id`, max(`scale`) AS `scale` FROM `photo` WHERE `uid` = %d AND `album` = '%s' 
			$sql_extra GROUP BY `resource_id`",
			intval($owner_uid),
			dbesc($album)
		);
		if(count($r)) {
			$a->set_pager_total(count($r));
			$a->set_pager_itemspage(20);
		}

		$r = q("SELECT `resource_id`, `id`, `filename`, max(`scale`) AS `scale`, `desc` FROM `photo` WHERE `uid` = %d AND `album` = '%s' 
			$sql_extra GROUP BY `resource_id` ORDER BY `created` DESC LIMIT %d , %d",
			intval($owner_uid),
			dbesc($album),
			intval($a->pager['start']),
			intval($a->pager['itemspage'])
		);

		$o .= '<h3>' . $album . '</h3>';
		
		if($cmd === 'edit') {		
			if(($album !== t('Profile Photos')) && ($album !== 'Contact Photos') && ($album !== t('Contact Photos'))) {
				if($can_post) {
					$edit_tpl = get_markup_template('album_edit.tpl');
					$o .= replace_macros($edit_tpl,array(
						'$nametext' => t('New album name: '),
						'$nickname' => $a->data['user']['nickname'],
						'$album' => $album,
						'$hexalbum' => bin2hex($album),
						'$submit' => t('Submit'),
						'$dropsubmit' => t('Delete Album')
					));
				}
			}
		}
		else {
			if(($album !== t('Profile Photos')) && ($album !== 'Contact Photos') && ($album !== t('Contact Photos'))) {
				if($can_post) {
					$o .= '<div id="album-edit-link"><a href="'. z_path() . '/photos/' 
						. $a->data['user']['nickname'] . '/album/' . bin2hex($album) . '/edit' . '">' 
						. t('Edit Album') . '</a></div>';
 				}
			}
		}
		$tpl = get_markup_template('photo_album.tpl');
		if(count($r))
			foreach($r as $rr) {
				$o .= replace_macros($tpl,array(
					'$id' => $rr['id'],
					'$photolink' => z_path() . '/photos/' . $a->data['user']['nickname'] . '/image/' . $rr['resource_id'],
					'$phototitle' => t('View Photo'),
					'$imgsrc' => z_path() . '/photo/' . $rr['resource_id'] . '-' . $rr['scale'] . '.jpg',
					'$imgalt' => $rr['filename'],
					'$desc'=> $rr['desc']
				));

		}
		$o .= '<div id="photo-album-end"></div>';
		$o .= paginate($a);

		return $o;

	}	


	if($datatype === 'image') {



		//$o = '';
		// fetch image, item containing image, then comments

		$ph = q("SELECT * FROM `photo` WHERE `uid` = %d AND `resource_id` = '%s' 
			$sql_extra ORDER BY `scale` ASC ",
			intval($owner_uid),
			dbesc($datum)
		);

		if(! count($ph)) {
			notice( t('Photo not available') . EOL );
			return;
		}

		$prevlink = '';
		$nextlink = '';

		$prvnxt = q("SELECT `resource_id` FROM `photo` WHERE `album` = '%s' AND `uid` = %d AND `scale` = 0 
			$sql_extra ORDER BY `created` DESC ",
			dbesc($ph[0]['album']),
			intval($owner_uid)
		); 

		if(count($prvnxt)) {
			for($z = 0; $z < count($prvnxt); $z++) {
				if($prvnxt[$z]['resource_id'] == $ph[0]['resource_id']) {
					$prv = $z - 1;
					$nxt = $z + 1;
					if($prv < 0)
						$prv = count($prvnxt) - 1;
					if($nxt >= count($prvnxt))
						$nxt = 0;
					break;
				}
			}
			$prevlink = z_path() . '/photos/' . $a->data['user']['nickname'] . '/image/' . $prvnxt[$prv]['resource_id'] ;
			$nextlink = z_path() . '/photos/' . $a->data['user']['nickname'] . '/image/' . $prvnxt[$nxt]['resource_id'] ;
 		}


		if(count($ph) == 1)
			$hires = $lores = $ph[0];
		if(count($ph) > 1) {
			if($ph[1]['scale'] == 2) {
				// original is 640 or less, we can display it directly
				$hires = $lores = $ph[0];
			}
			else {
			$hires = $ph[0];
			$lores = $ph[1];
			}
		}

		$album_link = z_path() . '/photos/' . $a->data['user']['nickname'] . '/album/' . bin2hex($ph[0]['album']);
 		$tools = Null;
 		$lock = Null;
 
		if($can_post && ($ph[0]['uid'] == $owner_uid)) {
			$tools = array(
				'edit'	=> array(z_path() . '/photos/' . $a->data['user']['nickname'] . '/image/' . $datum . '/edit', t('Edit photo')),
				'profile'=>array(z_path() . '/profile_photo/use/'.$ph[0]['resource_id'], t('Use as profile photo')),
			);

			// lock
			$lock = ( ( ($ph[0]['uid'] == local_user()) && (strlen($ph[0]['allow_cid']) || strlen($ph[0]['allow_gid']) 
					|| strlen($ph[0]['deny_cid']) || strlen($ph[0]['deny_gid'])) ) 
					? t('Private Message')
					: Null);
	  		
			
		}

		if($prevlink)
			$prevlink = array($prevlink, '<div class="icon prev"></div>') ;

		$photo = array(
			'href' => z_path() . '/photo/' . $hires['resource_id'] . '-' . $hires['scale'] . '.jpg',
			'title'=> t('View Full Size'),
			'src'  => z_path() . '/photo/' . $lores['resource_id'] . '-' . $lores['scale'] . '.jpg'
		);

		if($nextlink)
			$nextlink = array($nextlink, '<div class="icon next"></div>');


		// Do we have an item for this photo?

		$linked_items = q("SELECT * FROM `item` WHERE `resource_id` = '%s' $sql_extra LIMIT 1",
			dbesc($datum)
		);
		if(count($linked_items)) {
			$link_item = $linked_items[0];
			$r = q("SELECT COUNT(*) AS `total`
				FROM `item` LEFT JOIN `contact` ON `contact`.`id` = `item`.`contact_id`
				WHERE `parent_uri` = '%s' AND `uri` != '%s' AND `item`.`deleted` = 0
				AND `contact`.`blocked` = 0 AND `contact`.`pending` = 0
				AND `item`.`uid` = %d 
				$sql_extra ",
				dbesc($link_item['uri']),
				dbesc($link_item['uri']),
				intval($link_item['uid'])

			);

			if(count($r))
				$a->set_pager_total($r[0]['total']);


			$r = q("SELECT `item`.*, `item`.`id` AS `item_id`, 
				`contact`.`name`, `contact`.`photo`, `contact`.`url`, `contact`.`network`, 
				`contact`.`rel`, `contact`.`thumb`, `contact`.`self`, 
				`contact`.`id` AS `cid`, `contact`.`uid` AS `contact-uid`
				FROM `item` LEFT JOIN `contact` ON `contact`.`id` = `item`.`contact_id`
				WHERE `parent_uri` = '%s' AND `uri` != '%s' AND `item`.`deleted` = 0
				AND `contact`.`blocked` = 0 AND `contact`.`pending` = 0
				AND `item`.`uid` = %d
				$sql_extra
				ORDER BY `parent` DESC, `id` ASC LIMIT %d ,%d ",
				dbesc($link_item['uri']),
				dbesc($link_item['uri']),
				intval($link_item['uid']),
				intval($a->pager['start']),
				intval($a->pager['itemspage'])

			);
		
			if((local_user()) && (local_user() == $link_item['uid'])) {
				q("UPDATE `item` SET `unseen` = 0 WHERE `parent` = %d and `uid` = %d",
					intval($link_item['parent']),
					intval(local_user())
				);
			}
		}

		$tags=Null;

		if(count($linked_items) && strlen($link_item['tag'])) {
			$arr = explode(',',$link_item['tag']);
			// parse tags and add links
			$tag_str = '';
			foreach($arr as $t) {
				if(strlen($tag_str))
					$tag_str .= ', ';
				$tag_str .= bbcode($t);
			} 
			$tags = array(t('Tags: '), $tag_str);
			if($cmd === 'edit') {
				$tags[] = z_path() . '/tagrm/' . $link_item['id'];
				$tags[] = t('[Remove any tag]');
			}
		}


		$edit = Null;
		if(($cmd === 'edit') && ($can_post)) {
			$edit_tpl = get_markup_template('photo_edit.tpl');
			$edit = replace_macros($edit_tpl, array(
				'$id' => $ph[0]['id'],
				'$album' => $ph[0]['album'],
				'$newalbum' => t('New album name'), 
				'$nickname' => $a->data['user']['nickname'],
				'$resource_id' => $ph[0]['resource_id'],
				'$capt_label' => t('Caption'),
				'$caption' => $ph[0]['desc'],
				'$tag_label' => t('Add a Tag'),
				'$tags' => $link_item['tag'],
				'$permissions' => t('Permissions'),
				'$aclselect' => populate_acl($ph[0]),
				'$help_tags' => t('Example: @bob, @Barbara_Jensen, @jim@example.com, #California, #camping'),
				'$item_id' => ((count($linked_items)) ? $link_item['id'] : 0),
				'$submit' => t('Submit'),
				'$delete' => t('Delete Photo')
			));
		}

		if(count($linked_items)) {

			$cmnt_tpl = get_markup_template('comment_item.tpl');
			$tpl = get_markup_template('photo_item.tpl');
			$return_url = $a->cmd;

			$like_tpl = get_markup_template('like_noshare.tpl');

			$likebuttons = '';

			if($can_post || can_write_wall($a,$owner_uid)) {
				$likebuttons = replace_macros($like_tpl,array(
					'$id' => $link_item['id'],
					'$likethis' => t("I like this \x28toggle\x29"),
					'$nolike' => t("I don't like this \x28toggle\x29"),
					'$share' => t('Share'),
					'$wait' => t('Please wait')
				));
			}

			$comments = '';
			if(! count($r)) {
				if($can_post || can_write_wall($a,$owner_uid)) {
					if($link_item['last_child']) {
						$comments .= replace_macros($cmnt_tpl,array(
							'$return_path' => '', 
							'$jsreload' => $return_url,
							'$type' => 'wall-comment',
							'$id' => $link_item['id'],
							'$parent' => $link_item['id'],
							'$profile_uid' =>  $owner_uid,
							'$mylink' => $contact['url'],
							'$mytitle' => t('This is you'),
							'$myphoto' => $contact['thumb'],
							'$comment' => t('Comment'),
							'$submit' => t('Submit'),
							'$ww' => ''
						));
					}
				}
			}

			$alike = array();
			$dlike = array();
			
			$like = '';
			$dislike = '';

			// display comments
			if(count($r)) {

				foreach($r as $item) {
					like_puller($a,$item,$alike,'like');
					like_puller($a,$item,$dlike,'dislike');
				}

				$like    = ((isset($alike[$link_item['id']])) ? format_like($alike[$link_item['id']],$alike[$link_item['id'] . '-l'],'like',$link_item['id']) : '');
				$dislike = ((isset($dlike[$link_item['id']])) ? format_like($dlike[$link_item['id']],$dlike[$link_item['id'] . '-l'],'dislike',$link_item['id']) : '');



				if($can_post || can_write_wall($a,$owner_uid)) {
					if($link_item['last_child']) {
						$comments .= replace_macros($cmnt_tpl,array(
							'$return_path' => '',
							'$jsreload' => $return_url,
							'$type' => 'wall-comment',
							'$id' => $link_item['id'],
							'$parent' => $link_item['id'],
							'$profile_uid' =>  $owner_uid,
							'$mylink' => $contact['url'],
							'$mytitle' => t('This is you'),
							'$myphoto' => $contact['thumb'],
							'$comment' => t('Comment'),
							'$submit' => t('Submit'),
							'$ww' => ''
						));
					}
				}


				foreach($r as $item) {
					$comment = '';
					$template = $tpl;
					$sparkle = '';

					if(((activity_match($item['verb'],ACTIVITY_LIKE)) || (activity_match($item['verb'],ACTIVITY_DISLIKE))) && ($item['id'] != $item['parent']))
						continue;

					$redirect_url = z_path() . '/redir/' . $item['cid'] ;
			
					if($can_post || can_write_wall($a,$owner_uid)) {

						if($item['last_child']) {
							$comments .= replace_macros($cmnt_tpl,array(
								'$return_path' => '',
								'$jsreload' => $return_url,
								'$type' => 'wall-comment',
								'$id' => $item['item_id'],
								'$parent' => $item['parent'],
								'$profile_uid' =>  $owner_uid,
								'$mylink' => $contact['url'],
								'$mytitle' => t('This is you'),
								'$myphoto' => $contact['thumb'],
								'$comment' => t('Comment'),
								'$submit' => t('Submit'),
								'$ww' => ''
							));
						}
					}


					if(local_user() && ($item['contact-uid'] == local_user()) 
						&& ($item['network'] == 'dfrn') && (! $item['self'] )) {
						$profile_url = $redirect_url;
						$sparkle = ' sparkle';
					}
					else {
						$profile_url = $item['url'];
						$sparkle = '';
					}
 
					$diff_author = (($item['url'] !== $item['author_link']) ? true : false);

					$profile_name   = (((strlen($item['author_name']))   && $diff_author) ? $item['author_name']   : $item['name']);
					$profile_avatar = (((strlen($item['author_avatar'])) && $diff_author) ? $item['author_avatar'] : $item['thumb']);

					$profile_link = $profile_url;

					$drop = '';

					if(($item['contact_id'] == remote_user()) || ($item['uid'] == local_user()))
						$drop = replace_macros(get_markup_template('photo_drop.tpl'), array('$id' => $item['id'], '$delete' => t('Delete')));


					$comments .= replace_macros($template,array(
						'$id' => $item['item_id'],
						'$profile_url' => $profile_link,
						'$name' => $profile_name,
						'$thumb' => $profile_avatar,
						'$sparkle' => $sparkle,
						'$title' => $item['title'],
						'$body' => bbcode($item['body']),
						'$ago' => relative_date($item['created']),
						'$indent' => (($item['parent'] != $item['item_id']) ? ' comment' : ''),
						'$drop' => $drop,
						'$comment' => $comment
					));
				}
			}

			$paginate = paginate($a);
		}
		
		$photo_tpl = get_markup_template('photo_view.tpl');
		$o .= replace_macros($photo_tpl, array(
			'$id' => $ph[0]['id'],
			'$album' => array($album_link,$ph[0]['album']),
			'$tools' => $tools,
			'$lock' => $lock,
			'$photo' => $photo,
			'$prevlink' => $prevlink,
			'$nextlink' => $nextlink,
			'$desc' => $ph[0]['desc'],
			'$tags' => $tags,
			'$edit' => $edit,	
			'$likebuttons' => $likebuttons,
			'$like' => $like,
			'$dislike' => $dislike,
			'$comments' => $comments,
			'$paginate' => $paginate,
		));
		
		return $o;
	}

	// Default - show recent photos with upload link (if applicable)
	//$o = '';

	$r = q("SELECT `resource_id`, max(`scale`) AS `scale` FROM `photo` WHERE `uid` = %d AND `album` != '%s' AND `album` != '%s' 
		$sql_extra GROUP BY `resource_id`",
		intval($a->data['user']['uid']),
		dbesc('Contact Photos'),
		dbesc( t('Contact Photos'))
	);
	if(count($r)) {
		$a->set_pager_total(count($r));
		$a->set_pager_itemspage(20);
	}

	$r = q("SELECT `resource_id`, `id`, `filename`, `album`, max(`scale`) AS `scale` FROM `photo`
		WHERE `uid` = %d AND `album` != '%s' AND `album` != '%s'  
		$sql_extra GROUP BY `resource_id` ORDER BY `created` DESC LIMIT %d , %d",
		intval($a->data['user']['uid']),
		dbesc('Contact Photos'),
		dbesc( t('Contact Photos')),
		intval($a->pager['start']),
		intval($a->pager['itemspage'])
	);

	$o .= '<h3>' . t('Recent Photos') . '</h3>';

	if($can_post) {
		$o .= '<div id="photo-top-links"><a id="photo-top-upload-link" href="'. z_path() . '/photos/' 
			. $a->data['user']['nickname'] . '/upload' . '">' . t('Upload New Photos') . '</a></div>';
	}

	$tpl = get_markup_template('photo_top.tpl');
	if(count($r)) {
		foreach($r as $rr) {
			$o .= replace_macros($tpl,array(
				'$id'         => $rr['id'],
				'$photolink'  => z_path() . '/photos/' . $a->data['user']['nickname'] . '/image/' . $rr['resource_id'],
				'$phototitle' => t('View Photo'),
				'$imgsrc'     => z_path() . '/photo/' . $rr['resource_id'] . '-' . ((($rr['scale']) == 6) ? 4 : $rr['scale']) . '.jpg',
				'$albumlink'  => z_path() . '/photos/' . $a->data['user']['nickname'] . '/album/' . bin2hex($rr['album']),
				'$albumname'  => $rr['album'],
				'$albumalt'   => t('View Album'),
				'$imgalt'     => $rr['filename']
			));

		}
		$o .= '<div id="photo-top-end"></div>';
	}
	$o .= paginate($a);
	return $o;
}

