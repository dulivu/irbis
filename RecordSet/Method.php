<?php
namespace Irbis\RecordSet;


/**
 * Representa un método del modelo
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <GeorgeL1102@gmail.com>
 * @version		1.0
 */
class Method extends Member {

	private $name;
	private $stack;

	public function __construct ($name) {
		$this->name = $name;
		$this->stack = [];
	}

	/**
	 * Añade una retrollamada a la pila
	 * 
	 * @param Closure $fn
	 */
	public function stack (\Closure $fn) {
		$this->stack[] = $fn;
	}

	/**
	 * Quitá de la pila el último elemento
	 * lo ejecuta y devuelve su resultado
	 *
	 * @param array $args - parámetros a enviar
	 * @param object $bind - objeto para enlazar
	 *
	 * @return mix - lo que la retrollamada devuelva
	 */
	public function call ($args, $bind) {
		$fn = array_pop($this->stack);
		return $fn === null ? 
			$fn : $fn->call($bind, ...$args);
	}
}