<?php

function profile_init(&$a) {

	if((get_config('system','block_public')) && (! local_user()) && (! remote_user()))
		return;

	if($a->argc > 1)
		$which = $a->argv[1];
	else {
		notice( t('No profile') . EOL );
		$a->error = 404;
		return;
	}

	$profile = 0;
	if((local_user()) && ($a->argc > 2) && ($a->argv[2] === 'view')) {
		$which = $a->user['nickname'];
		$profile = $a->argv[1];		
	}

	profile_load($a,$which,$profile);

	if((x($a->profile,'page_flags')) && ($a->profile['page_flags'] & PAGE_COMMUNITY)) {
		$a->page['htmlhead'] .= '<meta name="friendika.community" content="true" />';
	}
	if(x($a->profile,'openidserver'))				
		$a->page['htmlhead'] .= '<link rel="openid.server" href="' . $a->profile['openidserver'] . '" />' . "\r\n";
	if(x($a->profile,'openid')) {
		$delegate = ((strstr($a->profile['openid'],'://')) ? $a->profile['openid'] : 'http://' . $a->profile['openid']);
		$a->page['htmlhead'] .= '<link rel="openid.delegate" href="' . $delegate . '" />' . "\r\n";
	}

	$keywords = ((x($a->profile,'pub_keywords')) ? $a->profile['pub_keywords'] : '');
	$keywords = str_replace(array(',',' ',',,'),array(' ',',',','),$keywords);
	if(strlen($keywords))
		$a->page['htmlhead'] .= '<meta name="keywords" content="' . $keywords . '" />' . "\r\n" ;

	$a->page['htmlhead'] .= '<meta name="dfrn-global-visibility" content="' . (($a->profile['net_publish']) ? 'true' : 'false') . '" />' . "\r\n" ;
	$a->page['htmlhead'] .= '<link rel="alternate" type="application/atom+xml" href="' . z_path() . '/dfrn_poll/' . $which .'" />' . "\r\n" ;
	$uri = urlencode('acct:' . $a->profile['nickname'] . '@' . $a->get_hostname() . (($a->path) ? '/' . $a->path : ''));
	$a->page['htmlhead'] .= '<link rel="lrdd" type="application/xrd+xml" href="' . z_path() . '/xrd/?uri=' . $uri . '" />' . "\r\n";
	header('Link: <' . z_path() . '/xrd/?uri=' . $uri . '>; rel="lrdd"; type="application/xrd+xml"', false);
  	
	$dfrn_pages = array('request', 'confirm', 'notify', 'poll');
	foreach($dfrn_pages as $dfrn)
		$a->page['htmlhead'] .= "<link rel=\"dfrn-{$dfrn}\" href=\"".z_path()."/dfrn_{$dfrn}/{$which}\" />\r\n";

}


function profile_content(&$a, $update = 0) {

	if(get_config('system','block_public') && (! local_user()) && (! remote_user())) {
		return login();
	}

	require_once("include/bbcode.php");
	require_once('include/security.php');
	require_once('include/conversation.php');
	require_once('include/acl_selectors.php');
	$groups = array();

	$tab = 'posts';
	$o = '';

	if($update) {
		// Ensure we've got a profile owner if updating.
		$a->profile['profile_uid'] = $update;
	}
	else {
		if($a->profile['profile_uid'] == local_user())		
			$o .= '<script>	$(document).ready(function() { $(\'#nav-home-link\').addClass(\'nav-selected\'); });</script>';
	}

	$contact = null;
	$remote_contact = false;

	if(remote_user()) {
		$contact_id = $_SESSION['visitor_id'];
		$groups = init_groups_visitor($contact_id);
		$r = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($contact_id),
			intval($a->profile['profile_uid'])
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

	$is_owner = ((local_user()) && (local_user() == $a->profile['profile_uid']) ? true : false);

	if($a->profile['hidewall'] && (! $is_owner) && (! $remote_contact)) {
		notice( t('Access to this profile has been restricted.') . EOL);
		return;
	}

	
	if(! $update) {
		if(x($_GET,'tab'))
			$tab = notags(trim($_GET['tab']));

		$tpl = get_markup_template('profile_tabs.tpl');

		$o .= replace_macros($tpl,array(
			'$url' => z_path() . '/' . $a->cmd,
			'$phototab' => z_path() . '/photos/' . $a->profile['nickname'],
			'$status' => t('Status'),
			'$profile' => t('Profile'),
			'$photos' => t('Photos'),
			'$events' => (($is_owner) ? t('Events') : ''),
			'$notes' => (($is_owner) ? 	t('Personal Notes') : ''),
			'$activetab' => $tab,
		));


		if($tab === 'profile') {
			require_once('include/profile_advanced.php');
			$o .= advanced_profile($a);
			call_hooks('profile_advanced',$o);
			return $o;
		}

		$commpage = (($a->profile['page_flags'] == PAGE_COMMUNITY) ? true : false);
		$commvisitor = (($commpage && $remote_contact == true) ? true : false);

		$celeb = ((($a->profile['page_flags'] == PAGE_SOAPBOX) || ($a->profile['page_flags'] == PAGE_COMMUNITY)) ? true : false);

		if(can_write_wall($a,$a->profile['profile_uid'])) {

			$x = array(
				'is_owner' => $is_owner,
            	'allow_location' => ((($is_owner || $commvisitor) && $a->profile['allow_location']) ? true : false),
	            'default_location' => (($is_owner) ? $a->user['default_location'] : ''),
    	        'nickname' => $a->profile['nickname'],
        	    'lockstate' => (((is_array($a->user) && ((strlen($a->user['allow_cid'])) || (strlen($a->user['allow_gid'])) || (strlen($a->user['deny_cid'])) || (strlen($a->user['deny_gid']))))) ? 'lock' : 'unlock'),
            	'acl' => (($is_owner) ? populate_acl($a->user, $celeb) : ''),
	            'bang' => '',
    	        'visitor' => (($is_owner || $commvisitor) ? 'block' : 'none'),
        	    'profile_uid' => $a->profile['profile_uid']
        	);

        	$o .= status_editor($a,$x);
		}

		// This is ugly, but we can't pass the profile_uid through the session to the ajax updater,
		// because browser prefetching might change it on us. We have to deliver it with the page.

		if($tab === 'posts') {
			$o .= '<div id="live-profile"></div>' . "\r\n";
			$o .= "<script> var profile_uid = " . $a->profile['profile_uid'] 
				. "; var netargs = ''; var profile_page = " . $a->pager['page'] . "; </script>\r\n";
		}
	}

	if($is_owner) {
		$r = q("UPDATE `item` SET `unseen` = 0 
			WHERE `wall` = 1 AND `unseen` = 1 AND `uid` = %d",
			intval(local_user())
		);
	}

	/**
	 * Get permissions SQL - if $remote_contact is true, our remote user has been pre-verified and we already have fetched his/her groups
	 */

	$sql_extra = permissions_sql($a->profile['profile_uid'],$remote_contact,$groups);


	$r = q("SELECT COUNT(*) AS `total`
		FROM `item` LEFT JOIN `contact` ON `contact`.`id` = `item`.`contact_id`
		WHERE `item`.`uid` = %d AND `item`.`visible` = 1 AND `item`.`deleted` = 0
		AND `contact`.`blocked` = 0 AND `contact`.`pending` = 0 
		AND `item`.`id` = `item`.`parent` AND `item`.`wall` = 1
		$sql_extra ",
		intval($a->profile['profile_uid'])

	);

	if(count($r)) {
		$a->set_pager_total($r[0]['total']);
		$a->set_pager_itemspage(40);
	}

	$r = q("SELECT `item`.`id` AS `item_id`, `contact`.`uid` AS `contact-uid`
		FROM `item` LEFT JOIN `contact` ON `contact`.`id` = `item`.`contact_id`
		WHERE `item`.`uid` = %d AND `item`.`visible` = 1 AND `item`.`deleted` = 0
		AND `contact`.`blocked` = 0 AND `contact`.`pending` = 0
		AND `item`.`id` = `item`.`parent` AND `item`.`wall` = 1
		$sql_extra
		ORDER BY `item`.`created` DESC LIMIT %d ,%d ",
		intval($a->profile['profile_uid']),
		intval($a->pager['start']),
		intval($a->pager['itemspage'])

	);

	$parents_arr = array();
	$parents_str = '';

	if(count($r)) {
		foreach($r as $rr)
			$parents_arr[] = $rr['item_id'];
		$parents_str = implode(', ', $parents_arr);
 
		$r = q("SELECT `item`.*, `item`.`id` AS `item_id`, 
			`contact`.`name`, `contact`.`photo`, `contact`.`url`, `contact`.`network`, `contact`.`rel`, 
			`contact`.`thumb`, `contact`.`self`, `contact`.`writable`, 
			`contact`.`id` AS `cid`, `contact`.`uid` AS `contact-uid`
			FROM `item`, (SELECT `p`.`id`,`p`.`created` FROM `item` AS `p` WHERE `p`.`parent` = `p`.`id`) AS `parentitem`, `contact`
			WHERE `item`.`uid` = %d AND `item`.`visible` = 1 AND `item`.`deleted` = 0
			AND `contact`.`id` = `item`.`contact_id`
			AND `contact`.`blocked` = 0 AND `contact`.`pending` = 0
			AND `item`.`parent` = `parentitem`.`id` AND `item`.`parent` IN ( %s )
			$sql_extra
			ORDER BY `parentitem`.`created` DESC, `gravity` ASC, `item`.`created` ASC ",
			intval($a->profile['profile_uid']),
			dbesc($parents_str)
		);
	}

	if($is_owner && ! $update)
		$o .= get_birthdays();

	$o .= conversation($a,$r,'profile',$update);

	if(! $update) {
		
		$o .= paginate($a);
		$o .= '<div class="cc-license">' . t('Shared content is covered by the <a href="http://creativecommons.org/licenses/by/3.0/">Creative Commons Attribution 3.0</a> license.') . '</div>';
	}

	return $o;
}
