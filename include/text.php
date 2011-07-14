<?php



// This is our template processor.
// $s is the string requiring macro substitution.
// $r is an array of key value pairs (search => replace)
// returns substituted string.
// WARNING: this is pretty basic, and doesn't properly handle search strings that are substrings of each other.
// For instance if 'test' => "foo" and 'testing' => "bar", testing could become either bar or fooing, 
// depending on the order in which they were declared in the array.   

require_once("include/template_processor.php");

if(! function_exists('replace_macros')) {  
function replace_macros($s,$r) {
	global $t;
	
	return $t->replace($s,$r);

}}



// random hash, 64 chars

if(! function_exists('random_string')) {
function random_string($len = 0) {
	$s = hash('sha256',uniqid(rand(),true));
	return(($len) ? substr($s,0,$len) : $s);
}}

/**
 * This is our primary input filter. 
 *
 * The high bit hack only involved some old IE browser, forget which (IE5/Mac?)
 * that had an XSS attack vector due to stripping the high-bit on an 8-bit character
 * after cleansing, and angle chars with the high bit set could get through as markup.
 * 
 * This is now disabled because it was interfering with some legitimate unicode sequences 
 * and hopefully there aren't a lot of those browsers left. 
 *
 * Use this on any text input where angle chars are not valid or permitted
 * They will be replaced with safer brackets. This may be filtered further
 * if these are not allowed either.   
 *
 */

if(! function_exists('notags')) {
function notags($string) {

	return(str_replace(array("<",">"), array('[',']'), $string));

//  High-bit filter no longer used
//	return(str_replace(array("<",">","\xBA","\xBC","\xBE"), array('[',']','','',''), $string));
}}

// use this on "body" or "content" input where angle chars shouldn't be removed,
// and allow them to be safely displayed.

if(! function_exists('escape_tags')) {
function escape_tags($string) {

	return(htmlspecialchars($string));
}}

// generate a string that's random, but usually pronounceable. 
// used to generate initial passwords

if(! function_exists('autoname')) {
function autoname($len) {

	$vowels = array('a','a','ai','au','e','e','e','ee','ea','i','ie','o','ou','u'); 
	if(mt_rand(0,5) == 4)
		$vowels[] = 'y';

	$cons = array(
			'b','bl','br',
			'c','ch','cl','cr',
			'd','dr',
			'f','fl','fr',
			'g','gh','gl','gr',
			'h',
			'j',
			'k','kh','kl','kr',
			'l',
			'm',
			'n',
			'p','ph','pl','pr',
			'qu',
			'r','rh',
			's','sc','sh','sm','sp','st',
			't','th','tr',
			'v',
			'w','wh',
			'x',
			'z','zh'
			);

	$midcons = array('ck','ct','gn','ld','lf','lm','lt','mb','mm', 'mn','mp',
				'nd','ng','nk','nt','rn','rp','rt');

	$noend = array('bl', 'br', 'cl','cr','dr','fl','fr','gl','gr',
				'kh', 'kl','kr','mn','pl','pr','rh','tr','qu','wh');

	$start = mt_rand(0,2);
  	if($start == 0)
    		$table = $vowels;
  	else
    		$table = $cons;

	$word = '';

	for ($x = 0; $x < $len; $x ++) {
  		$r = mt_rand(0,count($table) - 1);
  		$word .= $table[$r];
  
  		if($table == $vowels)
    			$table = array_merge($cons,$midcons);
  		else
    			$table = $vowels;

	}

	$word = substr($word,0,$len);

	foreach($noend as $noe) {
  		if((strlen($word) > 2) && (substr($word,-2) == $noe)) {
    			$word = substr($word,0,-1);
    			break;
  		}
	}
	if(substr($word,-1) == 'q')
		$word = substr($word,0,-1);    
	return $word;
}}


// escape text ($str) for XML transport
// returns escaped text.

if(! function_exists('xmlify')) {
function xmlify($str) {
	$buffer = '';
	
	for($x = 0; $x < strlen($str); $x ++) {
		$char = $str[$x];
        
		switch( $char ) {

			case "\r" :
				break;
			case "&" :
				$buffer .= '&amp;';
				break;
			case "'" :
				$buffer .= '&apos;';
				break;
			case "\"" :
				$buffer .= '&quot;';
				break;
			case '<' :
				$buffer .= '&lt;';
				break;
			case '>' :
				$buffer .= '&gt;';
				break;
			case "\n" :
				$buffer .= "\n";
				break;
			default :
				$buffer .= $char;
				break;
		}	
	}
	$buffer = trim($buffer);
	return($buffer);
}}

// undo an xmlify
// pass xml escaped text ($s), returns unescaped text

if(! function_exists('unxmlify')) {
function unxmlify($s) {
	$ret = str_replace('&amp;','&', $s);
	$ret = str_replace(array('&lt;','&gt;','&quot;','&apos;'),array('<','>','"',"'"),$ret);
	return $ret;	
}}

// convenience wrapper, reverse the operation "bin2hex"

if(! function_exists('hex2bin')) {
function hex2bin($s) {
	if(! ctype_xdigit($s)) {
		logger('hex2bin: illegal input: ' . print_r(debug_backtrace(), true));
		return($s);
	}

	return(pack("H*",$s));
}}


// wrapper to load a view template, checking for alternate
// languages before falling back to the default

// obsolete, deprecated.

if(! function_exists('load_view_file')) {
function load_view_file($s) {
	global $lang, $a;
	if(! isset($lang))
		$lang = 'en';
	$b = basename($s);
	$d = dirname($s);
	if(file_exists("$d/$lang/$b"))
		return file_get_contents("$d/$lang/$b");
	
	$theme = current_theme();
	
	if(file_exists("$d/theme/$theme/$b"))
		return file_get_contents("$d/theme/$theme/$b");
			
	return file_get_contents($s);
}}

if(! function_exists('get_intltext_template')) {
function get_intltext_template($s) {
	global $lang;

	if(! isset($lang))
		$lang = 'en';

	if(file_exists("view/$lang/$s"))
		return file_get_contents("view/$lang/$s");
	elseif(file_exists("view/en/$s"))
		return file_get_contents("view/en/$s");
	else
		return file_get_contents("view/$s");
}}

if(! function_exists('get_markup_template')) {
function get_markup_template($s) {

	$theme = current_theme();
	
	if(file_exists("view/theme/$theme/$s"))
		return file_get_contents("view/theme/$theme/$s");
	else
		return file_get_contents("view/$s");

}}





// for html,xml parsing - let's say you've got
// an attribute foobar="class1 class2 class3"
// and you want to find out if it contains 'class3'.
// you can't use a normal sub string search because you
// might match 'notclass3' and a regex to do the job is 
// possible but a bit complicated. 
// pass the attribute string as $attr and the attribute you 
// are looking for as $s - returns true if found, otherwise false

if(! function_exists('attribute_contains')) {
function attribute_contains($attr,$s) {
	$a = explode(' ', $attr);
	if(count($a) && in_array($s,$a))
		return true;
	return false;
}}



if(! function_exists('activity_match')) {
function activity_match($haystack,$needle) {
	if(($haystack === $needle) || ((basename($needle) === $haystack) && strstr($needle,NAMESPACE_ACTIVITY_SCHEMA)))
		return true;
	return false;
}}


// Pull out all #hashtags and @person tags from $s;
// We also get @person@domain.com - which would make 
// the regex quite complicated as tags can also
// end a sentence. So we'll run through our results
// and strip the period from any tags which end with one.
// Returns array of tags found, or empty array.


if(! function_exists('get_tags')) {
function get_tags($s) {
	$ret = array();

	// ignore anything in a code block

	$s = preg_replace('/\[code\](.*?)\[\/code\]/sm','',$s);

	// Match full names against @tags including the space between first and last
	// We will look these up afterward to see if they are full names or not recognisable.

	if(preg_match_all('/(@[^ \x0D\x0A,:?]+ [^ \x0D\x0A,:?]+)([ \x0D\x0A,:?]|$)/',$s,$match)) {
		foreach($match[1] as $mtch) {
			if(strstr($mtch,"]")) {
				// we might be inside a bbcode color tag - leave it alone
				continue;
			}
			if(substr($mtch,-1,1) === '.')
				$ret[] = substr($mtch,0,-1);
			else
				$ret[] = $mtch;
		}
	}

	// Otherwise pull out single word tags. These can be @nickname, @first_last
	// and #hash tags.

	if(preg_match_all('/([@#][^ \x0D\x0A,:?]+)([ \x0D\x0A,:?]|$)/',$s,$match)) {
		foreach($match[1] as $mtch) {
			if(strstr($mtch,"]")) {
				// we might be inside a bbcode color tag - leave it alone
				continue;
			}
			// ignore strictly numeric tags like #1
			if((strpos($mtch,'#') === 0) && ctype_digit(substr($mtch,1)))
				continue;
			if(substr($mtch,-1,1) === '.')
				$ret[] = substr($mtch,0,-1);
			else
				$ret[] = $mtch;
		}
	}
	return $ret;
}}


// quick and dirty quoted_printable encoding

if(! function_exists('qp')) {
function qp($s) {
return str_replace ("%","=",rawurlencode($s));
}} 



if(! function_exists('get_mentions')) {
function get_mentions($item) {
	$o = '';
	if(! strlen($item['tag']))
		return $o;

	$arr = explode(',',$item['tag']);
	foreach($arr as $x) {
		$matches = null;
		if(preg_match('/@\[url=([^\]]*)\]/',$x,$matches)) {
			$o .= "\t\t" . '<link rel="mentioned" href="' . $matches[1] . '" />' . "\r\n";
			$o .= "\t\t" . '<link rel="ostatus:attention" href="' . $matches[1] . '" />' . "\r\n";
		}
	}
	return $o;
}}



/**
 *
 * Function: linkify
 *
 * Replace naked text hyperlink with HTML formatted hyperlink
 *
 */

if(! function_exists('linkify')) {
function linkify($s) {
	$s = preg_replace("/(https?\:\/\/[a-zA-Z0-9\:\/\-\?\&\.\=\_\~\#\'\%\$\!\+]*)/", ' <a href="$1" target="external-link">$1</a>', $s);
	return($s);
}}


/**
 * 
 * Function: smilies
 *
 * Description:
 * Replaces text emoticons with graphical images
 *
 * @Parameter: string $s
 *
 * Returns string
 */

if(! function_exists('smilies')) {
function smilies($s) {
	$a = get_app();

	return str_replace(
	array( '&lt;3', '&lt;/3', '&lt;\\3', ':-)', ':)', ';-)', ':-(', ':(', ':-P', ':P', ':-"', ':-x', ':-X', ':-D', '8-|', '8-O'),
	array(
		'<img src="' . z_path() . '/images/smiley-heart.gif" alt="<3" />',
		'<img src="' . z_path() . '/images/smiley-brokenheart.gif" alt="</3" />',
		'<img src="' . z_path() . '/images/smiley-brokenheart.gif" alt="<\\3" />',
		'<img src="' . z_path() . '/images/smiley-smile.gif" alt=":-)" />',
		'<img src="' . z_path() . '/images/smiley-smile.gif" alt=":)" />',
		'<img src="' . z_path() . '/images/smiley-wink.gif" alt=";-)" />',
		'<img src="' . z_path() . '/images/smiley-frown.gif" alt=":-(" />',
		'<img src="' . z_path() . '/images/smiley-frown.gif" alt=":(" />',
		'<img src="' . z_path() . '/images/smiley-tongue-out.gif" alt=":-P" />',
		'<img src="' . z_path() . '/images/smiley-tongue-out.gif" alt=":P" />',
		'<img src="' . z_path() . '/images/smiley-kiss.gif" alt=":-\"" />',
		'<img src="' . z_path() . '/images/smiley-kiss.gif" alt=":-x" />',
		'<img src="' . z_path() . '/images/smiley-kiss.gif" alt=":-X" />',
		'<img src="' . z_path() . '/images/smiley-laughing.gif" alt=":-D" />',
		'<img src="' . z_path() . '/images/smiley-surprised.gif" alt="8-|" />',
		'<img src="' . z_path() . '/images/smiley-surprised.gif" alt="8-O" />'
	), $s);
}}



if(! function_exists('day_translate')) {
function day_translate($s) {
	$ret = str_replace(array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
		array( t('Monday'), t('Tuesday'), t('Wednesday'), t('Thursday'), t('Friday'), t('Saturday'), t('Sunday')),
		$s);

	$ret = str_replace(array('January','February','March','April','May','June','July','August','September','October','November','December'),
		array( t('January'), t('February'), t('March'), t('April'), t('May'), t('June'), t('July'), t('August'), t('September'), t('October'), t('November'), t('December')),
		$ret);

	return $ret;
}}


if(! function_exists('prepare_body')) {
function prepare_body($item,$attach = false) {

	$s = prepare_text($item['body']);
	if(! $attach)
		return $s;

	$arr = explode(',',$item['attach']);
	if(count($arr)) {
		$s .= '<div class="body-attach">';
		foreach($arr as $r) {
			$matches = false;
			$icon = '';
			$cnt = preg_match('|\[attach\]href=\"(.*?)\" size=\"(.*?)\" type=\"(.*?)\" title=\"(.*?)\"\[\/attach\]|',$r,$matches);
			if($cnt) {
				$icontype = strtolower(substr($matches[3],0,strpos($matches[3],'/')));
				switch($icontype) {
					case 'video':
					case 'audio':
					case 'image':
					case 'text':
						$icon = '<div class="attachtype type-' . $icontype . '"></div>';
						break;
					default:
						$icon = '<div class="attachtype type-unkn"></div>';
						break;
				}
				$title = ((strlen(trim($matches[4]))) ? escape_tags(trim($matches[4])) : escape_tags($matches[1]));
				$title .= ' ' . $matches[2] . ' ' . t('bytes');

				$s .= '<a href="' . strip_tags($matches[1]) . '" title="' . $title . '" class="attachlink" target="external-link" >' . $icon . '</a>';
			}
		}
		$s .= '<div class="clear"></div></div>';
	}
	return $s;
}}

if(! function_exists('prepare_text')) {
function prepare_text($text) {

	require_once('include/bbcode.php');

	$s = smilies(bbcode($text));

	return $s;
}}

if(! function_exists('unamp')) {
function unamp($s) {
	return str_replace('&amp;', '&', $s);
}}

if(! function_exists('return_bytes')) {
function return_bytes ($size_str) {
    switch (substr ($size_str, -1))
    {
        case 'M': case 'm': return (int)$size_str * 1048576;
        case 'K': case 'k': return (int)$size_str * 1024;
        case 'G': case 'g': return (int)$size_str * 1073741824;
        default: return $size_str;
    }
}}

