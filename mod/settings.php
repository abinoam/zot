<?php


function settings_init(&$a) {

	if(local_user()) {
		profile_load($a,$a->user['nickname']);
	}

	$a->page['htmlhead'] .= "<script> var ispublic = '" . t('everybody') . "';" ;

	$a->page['htmlhead'] .= <<< EOT

	$(document).ready(function() {
		$('#contact_allow, #contact_deny, #group_allow, #group_deny').change(function() { 
			var selstr;
			$('#contact_allow option:selected, #contact_deny option:selected, 
				#group_allow option:selected, #group_deny option:selected').each( function() {
				selstr = $(this).text();
				$('#jot-public').hide();
			});
			if(selstr == null) { 
				$('#jot-public').show();
			}
		}).trigger('change');
	});
	</script>
EOT;

}


function settings_post(&$a) {

	if(! local_user()) {
		notice( t('Permission denied.') . EOL);
		return;
	}

	if(count($a->user) && x($a->user,'uid') && $a->user['uid'] != local_user()) {
		notice( t('Permission denied.') . EOL);
		return;
	}

	if(($a->argc > 1) && ($a->argv[1] == 'addon')) {
		call_hooks('plugin_settings_post', $_POST);
		return;
	}

	call_hooks('settings_post', $_POST);

	if((x($_POST,'npassword')) || (x($_POST,'confirm'))) {

		$newpass = $_POST['npassword'];
		$confirm = $_POST['confirm'];

		$err = false;
		if($newpass != $confirm ) {
			notice( t('Passwords do not match. Password unchanged.') . EOL);
			$err = true;
		}

		if((! x($newpass)) || (! x($confirm))) {
			notice( t('Empty passwords are not allowed. Password unchanged.') . EOL);
			$err = true;
		}

		if(! $err) {
			$password = hash('whirlpool',$newpass);
			$r = q("UPDATE `user` SET `password` = '%s' WHERE `uid` = %d LIMIT 1",
				dbesc($password),
				intval(local_user())
			);
			if($r)
				info( t('Password changed.') . EOL);
			else
				notice( t('Password update failed. Please try again.') . EOL);
		}
	}

	$theme            = ((x($_POST,'theme'))      ? notags(trim($_POST['theme']))        : '');
	$username         = ((x($_POST,'username'))   ? notags(trim($_POST['username']))     : '');
	$email            = ((x($_POST,'email'))      ? notags(trim($_POST['email']))        : '');
	$timezone         = ((x($_POST,'timezone'))   ? notags(trim($_POST['timezone']))     : '');
	$defloc           = ((x($_POST,'defloc'))     ? notags(trim($_POST['defloc']))       : '');
	$openid           = ((x($_POST,'openid_url')) ? notags(trim($_POST['openid_url']))   : '');
	$maxreq           = ((x($_POST,'maxreq'))     ? intval($_POST['maxreq'])             : 0);
	$expire           = ((x($_POST,'expire'))     ? intval($_POST['expire'])             : 0);

	$allow_location   = (((x($_POST,'allow_location')) && (intval($_POST['allow_location']) == 1)) ? 1: 0);
	$publish          = (((x($_POST,'profile_in_directory')) && (intval($_POST['profile_in_directory']) == 1)) ? 1: 0);
	$net_publish      = (((x($_POST,'profile_in_netdirectory')) && (intval($_POST['profile_in_netdirectory']) == 1)) ? 1: 0);
	$old_visibility   = (((x($_POST,'visibility')) && (intval($_POST['visibility']) == 1)) ? 1 : 0);
	$page_flags       = (((x($_POST,'page_flags')) && (intval($_POST['page_flags']))) ? intval($_POST['page_flags']) : 0);
	$blockwall        = (((x($_POST,'blockwall')) && (intval($_POST['blockwall']) == 1)) ? 0: 1); // this setting is inverted!

	$hide_friends = (($_POST['hide_friends'] == 1) ? 1: 0);
	$hidewall = (($_POST['hidewall'] == 1) ? 1: 0);

	$notify = 0;

	if(x($_POST,'notify1'))
		$notify += intval($_POST['notify1']);
	if(x($_POST,'notify2'))
		$notify += intval($_POST['notify2']);
	if(x($_POST,'notify3'))
		$notify += intval($_POST['notify3']);
	if(x($_POST,'notify4'))
		$notify += intval($_POST['notify4']);
	if(x($_POST,'notify5'))
		$notify += intval($_POST['notify5']);

	$email_changed = false;

	$err = '';

	$name_change = false;

	if($username != $a->user['username']) {
		$name_change = true;
		if(strlen($username) > 40)
			$err .= t('Please use a shorter name.') . EOL;
		if(strlen($username) < 3)
			$err .= t('Name too short.') . EOL;
	}

	if($email != $a->user['email']) {
		$email_changed = true;
        if(! valid_email($email))
			$err .= t('Not valid email.') . EOL;
		if((x($a->config,'admin_email')) && (strcasecmp($email,$a->config['admin_email']) == 0)) {
			$err .= t('Cannot change to that email.') . EOL;
			$email = $a->user['email'];
		}
	}

	if(strlen($err)) {
		notice($err . EOL);
		return;
	}

	if($timezone != $a->user['timezone']) {
		if(strlen($timezone))
			date_default_timezone_set($timezone);
	}

	$str_group_allow   = perms2str($_POST['group_allow']);
	$str_contact_allow = perms2str($_POST['contact_allow']);
	$str_group_deny    = perms2str($_POST['group_deny']);
	$str_contact_deny  = perms2str($_POST['contact_deny']);

	$openidserver = $a->user['openidserver'];

	// If openid has changed or if there's an openid but no openidserver, try and discover it.

	if($openid != $a->user['openid'] || (strlen($openid) && (! strlen($openidserver)))) {
		$tmp_str = $openid;
		if(strlen($tmp_str) && validate_url($tmp_str)) {
			logger('updating openidserver');
			require_once('library/openid.php');
			$open_id_obj = new LightOpenID;
			$open_id_obj->identity = $openid;
			$openidserver = $open_id_obj->discover($open_id_obj->identity);
		}
		else
			$openidserver = '';
	}

	$r = q("UPDATE `user` SET `username` = '%s', `email` = '%s', `openid` = '%s', `timezone` = '%s',  `allow_cid` = '%s', `allow_gid` = '%s', `deny_cid` = '%s', `deny_gid` = '%s', `notify_flags` = %d, `page_flags` = %d, `default_location` = '%s', `allow_location` = %d, `theme` = '%s', `maxreq` = %d, `expire` = %d, `openidserver` = '%s', `blockwall` = %d, `hidewall` = %d  WHERE `uid` = %d LIMIT 1",
			dbesc($username),
			dbesc($email),
			dbesc($openid),
			dbesc($timezone),
			dbesc($str_contact_allow),
			dbesc($str_group_allow),
			dbesc($str_contact_deny),
			dbesc($str_group_deny),
			intval($notify),
			intval($page_flags),
			dbesc($defloc),
			intval($allow_location),
			dbesc($theme),
			intval($maxreq),
			intval($expire),
			dbesc($openidserver),
			intval($blockwall),
			intval($hidewall),
			intval(local_user())
	);
	if($r)
		info( t('Settings updated.') . EOL);

	$r = q("UPDATE profile SET publish = %d, net_publish = %d, hide_friends = %d WHERE is_default = 1 AND uid = %d LIMIT 1",
		intval($publish),
		intval($net_publish),
		intval($hide_friends),
		intval(local_user())
	);


	if($name_change) {
		q("UPDATE contact SET name = '%s', name_date = '%s' WHERE uid = %d AND self = 1 LIMIT 1",
			dbesc($username),
			dbesc(datetime_convert()),
			intval(local_user())
		);
	}		

	if($old_visibility != $net_publish) {
		// Update global directory in background
		$url = $_SESSION['my_url'];
		if($url && strlen(get_config('system','directory_submit_url')))
			proc_run('php',"include/directory.php","$url");
	}

	$_SESSION['theme'] = $theme;
	if($email_changed && $a->config['register_policy'] == REGISTER_VERIFY) {

		// FIXME - set to un-verified, blocked and redirect to logout

	}

	goaway(z_path() . '/settings' );
	return; // NOTREACHED
}
		

if(! function_exists('settings_content')) {
function settings_content(&$a) {

	$o = '';
	$o .= '<script>	$(document).ready(function() { $(\'#nav-settings-link\').addClass(\'nav-selected\'); });</script>';

	if(! local_user()) {
		notice( t('Permission denied.') . EOL );
		return;
	}
	
	$tabtpl = get_markup_template("settings_tabs.tpl");
	$tabs = replace_macros($tabtpl, array(
		'$account' => array( t('Account settings'), z_path().'/settings'),
		'$plugins' => array( t('Plugin settings'), z_path().'/settings/addon')
	));
		

	if(($a->argc > 1) && ($a->argv[1] === 'addon')) {
		$settings_addons = "";
		
		$r = q("SELECT * FROM hook WHERE hook = 'plugin_settings' ");
		if(! results($r))
			$settings_addons = t('No Plugin settings configured');

		call_hooks('plugin_settings', $settings_addons);
		
		
		$tpl = get_markup_template("settings_addons.tpl");
		$o .= replace_macros($tpl, array(
			'$title'	=> t('Plugin Settings'),
			'$tabs'		=> $tabs,
			'$settings_addons' => $settings_addons
		));
		return $o;
	}
		
	require_once('include/acl_selectors.php');

	$p = q("SELECT * FROM profile WHERE is_default = 1 AND uid = %d LIMIT 1",
		intval(local_user())
	);
	if(count($p))
		$profile = $p[0];

	$username = $a->user['username'];
	$email    = $a->user['email'];
	$nickname = $a->user['nickname'];
	$timezone = $a->user['timezone'];
	$notify   = $a->user['notify_flags'];
	$defloc   = $a->user['default_location'];
	$openid   = $a->user['openid'];
	$maxreq   = $a->user['maxreq'];
	$expire   = ((intval($a->user['expire'])) ? $a->user['expire'] : '');
	$blockwall = $a->user['blockwall'];

	if(! strlen($a->user['timezone']))
		$timezone = date_default_timezone_get();

	$pageset_tpl = get_markup_template('pagetypes.tpl');
	$pagetype = replace_macros($pageset_tpl,array(
		'$page_normal' 	=> array('page_flags', t('Normal Account'), PAGE_NORMAL, 
			t('This account is a normal personal profile'), 
			($a->user['page_flags'] == PAGE_NORMAL)),
								
		'$page_soapbox' 	=> array('page_flags', t('Soapbox Account'), PAGE_SOAPBOX, 
			t('Automatically approve all connection/friend requests as read-only fans'), 
			($a->user['page_flags'] == PAGE_SOAPBOX)),
									
		'$page_community'	=> array('page_flags', t('Community/Celebrity Account'), PAGE_COMMUNITY, 
			t('Automatically approve all connection/friend requests as read-write fans'), 
			($a->user['page_flags'] == PAGE_COMMUNITY)),
									
		'$page_freelove' 	=> array('page_flags', t('Automatic Friend Account'), PAGE_FREELOVE, 
			t('Automatically approve all connection/friend requests as friends'), 
			($a->user['page_flags'] == PAGE_FREELOVE)),
	));

	$noid = get_config('system','no_openid');

	if($noid) {
		$openid_field = false;
	}
	else {
		$openid_field = array('openid_url', t('OpenID:'),$openid, t("\x28Optional\x29 Allow this OpenID to login to this account."));
	}


	$opt_tpl = get_markup_template("field_yesno.tpl");
	if(get_config('system','publish_all')) {
		$profile_in_dir = '<input type="hidden" name="profile_in_directory" value="1" />';
	}
	else {
		$profile_in_dir = replace_macros($opt_tpl,array(
			'$field' 	=> array('profile_in_directory', t('Publish your default profile in your local site directory?'), $profile['publish'], '', array(t('No'),t('Yes'))),
		));
	}

	if(strlen(get_config('system','directory_submit_url'))) {
		$profile_in_net_dir = replace_macros($opt_tpl,array(
			'$field' 	=> array('profile_in_netdirectory', t('Publish your default profile in the global social directory?'), $profile['net_publish'], '', array(t('No'),t('Yes'))),
		));
	}
	else
		$profile_in_net_dir = '';


	$hide_friends = replace_macros($opt_tpl,array(
			'$field' 	=> array('hide_friends', t('Hide your contact/friend list from viewers of your default profile?'), $profile['hide_friends'], '', array(t('No'),t('Yes'))),
	));

	$hide_wall = replace_macros($opt_tpl,array(
			'$field' 	=> array('hidewall',  t('Hide profile details and all your messages from unknown viewers?'), $a->user['hidewall'], '', array(t('No'),t('Yes'))),

	));

	$invisible = (((! $profile['publish']) && (! $profile['net_publish']))
		? true : false);

	if($invisible)
		info( t('Profile is <strong>not published</strong>.') . EOL );

	
	$default_theme = get_config('system','theme');
	if(! $default_theme)
		$default_theme = 'default';
	
	$themes = array();
	$files = glob('view/theme/*');
	if($files) {
		foreach($files as $file) {
			$f = basename($file);
			$theme_name = ((file_exists($file . '/experimental')) ?  sprintf("%s - \x28Experimental\x29", $f) : $f);
			$themes[$f]=$theme_name;
		}
	}
	$theme_selected = (!x($_SESSION,'theme')? $default_theme : $_SESSION['theme']);


	$subdir = ((strlen($a->get_path())) ? '<br />' . t('or') . ' ' . z_path() . '/profile/' . $nickname : '');

	$tpl_addr = get_markup_template("settings_nick_set.tpl");

	$prof_addr = replace_macros($tpl_addr,array(
		'$desc' => t('Your Identity Address is'),
		'$nickname' => $nickname,
		'$subdir' => $subdir,
		'$basepath' => $a->get_hostname()
	));

	$stpl = get_markup_template('settings.tpl');

	$celeb = ((($a->user['page_flags'] == PAGE_SOAPBOX) || ($a->user['page_flags'] == PAGE_COMMUNITY)) ? true : false);

	

	$o .= replace_macros($stpl,array(
		'$tabs' 	=> $tabs,
		'$ptitle' 	=> t('Account Settings'),

		'$submit' 	=> t('Submit'),
		'$baseurl' => z_path(),
		'$uid' => local_user(),
		
		'$nickname_block' => $prof_addr,
		'$uexport' => t('Export Personal Data'),
		
		
		'$h_pass' 	=> t('Password Settings'),
		'$password1'=> array('npassword', t('New Password:'), '', ''),
		'$password2'=> array('confirm', t('Confirm:'), '', t('Leave password fields blank unless changing')),
		'$oid_enable' => (! get_config('system','no_openid')),
		'$openid'	=> $openid_field,
		
		'$h_basic' 	=> t('Basic Settings'),
		'$username' => array('username',  t('Full Name:'), $username,''),
		'$email' 	=> array('email', t('Email Address:'), $email, ''),
		'$timezone' => array('timezone_select' , t('Your Timezone:'), select_timezone($timezone), ''),
		'$defloc'	=> array('defloc', t('Default Post Location:'), $defloc, ''),
		'$allowloc' => array('allow_location', t('Use Browser Location:'), ($a->user['allow_location'] == 1), ''),
		'$theme'	=> array('theme', t('Display Theme:'), $theme_selected, '', $themes),



		'$h_prv' 	=> t('Security and Privacy Settings'),

		'$maxreq' 	=> array('maxreq', t('Maximum Friend Requests/Day:'), $maxreq ,t("\x28to prevent spam abuse\x29")),
		'$permissions' => t('Default Post Permissions'),
		'$permdesc' => t("\x28click to open/close\x29"),
		'$visibility' => $profile['net_publish'],
		'$aclselect' => populate_acl($a->user,$celeb),

		'$blockwall'=> array('blockwall', t('Allow friends to post to your profile page:'), !$blockwall, ''),
		'$expire'	=> array('expire', t("Automatically expire posts after days:"), $expire, t('If empty, posts will not expire. Expired posts will be deleted')),

		'$profile_in_dir' => $profile_in_dir,
		'$profile_in_net_dir' => $profile_in_net_dir,
		'$hide_friends' => $hide_friends,
		'$hide_wall' => $hide_wall,
		
		
		
		'$h_not' 	=> t('Notification Settings'),
		'$lbl_not' 	=> t('Send a notification email when:'),
		'$notify1'	=> array('notify1', t('You receive an introduction'), ($notify & NOTIFY_INTRO), NOTIFY_INTRO, ''),
		'$notify2'	=> array('notify2', t('Your introductions are confirmed'), ($notify & NOTIFY_CONFIRM), NOTIFY_CONFIRM, ''),
		'$notify3'	=> array('notify3', t('Someone writes on your profile wall'), ($notify & NOTIFY_WALL), NOTIFY_WALL, ''),
		'$notify4'	=> array('notify4', t('Someone writes a followup comment'), ($notify & NOTIFY_COMMENT), NOTIFY_COMMENT, ''),
		'$notify5'	=> array('notify5', t('You receive a private message'), ($notify & NOTIFY_MAIL), NOTIFY_MAIL, ''),
		
		'$h_advn' => t('Advanced Page Settings'),
		'$pagetype' => $pagetype,

	));

	call_hooks('settings_form',$o);

	$o .= '</form>' . "\r\n";

	return $o;

}}

