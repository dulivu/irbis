<?php
namespace Irbis\Traits;


/**
 * @package 	irbis
 * @author		Jorge Luis Quico C. <GeorgeL1102@gmail.com>
 * @version		1.0
 */
trait EventsStatic {
	private static $events = [];

	public static function on ($e, $fn) {
		self::$events[$e] = self::$events[$e] ?? [];
		self::$events[$e][] = $fn;
	}

	public static function fire ($e, $bind, $params = array()) {
		$params = is_array($params) ? $params : [$params];
		if (isset(self::$events[$e])) 
			foreach (self::$events[$e] as $fn)
				$fn->call($bind, ...$params);
	}
}