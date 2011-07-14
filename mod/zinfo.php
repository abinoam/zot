<?php

function zinfo_init(&$a) {

	if($a->argc > 1)
		$nick = $a->argv[1];

	if(! $nick)
		killme();

	$r = q("select user.*, contact.photo from user left join contact on contact.uid = user.uid where contact.self = 1 and user.nickname = '%s' limit 1",
		dbesc($nick)
	);
	if(count($r)) {
		$ret = array(
			'fullname' => $r[0]['username'],
			'nickname' => $r[0]['nickname'],
			'photo' => $r[0]['photo'],
			'url' => z_path() . '/profile/' . $r[0]['nickname'],
			'id' => z_path() . '/id/' . $r[0]['uid'],
			'post' => z_path() . '/zpost/' . $r[0]['nickname'],
			'pubkey' => $r[0]['pubkey']
		);
		echo json_encode($ret);
		killme();
	}
}
