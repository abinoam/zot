<?php

require_once("include/oembed.php");
require_once('include/event.php');

	// BBcode 2 HTML was written by WAY2WEB.net
	// extended to work with Mistpark/Friendika - Mike Macgirvin

function bbcode($Text,$preserve_nl = false) {

	// Replace any html brackets with HTML Entities to prevent executing HTML or script
	// Don't use strip_tags here because it breaks [url] search by replacing & with amp

	$Text = str_replace("<", "&lt;", $Text);
	$Text = str_replace(">", "&gt;", $Text);

	// Convert new line chars to html <br /> tags

	$Text = nl2br($Text);
	if($preserve_nl)
		$Text = str_replace(array("\n","\r"), array('',''),$Text);

	// If we find any event code, turn it into an event.
	// After we're finished processing the bbcode we'll 
	// replace all of the event code with a reformatted version.

	$ev = bbtoevent($Text);

	// Set up the parameters for a URL search string
	$URLSearchString = "^\[\]";
	// Set up the parameters for a MAIL search string
	$MAILSearchString = $URLSearchString;

	// Perform URL Search


	$Text = preg_replace("/([^\]\=]|^)(https?\:\/\/[a-zA-Z0-9\:\/\-\?\&\.\=\_\~\#\'\%\$\!\+\,]+)/", ' <a href="$2" target="external-link">$2</a>', $Text);

	$Text = preg_replace("/\[url\]([$URLSearchString]*)\[\/url\]/", '<a href="$1" target="external-link">$1</a>', $Text);
	$Text = preg_replace("(\[url\=([$URLSearchString]*)\](.*?)\[/url\])", '<a href="$1" target="external-link">$2</a>', $Text);
	//$Text = preg_replace("(\[url\=([$URLSearchString]*)\]([$URLSearchString]*)\[/url\])", '<a href="$1" target="_blank">$2</a>', $Text);


	// Perform MAIL Search
	$Text = preg_replace("(\[mail\]([$MAILSearchString]*)\[/mail\])", '<a href="mailto:$1">$1</a>', $Text);
	$Text = preg_replace("/\[mail\=([$MAILSearchString]*)\](.*?)\[\/mail\]/", '<a href="mailto:$1">$2</a>', $Text);
         
	// Check for bold text
	$Text = preg_replace("(\[b\](.*?)\[\/b\])is",'<strong>$1</strong>',$Text);

	// Check for Italics text
	$Text = preg_replace("(\[i\](.*?)\[\/i\])is",'<em>$1</em>',$Text);

	// Check for Underline text
	$Text = preg_replace("(\[u\](.*?)\[\/u\])is",'<u>$1</u>',$Text);

	// Check for strike-through text
	$Text = preg_replace("(\[s\](.*?)\[\/s\])is",'<strike>$1</strike>',$Text);

	// Check for over-line text
	$Text = preg_replace("(\[o\](.*?)\[\/o\])is",'<span class="overline">$1</span>',$Text);

	// Check for colored text
	$Text = preg_replace("(\[color=(.*?)\](.*?)\[\/color\])is","<span style=\"color: $1;\">$2</span>",$Text);

	// Check for sized text
	$Text = preg_replace("(\[size=(.*?)\](.*?)\[\/size\])is","<span style=\"font-size: $1;\">$2</span>",$Text);

	// Check for list text
	$Text = preg_replace("/\[list\](.*?)\[\/list\]/is", '<ul class="listbullet">$1</ul>' ,$Text);
	$Text = preg_replace("/\[list=1\](.*?)\[\/list\]/is", '<ul class="listdecimal">$1</ul>' ,$Text);
	$Text = preg_replace("/\[list=i\](.*?)\[\/list\]/s",'<ul class="listlowerroman">$1</ul>' ,$Text);
	$Text = preg_replace("/\[list=I\](.*?)\[\/list\]/s", '<ul class="listupperroman">$1</ul>' ,$Text);
	$Text = preg_replace("/\[list=a\](.*?)\[\/list\]/s", '<ul class="listloweralpha">$1</ul>' ,$Text);
	$Text = preg_replace("/\[list=A\](.*?)\[\/list\]/s", '<ul class="listupperalpha">$1</ul>' ,$Text);
	$Text = preg_replace("/\[li\](.*?)\[\/li\]/s", '<li>$1</li>' ,$Text);

	$Text = preg_replace("/\[td\](.*?)\[\/td\]/s", '<td>$1</td>' ,$Text);
	$Text = preg_replace("/\[tr\](.*?)\[\/tr\]/s", '<tr>$1</tr>' ,$Text);
	$Text = preg_replace("/\[table\](.*?)\[\/table\]/s", '<table>$1</table>' ,$Text);

	$Text = preg_replace("/\[table border=1\](.*?)\[\/table\]/s", '<table border="1" >$1</table>' ,$Text);
	$Text = preg_replace("/\[table border=0\](.*?)\[\/table\]/s", '<table border="0" >$1</table>' ,$Text);

	
//	$Text = str_replace("[*]", "<li>", $Text);

	// Check for font change text
	$Text = preg_replace("(\[font=(.*?)\](.*?)\[\/font\])","<span style=\"font-family: $1;\">$2</span>",$Text);

	// Declare the format for [code] layout
	$CodeLayout = '<code>$1</code>';
	// Check for [code] text
	$Text = preg_replace("/\[code\](.*?)\[\/code\]/is","$CodeLayout", $Text);
	// Declare the format for [quote] layout
	$QuoteLayout = '<blockquote>$1</blockquote>';                     
	// Check for [quote] text
	$Text = preg_replace("/\[quote\](.*?)\[\/quote\]/is","$QuoteLayout", $Text);
         
	// Images
	// [img]pathtoimage[/img]
	$Text = preg_replace("/\[img\](.*?)\[\/img\]/", '<img src="$1" alt="' . t('Image/photo') . '" />', $Text);

	// html5 video and audio

	$Text = preg_replace("/\[video\](.*?)\[\/video\]/", '<video src="$1" controls="controls" width="425" height="350"><a href="$1">$1</a></video>', $Text);

	$Text = preg_replace("/\[audio\](.*?)\[\/audio\]/", '<audio src="$1" controls="controls"><a href="$1">$1</a></audio>', $Text);

	$Text = preg_replace("/\[iframe\](.*?)\[\/iframe\]/", '<iframe src="$1" width="425" height="350"><a href="$1">$1</a></iframe>', $Text);
         
	// [img=widthxheight]image source[/img]
	$Text = preg_replace("/\[img\=([0-9]*)x([0-9]*)\](.*?)\[\/img\]/", '<img src="$3" style="height:{$2}px; width:{$1}px;" >', $Text);

	if (get_pconfig(local_user(), 'oembed', 'use_for_youtube' )==1){
		// use oembed for youtube links
		$Text = preg_replace("/\[youtube\]/",'[embed]',$Text); 
		$Text = preg_replace("/\[\/youtube\]/",'[/embed]',$Text); 
	} else {
		// Youtube extensions
        $Text = preg_replace("/\[youtube\]https?:\/\/www.youtube.com\/watch\?v\=(.*?)\[\/youtube\]/",'[youtube]$1[/youtube]',$Text); 
        $Text = preg_replace("/\[youtube\]https?:\/\/youtu.be\/(.*?)\[\/youtube\]/",'[youtube]$1[/youtube]',$Text); 
		$Text = preg_replace("/\[youtube\](.*?)\[\/youtube\]/", '<iframe width="425" height="349" src="http://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe>', $Text);
	}
//	$Text = preg_replace("/\[youtube\](.*?)\[\/youtube\]/", '<object width="425" height="350" type="application/x-shockwave-flash" data="http://www.youtube.com/v/$1" ><param name="movie" value="http://www.youtube.com/v/$1"></param><!--[if IE]><embed src="http://www.youtube.com/v/$1" type="application/x-shockwave-flash" width="425" height="350" /><![endif]--></object>', $Text);



	// oembed tag
	$Text = oembed_bbcode2html($Text);

	// If we found an event earlier, strip out all the event code and replace with a reformatted version.

	if(x($ev,'desc') && x($ev,'start')) {
		$sub = format_event_html($ev);

		$Text = preg_replace("/\[event\-description\](.*?)\[\/event\-description\]/is",$sub,$Text);
		$Text = preg_replace("/\[event\-start\](.*?)\[\/event\-start\]/is",'',$Text);
		$Text = preg_replace("/\[event\-finish\](.*?)\[\/event\-finish\]/is",'',$Text);
		$Text = preg_replace("/\[event\-location\](.*?)\[\/event\-location\]/is",'',$Text);
		$Text = preg_replace("/\[event\-adjust\](.*?)\[\/event\-adjust\]/is",'',$Text);
	}


	
	call_hooks('bbcode',$Text);

	return $Text;
}
