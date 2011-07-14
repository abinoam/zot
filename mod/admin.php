<?php

 /**
  * Friendika admin
  */
  
 
function admin_init(&$a) {
	if(!is_site_admin()) {
		notice( t('Permission denied.') . EOL);
		return;
	}
}

function admin_post(&$a){
	if(!is_site_admin()) {
		return login(false);
	}
	
	// urls
	if ($a->argc > 1){
		switch ($a->argv[1]){
			case 'site':
				admin_page_site_post($a);
				break;
			case 'users':
				admin_page_users_post($a);
				break;
			case 'plugins':
				if ($a->argc > 2 && 
					is_file("addon/".$a->argv[2]."/".$a->argv[2].".php")){
						@include_once("addon/".$a->argv[2]."/".$a->argv[2].".php");
						if(function_exists($a->argv[2].'_plugin_admin_post')) {
							$func = $a->argv[2].'_plugin_admin_post';
							$func($a);
						}
				}
				goaway(z_path() . '/admin/plugins/' . $a->argv[2] );
				return; // NOTREACHED							
				break;
			case 'logs':
				admin_page_logs_post($a);
				break;
		}
	}

	goaway(z_path() . '/admin' );
	return; // NOTREACHED	
}

function admin_content(&$a) {

	if(!is_site_admin()) {
		return login(false);
	}

	/**
	 * Side bar links
	 */

	// array( url, name, extra css classes )
	$aside = Array(
		'site'	 =>	Array(z_path()."/admin/site/", t("Site") , "site"),
		'users'	 =>	Array(z_path()."/admin/users/", t("Users") , "users"),
		'plugins'=>	Array(z_path()."/admin/plugins/", t("Plugins") , "plugins")
	);
	
	/* get plugins admin page */
	
	$r = q("SELECT * FROM `addon` WHERE `plugin_admin`=1");
	$aside['plugins_admin']=Array();
	foreach ($r as $h){
		$plugin =$h['name'];
		$aside['plugins_admin'][] = Array(z_path()."/admin/plugins/".$plugin, $plugin, "plugin");
		// temp plugins with admin
		$a->plugins_admin[] = $plugin;
	}
		
	$aside['logs'] = Array(z_path()."/admin/logs/", t("Logs"), "logs");

	$t = get_markup_template("admin_aside.tpl");
	$a->page['aside'] = replace_macros( $t, array(
			'$admin' => $aside, 
			'$h_pending' => t('User registrations waiting for confirmation'),
			'$admurl'=> z_path()."/admin/"
	));



	/**
	 * Page content
	 */
	$o = '';
	
	// urls
	if ($a->argc > 1){
		switch ($a->argv[1]){
			case 'site':
				$o = admin_page_site($a);
				break;
			case 'users':
				$o = admin_page_users($a);
				break;
			case 'plugins':
				$o = admin_page_plugins($a);
				break;
			case 'logs':
				$o = admin_page_logs($a);
				break;				
			default:
				notice( t("Item not found.") );
		}
	} else {
		$o = admin_page_summary($a);
	}
	return $o;
} 


/**
 * Admin Summary Page
 */
function admin_page_summary(&$a) {
	$r = q("SELECT `page_flags`, COUNT(uid) as `count` FROM `user` GROUP BY `page_flags`");
	$accounts = Array(
		Array( t('Normal Account'), 0),
		Array( t('Soapbox Account'), 0),
		Array( t('Community/Celebrity Account'), 0),
		Array( t('Automatic Friend Account'), 0)
	);
	$users=0;
	foreach ($r as $u){ $accounts[$u['page_flags']][1] = $u['count']; $users+=$u['count']; }

	
	$r = q("SELECT COUNT(id) as `count` FROM `register`");
	$pending = $r[0]['count'];
	
	
	
	
	
	$t = get_markup_template("admin_summary.tpl");
	return replace_macros($t, array(
		'$title' => t('Administration'),
		'$page' => t('Summary'),
		'$users' => Array( t('Registered users'), $users),
		'$accounts' => $accounts,
		'$pending' => Array( t('Pending registrations'), $pending),
		'$version' => Array( t('Version'), FRIENDIKA_VERSION),
		'$build' =>  get_config('system','build'),
		'$plugins' => Array( t('Active plugins'), $a->plugins )
	));
}


/**
 * Admin Site Page
 */
function admin_page_site_post(&$a){
	if (!x($_POST,"page_site")){
		return;
	}

	
	$sitename 			=	((x($_POST,'sitename'))			? notags(trim($_POST['sitename']))			: '');
	$banner				=	((x($_POST,'banner'))      		? trim($_POST['banner'])					: false);
	$language			=	((x($_POST,'language'))			? notags(trim($_POST['language']))			: '');
	$theme				=	((x($_POST,'theme'))			? notags(trim($_POST['theme']))				: '');
	$maximagesize		=	((x($_POST,'maximagesize'))		? intval(trim($_POST['maximagesize']))		:  0);
	
	
	$register_policy	=	((x($_POST,'register_policy'))	? intval(trim($_POST['register_policy']))	:  0);
	$register_text		=	((x($_POST,'register_text'))	? notags(trim($_POST['register_text']))		: '');	
	
	$allowed_sites		=	((x($_POST,'allowed_sites'))	? notags(trim($_POST['allowed_sites']))		: '');
	$allowed_email		=	((x($_POST,'allowed_email'))	? notags(trim($_POST['allowed_email']))		: '');
	$block_public		=	((x($_POST,'block_public'))		? True	:	False);
	$force_publish		=	((x($_POST,'publish_all'))		? True	:	False);
	$global_directory	=	((x($_POST,'directory_submit_url'))	? notags(trim($_POST['directory_submit_url']))	: '');
	$no_multi_reg		=	((x($_POST,'no_multi_reg'))		? True	:	False);
	$no_openid			=	!((x($_POST,'no_openid'))		? True	:	False);
	$no_gravatar		=	!((x($_POST,'no_gravatar'))		? True	:	False);
	$no_regfullname		=	!((x($_POST,'no_regfullname'))	? True	:	False);
	$no_utf				=	!((x($_POST,'no_utf'))			? True	:	False);
	$no_community_page	=	!((x($_POST,'no_community_page'))	? True	:	False);

	$verifyssl			=	((x($_POST,'verifyssl'))		? True	:	False);
	$proxyuser			=	((x($_POST,'proxyuser'))		? notags(trim($_POST['global_search_url']))	: '');
	$proxy				=	((x($_POST,'proxy'))			? notags(trim($_POST['global_search_url']))	: '');
	$timeout			=	((x($_POST,'timeout'))			? intval(trim($_POST['timeout']))		: 60);
	$dfrn_only          =	((x($_POST,'dfrn_only'))	    ? True	:	False);
    $ostatus_disabled   =   !((x($_POST,'ostatus_disabled')) ? True  :   False);


	set_config('config','sitename',$sitename);
	if ($banner==""){
		// don't know why, but del_config doesn't work...
		q("DELETE FROM `config` WHERE `cat` = '%s' AND `k` = '%s' LIMIT 1",
			dbesc("system"),
			dbesc("banner")
		);
	} else {
		set_config('system','banner', $banner);
	}
	set_config('system','language', $language);
	set_config('system','theme', $theme);
	set_config('system','maximagesize', $maximagesize);
	
	set_config('config','register_policy', $register_policy);
	set_config('config','register_text', $register_text);
	set_config('system','allowed_sites', $allowed_sites);
	set_config('system','allowed_email', $allowed_email);
	set_config('system','block_public', $block_public);
	set_config('system','publish_all', $force_publish);
	if ($global_directory==""){
		// don't know why, but del_config doesn't work...
		q("DELETE FROM `config` WHERE `cat` = '%s' AND `k` = '%s' LIMIT 1",
			dbesc("system"),
			dbesc("directory_submit_url")
		);
	} else {
		set_config('system','directory_submit_url', $global_directory);
	}
	set_config('system','directory_search_url', $global_search_url);
	set_config('system','block_extended_register', $no_multi_reg);
	set_config('system','no_openid', $no_openid);
	set_config('system','no_gravatar', $no_gravatar);
	set_config('system','no_regfullname', $no_regfullname);
	set_config('system','no_community_page', $no_community_page);
	set_config('system','proxy', $no_utf);
	set_config('system','verifyssl', $verifyssl);
	set_config('system','proxyuser', $proxyuser);
	set_config('system','proxy', $proxy);
	set_config('system','curl_timeout', $timeout);
	set_config('system','dfrn_only', $dfrn_only);
	set_config('system','ostatus_disabled', $ostatus_disabled);

	info( t('Site settings updated.') . EOL);
	goaway(z_path() . '/admin/site' );
	return; // NOTREACHED	
	
}
 
function admin_page_site(&$a) {
	
	/* Installed langs */
	$lang_choices = array();
	$langs = glob('view/*/strings.php');
	
	if(is_array($langs) && count($langs)) {
		if(! in_array('view/en/strings.php',$langs))
			$langs[] = 'view/en/';
		asort($langs);
		foreach($langs as $l) {
			$t = explode("/",$l);
			$lang_choices[$t[1]] = $t[1];
		}
	}
	
	/* Installed themes */
	$theme_choices = array();
	$files = glob('view/theme/*');
	if($files) {
		foreach($files as $file) {
			$f = basename($file);
			$theme_name = ((file_exists($file . '/experimental')) ?  sprintf("%s - \x28Experimental\x29", $f) : $f);
			$theme_choices[$f] = $theme_name;
		}
	}
	
	
	/* Banner */
	$banner = get_config('system','banner');
	if($banner == false) 
		$banner = '<a href="http://project.friendika.com"><img id="logo-img" src="images/friendika-32.png" alt="logo" /></a><span id="logo-text"><a href="http://project.friendika.com">Friendika</a></span>';
	$banner = htmlspecialchars($banner);
	
	//echo "<pre>"; var_dump($lang_choices); die("</pre>");

	/* Register policy */
	$register_choices = Array(
		REGISTER_CLOSED => t("Closed"),
		REGISTER_APPROVE => t("Requires approval"),
		REGISTER_OPEN => t("Open")
	); 
	
	$t = get_markup_template("admin_site.tpl");
	return replace_macros($t, array(
		'$title' => t('Administration'),
		'$page' => t('Site'),
		'$submit' => t('Submit'),
		'$registration' => t('Registration'),
		'$upload' => t('File upload'),
		'$corporate' => t('Policies'),
		'$advanced' => t('Advanced'),
		
		'$baseurl' => z_path(),
									// name, label, value, help string, extra data...
		'$sitename' 		=> array('sitename', t("Site name"), htmlentities($a->config['sitename'], ENT_QUOTES), ""),
		'$banner'			=> array('banner', t("Banner/Logo"), $banner, ""),
		'$language' 		=> array('language', t("System language"), get_config('system','language'), "", $lang_choices),
		'$theme' 			=> array('theme', t("System theme"), get_config('system','theme'), "Default system theme (which may be over-ridden by user profiles)", $theme_choices),

		'$maximagesize'		=> array('maximagesize', t("Maximum image size"), get_config('system','maximagesize'), "Maximum size in bytes of uploaded images. Default is 0, which means no limits."),

		'$register_policy'	=> array('register_policy', t("Register policy"), $a->config['register_policy'], "", $register_choices),
		'$register_text'	=> array('register_text', t("Register text"), htmlentities($a->config['register_text'], ENT_QUOTES), "Will be displayed prominently on the registration page."),
		'$allowed_sites'	=> array('allowed_sites', t("Allowed friend domains"), get_config('system','allowed_sites'), "Comma separated list of domains which are allowed to establish friendships with this site. Wildcards are accepted. Empty to allow any domains"),
		'$allowed_email'	=> array('allowed_email', t("Allowed email domains"), get_config('system','allowed_email'), "Comma separated list of domains which are allowed in email addresses for registrations to this site. Wildcards are accepted. Empty to allow any domains"),
		'$block_public'		=> array('block_public', t("Block public"), get_config('system','block_public'), "Check to block public access to all otherwise public personal pages on this site unless you are currently logged in."),
		'$force_publish'	=> array('publish_all', t("Force publish"), get_config('system','publish_all'), "Check to force all profiles on this site to be listed in the site directory."),
		'$global_directory'	=> array('directory_submit_url', t("Global directory update URL"), get_config('system','directory_submit_url'), "URL to update the global directory. If this is not set, the global directory is completely unavailable to the application."),
			
		'$no_multi_reg'		=> array('no_multi_reg', t("Block multiple registrations"),  get_config('system','block_extended_register'), "Disallow users to register additional accounts for use as pages."),
		'$no_openid'		=> array('no_openid', t("OpenID support"), !get_config('system','no_openid'), "OpenID support for registration and logins."),
		'$no_gravatar'		=> array('no_gravatar', t("Gravatar support"), !get_config('system','no_gravatar'), "Search new user's photo on Gravatar."),
		'$no_regfullname'	=> array('no_regfullname', t("Fullname check"), !get_config('system','no_regfullname'), "Force users to register with a space between firstname and lastname in Full name, as an antispam measure"),
		'$no_utf'			=> array('no_utf', t("UTF-8 Regular expressions"), !get_config('system','proxy'), "Use PHP UTF8 regular expressions"),
		'$no_community_page' => array('no_community_page', t("Show Community Page"), !get_config('system','no_community_page'), "Display a Community page showing all recent public postings on this site."),
		'$ostatus_disabled' => array('ostatus_disabled', t("Enable OStatus support"), !get_config('system','ostatus_disable'), "Provide built-in OStatus \x28identi.ca, status.net, etc.\x29 compatibility. All communications in OStatus are public, so privacy warnings will be occasionally displayed."),	
		'$dfrn_only'        => array('dfrn_only', t('Only allow Friendika contacts'), get_config('system','dfrn_only'), "All contacts must use Friendika protocols. All other built-in communication protocols disabled."),
		'$verifyssl' 		=> array('verifyssl', t("Verify SSL"), get_config('system','verifyssl'), "If you wish, you can turn on strict certificate checking. This will mean you cannot connect (at all) to self-signed SSL sites."),
		'$proxyuser'		=> array('proxyuser', t("Proxy user"), get_config('system','proxyuser'), ""),
		'$proxy'			=> array('proxy', t("Proxy URL"), get_config('system','proxy'), ""),
		'$timeout'			=> array('timeout', t("Network timeout"), (x(get_config('system','curl_timeout'))?get_config('system','curl_timeout'):60), "Value is in seconds. Set to 0 for unlimited (not recommended)."),

			
	));

}


/**
 * Users admin page
 */
function admin_page_users_post(&$a){
	$pending = ( x(£_POST, 'pending') ? $_POST['pending'] : Array() );
	$users = ( x($_POST, 'user') ? $_POST['user'] : Array() );
	
	if (x($_POST,'page_users_block')){
		foreach($users as $uid){
			q("UPDATE `user` SET `blocked`=1-`blocked` WHERE `uid`=%s",
				intval( $uid )
			);
		}
		notice( sprintf( tt("%s user blocked", "%s users blocked/unblocked", count($users)), count($users)) );
	}
	if (x($_POST,'page_users_delete')){
		require_once("include/Contact.php");
		foreach($users as $uid){
			user_remove($uid);
		}
		notice( sprintf( tt("%s user deleted", "%s users deleted", count($users)), count($users)) );
	}
	
	if (x($_POST,'page_users_approve')){
		require_once("mod/regmod.php");
		foreach($pending as $hash){
			user_allow($hash);
		}
	}
	if (x($_POST,'page_users_deny')){
		require_once("mod/regmod.php");
		foreach($pending as $hash){
			user_deny($hash);
		}
	}
	goaway(z_path() . '/admin/users' );
	return; // NOTREACHED	
}
 
function admin_page_users(&$a){
	if ($a->argc>2) {
		$uid = $a->argv[3];
		$user = q("SELECT * FROM `user` WHERE `uid`=%d", intval($uid));
		if (count($user)==0){
			notice( 'User not found' . EOL);
			goaway(z_path() . '/admin/users' );
			return; // NOTREACHED						
		}		
		switch($a->argv[2]){
			case "delete":{
				// delete user
				require_once("include/Contact.php");
				user_remove($uid);
				
				notice( sprintf(t("User '%s' deleted"), $user[0]['username']) . EOL);
			}; break;
			case "block":{
				q("UPDATE `user` SET `blocked`=%d WHERE `uid`=%s",
					intval( 1-$user[0]['blocked'] ),
					intval( $uid )
				);
				notice( sprintf( ($user[0]['blocked']?t("User '%s' unblocked"):t("User '%s' blocked")) , $user[0]['username']) . EOL);
			}; break;
		}
		goaway(z_path() . '/admin/users' );
		return; // NOTREACHED	
		
	}
	
	/* get pending */
	$pending = q("SELECT `register`.*, `contact`.`name`, `user`.`email`
				 FROM `register`
				 LEFT JOIN `contact` ON `register`.`uid` = `contact`.`uid`
				 LEFT JOIN `user` ON `register`.`uid` = `user`.`uid`;");
	
	/* get users */

	$total = q("SELECT count(*) as total FROM `user` where 1");
	if(count($total)) {
		$a->set_pager_total($total[0]['total']);
		$a->set_pager_itemspage(100);
	}

	$users = q("SELECT `user` . * , `contact`.`name` , `contact`.`url` , `contact`.`micro`, `lastitem`.`lastitem_date`
				FROM
					(SELECT MAX(`item`.`changed`) as `lastitem_date`, `item`.`uid`
					FROM `item`
					WHERE `item`.`type` = 'wall'
					GROUP BY `item`.`uid`) AS `lastitem`
						 RIGHT OUTER JOIN `user` ON `user`.`uid` = `lastitem`.`uid`,
					   `contact`
				WHERE
					   `user`.`uid` = `contact`.`uid`
						AND `user`.`verified` =1
					AND `contact`.`self` =1
				ORDER BY `contact`.`name` LIMIT %d, %d
				",
				intval($a->pager['start']),
				intval($a->pager['itemspage'])
				);
					
	function _setup_users($e){
		$accounts = Array(
			t('Normal Account'), 
			t('Soapbox Account'),
			t('Community/Celebrity Account'),
			t('Automatic Friend Account')
		);
		$e['page_flags'] = $accounts[$e['page_flags']];
		$e['register_date'] = relative_date($e['register_date']);
		$e['login_date'] = relative_date($e['login_date']);
		$e['lastitem_date'] = relative_date($e['lastitem_date']);
		return $e;
	}
	$users = array_map("_setup_users", $users);
	
	$t = get_markup_template("admin_users.tpl");
	$o = replace_macros($t, array(
		// strings //
		'$title' => t('Administration'),
		'$page' => t('Users'),
		'$submit' => t('Submit'),
		'$select_all' => t('select all'),
		'$h_pending' => t('User registrations waiting for confirm'),
		'$th_pending' => array( t('Request date'), t('Name'), t('Email') ),
		'$no_pending' =>  t('No registrations.'),
		'$approve' => t('Approve'),
		'$deny' => t('Deny'),
		'$delete' => t('Delete'),
		'$block' => t('Block'),
		'$unblock' => t('Unblock'),
		
		'$h_users' => t('Users'),
		'$th_users' => array( t('Name'), t('Email'), t('Register date'), t('Last login'), t('Last item'),  t('Account') ),

		'$confirm_delete_multi' => t('Selected users will be deleted!\n\nEverything these users had posted on this site will be permanently deleted!\n\nAre you sure?'),
		'$confirm_delete' => t('The user {0} will be deleted!\n\nEverything this user has posted on this site will be permanently deleted!\n\nAre you sure?'),


		// values //
		'$baseurl' => z_path(),

		'$pending' => $pending,
		'$users' => $users,
	));
	$o .= paginate($a);
	return $o;
}


/*
 * Plugins admin page
 */

function admin_page_plugins(&$a){
	
	/**
	 * Single plugin
	 */
	if ($a->argc == 3){
		$plugin = $a->argv[2];
		if (!is_file("addon/$plugin/$plugin.php")){
			notice( t("Item not found.") );
			return;
		}
		
		if (x($_GET,"a") && $_GET['a']=="t"){
			// Toggle plugin status
			$idx = array_search($plugin, $a->plugins);
			if ($idx){
				unset($a->plugins[$idx]);
				uninstall_plugin($plugin);
				info( sprintf( t("Plugin %s disabled."), $plugin ) );
			} else {
				$a->plugins[] = $plugin;
				install_plugin($plugin);
				info( sprintf( t("Plugin %s enabled."), $plugin ) );
			}
			set_config("system","addon", implode(", ",$a->plugins));
			goaway(z_path() . '/admin/plugins' );
			return; // NOTREACHED	
		}
		// display plugin details
		require_once('library/markdown.php');

		if (in_array($plugin, $a->plugins)){
			$status="on"; $action= t("Disable");
		} else {
			$status="off"; $action= t("Enable");
		}
		
		$readme=Null;
		if (is_file("addon/$plugin/README.md")){
			$readme = file_get_contents("addon/$plugin/README.md");
			$readme = Markdown($readme);
		} else if (is_file("addon/$plugin/README")){
			$readme = "<pre>". file_get_contents("addon/$plugin/README") ."</pre>";
		} 
		
		$admin_form="";
		if (in_array($plugin, $a->plugins_admin)){
			@require_once("addon/$plugin/$plugin.php");
			$func = $plugin.'_plugin_admin';
			$func($a, $admin_form);
		}
		
		$t = get_markup_template("admin_plugins_details.tpl");
		return replace_macros($t, array(
			'$title' => t('Administration'),
			'$page' => t('Plugins'),
			'$toggle' => t('Toggle'),
			'$settings' => t('Settings'),
			'$baseurl' => z_path(),
		
			'$plugin' => $plugin,
			'$status' => $status,
			'$action' => $action,
			'$info' => get_plugin_info($plugin),
		
			'$admin_form' => $admin_form,
			
			'$readme' => $readme
		));
	} 
	 
	 
	
	/**
	 * List plugins
	 */
	
	$plugins = array();
	$files = glob("addon/*/");
	if($files) {
		foreach($files as $file) {	
			if (is_dir($file)){
				list($tmp, $id)=array_map("trim", explode("/",$file));
				$info = get_plugin_info($id);
				$plugins[] = array( $id, (in_array($id,  $a->plugins)?"on":"off") , $info);
			}
		}
	}
	
	$t = get_markup_template("admin_plugins.tpl");
	return replace_macros($t, array(
		'$title' => t('Administration'),
		'$page' => t('Plugins'),
		'$submit' => t('Submit'),
		'$baseurl' => z_path(),
	
		'$plugins' => $plugins
	));
}


/**
 * Logs admin page
 */
 
function admin_page_logs_post(&$a) {
	if (x($_POST,"page_logs")) {

		$logfile 		=	((x($_POST,'logfile'))		? notags(trim($_POST['logfile']))	: '');
		$debugging		=	((x($_POST,'debugging'))	? true								: false);
		$loglevel 		=	((x($_POST,'loglevel'))		? intval(trim($_POST['loglevel']))	: 0);

		set_config('system','logfile', $logfile);
		set_config('system','debugging',  $debugging);
		set_config('system','loglevel', $loglevel);

		
	}

	info( t("Log settings updated.") );
	goaway(z_path() . '/admin/logs' );
	return; // NOTREACHED	
}
 
function admin_page_logs(&$a){
	
	$log_choices = Array(
		LOGGER_NORMAL => 'Normal',
		LOGGER_TRACE => 'Trace',
		LOGGER_DEBUG => 'Debug',
		LOGGER_DATA => 'Data',
		LOGGER_ALL => 'All'
	);
	
	$t = get_markup_template("admin_logs.tpl");

	$f = get_config('system','logfile');
	$size = filesize($f);
	if($size > 5000000)
		$size = 5000000;

	$data = '';
	$fp = fopen($f,'r');
	if($fp) {
		$seek = fseek($fp,0-$size,SEEK_END);
		if($seek === 0) {
			fgets($fp); // throw away the first partial line
			$data = escape_tags(fread($fp,$size));
			while(! feof($fp))
				$data .= escape_tags(fread($fp,4096));
		}
		fclose($fp);
	}


	return replace_macros($t, array(
		'$title' => t('Administration'),
		'$page' => t('Logs'),
		'$submit' => t('Submit'),
		'$clear' => t('Clear'),
		'$data' => $data,
		'$baseurl' => z_path(),
		'$logname' =>  get_config('system','logfile'),
		
									// name, label, value, help string, extra data...
		'$debugging' 		=> array('debugging', t("Debugging"),get_config('system','debugging'), ""),
		'$logfile'			=> array('logfile', t("Log file"), get_config('system','logfile'), t("Must be writable by web server. Relative to your Friendika index.php.")),
		'$loglevel' 		=> array('loglevel', t("Log level"), get_config('system','loglevel'), "", $log_choices),
	));
}

