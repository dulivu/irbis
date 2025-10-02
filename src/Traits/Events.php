<?php
namespace Irbis\Traits;


/**
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
trait Events {
	private $events = [];

	public function on ($e, $fn) {
		$this->events[$e] = $this->events[$e] ?? [];
		$this->events[$e][] = $fn;
		return $this;
	}

	public function fire ($e, $params = array()) {
		$params = is_array($params) ? $params : [$params];
		if (isset($this->events[$e])) 
			foreach ($this->events[$e] as $fn)
				$fn->call($this, ...$params);
		return $this;
	}
}