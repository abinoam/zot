<?php

require_once('library/HTML5/Parser.php');


if(! function_exists('scrape_meta')) {
function scrape_meta($url) {

	$a = get_app();

	$ret = array();

	logger('scrape_meta: url=' . $url);

	$s = fetch_url($url);

	if(! $s) 
		return $ret;

	$headers = $a->get_curl_headers();
	logger('scrape_meta: headers=' . $headers, LOGGER_DEBUG);

	$lines = explode("\n",$headers);
	if(count($lines)) {
		foreach($lines as $line) {				
			// don't try and run feeds through the html5 parser
			if(stristr($line,'content-type:') && ((stristr($line,'application/atom+xml')) || (stristr($line,'application/rss+xml'))))
				return ret;
		}
	}

	$dom = HTML5_Parser::parse($s);

	if(! $dom)
		return $ret;

	$items = $dom->getElementsByTagName('meta');

	// get DFRN link elements

	foreach($items as $item) {
		$x = $item->getAttribute('name');
		if(substr($x,0,5) == "dfrn-")
			$ret[$x] = $item->getAttribute('content');
	}

	return $ret;
}}


if(! function_exists('scrape_vcard')) {
function scrape_vcard($url) {

	$a = get_app();

	$ret = array();

	logger('scrape_vcard: url=' . $url);

	$s = fetch_url($url);

	if(! $s) 
		return $ret;

	$headers = $a->get_curl_headers();
	$lines = explode("\n",$headers);
	if(count($lines)) {
		foreach($lines as $line) {				
			// don't try and run feeds through the html5 parser
			if(stristr($line,'content-type:') && ((stristr($line,'application/atom+xml')) || (stristr($line,'application/rss+xml'))))
				return ret;
		}
	}

	$dom = HTML5_Parser::parse($s);

	if(! $dom)
		return $ret;

	// Pull out hCard profile elements

	$items = $dom->getElementsByTagName('*');
	foreach($items as $item) {
		if(attribute_contains($item->getAttribute('class'), 'vcard')) {
			$level2 = $item->getElementsByTagName('*');
			foreach($level2 as $x) {
				if(attribute_contains($x->getAttribute('class'),'fn'))
					$ret['fn'] = $x->textContent;
				if((attribute_contains($x->getAttribute('class'),'photo'))
					|| (attribute_contains($x->getAttribute('class'),'avatar')))
					$ret['photo'] = $x->getAttribute('src');
				if((attribute_contains($x->getAttribute('class'),'nickname'))
					|| (attribute_contains($x->getAttribute('class'),'uid')))
					$ret['nick'] = $x->textContent;
			}
		}
	}

	return $ret;
}}


if(! function_exists('scrape_feed')) {
function scrape_feed($url) {

	$a = get_app();

	$ret = array();
	$s = fetch_url($url);

	if(! $s) 
		return $ret;

	$headers = $a->get_curl_headers();
	logger('scrape_feed: headers=' . $headers, LOGGER_DEBUG);

	$lines = explode("\n",$headers);
	if(count($lines)) {
		foreach($lines as $line) {				
			if(stristr($line,'content-type:')) {
				if(stristr($line,'application/atom+xml') || stristr($s,'<feed')) {
					$ret['feed_atom'] = $url;
					return $ret;
				}
 				if(stristr($line,'application/rss+xml') || stristr($s,'<rss')) {
					$ret['feed_rss'] = $url;
					return $ret;
				}
			}
		}
	}

	$dom = HTML5_Parser::parse($s);

	if(! $dom)
		return $ret;


	$items = $dom->getElementsByTagName('img');

	// get img elements (twitter)

	if($items) {
		foreach($items as $item) {
			$x = $item->getAttribute('id');
			if($x === 'profile-image') {
				$ret['photo'] = $item->getAttribute('src');
			}
		}
	}


	$head = $dom->getElementsByTagName('base');
	if($head) {
		foreach($head as $head0) {
			$basename = $head0->getAttribute('href');
			break;
		}
	}
	if(! $basename)
		$basename = substr($url,0,strrpos($url,'/')) . '/';

	$items = $dom->getElementsByTagName('link');

	// get Atom/RSS link elements, take the first one of either.

	if($items) {
		foreach($items as $item) {
			$x = $item->getAttribute('rel');
			if(($x === 'alternate') && ($item->getAttribute('type') === 'application/atom+xml')) {
				if(! x($ret,'feed_atom'))
					$ret['feed_atom'] = $item->getAttribute('href');
			}
			if(($x === 'alternate') && ($item->getAttribute('type') === 'application/rss+xml')) {
				if(! x($ret,'feed_rss'))
					$ret['feed_rss'] = $item->getAttribute('href');
			}
		}	
	}

	// Drupal and perhaps others only provide relative URL's. Turn them into absolute.

	if(x($ret,'feed_atom') && (! strstr($ret['feed_atom'],'://')))
		$ret['feed_atom'] = $basename . $ret['feed_atom'];
	if(x($ret,'feed_rss') && (! strstr($ret['feed_rss'],'://')))
		$ret['feed_rss'] = $basename . $ret['feed_rss'];

	return $ret;
}}


function probe_url($url) {
	require_once('include/email.php');

	$result = array();

	if(! $url)
		return $result;

	$links = lrdd($url);

	if(count($links)) {
		logger('probe_url: found lrdd links: ' . print_r($links,true), LOGGER_DATA);
		foreach($links as $link) {
			if($link['@attributes']['rel'] === NAMESPACE_ZOT)
				$zot = unamp($link['@attributes']['href']);
		}
	}

	if(strlen($zot)) {
		$s = fetch_url($zot);
		if($s) {
			$j = json_decode($s);
			if($j) {
				$network = NETWORK_ZOT;
				$vcard   = array(
					'fn'    => $j->fullname, 
					'nick'  => $j->nickname, 
					'photo' => $j->photo
				);
				$profile  = $j->url;
				$notify   = $j->post;
				$key      = $j->pubkey;
				$poll     = 'N/A';
			}
		}
	}

	$vcard['fn'] = notags($vcard['fn']);
	$vcard['nick'] = notags($vcard['nick']);


	$result['name'] = $vcard['fn'];
	$result['nick'] = $vcard['nick'];
	$result['url'] = $profile;
	$result['addr'] = $addr;
	$result['notify'] = $notify;
	$result['poll'] = $poll;
	$result['request'] = $request;
	$result['confirm'] = $confirm;
	$result['photo'] = $vcard['photo'];
	$result['priority'] = $priority;
	$result['network'] = $network;
	$result['alias'] = $alias;
	$result['key'] = $key;

	logger('probe_url: ' . print_r($result,true), LOGGER_DEBUG);

	return $result;
}
