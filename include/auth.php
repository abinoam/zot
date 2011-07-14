<?php


function nuke_session() {
	unset($_SESSION['authenticated']);
	unset($_SESSION['uid']);
	unset($_SESSION['visitor_id']);
	unset($_SESSION['administrator']);
	unset($_SESSION['cid']);
	unset($_SESSION['theme']);
	unset($_SESSION['page_flags']);
}

function auth_local($record) {

	$a = get_app();

	$_SESSION['uid'] = $record['uid'];
	$_SESSION['theme'] = $record['theme'];
	$_SESSION['authenticated'] = 1;
	$_SESSION['page_flags'] = $record['page_flags'];
	$_SESSION['my_url'] = z_path() . '/profile/' . $record['nickname'];
	$_SESSION['addr'] = $_SERVER['REMOTE_ADDR'];

	$a->user = $record;

	/**
	 * First time login - go to upload pfoile photo page
	 */

	if($a->user['login_date'] === '0000-00-00 00:00:00') {
		$_SESSION['return_url'] = 'profile_photo/new';
		$a->module = 'profile_photo';
		info( sprintf( t("Welcome %s"), $a->user['username']) . EOL);
		info( t('Please upload a profile photo.') . EOL);
	}
	else
		info( sprintf( t("Welcome back %s"),$a->user['username']) . EOL);

	$member_since = strtotime($a->user['register_date']);
	if(time() < ($member_since + ( 60 * 60 * 24 * 14)))
		$_SESSION['new_member'] = true;
	else
		$_SESSION['new_member'] = false;

	if(strlen($a->user['timezone'])) {
		date_default_timezone_set($a->user['timezone']);
		$a->timezone = $a->user['timezone'];
	}

	// Find other identities which share this email and password

	$r = q("SELECT `uid`,`username` FROM `user` WHERE `password` = '%s' AND `email` = '%s'",
		dbesc($a->user['password']),
		dbesc($a->user['email'])
	);
	if(count($r))
		$a->identities = $r;

	// load the 'self' contact record

	$r = q("SELECT * FROM `contact` WHERE `uid` = %d AND `self` = 1 LIMIT 1",
		intval($_SESSION['uid']));
	if(count($r)) {
		$a->contact = $r[0];
		$a->cid = $r[0]['id'];
		$_SESSION['cid'] = $a->cid;
	}

	$l = get_language();

	q("UPDATE `user` SET `login_date` = '%s', `language` = '%s' WHERE `uid` = %d LIMIT 1",
		dbesc(datetime_convert()),
		dbesc($l),
		intval($_SESSION['uid'])
	);

	call_hooks('logged_in', $a->user);
	return true;
}


// login/logout 


function login_logout() {

	if((isset($_SESSION)) && (x($_SESSION,'authenticated')) 
		&& ((! (x($_POST,'auth-params'))) || ($_POST['auth-params'] !== 'login'))) {

		if(((x($_POST,'auth-params')) && ($_POST['auth-params'] === 'logout')) || ($a->module === 'logout')) {
	
			// process logout request

			nuke_session();
			info( t('Logged out.') . EOL);
			goaway(z_root());
		}

		if(x($_SESSION,'visitor_id') && (! x($_SESSION,'uid'))) {
			$r = q("SELECT * FROM `contact` WHERE `id` = %d LIMIT 1",
				intval($_SESSION['visitor_id'])
			);
			if(results($r)) {
				$a->contact = $r[0];
			}
		}

		if(x($_SESSION,'uid')) {

			// already logged in user returning

			$check = get_config('system','paranoia');

			// extra paranoia - if the IP changed, log them out

			if($check && ($_SESSION['addr'] != $_SERVER['REMOTE_ADDR'])) {
				nuke_session();
				goaway(z_root());
			}

			$r = q("SELECT * FROM `user` WHERE `uid` = %d LIMIT 1",
				intval($_SESSION['uid'])
			);

			if(! count($r)) {
				nuke_session();
				goaway(z_root());
			}

			auth_local($r);
		}
	}
	else {

		if(isset($_SESSION)) {
			nuke_session();
		}

		if((x($_POST,'password')) && strlen($_POST['password']))
			$encrypted = hash('whirlpool',trim($_POST['password']));
		else {
			if((x($_POST,'openid_url')) && strlen($_POST['openid_url'])) {

				$noid = get_config('system','no_openid');

				$openid_url = trim($_POST['openid_url']);

				// validate_url alters the calling parameter

				$temp_string = $openid_url;

				// if it's an email address or doesn't resolve to a URL, fail.

				if(($noid) || (strpos($temp_string,'@')) || (! validate_url($temp_string))) {
					notice( t('Login failed.') . EOL);
					goaway(z_root());
					// NOTREACHED
				}

				// Otherwise it's probably an openid.

				require_once('library/openid.php');
				$openid = new LightOpenID;
				$openid->identity = $openid_url;
				$_SESSION['openid'] = $openid_url;

				$a = get_app();

				$openid->returnUrl = z_path() . '/openid'; 

				$r = q("SELECT `uid` FROM `user` WHERE `openid` = '%s' LIMIT 1",
					dbesc($openid_url)
				);
				if(results($r)) { 
					// existing account
					goaway($openid->authUrl());
					// NOTREACHED	
				}
				else {
					if($a->config['register_policy'] == REGISTER_CLOSED) {
						$a = get_app();
						notice( t('Login failed.') . EOL);
						goaway(z_root());
						// NOTREACHED
					}
					// new account
					$_SESSION['register'] = 1;
					$openid->required = array('namePerson/friendly', 'contact/email', 'namePerson');
					$openid->optional = array('namePerson/first','media/image/aspect11','media/image/default');
					goaway($openid->authUrl());
					// NOTREACHED	
				}
			}
		}
		if((x($_POST,'auth-params')) && $_POST['auth-params'] === 'login') {

			$record = null;

			$addon_auth = array(
				'username' => trim($_POST['openid_url']), 
				'password' => trim($_POST['password']),
				'authenticated' => 0,
				'user_record' => null
			);

			/**
			 *
			 * A plugin indicates successful login by setting 'authenticated' to non-zero value and returning a user record
			 * Plugins should never set 'authenticated' except to indicate success - as hooks may be chained
			 * and later plugins should not interfere with an earlier one that succeeded.
			 *
			 */

			call_hooks('authenticate', $addon_auth);

			if(($addon_auth['authenticated']) && (count($addon_auth['user_record']))) {
				$record = $addon_auth['user_record'];
			}
			else {

				// process normal login request

				$r = q("SELECT * FROM `user` WHERE ( `email` = '%s' OR `nickname` = '%s' ) 
					AND `password` = '%s' AND `blocked` = 0 AND `verified` = 1 LIMIT 1",
					dbesc(trim($_POST['openid_url'])),
					dbesc(trim($_POST['openid_url'])),
					dbesc($encrypted)
				);
				if(results($r))
					$record = $r[0];
			}

			if((! $record) || (! count($record))) {
				logger('authenticate: failed login attempt: ' . trim($_POST['openid_url'])); 
				notice( t('Login failed.') . EOL );
				goaway(z_root());
  			}

			auth_local($record);

			if(($a->module !== 'home') && isset($_SESSION['return_url']))
				goaway(z_path() . '/' . $_SESSION['return_url']);
		}
	}
}

// Returns an array of group id's this contact is a member of.
// This array will only contain group id's related to the uid of this
// DFRN contact. They are *not* neccessarily unique across the entire site. 


if(! function_exists('init_groups_visitor')) {
function init_groups_visitor($contact_id) {
	$groups = array();
	$r = q("SELECT `gid` FROM `group_member` 
		WHERE `contact_id` = %d ",
		intval($contact_id)
	);
	if(count($r)) {
		foreach($r as $rr)
			$groups[] = $rr['gid'];
	}
	return $groups;
}}


