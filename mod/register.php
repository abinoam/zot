<?php

if(! function_exists('register_post')) {
function register_post(&$a) {

	global $lang;

	$verified = 0;
	$blocked  = 1;

	switch($a->config['register_policy']) {

	
	case REGISTER_OPEN:
		$blocked = 0;
		$verified = 1;
		break;

	case REGISTER_APPROVE:
		$blocked = 1;
		$verified = 0;
		break;

	default:
	case REGISTER_CLOSED:
		if(! is_site_admin()) {
			notice( t('Permission denied.') . EOL );
			return;
		}
		$blocked = 1;
		$verified = 0;
		break;
	}


	$username   = ((x($_POST,'username'))   ? notags(trim($_POST['username']))   : '');
	$nickname   = ((x($_POST,'nickname'))   ? notags(trim($_POST['nickname']))   : '');
	$email      = ((x($_POST,'email'))      ? notags(trim($_POST['email']))      : '');
	$openid_url = ((x($_POST,'openid_url')) ? notags(trim($_POST['openid_url'])) : '');
	$photo      = ((x($_POST,'photo'))      ? notags(trim($_POST['photo']))      : '');
	$publish    = ((x($_POST,'profile_publish_reg') && intval($_POST['profile_publish_reg'])) ? 1 : 0);

	$netpublish = ((strlen(get_config('system','directory_submit_url'))) ? $publish : 0);
		
	$tmp_str = $openid_url;
	if((! x($username)) || (! x($email)) || (! x($nickname))) {
		if($openid_url) {
			if(! validate_url($tmp_str)) {
				notice( t('Invalid OpenID url') . EOL);
				return;
			}
			$_SESSION['register'] = 1;
			$_SESSION['openid'] = $openid_url;
			require_once('library/openid.php');
			$openid = new LightOpenID;
			$openid->identity = $openid_url;
			$openid->returnUrl = z_path() . '/openid'; 
			$openid->required = array('namePerson/friendly', 'contact/email', 'namePerson');
			$openid->optional = array('namePerson/first','media/image/aspect11','media/image/default');
			goaway($openid->authUrl());
			// NOTREACHED	
		}

		notice( t('Please enter the required information.') . EOL );
		return;
	}

	if(! validate_url($tmp_str))
		$openid_url = '';


	$err = '';

	// collapse multiple spaces in name
	$username = preg_replace('/ +/',' ',$username);

	if(mb_strlen($username) > 48)
		$err .= t('Please use a shorter name.') . EOL;
	if(mb_strlen($username) < 3)
		$err .= t('Name too short.') . EOL;

	// I don't really like having this rule, but it cuts down
	// on the number of auto-registrations by Russian spammers
	
	//  Using preg_match was completely unreliable, due to mixed UTF-8 regex support
	//	$no_utf = get_config('system','no_utf');
	//	$pat = (($no_utf) ? '/^[a-zA-Z]* [a-zA-Z]*$/' : '/^\p{L}* \p{L}*$/u' ); 

	// So now we are just looking for a space in the full name. 
	
	$loose_reg = get_config('system','no_regfullname');
	if(! $loose_reg) {
		$username = mb_convert_case($username,MB_CASE_TITLE,'UTF-8');
		if(! strpos($username,' '))
			$err .= t("That doesn't appear to be your full \x28First Last\x29 name.") . EOL;
	}


	if(! allowed_email($email))
			$err .= t('Your email domain is not among those allowed on this site.') . EOL;

	if((! valid_email($email)) || (! validate_email($email)))
		$err .= t('Not a valid email address.') . EOL;

	// Disallow somebody creating an account using openid that uses the admin email address,
	// since openid bypasses email verification. We'll allow it if there is not yet an admin account.

	if((x($a->config,'admin_email')) && (strcasecmp($email,$a->config['admin_email']) == 0) && strlen($openid_url)) {
		$r = q("SELECT * FROM `user` WHERE `email` = '%s' LIMIT 1",
			dbesc($email)
		);
		if(count($r))
			$err .= t('Cannot use that email.') . EOL;
	}

	$nickname = $_POST['nickname'] = strtolower($nickname);

	if(! preg_match("/^[a-z][a-z0-9\-\_]*$/",$nickname))
		$err .= t('Your "nickname" can only contain "a-z", "0-9", "-", and "_", and must also begin with a letter.') . EOL;
	$r = q("SELECT `uid` FROM `user`
               	WHERE `nickname` = '%s' LIMIT 1",
               	dbesc($nickname)
	);
	if(count($r))
		$err .= t('Nickname is already registered. Please choose another.') . EOL;

	if(strlen($err)) {
		notice( $err );
		return;
	}


	$new_password = autoname(6) . mt_rand(100,9999);
	$new_password_encoded = hash('whirlpool',$new_password);

	$res=openssl_pkey_new(array(
		'digest_alg' => 'sha1',
		'private_key_bits' => 4096,
		'encrypt_key' => false ));

	// Get private key

	if(empty($res)) {
		notice( t('SERIOUS ERROR: Generation of security keys failed.') . EOL);
		return;
	}

	$prvkey = '';

	openssl_pkey_export($res, $prvkey);

	// Get public key

	$pkey = openssl_pkey_get_details($res);
	$pubkey = $pkey["key"];

	$r = q("INSERT INTO `user` ( `username`, `password`, `email`, `openid`, `nickname`,
		`pubkey`, `prvkey`, `register_date`, `verified`, `blocked` )
		VALUES ( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d )",
		dbesc($username),
		dbesc($new_password_encoded),
		dbesc($email),
		dbesc($openid_url),
		dbesc($nickname),
		dbesc($pubkey),
		dbesc($prvkey),
		dbesc(datetime_convert()),
		intval($verified),
		intval($blocked)
		);

	if($r) {
		$r = q("SELECT `uid` FROM `user` 
			WHERE `username` = '%s' AND `password` = '%s' LIMIT 1",
			dbesc($username),
			dbesc($new_password_encoded)
			);
		if($r !== false && count($r))
			$newuid = intval($r[0]['uid']);
	}
	else {
		notice( t('An error occurred during registration. Please try again.') . EOL );
		return;
	} 		

	/**
	 * if somebody clicked submit twice very quickly, they could end up with two accounts 
	 * due to race condition. Remove this one.
	 */

	$r = q("SELECT `uid` FROM `user`
               	WHERE `nickname` = '%s' ",
               	dbesc($nickname)
	);
	if((count($r) > 1) && $newuid) {
		$err .= t('Nickname is already registered. Please choose another.') . EOL;
		q("DELETE FROM `user` WHERE `uid` = %d LIMIT 1",
			intval($newuid)
		);
		notice ($err);
		return;
	}

	if(x($newuid) !== false) {
		$r = q("INSERT INTO `profile` ( `uid`, `profile_name`, `is_default`, `name`, `photo`, `thumb`, `publish`, `net_publish` )
			VALUES ( %d, '%s', %d, '%s', '%s', '%s', %d, %d ) ",
			intval($newuid),
			'default',
			1,
			dbesc($username),
			dbesc("/photo/profile/{$newuid}.jpg"),
			dbesc("/photo/avatar/{$newuid}.jpg"),
			intval($publish),
			intval($netpublish)

		);
		if($r === false) {
			notice( t('An error occurred creating your default profile. Please try again.') . EOL );
			// Start fresh next time.
			$r = q("DELETE FROM `user` WHERE `uid` = %d",
				intval($newuid));
			return;
		}
		$r = q("INSERT INTO `contact` ( `uid`, `created`, `self`, `name`, `nick`, `photo`, `thumb`, `micro`, `blocked`, `pending`, `url`, `notify`, `name_date`, `uri_date`, `avatar_date` )
			VALUES ( %d, '%s', 1, '%s', '%s', '%s', '%s', '%s', 0, 0, '%s', '%s', '%s', '%s', '%s' ) ",
			intval($newuid),
			datetime_convert(),
			dbesc($username),
			dbesc($nickname),
			dbesc("/photo/profile/{$newuid}.jpg"),
			dbesc("/photo/avatar/{$newuid}.jpg"),
			dbesc("/photo/micro/{$newuid}.jpg"),
			dbesc("/profile/$nickname"),
			dbesc("/zpost/$nickname"),
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			dbesc(datetime_convert())
		);


	}

	$use_gravatar = ((get_config('system','no_gravatar')) ? false : true);

	// if we have an openid photo use it. 
	// otherwise unless it is disabled, use gravatar

	if($use_gravatar || strlen($photo)) {

		require_once('include/Photo.php');

		if(($use_gravatar) && (! strlen($photo))) 
			$photo = gravatar_img($email);
		$photo_failure = false;

		$filename = basename($photo);
		$img_str = fetch_url($photo,true);
		$img = new Photo($img_str);
		if($img->is_valid()) {

			$img->scaleImageSquare(175);
					
			$hash = photo_new_resource();

			$r = $img->store($newuid, 0, $hash, $filename, t('Profile Photos'), 4 );

			if($r === false)
				$photo_failure = true;

			$img->scaleImage(80);

			$r = $img->store($newuid, 0, $hash, $filename, t('Profile Photos'), 5 );

			if($r === false)
				$photo_failure = true;

			$img->scaleImage(48);

			$r = $img->store($newuid, 0, $hash, $filename, t('Profile Photos'), 6 );

			if($r === false)
				$photo_failure = true;

			if(! $photo_failure) {
				q("UPDATE `photo` SET `profile` = 1 WHERE `resource_id` = '%s' ",
					dbesc($hash)
				);
			}
		}
	}

	if($netpublish && $a->config['register_policy'] != REGISTER_APPROVE) {
		$url = z_path() . "/profile/$nickname";
		proc_run('php',"include/directory.php","$url");
	}


	if( $a->config['register_policy'] == REGISTER_OPEN ) {
		$email_tpl = get_intltext_template("register_open_eml.tpl");
		$email_tpl = replace_macros($email_tpl, array(
				'$sitename' => $a->config['sitename'],
				'$siteurl' =>  z_path(),
				'$username' => $username,
				'$email' => $email,
				'$password' => $new_password,
				'$uid' => $newuid ));

		$res = mail($email, sprintf(t('Registration details for %s'), $a->config['sitename']),
			$email_tpl, 
				'From: ' . t('Administrator') . '@' . $_SERVER['SERVER_NAME'] . "\n"
				. 'Content-type: text/plain; charset=UTF-8' . "\n"
				. 'Content-transfer-encoding: 8bit' );


		if($res) {
			info( t('Registration successful. Please check your email for further instructions.') . EOL ) ;
			goaway(z_path());
		}
		else {
			notice( t('Failed to send email message. Here is the message that failed.') . $email_tpl . EOL );
		}
	}
	elseif($a->config['register_policy'] == REGISTER_APPROVE) {
		if(! strlen($a->config['admin_email'])) {
			notice( t('Your registration can not be processed.') . EOL);
			goaway(z_path());
		}

		$hash = random_string();
		$r = q("INSERT INTO `register` ( `hash`, `created`, `uid`, `password`, `language` ) VALUES ( '%s', '%s', %d, '%s', '%s' ) ",
			dbesc($hash),
			dbesc(datetime_convert()),
			intval($newuid),
			dbesc($new_password),
			dbesc($lang)
		);

		$r = q("SELECT `language` FROM `user` WHERE `email` = '%s' LIMIT 1",
			dbesc($a->config['admin_email'])
		);
		if(count($r))
			push_lang($r[0]['language']);
		else
			push_lang('en');


		$email_tpl = get_intltext_template("register_verify_eml.tpl");
		$email_tpl = replace_macros($email_tpl, array(
				'$sitename' => $a->config['sitename'],
				'$siteurl' =>  z_path(),
				'$username' => $username,
				'$email' => $email,
				'$password' => $new_password,
				'$uid' => $newuid,
				'$hash' => $hash
		 ));

		$res = mail($a->config['admin_email'], sprintf(t('Registration request at %s'), $a->config['sitename']),
			$email_tpl,
				'From: ' . t('Administrator') . '@' . $_SERVER['SERVER_NAME'] . "\n"
				. 'Content-type: text/plain; charset=UTF-8' . "\n"
				. 'Content-transfer-encoding: 8bit' );

		pop_lang();

		if($res) {
			info( t('Your registration is pending approval by the site owner.') . EOL ) ;
			goaway(z_path());
		}

	}

	return;
}}






if(! function_exists('register_content')) {
function register_content(&$a) {

	// logged in users can register others (people/pages/groups)
	// even with closed registrations, unless specifically prohibited by site policy.
	// 'block_extended_register' blocks all registrations, period.

	$block = get_config('system','block_extended_register');

	if((($a->config['register_policy'] == REGISTER_CLOSED) && (! local_user())) || ($block)) {
		notice("Permission denied." . EOL);
		return;
	}

	if(x($_SESSION,'theme'))
		unset($_SESSION['theme']);


	$username     = ((x($_POST,'username'))     ? $_POST['username']     : ((x($_GET,'username'))     ? $_GET['username']              : ''));
	$email        = ((x($_POST,'email'))        ? $_POST['email']        : ((x($_GET,'email'))        ? $_GET['email']                 : ''));
	$openid_url   = ((x($_POST,'openid_url'))   ? $_POST['openid_url']   : ((x($_GET,'openid_url'))   ? $_GET['openid_url']            : ''));
	$nickname     = ((x($_POST,'nickname'))     ? $_POST['nickname']     : ((x($_GET,'nickname'))     ? $_GET['nickname']              : ''));
	$photo        = ((x($_POST,'photo'))        ? $_POST['photo']        : ((x($_GET,'photo'))        ? hex2bin($_GET['photo'])        : ''));

	$noid = get_config('system','no_openid');

	if($noid) {
		$oidhtml = '';
		$fillwith = '';
		$fillext = '';
		$oidlabel = '';
	}
	else {
		$oidhtml = '<label for="register-openid" id="label-register-openid" >$oidlabel</label><input type="text" maxlength="60" size="32" name="openid_url" class="openid" id="register-openid" value="$openid" >';
		$fillwith = t("You may \x28optionally\x29 fill in this form via OpenID by supplying your OpenID and clicking 'Register'.");
		$fillext =  t('If you are not familiar with OpenID, please leave that field blank and fill in the rest of the items.');
		$oidlabel = t("Your OpenID \x28optional\x29: ");
	}

	// I set this and got even more fake names than before...

	$realpeople = ''; // t('Members of this network prefer to communicate with real people who use their real names.');

	if(get_config('system','publish_all')) {
		$profile_publish_reg = '<input type="hidden" name="profile_publish_reg" value="1" />';
	}
	else {
		$publish_tpl = get_markup_template("profile_publish.tpl");
		$profile_publish = replace_macros($publish_tpl,array(
			'$instance'     => 'reg',
			'$pubdesc'      => t('Include your profile in member directory?'),
			'$yes_selected' => ' checked="checked" ',
			'$no_selected'  => '',
			'$str_yes'      => t('Yes'),
			'$str_no'       => t('No')
		));
	}


	$license = t('Shared content is covered by the <a href="http://creativecommons.org/licenses/by/3.0/">Creative Commons Attribution 3.0</a> license.');


	$o = get_markup_template("register.tpl");
	$o = replace_macros($o, array(
		'$oidhtml' => $oidhtml,
		'$regtitle'  => t('Registration'),
		'$registertext' =>((x($a->config,'register_text'))
			? '<div class="error-message">' . $a->config['register_text'] . '</div>'
			: "" ),
		'$fillwith'  => $fillwith,
		'$fillext'   => $fillext,
		'$oidlabel'  => $oidlabel,
		'$openid'    => $openid_url,
		'$namelabel' => t('Your Full Name ' . "\x28" . 'e.g. Joe Smith' . "\x29" . ': '),
		'$addrlabel' => t('Your Email Address: '),
		'$nickdesc'  => t('Choose a profile nickname. This must begin with a text character. Your profile address on this site will then be \'<strong>nickname@$sitename</strong>\'.'),
		'$nicklabel' => t('Choose a nickname: '),
		'$photo'     => $photo,
		'$publish'   => $profile_publish,
		'$regbutt'   => t('Register'),
		'$username'  => $username,
		'$email'     => $email,
		'$nickname'  => $nickname,
		'$license'   => $license,
		'$sitename'  => $a->get_hostname()
	));
	return $o;

}}

