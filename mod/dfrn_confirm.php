<?php

/*
 * Module: dfrn_confirm
 * Purpose: Friendship acceptance for DFRN contacts
 *
 * There are two possible entry points and three scenarios.
 *
 *   1. A form was submitted by our user approving a friendship that originated elsewhere.
 *      This may also be called from dfrn_request to automatically approve a friendship.
 *
 *   2. We may be the target or other side of the conversation to scenario 1, and will 
 *      interact with that process on our own user's behalf.
 *   
 */

function dfrn_confirm_post(&$a,$handsfree = null) {

	if(is_array($handsfree)) {

		/**
		 * We were called directly from dfrn_request due to automatic friend acceptance.
		 * Any $_POST parameters we may require are supplied in the $handsfree array.
		 *
		 */

		$node = $handsfree['node'];
		$a->interactive = false; // notice() becomes a no-op since nobody is there to see it

	}
	else {
		if($a->argc > 1)
			$node = $a->argv[1];
	}

		/**
		 *
		 * Main entry point. Scenario 1. Our user received a friend request notification (perhaps 
		 * from another site) and clicked 'Approve'. 
		 * $POST['source_url'] is not set. If it is, it indicates Scenario 2.
		 *
		 * We may also have been called directly from dfrn_request ($handsfree != null) due to 
		 * this being a page type which supports automatic friend acceptance. That is also Scenario 1
		 * since we are operating on behalf of our registered user to approve a friendship.
		 *
		 */

	if(! x($_POST,'source_url')) {

		$uid = ((is_array($handsfree)) ? $handsfree['uid'] : local_user());

		if(! $uid) {
			notice( t('Permission denied.') . EOL );
			return;
		}	

		$user = q("SELECT * FROM `user` WHERE `uid` = %d LIMIT 1",
			intval($uid)
		);

		if(! $user) {
			notice( t('Profile not found.') . EOL );
			return;
		}	


		// These data elements may come from either the friend request notification form or $handsfree array.

		if(is_array($handsfree)) {
			logger('dfrn_confirm: Confirm in handsfree mode');
			$dfrn_id   = $handsfree['dfrn_id'];
			$intro_id  = $handsfree['intro_id'];
			$duplex    = $handsfree['duplex'];
		}
		else {
			$dfrn_id  = ((x($_POST,'dfrn_id'))    ? notags(trim($_POST['dfrn_id'])) : "");
			$intro_id = ((x($_POST,'intro_id'))   ? intval($_POST['intro_id'])      : 0 );
			$duplex   = ((x($_POST,'duplex'))     ? intval($_POST['duplex'])        : 0 );
			$cid      = ((x($_POST,'contact_id')) ? intval($_POST['contact_id'])    : 0 );
		}

		/**
		 *
		 * Ensure that dfrn_id has precedence when we go to find the contact record.
		 * We only want to search based on contact id if there is no dfrn_id, 
		 * e.g. for OStatus network followers.
		 *
		 */

		if(strlen($dfrn_id))
			$cid = 0;

		logger('dfrn_confirm: Confirming request for dfrn_id (issued) ' . $dfrn_id);
		if($cid)
			logger('dfrn_confirm: Confirming follower with contact_id: ' . $cid);


		/**
		 *
		 * The other person will have been issued an ID when they first requested friendship.
		 * Locate their record. At this time, their record will have both pending and blocked set to 1. 
		 * There won't be any dfrn_id if this is a network follower, so use the contact_id instead.
		 *
		 */

		$r = q("SELECT * FROM `contact` WHERE ( ( `issued_id` != '' AND `issued_id` = '%s' ) OR ( `id` = %d AND `id` != 0 ) ) AND `uid` = %d LIMIT 1",
			dbesc($dfrn_id),
			intval($cid),
			intval($uid)
		);

		if(! count($r)) {
			logger('dfrn_confirm: Contact not found in DB.'); 
			notice( t('Contact not found.') . EOL );
			return;
		}

		$contact = $r[0];

		$contact_id   = $contact['id'];
		$relation     = $contact['rel'];
		$site_pubkey  = $contact['site_pubkey'];
		$dfrn_confirm = $contact['confirm'];
		$aes_allow    = $contact['aes_allow'];

		$network = ((strlen($contact['issued_id'])) ? 'dfrn' : 'stat');

		if($network === 'dfrn') {

			/**
			 *
			 * Generate a key pair for all further communications with this person.
			 * We have a keypair for every contact, and a site key for unknown people.
			 * This provides a means to carry on relationships with other people if 
			 * any single key is compromised. It is a robust key. We're much more 
			 * worried about key leakage than anybody cracking it.  
			 *
			 */

			$res = openssl_pkey_new(array(
				'digest_alg' => 'sha1',
				'private_key_bits' => 4096,
				'encrypt_key' => false )
			);

			$private_key = '';

			openssl_pkey_export($res, $private_key);

			$pubkey = openssl_pkey_get_details($res);
			$public_key = $pubkey["key"];

			// Save the private key. Send them the public key.

			$r = q("UPDATE `contact` SET `prvkey` = '%s' WHERE `id` = %d AND `uid` = %d LIMIT 1",
				dbesc($private_key),
				intval($contact_id),
				intval($uid) 
			);

			$params = array();

			/**
			 *
			 * Per the DFRN protocol, we will verify both ends by encrypting the dfrn_id with our 
			 * site private key (person on the other end can decrypt it with our site public key).
			 * Then encrypt our profile URL with the other person's site public key. They can decrypt
			 * it with their site private key. If the decryption on the other end fails for either
			 * item, it indicates tampering or key failure on at least one site and we will not be 
			 * able to provide a secure communication pathway.
			 *
			 * If other site is willing to accept full encryption, (aes_allow is 1 AND we have php5.3 
			 * or later) then we encrypt the personal public key we send them using AES-256-CBC and a 
			 * random key which is encrypted with their site public key.  
			 *
			 */

			$src_aes_key = random_string();

			$result = '';
			openssl_private_encrypt($dfrn_id,$result,$user[0]['prvkey']);

			$params['dfrn_id'] = bin2hex($result);
			$params['public_key'] = $public_key;


			$my_url = z_path() . '/profile/' . $user[0]['nickname'];

			openssl_public_encrypt($my_url, $params['source_url'], $site_pubkey);
			$params['source_url'] = bin2hex($params['source_url']);

			if($aes_allow && function_exists('openssl_encrypt')) {
				openssl_public_encrypt($src_aes_key, $params['aes_key'], $site_pubkey);
				$params['aes_key'] = bin2hex($params['aes_key']);
				$params['public_key'] = bin2hex(openssl_encrypt($public_key,'AES-256-CBC',$src_aes_key));
			}

			$params['dfrn_version'] = DFRN_PROTOCOL_VERSION ;
			if($duplex == 1)
				$params['duplex'] = 1;

			logger('dfrn_confirm: Confirm: posting data to ' . $dfrn_confirm . ': ' . print_r($params,true), LOGGER_DATA);

			/**
			 *
			 * POST all this stuff to the other site.
			 * Temporarily raise the network timeout to 120 seconds because the default 60
			 * doesn't always give the other side quite enough time to decrypt everything.
			 *
			 */

			$a->config['system']['curl_timeout'] = 120;

			$res = post_url($dfrn_confirm,$params);

			logger('dfrn_confirm: Confirm: received data: ' . $res, LOGGER_DATA);

			// Now figure out what they responded. Try to be robust if the remote site is 
			// having difficulty and throwing up errors of some kind. 

			$leading_junk = substr($res,0,strpos($res,'<?xml'));

			$res = substr($res,strpos($res,'<?xml'));
			if(! strlen($res)) {

					// No XML at all, this exchange is messed up really bad.
					// We shouldn't proceed, because the xml parser might choke,
					// and $status is going to be zero, which indicates success.
					// We can hardly call this a success.  
	
				notice( t('Response from remote site was not understood.') . EOL);
				return;
			}

			if(strlen($leading_junk) && get_config('system','debugging')) {
	
					// This might be more common. Mixed error text and some XML.
					// If we're configured for debugging, show the text. Proceed in either case.

				notice( t('Unexpected response from remote site: ') . EOL . $leading_junk . EOL );
			}

			$xml = parse_xml_string($res);
			$status = (int) $xml->status;
			$message = unxmlify($xml->message);   // human readable text of what may have gone wrong.
			switch($status) {
				case 0:
					notice( t("Confirmation completed successfully.") . EOL);
					if(strlen($message))
						notice( t('Remote site reported: ') . $message . EOL);
					break;
				case 1:
					// birthday paradox - generate new dfrn_id and fall through.
					$new_dfrn_id = random_string();
					$r = q("UPDATE contact SET `issued_id` = '%s' WHERE `id` = %d AND `uid` = %d LIMIT 1",
						dbesc($new_dfrn_id),
						intval($contact_id),
						intval($uid) 
					);

				case 2:
					notice( t("Temporary failure. Please wait and try again.") . EOL);
					if(strlen($message))
						notice( t('Remote site reported: ') . $message . EOL);
					break;


				case 3:
					notice( t("Introduction failed or was revoked.") . EOL);
					if(strlen($message))
						notice( t('Remote site reported: ') . $message . EOL);
					break;
				}

			if(($status == 0) && ($intro_id)) {
	
				// Success. Delete the notification.
	
				$r = q("DELETE FROM `intro` WHERE `id` = %d AND `uid` = %d LIMIT 1",
					intval($intro_id),
					intval($uid)
				);
				
			}

			if($status != 0) 
				return;
		}


		/*
		 *
		 * We have now established a relationship with the other site.
		 * Let's make our own personal copy of their profile photo so we don't have
		 * to always load it from their site.
		 *
		 * We will also update the contact record with the nature and scope of the relationship.
		 *
		 */

		require_once("Photo.php");

		$photos = import_profile_photo($contact['photo'],$uid,$contact_id);
		
		logger('dfrn_confirm: confirm - imported photos');

		if($network === 'dfrn') {

			$new_relation = REL_VIP;
			if(($relation == REL_FAN) || ($duplex))
				$new_relation = REL_BUD;

			if(($relation == REL_FAN) && ($duplex))
				$duplex = 0;

			$r = q("UPDATE `contact` SET `photo` = '%s', 
				`thumb` = '%s',
				`micro` = '%s', 
				`rel` = %d, 
				`name_date` = '%s', 
				`uri_date` = '%s', 
				`avatar_date` = '%s', 
				`blocked` = 0, 
				`pending` = 0,
				`duplex` = %d,
				`network` = 'dfrn' WHERE `id` = %d LIMIT 1
			",
				dbesc($photos[0]),
				dbesc($photos[1]),
				dbesc($photos[2]),
				intval($new_relation),
				dbesc(datetime_convert()),
				dbesc(datetime_convert()),
				dbesc(datetime_convert()),
				intval($duplex),
				intval($contact_id)
			);
		}
		else {  
			// $network !== 'dfrn'

			$notify = '';
			$poll   = '';

			$arr = lrdd($contact['url']);
			if(count($arr)) {
				foreach($arr as $link) {
					if($link['@attributes']['rel'] === 'salmon')
						$notify = $link['@attributes']['href'];
					if($link['@attributes']['rel'] === NAMESPACE_FEED)
						$poll = $link['@attributes']['href'];
				}
			}

			$r = q("DELETE FROM `intro` WHERE `id` = %d AND `uid` = %d LIMIT 1",
				intval($intro_id),
				intval($uid)
			);


			$r = q("UPDATE `contact` SET `photo` = '%s', 
				`thumb` = '%s',
				`micro` = '%s', 
				`name_date` = '%s', 
				`uri_date` = '%s', 
				`avatar_date` = '%s', 
				`notify` = '%s',
				`poll` = '%s',
				`blocked` = 0, 
				`pending` = 0,
				`network` = 'stat'
				WHERE `id` = %d LIMIT 1
			",
				dbesc($photos[0]),
				dbesc($photos[1]),
				dbesc($photos[2]),
				dbesc(datetime_convert()),
				dbesc(datetime_convert()),
				dbesc(datetime_convert()),
				dbesc($notify),
				dbesc($poll),
				intval($contact_id)
			);			
		}

		if($r === false)
				notice( t('Unable to set contact photo.') . EOL);

		// reload contact info

		$r = q("SELECT * FROM `contact` WHERE `id` = %d LIMIT 1",
			intval($contact_id)
		);
		if(count($r))
			$contact = $r[0];
		else
			$contact = null;

		// Send a new friend post if we are allowed to...

		$r = q("SELECT `hide_friends` FROM `profile` WHERE `uid` = %d AND `is_default` = 1 LIMIT 1",
			intval($uid)
		);
		if((count($r)) && ($r[0]['hide_friends'] == 0) && (is_array($contact)) &&  isset($new_relation) && ($new_relation == REL_BUD)) {

			require_once('include/items.php');

			$self = q("SELECT * FROM `contact` WHERE `self` = 1 AND `uid` = %d LIMIT 1",
				intval($uid)
			);

			if(count($self)) {

				$arr = array();
				$arr['uri'] = $arr['parent_uri'] = item_new_uri($a->get_hostname(), $uid); 
				$arr['uid'] = $uid;
				$arr['contact_id'] = $self[0]['id'];
				$arr['wall'] = 1;
				$arr['type'] = 'wall';
				$arr['gravity'] = 0;
				$arr['author_name'] = $arr['owner_name'] = $self[0]['name'];
				$arr['author_link'] = $arr['owner_link'] = $self[0]['url'];
				$arr['author_avatar'] = $arr['owner_avatar'] = $self[0]['thumb'];
				$arr['verb'] = ACTIVITY_FRIEND;
				$arr['object_type'] = ACTIVITY_OBJ_PERSON;
				
				$A = '[url=' . $self[0]['url'] . ']' . $self[0]['name'] . '[/url]';
				$B = '[url=' . $contact['url'] . ']' . $contact['name'] . '[/url]';
				$BPhoto = '[url=' . $contact['url'] . ']' . '[img]' . $contact['thumb'] . '[/img][/url]';
				$arr['body'] =  sprintf( t('%1$s is now friends with %2$s'), $A, $B)."\n\n\n".$Bphoto;

				$arr['object'] = '<object><type>' . ACTIVITY_OBJ_PERSON . '</type><title>' . $contact['name'] . '</title>'
					. '<id>' . $contact['url'] . '/' . $contact['name'] . '</id>';
				$arr['object'] .= '<link>' . xmlify('<link rel="alternate" type="text/html" href="' . $contact['url'] . '" />' . "\n");
				$arr['object'] .= xmlify('<link rel="photo" type="image/jpeg" href="' . $contact['thumb'] . '" />' . "\n");
				$arr['object'] .= '</link></object>' . "\n";
				$arr['last_child'] = 1;

				$arr['allow_cid'] = $user[0]['allow_cid'];
				$arr['allow_gid'] = $user[0]['allow_gid'];
				$arr['deny_cid']  = $user[0]['deny_cid'];
				$arr['deny_gid']  = $user[0]['deny_gid'];

				$i = item_store($arr);
				if($i)
			    	proc_run('php',"include/notifier.php","activity","$i");

			}

		}
		// Let's send our user to the contact editor in case they want to
		// do anything special with this new friend.

		if($handsfree === null)
			goaway(z_path() . '/contacts/' . intval($contact_id));
		else
			return;  
		//NOTREACHED
	}

	/**
	 *
	 *
	 * End of Scenario 1. [Local confirmation of remote friend request].
	 *
	 * Begin Scenario 2. This is the remote response to the above scenario.
	 * This will take place on the site that originally initiated the friend request.
	 * In the section above where the confirming party makes a POST and 
	 * retrieves xml status information, they are communicating with the following code.
	 *
	 */

	if(x($_POST,'source_url')) {

		// We are processing an external confirmation to an introduction created by our user.

		$public_key = ((x($_POST,'public_key'))   ? $_POST['public_key']           : '');
		$dfrn_id    = ((x($_POST,'dfrn_id'))      ? hex2bin($_POST['dfrn_id'])     : '');
		$source_url = ((x($_POST,'source_url'))   ? hex2bin($_POST['source_url'])  : '');
		$aes_key    = ((x($_POST,'aes_key'))      ? $_POST['aes_key']              : '');
		$duplex     = ((x($_POST,'duplex'))       ? intval($_POST['duplex'])       : 0 );
		$version_id = ((x($_POST,'dfrn_version')) ? (float) $_POST['dfrn_version'] : 2.0);
	
		logger('dfrn_confirm: requestee contacted: ' . $node);

		logger('dfrn_confirm: request: POST=' . print_r($_POST,true), LOGGER_DATA);

		// If $aes_key is set, both of these items require unpacking from the hex transport encoding.

		if(x($aes_key)) {
			$aes_key = hex2bin($aes_key);
			$public_key = hex2bin($public_key);
		}

		// Find our user's account

		$r = q("SELECT * FROM `user` WHERE `nickname` = '%s' LIMIT 1",
			dbesc($node));

		if(! count($r)) {
			$message = sprintf(t('No user record found for \'%s\' '), $node);
			xml_status(3,$message); // failure
			// NOTREACHED
		}

		$my_prvkey = $r[0]['prvkey'];
		$local_uid = $r[0]['uid'];


		if(! strstr($my_prvkey,'PRIVATE KEY')) {
			$message = t('Our site encryption key is apparently messed up.');
			xml_status(3,$message);
		}

		// verify everything

		$decrypted_source_url = "";
		openssl_private_decrypt($source_url,$decrypted_source_url,$my_prvkey);


		if(! strlen($decrypted_source_url)) {
			$message = t('Empty site URL was provided or URL could not be decrypted by us.');
			xml_status(3,$message);
			// NOTREACHED
		}

		$ret = q("SELECT * FROM `contact` WHERE `url` = '%s' AND `uid` = %d LIMIT 1",
			dbesc($decrypted_source_url),
			intval($local_uid)
		);

		if(! count($ret)) {
			// this is either a bogus confirmation (?) or we deleted the original introduction.
			$message = t('Contact record was not found for you on our site.');
			xml_status(3,$message);
			return; // NOTREACHED 
		}

		$relation = $ret[0]['rel'];

		// Decrypt all this stuff we just received

		$foreign_pubkey = $ret[0]['site_pubkey'];
		$dfrn_record    = $ret[0]['id'];

		$decrypted_dfrn_id = "";
		openssl_public_decrypt($dfrn_id,$decrypted_dfrn_id,$foreign_pubkey);

		if(strlen($aes_key)) {
			$decrypted_aes_key = "";
			openssl_private_decrypt($aes_key,$decrypted_aes_key,$my_prvkey);
			$dfrn_pubkey = openssl_decrypt($public_key,'AES-256-CBC',$decrypted_aes_key);
		}
		else {
			$dfrn_pubkey = $public_key;
		}

		$r = q("SELECT * FROM `contact` WHERE `dfrn_id` = '%s' LIMIT 1",
			dbesc($decrypted_dfrn_id)
		);
		if(count($r)) {
			$message = t('The ID provided by your system is a duplicate on our system. It should work if you try again.');
			xml_status(1,$message); // Birthday paradox - duplicate dfrn_id
			// NOTREACHED
		}

		$r = q("UPDATE `contact` SET `dfrn_id` = '%s', `pubkey` = '%s' WHERE `id` = %d LIMIT 1",
			dbesc($decrypted_dfrn_id),
			dbesc($dfrn_pubkey),
			intval($dfrn_record)
		);
		if(! count($r)) {
			$message = t('Unable to set your contact credentials on our system.');
			xml_status(3,$message);
		}

		// We're good but now we have to scrape the profile photo and send notifications.



		$r = q("SELECT `photo` FROM `contact` WHERE `id` = %d LIMIT 1",
			intval($dfrn_record));

		if(count($r))
			$photo = $r[0]['photo'];
		else
			$photo = z_path() . '/images/default-profile.jpg';
				
		require_once("Photo.php");

		$photos = import_profile_photo($photo,$local_uid,$dfrn_record);

		logger('dfrn_confirm: request - photos imported');

		$new_relation = REL_FAN;
		if(($relation == REL_VIP) || ($duplex))
			$new_relation = REL_BUD;

		if(($relation == REL_VIP) && ($duplex))
			$duplex = 0;

		$r = q("UPDATE `contact` SET 
			`photo` = '%s', 
			`thumb` = '%s', 
			`micro` = '%s',
			`rel` = %d, 
			`name_date` = '%s', 
			`uri_date` = '%s', 
			`avatar_date` = '%s', 
			`blocked` = 0, 
			`pending` = 0,
			`duplex` = %d, 
			`network` = 'dfrn' WHERE `id` = %d LIMIT 1
		",
			dbesc($photos[0]),
			dbesc($photos[1]),
			dbesc($photos[2]),
			intval($new_relation),
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			intval($duplex),
			intval($dfrn_record)
		);
		if($r === false) {    // indicates schema is messed up or total db failure
			$message = t('Unable to update your contact profile details on our system');
			xml_status(3,$message);
		}

		// Otherwise everything seems to have worked and we are almost done. Yay!
		// Send an email notification

		logger('dfrn_confirm: request: info updated');

		$r = q("SELECT `contact`.*, `user`.* FROM `contact` LEFT JOIN `user` ON `contact`.`uid` = `user`.`uid`
			WHERE `contact`.`id` = %d LIMIT 1",
			intval($dfrn_record)
		);
		if((count($r)) && ($r[0]['notify_flags'] & NOTIFY_CONFIRM)) {

			push_lang($r[0]['language']);
			$tpl = (($new_relation == REL_BUD) 
				? get_intltext_template('friend_complete_eml.tpl')
				: get_intltext_template('intro_complete_eml.tpl'));
		
			$email_tpl = replace_macros($tpl, array(
				'$sitename' => $a->config['sitename'],
				'$siteurl' =>  z_path(),
				'$username' => $r[0]['username'],
				'$email' => $r[0]['email'],
				'$fn' => $r[0]['name'],
				'$dfrn_url' => $r[0]['url'],
				'$uid' => $newuid )
			);
	
			$res = mail($r[0]['email'], sprintf( t("Connection accepted at %s") , $a->config['sitename']),
				$email_tpl,
				'From: ' . t('Administrator') . '@' . $_SERVER['SERVER_NAME'] . "\n"
				. 'Content-type: text/plain; charset=UTF-8' . "\n"
				. 'Content-transfer-encoding: 8bit' );

			if(!$res) {
				// pointless throwing an error here and confusing the person at the other end of the wire.
			}
			pop_lang();
		}
		xml_status(0); // Success
		return; // NOTREACHED

			////////////////////// End of this scenario ///////////////////////////////////////////////
	}

	// somebody arrived here by mistake or they are fishing. Send them to the homepage.

	goaway(z_path());
	// NOTREACHED

}
