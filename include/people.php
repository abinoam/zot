<?php

define (DEFAULT_PERSON_NAME,  'Anonymous');
define (DEFAULT_PERSON_PHOTO, '');
define (DEFAULT_PERSON_URL,   '');
define (DEFAULT_PERSON_ID,    0);

class person {

	private $name;
	private $photo;
	private $url;
	private $id;

	function __construct($name,$photo,$url) {
		$this->name  = (($name)  ? $name  : DEFAULT_PERSON_NAME );
		$this->photo = (($photo) ? $photo : DEFAULT_PERSON_PHOTO);
		$this->url   = (($url)   ? $url   : DEFAULT_PERSON_URL  );
		$this->id    = (($id)    ? $id    : DEFAULT_PERSON_ID   );
	}

	function to_array() {
		return(array(
			'name'  => $this->name,
			'photo' => $this->photo,
			'url'   => $this->url,
			'id'    => $this->id
		));
	}

	function to_json() {
		return json_encode($this->to_array());
	}
}

class contact extends person {

	private $relation;
	private $network;
	private $cid;




}


class member extends person {

	private $password;
	private $username;
	private $uid;
	private $role;



}

