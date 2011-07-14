<?php

if(! function_exists('home_init')) {
function home_init(&$a) {

	if(local_user() && ($a->user['nickname']))
		goaway( z_path() . "/profile/" . $a->user['nickname'] );

	if(strlen(get_config('system','singleuser')))
		goaway( z_path() . "/profile/" . get_config('system','singleuser'));

}}


if(! function_exists('home_content')) {
function home_content(&$a) {

	$o = '';

	if(x($_SESSION,'theme'))
		unset($_SESSION['theme']);

	$o .= '<h1>' . ((x($a->config,'sitename')) ? sprintf( t("Welcome to %s") ,$a->config['sitename']) : "" ) . '</h1>';
	if(file_exists('home.html'))
 		$o .= file_get_contents('home.html');

	$o .= login(($a->config['register_policy'] == REGISTER_CLOSED) ? 0 : 1);
	
	call_hooks("home_content",$o);
	
	return $o;

	
}} 
