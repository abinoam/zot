<?php

function xrd_content(&$a) {

	$uri = notags(trim($_GET['uri']));

	logger('mod_xrd: ' . $uri, LOGGER_DEBUG);

	if(substr($uri,0,4) === 'http')
		$name = basename($uri);
	else {
		$local = str_replace('acct:', '', $uri);
		if(substr($local,0,2) == '//')
			$local = substr($local,2);

		$name = substr($local,0,strpos($local,'@'));
	}

	$r = q("SELECT * FROM user WHERE nickname = '%s' LIMIT 1",
		dbesc($name)
	);

	if(! results($r))
		killme();

	header('Access-Control-Allow-Origin: *');
	header("Content-type: text/xml");

	$tpl = file_get_contents('view/xrd_person.tpl');

	$o = replace_macros($tpl, array(
		'$accturi'     => $uri,
		'$profile_url' => z_path() . '/profile/'       . $r[0]['nickname'],
		'$atom'        => z_path() . '/dfrn_poll/'     . $r[0]['nickname'],
		'$photo'       => z_path() . '/photo/profile/' . $r[0]['uid']      . '.jpg',
		'$zot_url'     => z_path() . '/zinfo/'         . $r[0]['nickname']
	));

	$arr = array('user' => $r[0], 'xml' => $o);
	call_hooks('personal_xrd', $arr);

	echo $o;
	killme();

}
