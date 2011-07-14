<?php

if(! function_exists('load_doc_file')) {
function load_doc_file($s) {
	global $lang;
	if(! isset($lang))
		$lang = 'en';
	$b = basename($s);
	$d = dirname($s);
	if(file_exists("$d/$lang/$b"))
		return file_get_contents("$d/$lang/$b");
	return file_get_contents($s);
}}



function help_content(&$a) {

	global $lang;

	require_once('library/markdown.php');

	$text = '';

	if($a->argc > 1) {
		$text = load_doc_file('doc/' . $a->argv[1] . '.md');
		$a->page['title'] = t('Help:') . ' ' . str_replace('-',' ',notags($a->argv[1]));
	}
	if(! $text) {
		$text = load_doc_file('doc/Home.md');
		$a->page['title'] = t('Help');
	}
	

	return Markdown($text);

}