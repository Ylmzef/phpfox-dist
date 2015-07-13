<?php

namespace Api\User;

class Object extends \Core\Objectify {
	public $id;
	public $url;
	public $name;
	public $name_link;
	public $photo;
	public $photo_link;
	public $location;
	public $gender;
	public $dob;

	public function perm($perm) {
		return \Phpfox::getUserParam($perm);
	}
}