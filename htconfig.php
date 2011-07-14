<?php

// If automatic system installation fails: 

// Copy or rename this file to .htconfig.php

// Why .htconfig.php? Because it contains sensitive information which could
// give somebody complete control of your database. Apache's default 
// configuration denies access to and refuses to serve any file beginning 
// with .ht

// Then set the following for your MySQL installation

$db_host = 'your.mysqlhost.com';
$db_user = 'mysqlusername';
$db_pass = 'mysqlpassword';
$db_data = 'mysqldatabasename';

// Choose a legal default timezone. If you are unsure, use "America/Los_Angeles".
// It can be changed later and only applies to timestamps for anonymous viewers.

$default_timezone = 'America/Los_Angeles';

// What is your site name?

$a->config['sitename'] = "Friendika Social Network";

// Your choices are REGISTER_OPEN, REGISTER_APPROVE, or REGISTER_CLOSED.
// Be certain to create your own personal account before setting 
// REGISTER_CLOSED. 'register_text' (if set) will be displayed prominently on 
// the registration page. REGISTER_APPROVE requires you set 'admin_email'
// to the email address of an already registered person who can authorise
// and/or approve/deny the request. 

$a->config['register_policy'] = REGISTER_OPEN;
$a->config['register_text'] = '';
$a->config['admin_email'] = '';

// Maximum size of an imported message, 0 is unlimited

$a->config['max_import_size'] = 200000;

// maximum size of uploaded photos

$a->config['system']['maximagesize'] = 800000;

// Location of PHP command line processor

$a->config['php_path'] = 'php';

// You shouldn't need to change anything else.
// Location of global directory submission page. 

$a->config['system']['directory_submit_url'] = 'http://dir.friendika.com/submit';
$a->config['system']['directory_search_url'] = 'http://dir.friendika.com/directory?search=';

// PuSH - aka pubsubhubbub URL. This makes delivery of public posts as fast as private posts

$a->config['system']['huburl'] = 'http://pubsubhubbub.appspot.com';

// Server-to-server private message encryption (RINO) is allowed by default. 
// Encryption will only be provided if this setting is true and the
// PHP mcrypt extension is installed on both systems 

$a->config['system']['rino_encrypt'] = true;

// default system theme

$a->config['system']['theme'] = 'duepuntozero';


// Addons or plugins are configured here.
// This is a comma seperated list of addons to enable. Example:
// $a->config['system']['addon'] = 'js_upload,randplace,oembed';

$a->config['system']['addon'] = 'js_upload';


// Disable oembed embedding
// This disable the conversion of [embed]$url[/embed] tag in html
// $a->config['system']['no_oembed'] = true;

