<?php

require_once('include/security.php');

function photo_init(&$a) {

	switch($a->argc) {
		case 3:
			$person = $a->argv[2];
			$type = $a->argv[1];
			break;
		case 2:
			$photo = $a->argv[1];
			break;
		case 1:
		default:
			killme();
			// NOTREACHED
	}

	$default = 'images/default-profile.jpg';

	if(isset($type)) {

		/**
		 * Profile photos
		 */

		switch($type) {

			case 'profile':
				$resolution = 4;
				break;
			case 'micro':
				$resolution = 6;
				$default = 'images/default-profile-mm.jpg';
				break;
			case 'avatar':
			default:
				$resolution = 5;
				$default = 'images/default-profile-sm.jpg';
				break;
		}

		$uid = str_replace('.jpg', '', $person);

		$r = q("SELECT * FROM `photo` WHERE `scale` = %d AND `uid` = %d AND `profile` = 1 LIMIT 1",
			intval($resolution),
			intval($uid)
		);
		if(count($r)) {
			$data = $r[0]['data'];
		}
		if(! isset($data)) {
			$data = file_get_contents($default);
		}
	}
	else {

		/**
		 * Other photos
		 */

		$resolution = 0;
		$photo = str_replace('.jpg','',$photo);
	
		if(substr($photo,-2,1) == '-') {
			$resolution = intval(substr($photo,-1,1));
			$photo = substr($photo,0,-2);
		}

		$r = q("SELECT `uid` FROM `photo` WHERE `resource_id` = '%s' AND `scale` = %d LIMIT 1",
			dbesc($photo),
			intval($resolution)
		);
		if(count($r)) {
			
			$sql_extra = permissions_sql($r[0]['uid']);

			// Now we'll see if we can access the photo

			$r = q("SELECT * FROM `photo` WHERE `resource_id` = '%s' AND `scale` = %d $sql_extra LIMIT 1",
				dbesc($photo),
				intval($resolution)
			);

			if(count($r)) {
				$data = $r[0]['data'];
			}
			else {

				// Does the picture exist? It may be a remote person with no credentials,
				// but who should otherwise be able to view it. Show a default image to let 
				// them know permissions was denied. It may be possible to view the image 
				// through an authenticated profile visit.
				// There won't be many completely unauthorised people seeing this because
				// they won't have the photo link, so there's a reasonable chance that the person
				// might be able to obtain permission to view it.
 
				$r = q("SELECT * FROM `photo` WHERE `resource_id` = '%s' AND `scale` = %d LIMIT 1",
					dbesc($photo),
					intval($resolution)
				);
				if(count($r)) {
					$data = file_get_contents('images/nosign.jpg');
				}
			}
		}
	}

	if(! isset($data)) {
		killme();
		// NOTREACHED
	}

	header("Content-type: image/jpeg");
	echo $data;
	killme();
	// NOTREACHED
}