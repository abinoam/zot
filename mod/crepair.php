<?php

function crepair_post(&$a) {
	if(! local_user())
		return;

	$cid = (($a->argc > 1) ? intval($a->argv[1]) : 0);

	if($cid) {
		$r = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($cid),
			intval(local_user())
		);
	}

	if(! count($r))
		return;

	$contact = $r[0];

	$nick    = ((x($_POST,'nick')) ? $_POST['nick'] : null);
	$url     = ((x($_POST,'url')) ? $_POST['url'] : null);
	$request = ((x($_POST,'request')) ? $_POST['request'] : null);
	$confirm = ((x($_POST,'confirm')) ? $_POST['confirm'] : null);
	$notify  = ((x($_POST,'notify')) ? $_POST['notify'] : null);
	$poll    = ((x($_POST,'poll')) ? $_POST['poll'] : null);


	$r = q("UPDATE `contact` SET `nick` = '%s', `url` = '%s', `request` = '%s', `confirm` = '%s', `notify` = '%s', `poll` = '%s'
		WHERE `id` = %d AND `uid` = %d LIMIT 1",
		dbesc($nick),
		dbesc($url),
		dbesc($request),
		dbesc($confirm),
		dbesc($notify),
		dbesc($poll),
		intval($contact['id']),
		local_user()
	);

	if($r)
		info( t('Contact settings applied.') . EOL);
	else
		notice( t('Contact update failed.') . EOL);

	return;
}



function crepair_content(&$a) {

	if(! local_user()) {
		notice( t('Permission denied.') . EOL);
		return;
	}

	$cid = (($a->argc > 1) ? intval($a->argv[1]) : 0);

	if($cid) {
		$r = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($cid),
			intval(local_user())
		);
	}

	if(! count($r)) {
		notice( t('Contact not found.') . EOL);
		return;
	}

	$contact = $r[0];

	$msg1 = t('Repair Contact Settings');

	$msg2 = t('<strong>WARNING: This is highly advanced</strong> and if you enter incorrect information your communications with this contact will stop working.');
	$msg3 = t('Please use your browser \'Back\' button <strong>now</strong> if you are uncertain what to do on this page.');

	$o .= '<h2>' . $msg1 . '</h2>';

	$o .= '<div class="error-message">' . $msg2 . EOL . EOL. $msg3 . '</div>';

	$tpl = get_markup_template('crepair.tpl');
	$o .= replace_macros($tpl, array(
		'$label_name' => t('Name'),
		'$label_nick' => t('Account Nickname'),
		'$label_url' => t('Account URL'),
		'$label_request' => t('Friend Request URL'),
		'$label_confirm' => t('Friend Confirm URL'),
		'$label_notify' => t('Notification Endpoint URL'),
		'$label_poll' => t('Poll/Feed URL'),
		'$contact_name' => $contact['name'],
		'$contact_nick' => $contact['nick'],
		'$contact_id'   => $contact['id'],
		'$contact_url'  => $contact['url'],
		'$request'      => $contact['request'],
		'$confirm'      => $contact['confirm'],
		'$notify'       => $contact['notify'],
		'$poll'         => $contact['poll'],
		'$lbl_submit'   => t('Submit')
	));

	return $o;

}
