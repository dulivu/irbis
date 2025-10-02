<?php

namespace Irbis\Traits;


/**
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
trait Singleton {

	private static $instance;

	public static function getInstance (...$params) {
		if (!self::$instance instanceof self)
			self::$instance = new self(...$params);
		return self::$instance;
	}

	private function __construct () {}
	private function __clone () {}
	public function __wakeup () {}	
}