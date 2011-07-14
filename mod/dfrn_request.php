<?php

/**
 *
 * Module: dfrn_request
 *
 * Purpose: Handles communication associated with the issuance of
 * friend requests.
 *
 */

if(! function_exists('dfrn_request_init')) {
function dfrn_request_init(&$a) {

	if((get_config('system','block_public')) && (! local_user()) && (! remote_user())) {
		return;
	}

	if($a->argc > 1)
		$which = $a->argv[1];

	profile_load($a,$which);
	return;
}}


/**
 * Function: dfrn_request_post
 *
 * Purpose:
 * Handles multiple scenarios.
 *
 * Scenario 1:
 * Clicking 'submit' on a friend request page.
 *
 * Scenario 2:
 * Following Scenario 1, we are brought back to our home site
 * in order to link our friend request with our own server cell.
 * After logging in, we click 'submit' to approve the linkage.
 *
 */

if(! function_exists('dfrn_request_post')) {
function dfrn_request_post(&$a) {

	if(($a->argc != 2) || (! count($a->profile)))
		return;


	if($_POST['cancel']) {
		goaway(z_path());
	} 


	/**
	 *
	 * Scenario 2: We've introduced ourself to another cell, then have been returned to our own cell
	 * to confirm the request, and then we've clicked submit (perhaps after logging in). 
	 * That brings us here:
	 *
	 */

	if((x($_POST,'localconfirm')) && ($_POST['localconfirm'] == 1)) {

		/**
		 * Ensure this is a valid request
		 */

		if(local_user() && ($a->user['nickname'] == $a->argv[1]) && (x($_POST,'dfrn_url'))) {


			$dfrn_url    = notags(trim($_POST['dfrn_url']));
			$aes_allow   = (((x($_POST,'aes_allow')) && ($_POST['aes_allow'] == 1)) ? 1 : 0);
			$confirm_key = ((x($_POST,'confirm_key')) ? $_POST['confirm_key'] : "");

			$contact_record = null;
	
			if(x($dfrn_url)) {

				/**
				 * Lookup the contact based on their URL (which is the only unique thing we have at the moment)
				 */
	
				$r = q("SELECT * FROM `contact` WHERE `uid` = %d AND `url` = '%s' AND `self` = 0 LIMIT 1",
					intval(local_user()),
					dbesc($dfrn_url)
				);
	
				if(count($r)) {
					if(strlen($r[0]['dfrn_id'])) {

						/**
						 * We don't need to be here. It has already happened.
						 */

						notice( t("This introduction has already been accepted.") . EOL );
						return;
					}
					else
						$contact_record = $r[0];
				}
	
				if(is_array($contact_record)) {
					$r = q("UPDATE `contact` SET `ret-aes` = %d WHERE `id` = %d LIMIT 1",
						intval($aes_allow),
						intval($contact_record['id'])
					);
				}
				else {
	
					/**
					 * Scrape the other site's profile page to pick up the dfrn links, key, fn, and photo
					 */

					require_once('Scrape.php');
	
					$parms = scrape_dfrn($dfrn_url);
	
					if(! count($parms)) {
						notice( t('Profile location is not valid or does not contain profile information.') . EOL );
						return;
					}
					else {
						if(! x($parms,'fn'))
							notice( t('Warning: profile location has no identifiable owner name.') . EOL );
						if(! x($parms,'photo'))
							notice( t('Warning: profile location has no profile photo.') . EOL );
						$invalid = validate_dfrn($parms);		
						if($invalid) {
							notice( sprintf( tt("%d required parameter was not found at the given location",
												"%d required parameters were not found at the given location",
												$invalid), $invalid) . EOL );
							return;
						}
					}

					$dfrn_request = $parms['dfrn-request'];

                    /********* Escape the entire array ********/

					dbesc_array($parms);

					/******************************************/

					/**
					 * Create a contact record on our site for the other person
					 */

					$r = q("INSERT INTO `contact` ( `uid`, `created`,`url`, `name`, `nick`, `photo`, `site_pubkey`,
						`request`, `confirm`, `notify`, `poll`, `aes_allow`) 
						VALUES ( %d, '%s', '%s', '%s' , '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d)",
						intval(local_user()),
						datetime_convert(),
						dbesc($dfrn_url),
						$parms['fn'],
						$parms['nick'],
						$parms['photo'],
						$parms['key'],
						$parms['dfrn-request'],
						$parms['dfrn-confirm'],
						$parms['dfrn-notify'],
						$parms['dfrn-poll'],
						intval($aes_allow)
					);
				}

				if($r) {
					info( t("Introduction complete.") . EOL);
				}

				/**
				 * Allow the blocked remote notification to complete
				 */

				if(is_array($contact_record))
					$dfrn_request = $contact_record['request'];

				if(strlen($dfrn_request) && strlen($confirm_key))
					$s = fetch_url($dfrn_request . '?confirm_key=' . $confirm_key);
				
				// (ignore reply, nothing we can do it failed)

				goaway($dfrn_url);
				return; // NOTREACHED

			}

		}

 		// invalid/bogus request

		notice( t('Unrecoverable protocol error.') . EOL );
		goaway(z_path());
		return; // NOTREACHED
	}

	/**
	 * Otherwise:
	 * 
	 * Scenario 1:
	 * We are the requestee. A person from a remote cell has made an introduction 
	 * on our profile web page and clicked submit. We will use their DFRN-URL to 
	 * figure out how to contact their cell.  
	 *
	 * Scrape the originating DFRN-URL for everything we need. Create a contact record
	 * and an introduction to show our user next time he/she logs in.
	 * Finally redirect back to the requestor so that their site can record the request.
	 * If our user (the requestee) later confirms this request, a record of it will need 
	 * to exist on the requestor's cell in order for the confirmation process to complete.. 
	 *
	 * It's possible that neither the requestor or the requestee are logged in at the moment,
	 * and the requestor does not yet have any credentials to the requestee profile.
	 *
	 * Who is the requestee? We've already loaded their profile which means their nickname should be
	 * in $a->argv[1] and we should have their complete info in $a->profile.
	 *
	 */

	if(! (is_array($a->profile) && count($a->profile))) {
		notice( t('Profile unavailable.') . EOL);
		return;
	}

	$nickname       = $a->profile['nickname'];
	$notify_flags   = $a->profile['notify_flags'];
	$uid            = $a->profile['uid'];
	$maxreq         = intval($a->profile['maxreq']);
	$contact_record = null;
	$failed         = false;
	$parms          = null;


	if( x($_POST,'dfrn_url')) {

		/**
		 * Block friend request spam
		 */

		if($maxreq) {
			$r = q("SELECT * FROM `intro` WHERE `datetime` > '%s' AND `uid` = %d",
				dbesc(datetime_convert('UTC','UTC','now - 24 hours')),
				intval($uid)
			);
			if(count($r) > $maxreq) {
				notice( sprintf( t('%s has received too many connection requests today.'),  $a->profile['name']) . EOL);
				notice( t('Spam protection measures have been invoked.') . EOL);
				notice( t('Friends are advised to please try again in 24 hours.') . EOL);
				return;
			} 
		}

		/**
		 *
		 * Cleanup old introductions that remain blocked. 
		 * Also remove the contact record, but only if there is no existing relationship
		 *
		 */

		$r = q("SELECT `intro`.*, `intro`.`id` AS `iid`, `contact`.`id` AS `cid`, `contact`.`rel` 
			FROM `intro` LEFT JOIN `contact` on `intro`.`contact_id` = `contact`.`id`
			WHERE `intro`.`blocked` = 1 AND `contact`.`self` = 0 AND `intro`.`datetime` < UTC_TIMESTAMP() - INTERVAL 30 MINUTE ");
		if(count($r)) {
			foreach($r as $rr) {
				if(! $rr['rel']) {
					q("DELETE FROM `contact` WHERE `id` = %d LIMIT 1",
						intval($rr['cid'])
					);
				}
				q("DELETE FROM `intro` WHERE `id` = %d LIMIT 1",
					intval($rr['iid'])
				);
			}
		}

		$url = trim($_POST['dfrn_url']);
		if(! strlen($url)) {
			notice( t("Invalid locator") . EOL );
			return;
		}

		// Canonicalise email-style profile locator

		$url = webfinger_dfrn($url);

		if(substr($url,0,5) === 'stat:') {
			$network = 'stat';
			$url = substr($url,5);
		}
		else {
			$network = 'dfrn';
		}

		logger('dfrn_request: url: ' . $url);

		if(! strlen($url)) {
			notice( t("Unable to resolve your name at the provided location.") . EOL);			
			return;
		}


		if($network === 'dfrn') {
			$ret = q("SELECT * FROM `contact` WHERE `uid` = %d AND `url` = '%s' AND `self` = 0 LIMIT 1", 
				intval($uid),
				dbesc($url)
			);

			if(count($ret)) {
				if(strlen($ret[0]['issued_id'])) {
					notice( t('You have already introduced yourself here.') . EOL );
					return;
				}
				elseif($ret[0]['rel'] == REL_BUD) {
					notice( sprintf( t('Apparently you are already friends with %s.'), $a->profile['name']) . EOL);
					return;
				}
				else {
					$contact_record = $ret[0];
					$parms = array('dfrn-request' => $ret[0]['request']);
				}
			}

			$issued_id = random_string();

			if(is_array($contact_record)) {
				// There is a contact record but no issued_id, so this
				// is a reciprocal introduction from a known contact
				$r = q("UPDATE `contact` SET `issued_id` = '%s' WHERE `id` = %d LIMIT 1",
					dbesc($issued_id),
					intval($contact_record['id'])
				);
			}
			else {
				if(! validate_url($url)) {
					notice( t('Invalid profile URL.') . EOL);
					goaway(z_path() . '/' . $a->cmd);
					return; // NOTREACHED
				}

				if(! allowed_url($url)) {
					notice( t('Disallowed profile URL.') . EOL);
					goaway(z_path() . '/' . $a->cmd);
					return; // NOTREACHED
				}
			

				require_once('Scrape.php');

				$parms = scrape_dfrn($url);

				if(! count($parms)) {
					notice( t('Profile location is not valid or does not contain profile information.') . EOL );
					goaway(z_path() . '/' . $a->cmd);
				}
				else {
					if(! x($parms,'fn'))
						notice( t('Warning: profile location has no identifiable owner name.') . EOL );
					if(! x($parms,'photo'))
						notice( t('Warning: profile location has no profile photo.') . EOL );
					$invalid = validate_dfrn($parms);		
					if($invalid) {
						notice( sprintf( tt("%d required parameter was not found at the given location",
											"%d required parameters were not found at the given location",
											$invalid), $invalid) . EOL );
	
						return;
					}
				}


				$parms['url'] = $url;
				$parms['issued_id'] = $issued_id;


				dbesc_array($parms);
				$r = q("INSERT INTO `contact` ( `uid`, `created`, `url`, `name`, `nick`, `issued_id`, `photo`, `site_pubkey`,
					`request`, `confirm`, `notify`, `poll` )
					VALUES ( %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )",
					intval($uid),
					datetime_convert(),
					$parms['url'],
					$parms['fn'],
					$parms['nick'],
					$parms['issued_id'],
					$parms['photo'],
					$parms['key'],
					$parms['dfrn-request'],
					$parms['dfrn-confirm'],
					$parms['dfrn-notify'],
					$parms['dfrn-poll']
				);

				// find the contact record we just created
				if($r) {	
					$r = q("SELECT `id` FROM `contact` 
						WHERE `uid` = %d AND `url` = '%s' AND `issued_id` = '%s' LIMIT 1",
						intval($uid),
						$parms['url'],
						$parms['issued_id']
					);
					if(count($r)) 
						$contact_record = $r[0];
				}
	
			}
			if($r === false) {
				notice( t('Failed to update contact record.') . EOL );
				return;
			}

			$hash = random_string() . (string) time();   // Generate a confirm_key
	
			if(is_array($contact_record)) {
				$ret = q("INSERT INTO `intro` ( `uid`, `contact_id`, `blocked`, `knowyou`, `note`, `hash`, `datetime`)
					VALUES ( %d, %d, 1, %d, '%s', '%s', '%s' )",
					intval($uid),
					intval($contact_record['id']),
					((x($_POST,'knowyou') && ($_POST['knowyou'] == 1)) ? 1 : 0),
					dbesc(notags(trim($_POST['dfrn-request-message']))),
					dbesc($hash),
					dbesc(datetime_convert())
				);
			}
	
			// This notice will only be seen by the requestor if the requestor and requestee are on the same server.

			if(! $failed) 
				info( t('Your introduction has been sent.') . EOL );

			// "Homecoming" - send the requestor back to their site to record the introduction.

			$dfrn_url = bin2hex(z_path() . '/profile/' . $nickname);
			$aes_allow = ((function_exists('openssl_encrypt')) ? 1 : 0);

			goaway($parms['dfrn-request'] . "?dfrn_url=$dfrn_url" 
				. '&dfrn_version=' . DFRN_PROTOCOL_VERSION 
				. '&confirm_key='  . $hash 
				. (($aes_allow) ? "&aes_allow=1" : "")
			);
			// NOTREACHED
			// END $network === 'dfrn'
		}
		elseif($network === 'stat') {
			
			/**
			 *
			 * OStatus network
			 * Check contact existence
			 * Try and scrape together enough information to create a contact record, with us as REL_VIP
			 * Substitute our user's feed URL into $url template
			 * Send the subscriber home to subscribe
			 *
			 */

			$url = str_replace('{uri}', z_path() . '/dfrn_poll/' . $nickname, $url);
			goaway($url);
			// NOTREACHED
			// END $network === 'stat'
		}

	}	return;
}}




if(! function_exists('dfrn_request_content')) {
function dfrn_request_content(&$a) {

	

	if(($a->argc != 2) || (! count($a->profile)))
		return "";


	// "Homecoming". Make sure we're logged in to this site as the correct user. Then offer a confirm button
	// to send us to the post section to record the introduction.

	if(x($_GET,'dfrn_url')) {

		if(! local_user()) {
			info( t("Please login to confirm introduction.") . EOL );

			/* setup the return URL to come back to this page if they use openid */

			$stripped = str_replace('q=','',$a->query_string);
			$_SESSION['return_url'] = trim($stripped,'/');

			return login();
		}

		// Edge case, but can easily happen in the wild. This person is authenticated, 
		// but not as the person who needs to deal with this request.

		if ($a->user['nickname'] != $a->argv[1]) {
			notice( t("Incorrect identity currently logged in. Please login to <strong>this</strong> profile.") . EOL);
			return login();
		}

		$dfrn_url = notags(trim(hex2bin($_GET['dfrn_url'])));
		$aes_allow = (((x($_GET,'aes_allow')) && ($_GET['aes_allow'] == 1)) ? 1 : 0);
		$confirm_key = (x($_GET,'confirm_key') ? $_GET['confirm_key'] : "");
		$tpl = get_markup_template("dfrn_req_confirm.tpl");
		$o  = replace_macros($tpl,array(
			'$dfrn_url' => $dfrn_url,
			'$aes_allow' => (($aes_allow) ? '<input type="hidden" name="aes_allow" value="1" />' : "" ),
			'$confirm_key' => $confirm_key,
			'$welcome' => sprintf( t('Welcome home %s.'), $a->user['username']),
			'$please' => sprintf( t('Please confirm your introduction/connection request to %s.'), $dfrn_url),
			'$submit' => t('Confirm'),
			'$uid' => $_SESSION['uid'],
			'$nickname' => $a->user['nickname'],
			'dfrn_rawurl' => $_GET['dfrn_url']
			));
		return $o;

	}
	elseif((x($_GET,'confirm_key')) && strlen($_GET['confirm_key'])) { 

		// we are the requestee and it is now safe to send our user their introduction,
		// We could just unblock it, but first we have to jump through a few hoops to 
		// send an email, or even to find out if we need to send an email. 

		$intro = q("SELECT * FROM `intro` WHERE `hash` = '%s' LIMIT 1",
			dbesc($_GET['confirm_key'])
		);

		if(count($intro)) {

			$r = q("SELECT `contact`.*, `user`.* FROM `contact` LEFT JOIN `user` ON `contact`.`uid` = `user`.`uid`
				WHERE `contact`.`id` = %d LIMIT 1",
				intval($intro[0]['contact_id'])
			);

			$auto_confirm = false;

			if(count($r)) {
				if($r[0]['page_flags'] != PAGE_NORMAL)
					$auto_confirm = true;				
				if(($r[0]['notify_flags'] & NOTIFY_INTRO) && (! $auto_confirm)) {
					$email_tpl = get_intltext_template('request_notify_eml.tpl');
					$email = replace_macros($email_tpl, array(
						'$requestor' => ((strlen(stripslashes($r[0]['name']))) ? stripslashes($r[0]['name']) : t('[Name Withheld]')),
						'$url' => stripslashes($r[0]['url']),
						'$myname' => $r[0]['username'],
						'$siteurl' => z_path(),
						'$sitename' => $a->config['sitename']
					));
					$res = mail($r[0]['email'], 
					    t("Introduction received at ") . $a->config['sitename'],
						$email,
						'From: ' . t('Administrator') . '@' . $_SERVER['SERVER_NAME'] . "\n"
						. 'Content-type: text/plain; charset=UTF-8' . "\n"
						. 'Content-transfer-encoding: 8bit' );

					// This is a redundant notification - no point throwing errors if it fails.
				}
				if($auto_confirm) {
					require_once('mod/dfrn_confirm.php');
					$handsfree = array(
						'uid' => $r[0]['uid'],
						'node' => $r[0]['nickname'],
						'dfrn_id' => $r[0]['issued_id'],
						'intro_id' => $intro[0]['id'],
						'duplex' => (($r[0]['page_flags'] == PAGE_FREELOVE) ? 1 : 0)
					);
					dfrn_confirm_post($a,$handsfree);
				}

			}

			if(! $auto_confirm) {

				// If we are auto_confirming, this record will have already been nuked
				// in dfrn_confirm_post()

				$r = q("UPDATE `intro` SET `blocked` = 0 WHERE `hash` = '%s' LIMIT 1",
					dbesc($_GET['confirm_key'])
				);
			}
		}
		killme();
		return; // NOTREACHED
	}
	else {

		/**
		 * Normal web request. Display our user's introduction form.
		 */
 
		if((get_config('system','block_public')) && (! local_user()) && (! remote_user())) {
			notice( t('Public access denied.') . EOL);
			return;
		}


		/**
		 * Try to auto-fill the profile address
		 */

		if(local_user()) {
			if(strlen($a->path)) {
				$myaddr = z_path() . '/profile/' . $a->user['nickname'];
			}
			else {
				$myaddr = $a->user['nickname'] . '@' . substr(z_path(), strpos(z_path(),'://') + 3 );
			}
		}
		elseif(x($_GET,'addr')) {
			$myaddr = hex2bin($_GET['addr']);
		}
		else {
			/* $_GET variables are already urldecoded */ 
			$myaddr = ((x($_GET,'address')) ? $_GET['address'] : '');
		}

		/**
		 *
		 * The auto_request form only has the profile address
		 * because nobody is going to read the comments and 
		 * it doesn't matter if they know you or not.
		 *
		 */

		if($a->profile['page_flags'] == PAGE_NORMAL)
			$tpl = get_markup_template('dfrn_request.tpl');
		else
			$tpl = get_markup_template('auto_request.tpl');

		$o .= replace_macros($tpl,array(
			'$header' => t('Friend/Connection Request'),
			'$desc' => t('Examples: jojo@demo.friendika.com, http://demo.friendika.com/profile/jojo, testuser@identi.ca'),
			'$pls_answer' => t('Please answer the following:'),
			'$does_know' => sprintf( t('Does %s know you?'),$a->profile['name']),
			'$yes' => t('Yes'),
			'$no' => t('No'),
			'$add_note' => t('Add a personal note:'),
			'$page_desc' => t("Please enter your 'Identity Address' from one of the following supported social networks:"),
			'$friendika' => t('Friendika'),
			'$statusnet' => t('StatusNet/Federated Social Web'),
			'$private_net' => t("Private \x28secure\x29 network"),
			'$public_net' => t("Public \x28insecure\x29 network"),
			'$your_address' => t('Your Identity Address:'),
			'$submit' => t('Submit Request'),
			'$cancel' => t('Cancel'),
			'$nickname' => $a->argv[1],
			'$name' => $a->profile['name'],
			'$myaddr' => $myaddr
		));
		return $o;
	}

	return; // Somebody is fishing.
}}
