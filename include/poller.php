<?php

require_once("boot.php");


function poller_run($argv, $argc){
	global $a, $db;

	if(is_null($a)) {
		$a = new App;
	}
  
	if(is_null($db)) {
	    @include(".htconfig.php");
    	require_once("dba.php");
	    $db = new dba($db_host, $db_user, $db_pass, $db_data);
    	unset($db_host, $db_user, $db_pass, $db_data);
  	};


	require_once('include/session.php');
	require_once('include/datetime.php');
	require_once('library/simplepie/simplepie.inc');
	require_once('include/items.php');
	require_once('include/Contact.php');
	require_once('include/email.php');

	load_config('config');
	load_config('system');

	$a->set_baseurl(get_config('system','url'));

	load_hooks();

	logger('poller: start');
	
	// run queue delivery process in the background

	proc_run('php',"include/queue.php");
	
	// once daily run expire in background

	$d1 = get_config('system','last_expire_day');
	$d2 = intval(datetime_convert('UTC','UTC','now','d'));

	if($d2 != intval($d1)) {
		set_config('system','last_expire_day',$d2);
		proc_run('php','include/expire.php');
	}

	// clear old cache
	q("DELETE FROM `cache` WHERE `updated` < '%s'",
		dbesc(datetime_convert('UTC','UTC',"now - 30 days")));

	$manual_id  = 0;
	$generation = 0;
	$hub_update = false;
	$force      = false;
	$restart    = false;

	if(($argc > 1) && ($argv[1] == 'force'))
		$force = true;

	if(($argc > 1) && ($argv[1] == 'restart')) {
		$restart = true;
		$generation = intval($argv[2]);
		if(! $generation)
			killme();		
	}

	if(($argc > 1) && intval($argv[1])) {
		$manual_id = intval($argv[1]);
		$force     = true;
	}

	$sql_extra = (($manual_id) ? " AND `id` = $manual_id " : "");

	reload_plugins();

	$d = datetime_convert();

	if(! $restart)
		call_hooks('cron', $d);


	$contacts = q("SELECT `id` FROM `contact` 
		WHERE ( `rel` = %d OR `rel` = %d ) AND `poll` != ''
		$sql_extra 
		AND `self` = 0 AND `blocked` = 0 AND `readonly` = 0 ORDER BY RAND()",
		intval(REL_FAN),
		intval(REL_BUD)
	);

	if(! count($contacts)) {
		return;
	}

	foreach($contacts as $c) {

		$res = q("SELECT * FROM `contact` WHERE `id` = %d LIMIT 1",
			intval($c['id'])
		);

		if(! count($res))
			continue;

		foreach($res as $contact) {

			$xml = false;

			if($manual_id)
				$contact['last_update'] = '0000-00-00 00:00:00';

			if($contact['priority'] || $contact['subhub']) {

				$hub_update = true;
				$update     = false;

				$t = $contact['last_update'];

				// We should be getting everything via a hub. But just to be sure, let's check once a day.
				// (You can make this more or less frequent if desired by setting 'pushpoll_frequency' appropriately)
				// This also lets us update our subscription to the hub, and add or replace hubs in case it
				// changed. We will only update hubs once a day, regardless of 'pushpoll_frequency'. 


				if($contact['subhub']) {
					$interval = get_config('system','pushpoll_frequency');
					$contact['priority'] = (($interval !== false) ? intval($interval) : 3);
					$hub_update = false;
	
					if((datetime_convert('UTC','UTC', 'now') > datetime_convert('UTC','UTC', $t . " + 1 day")) || $force)
							$hub_update = true;
				}

				/**
				 * Based on $contact['priority'], should we poll this site now? Or later?
				 */			

				switch ($contact['priority']) {
					case 5:
						if(datetime_convert('UTC','UTC', 'now') > datetime_convert('UTC','UTC', $t . " + 1 month"))
							$update = true;
						break;					
					case 4:
						if(datetime_convert('UTC','UTC', 'now') > datetime_convert('UTC','UTC', $t . " + 1 week"))
							$update = true;
						break;
					case 3:
						if(datetime_convert('UTC','UTC', 'now') > datetime_convert('UTC','UTC', $t . " + 1 day"))
							$update = true;
						break;
					case 2:
						if(datetime_convert('UTC','UTC', 'now') > datetime_convert('UTC','UTC', $t . " + 12 hour"))
							$update = true;
						break;
					case 1:
					default:
						if(datetime_convert('UTC','UTC', 'now') > datetime_convert('UTC','UTC', $t . " + 1 hour"))
							$update = true;
						break;
				}
				if((! $update) && (! $force))
					continue;
			}

			// Check to see if we are running out of memory - if so spawn a new process and kill this one

			$avail_memory = return_bytes(ini_get('memory_limit'));
			$memused = memory_get_peak_usage(true);
			if(intval($avail_memory)) {
				if(($memused / $avail_memory) > 0.95) {
					if($generation + 1 > 10) {
						logger('poller: maximum number of spawns exceeded. Terminating.');
						killme();
					}
					logger('poller: memory exceeded. ' . $memused . ' bytes used. Spawning new poll.');
					proc_run('php', 'include/poller.php', 'restart', (string) $generation + 1);
					killme();
				}
			}

			$importer_uid = $contact['uid'];
		
			$r = q("SELECT * FROM `contact` WHERE `uid` = %d AND `self` = 1 LIMIT 1",
				intval($importer_uid)
			);
			if(! count($r))
				continue;

			$importer = $r[0];

			logger("poller: poll: IMPORTER: {$importer['name']}, CONTACT: {$contact['name']}");

			$last_update = (($contact['last_update'] === '0000-00-00 00:00:00') 
				? datetime_convert('UTC','UTC','now - 30 days', ATOM_TIME)
				: datetime_convert('UTC','UTC',$contact['last_update'], ATOM_TIME)
			);

			if($contact['network'] === NETWORK_DFRN) {

				$idtosend = $orig_id = (($contact['dfrn_id']) ? $contact['dfrn_id'] : $contact['issued_id']);

				if(intval($contact['duplex']) && $contact['dfrn_id'])
					$idtosend = '0:' . $orig_id;
				if(intval($contact['duplex']) && $contact['issued_id'])
					$idtosend = '1:' . $orig_id;

				// they have permission to write to us. We already filtered this in the contact query.
				$perm = 'rw';

				$url = $contact['poll'] . '?dfrn_id=' . $idtosend 
					. '&dfrn_version=' . DFRN_PROTOCOL_VERSION 
					. '&type=data&last_update=' . $last_update 
					. '&perm=' . $perm ;
	
				$handshake_xml = fetch_url($url);

				logger('poller: handshake with url ' . $url . ' returns xml: ' . $handshake_xml, LOGGER_DATA);


				if(! $handshake_xml) {
					logger("poller: $url appears to be dead - marking for death ");
					// dead connection - might be a transient event, or this might
					// mean the software was uninstalled or the domain expired. 
					// Will keep trying for one month.
					mark_for_death($contact);

					// set the last_update so we don't keep polling

					$r = q("UPDATE `contact` SET `last_update` = '%s' WHERE `id` = %d LIMIT 1",
						dbesc(datetime_convert()),
						intval($contact['id'])
					);

					continue;
				}

				if(! strstr($handshake_xml,'<?xml')) {
					logger('poller: response from ' . $url . ' did not contain XML.');
					$r = q("UPDATE `contact` SET `last_update` = '%s' WHERE `id` = %d LIMIT 1",
						dbesc(datetime_convert()),
						intval($contact['id'])
					);
					continue;
				}


				$res = parse_xml_string($handshake_xml);
	
				if(intval($res->status) == 1) {
					logger("poller: $url replied status 1 - marking for death ");

					// we may not be friends anymore. Will keep trying for one month.
					// set the last_update so we don't keep polling

					$r = q("UPDATE `contact` SET `last_update` = '%s' WHERE `id` = %d LIMIT 1",
						dbesc(datetime_convert()),
						intval($contact['id'])
					);

					mark_for_death($contact);
				}
				else {
					if($contact['term_date'] != '0000-00-00 00:00:00') {
						logger("poller: $url back from the dead - removing mark for death");
						unmark_for_death($contact);
					}
				}

				if((intval($res->status) != 0) || (! strlen($res->challenge)) || (! strlen($res->dfrn_id)))
					continue;

				$postvars = array();

				$sent_dfrn_id = hex2bin((string) $res->dfrn_id);
				$challenge    = hex2bin((string) $res->challenge);

				$final_dfrn_id = '';

				if(($contact['duplex']) && strlen($contact['prvkey'])) {
					openssl_private_decrypt($sent_dfrn_id,$final_dfrn_id,$contact['prvkey']);
					openssl_private_decrypt($challenge,$postvars['challenge'],$contact['prvkey']);
				}
				else {
					openssl_public_decrypt($sent_dfrn_id,$final_dfrn_id,$contact['pubkey']);
					openssl_public_decrypt($challenge,$postvars['challenge'],$contact['pubkey']);
				}

				$final_dfrn_id = substr($final_dfrn_id, 0, strpos($final_dfrn_id, '.'));

				if(strpos($final_dfrn_id,':') == 1)
					$final_dfrn_id = substr($final_dfrn_id,2);

				if($final_dfrn_id != $orig_id) {
					logger('poller: ID did not decode: ' . $contact['id'] . ' orig: ' . $orig_id . ' final: ' . $final_dfrn_id);	
					// did not decode properly - cannot trust this site 
					continue;
				}

				$postvars['dfrn_id'] = $idtosend;
				$postvars['dfrn_version'] = DFRN_PROTOCOL_VERSION;
				$postvars['perm'] = 'rw';

				$xml = post_url($contact['poll'],$postvars);
			}
			elseif(($contact['network'] === NETWORK_OSTATUS) 
				|| ($contact['network'] === NETWORK_DIASPORA)
				|| ($contact['network'] === NETWORK_FEED) ) {

				// Upgrading DB fields from an older Friendika version
				// Will only do this once per notify-enabled OStatus contact
				// or if relationship changes

				$stat_writeable = ((($contact['notify']) && ($contact['rel'] == REL_VIP || $contact['rel'] == REL_BUD)) ? 1 : 0);

				if($stat_writeable != $contact['writable']) {
					q("UPDATE `contact` SET `writable` = %d WHERE `id` = %d LIMIT 1",
						intval($stat_writeable),
						intval($contact['id'])
					);
				}

				// Are we allowed to import from this person?

				if($contact['rel'] == REL_VIP || $contact['blocked'] || $contact['readonly'])
					continue;

				$xml = fetch_url($contact['poll']);
			}
			elseif($contact['network'] === NETWORK_MAIL) {

				$mail_disabled = ((function_exists('imap_open') && (! get_config('system','imap_disabled'))) ? 0 : 1);
				if($mail_disabled)
					continue;

				$mbox = null;
				$x = q("SELECT `prvkey` FROM `user` WHERE `uid` = %d LIMIT 1",
					intval($importer_uid)
				);
				$mailconf = q("SELECT * FROM `mailacct` WHERE `server` != '' AND `uid` = %d LIMIT 1",
					intval($importer_uid)
				);
				if(count($x) && count($mailconf)) {
				    $mailbox = construct_mailbox_name($mailconf[0]);
					$password = '';
					openssl_private_decrypt(hex2bin($mailconf[0]['pass']),$password,$x[0]['prvkey']);
					$mbox = email_connect($mailbox,$mailconf[0]['user'],$password);
					unset($password);
					if($mbox) {
						q("UPDATE `mailacct` SET `last_check` = '%s' WHERE `id` = %d AND `uid` = %d LIMIT 1",
							dbesc(datetime_convert()),
							intval($mailconf[0]['id']),
							intval($importer_uid)
						);
					}
				}
				if($mbox) {

					$msgs = email_poll($mbox,$contact['addr']);

					if(count($msgs)) {
						foreach($msgs as $msg_uid) {
							$datarray = array();
							$meta = email_msg_meta($mbox,$msg_uid);
							$headers = email_msg_headers($mbox,$msg_uid);

							// look for a 'references' header and try and match with a parent item we have locally.

							$raw_refs = ((x($headers,'references')) ? str_replace("\t",'',$headers['references']) : '');
							$datarray['uri'] = trim($meta->message_id,'<>');

							if($raw_refs) {
								$refs_arr = explode(' ', $raw_refs);
								if(count($refs_arr)) {
									for($x = 0; $x < count($refs_arr); $x ++)
										$refs_arr[$x] = "'" . str_replace(array('<','>',' '),array('','',''),dbesc($refs_arr[$x])) . "'";
								}
								$qstr = implode(',',$refs_arr);
								$r = q("SELECT `uri` , `parent_uri` FROM `item` WHERE `uri` IN ( $qstr ) AND `uid` = %d LIMIT 1",
									intval($importer_uid)
								);
								if(count($r))
									$datarray['parent_uri'] = $r[0]['uri'];
							}


							if(! x($datarray,'parent_uri'))
								$datarray['parent_uri'] = $datarray['uri'];

							// Have we seen it before?
							$r = q("SELECT * FROM `item` WHERE `uid` = %d AND `uri` = '%s' LIMIT 1",
								intval($importer_uid),
								dbesc($datarray['uri'])
							);

							if(count($r)) {
								if($meta->deleted && ! $r[0]['deleted']) {
									q("UPDATE `item` SET `deleted` = 1, `changed` = '%s' WHERE `id` = %d LIMIT 1",
										dbesc(datetime_convert()),
										intval($r[0]['id'])
									);
								}		
								continue;
							}
							$datarray['title'] = notags(trim($meta->subject));
							$datarray['created'] = datetime_convert('UTC','UTC',$meta->date);
	
							$r = email_get_msg($mbox,$msg_uid);
							if(! $r)
								continue;
							$datarray['body'] = escape_tags($r['body']);

							// some mailing lists have the original author as 'from' - add this sender info to msg body. 
							// todo: adding a gravatar for the original author would be cool

							if(! stristr($meta->from,$contact['addr']))
								$datarray['body'] = t('From: ') . escape_tags($meta->from) . "\n\n" . $datarray['body'];

							$datarray['uid'] = $importer_uid;
							$datarray['contact_id'] = $contact['id'];
							if($datarray['parent_uri'] === $datarray['uri'])
								$datarray['private'] = 1;
							$datarray['author_name'] = $contact['name'];
							$datarray['author_link'] = 'mailbox';
							$datarray['author_avatar'] = $contact['photo'];
						
							$stored_item = item_store($datarray);
							q("UPDATE `item` SET `last_child` = 0 WHERE `parent_uri` = '%s' AND `uid` = %d",
								dbesc($datarray['parent_uri']),
								intval($importer_uid)
							);
							q("UPDATE `item` SET `last_child` = 1 WHERE `id` = %d LIMIT 1",
								intval($stored_item)
							);
						}
					}

					imap_close($mbox);
				}
			}
			elseif($contact['network'] === NETWORK_FACEBOOK) {
				// TODO: work in progress			
			}

			if($xml) {
				logger('poller: received xml : ' . $xml, LOGGER_DATA);

				if(! strstr($xml,'<?xml')) {
					logger('poller: post_handshake: response from ' . $url . ' did not contain XML.');
					$r = q("UPDATE `contact` SET `last_update` = '%s' WHERE `id` = %d LIMIT 1",
						dbesc(datetime_convert()),
						intval($contact['id'])
					);
					continue;
				}


				consume_feed($xml,$importer,$contact,$hub,1, true);

				// do it twice. Ensures that children of parents which may be later in the stream aren't tossed
	
				consume_feed($xml,$importer,$contact,$hub,1);


				if((strlen($hub)) && ($hub_update) && (($contact['rel'] == REL_BUD) || (($contact['network'] === NETWORK_OSTATUS) && (! $contact['readonly'])))) {
					logger('poller: subscribing to hub(s) : ' . $hub . ' contact name : ' . $contact['name'] . ' local user : ' . $importer['name']);
					$hubs = explode(',', $hub);
					if(count($hubs)) {
						foreach($hubs as $h) {
							$h = trim($h);
							if(! strlen($h))
								continue;
							subscribe_to_hub($h,$importer,$contact);
						}
					}
				}
			}

			$updated = datetime_convert();

			$r = q("UPDATE `contact` SET `last_update` = '%s', `success_update` = '%s' WHERE `id` = %d LIMIT 1",
				dbesc($updated),
				dbesc($updated),
				intval($contact['id'])
			);

			// loop - next contact
		}
	}

		
	return;
}

if (array_search(__file__,get_included_files())===0){
  poller_run($argv,$argc);
  killme();
}
