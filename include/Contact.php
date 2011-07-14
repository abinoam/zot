<?php


// Included here for completeness, but this is a very dangerous operation.
// It is the caller's responsibility to confirm the requestor's intent and
// authorisation to do this.

function user_remove($uid) {
	if(! $uid)
		return;
	$a = get_app();
	logger('Removing user: ' . $uid);
	q("DELETE FROM `contact` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `group` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `group_member` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `intro` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `event` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `item` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `mail` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `photo` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `profile` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `profile_check` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `pconfig` WHERE `uid` = %d", intval($uid));
	q("DELETE FROM `user` WHERE `uid` = %d", intval($uid));
	if($uid == local_user()) {
		unset($_SESSION['authenticated']);
		unset($_SESSION['uid']);
		goaway(z_path());
	}
}


function contact_remove($id) {
	q("DELETE FROM `contact` WHERE `id` = %d LIMIT 1",
		intval($id)
	);
	q("DELETE FROM `item` WHERE `contact_id` = %d ",
		intval($id)
	);
	q("DELETE FROM `photo` WHERE `contact_id` = %d ",
		intval($id)
	);
	q("DELETE FROM `mail` WHERE `contact_id` = %d ",
		intval($id)
	);
	q("DELETE FROM `event` WHERE `cid` = %d ",
		intval($id)
	);
}


// Contact has refused to recognise us as a friend. We will start a countdown.
// If they still don't recognise us in 32 days, the relationship is over,
// and we won't waste any more time trying to communicate with them.
// This provides for the possibility that their database is temporarily messed
// up or some other transient event and that there's a possibility we could recover from it.
 
if(! function_exists('mark_for_death')) {
function mark_for_death($contact) {
	if($contact['term_date'] == '0000-00-00 00:00:00') {
		q("UPDATE `contact` SET `term_date` = '%s' WHERE `id` = %d LIMIT 1",
				dbesc(datetime_convert()),
				intval($contact['id'])
		);
	}
	else {
		$expiry = $contact['term_date'] . ' + 32 days ';
		if(datetime_convert() > datetime_convert('UTC','UTC',$expiry)) {

			// relationship is really truly dead. 

			contact_remove($contact['id']);

		}
	}

}}

if(! function_exists('unmark_for_death')) {
function unmark_for_death($contact) {
	// It's a miracle. Our dead contact has inexplicably come back to life.
	q("UPDATE `contact` SET `term_date` = '%s' WHERE `id` = %d LIMIT 1",
		dbesc('0000-00-00 00:00:00'),
		intval($contact['id'])
	);
}}

