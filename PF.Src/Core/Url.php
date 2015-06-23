<?php

namespace Core;

/**
 * Class Url
 * @package Core
 *
 * @method make($route)
 * @method send($route)
 * @method current()
 * @method uri()
 */
class Url {

	private static $_url = null;

	public function __construct() {
		if (self::$_url === null) {
			self::$_url = \Phpfox_Url::instance();
		}
	}

	public function route() {
		return '/' . trim(str_replace('.', '/', \Phpfox_Module::instance()->getFullControllerName()), '/');
	}

	public function __call($method, $args) {
		switch ($method) {
			case 'make':
				$method = 'makeUrl';
				break;
			case 'uri':
				$method = 'getUri';
				break;
			case 'current':
				$method = 'current';
				break;
		}

		return call_user_func_array([self::$_url, $method], $args);
	}
}