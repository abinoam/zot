<?php

function directory_init(&$a) {
	$a->set_pager_itemspage(60);
}


function directory_post(&$a) {
	if(x($_POST,'search'))
		$a->data['search'] = $_POST['search'];
}



function directory_content(&$a) {

	$everything = (($a->argc > 1 && $a->argv[1] === 'all' && is_site_admin()) ? true : false);

	if((get_config('system','block_public')) && (! local_user()) && (! remote_user())) {
		notice( t('Public access denied.') . EOL);
		return;
	}

	$o = '';
	$o .= '<script>	$(document).ready(function() { $(\'#nav-directory-link\').addClass(\'nav-selected\'); });</script>';
	if(x($_SESSION,'theme'))
		unset($_SESSION['theme']);

	if(x($a->data,'search'))
		$search = notags(trim($a->data['search']));
	else
		$search = ((x($_GET,'search')) ? notags(trim(rawurldecode($_GET['search']))) : '');

	$tpl = get_markup_template('directory_header.tpl');

	$globaldir = '';
	$gdirpath = dirname(get_config('system','directory_submit_url'));
	if(strlen($gdirpath)) {
		$globaldir = '<ul><li><div id="global-directory-link"><a href="'
		. $gdirpath . '">' . t('Global Directory') . '</a></div></li></ul>';
	}

	$admin = '';
	if(is_site_admin()) {
		if($everything)
			$admin =  '<ul><li><div id="directory-admin-link"><a href="' . z_path() . '/directory' . '">' . t('Normal site view') . '</a></div></li></ul>';
		else
			$admin = '<ul><li><div id="directory-admin-link"><a href="' . z_path() . '/directory/all' . '">' . t('View all site entries') . '</a></div></li></ul>';
	}

	$o .= replace_macros($tpl, array(
		'$search' => $search,
		'$globaldir' => $globaldir,
		'$admin' => $admin,
		'$finding' => (strlen($search) ? '<h4>' . t('Finding: ') . "'" . $search . "'" . '</h4>' : ""),
		'$sitedir' => t('Site Directory'),
		'$submit' => t('Find')
	));

	if($search)
		$search = dbesc($search);
	$sql_extra = ((strlen($search)) ? " AND MATCH (`profile`.`name`, `user`.`nickname`, `pdesc`, `locality`,`region`,`country_name`,`gender`,`marital`,`sexual`,`about`,`romance`,`work`,`education`,`pub_keywords`,`prv_keywords` ) AGAINST ('$search' IN BOOLEAN MODE) " : "");

	$publish = ((get_config('system','publish_all') || $everything) ? '' : " AND `publish` = 1 " );


	$r = q("SELECT COUNT(*) AS `total` FROM `profile` LEFT JOIN `user` ON `user`.`uid` = `profile`.`uid` WHERE `is_default` = 1 $publish AND `user`.`blocked` = 0 $sql_extra ");
	if(count($r))
		$a->set_pager_total($r[0]['total']);

	if($everything)
		$order = " ORDER BY `register_date` DESC ";
	else
		$order = " ORDER BY `name` ASC "; 


	$r = q("SELECT `profile`.*, `profile`.`uid` AS `profile_uid`, `user`.`nickname`, `user`.`timezone` FROM `profile` LEFT JOIN `user` ON `user`.`uid` = `profile`.`uid` WHERE `is_default` = 1 $publish AND `user`.`blocked` = 0 $sql_extra $order LIMIT %d , %d ",
		intval($a->pager['start']),
		intval($a->pager['itemspage'])
	);
	if(count($r)) {

		$tpl = get_markup_template('directory_item.tpl');

		if(in_array('small', $a->argv))
			$photo = 'thumb';
		else
			$photo = 'photo';

		foreach($r as $rr) {


			$profile_link = z_path() . '/profile/' . ((strlen($rr['nickname'])) ? $rr['nickname'] : $rr['profile_uid']);
		
			$pdesc = (($rr['pdesc']) ? $rr['pdesc'] . '<br />' : '');

			$details = '';
			if(strlen($rr['locality']))
				$details .= $rr['locality'];
			if(strlen($rr['region'])) {
				if(strlen($rr['locality']))
					$details .= ', ';
				$details .= $rr['region'];
			}
			if(strlen($rr['country_name'])) {
				if(strlen($details))
					$details .= ', ';
				$details .= $rr['country_name'];
			}
			if(strlen($rr['dob'])) {
				if(($years = age($rr['dob'],$rr['timezone'],'')) != 0)
					$details .= '<br />' . t('Age: ') . $years ; 
			}
			if(strlen($rr['gender']))
				$details .= '<br />' . t('Gender: ') . $rr['gender'];

			$entry = replace_macros($tpl,array(
				'$id' => $rr['id'],
				'$profile-link' => $profile_link,
				'$photo' => $rr[$photo],
				'$alt-text' => $rr['name'],
				'$name' => $rr['name'],
				'$details' => $pdesc . $details  


			));

			$arr = array('contact' => $rr, 'entry' => $entry);

			call_hooks('directory_item', $arr);

			$o .= $entry;

		}

		$o .= "<div class=\"directory-end\" ></div>\r\n";
		$o .= paginate($a);

	}
	else
		info( t("No entries \x28some entries may be hidden\x29.") . EOL);

	return $o;
}
