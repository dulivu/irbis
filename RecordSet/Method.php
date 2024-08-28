<?php
namespace Irbis\RecordSet;


/**
 * Representa un método del modelo
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
class Method extends Member {

	/**
	 * Nombre del método
	 * @var string
	 */
	public $name;
	/**
	 * pila de ejecuciones que puede
	 * realizar el método
	 * @var [closure]
	 */
	private $stack;

	public function __construct ($name) {
		$this->name = $name;
		$this->stack = [];
	}

	/**
	 * Añade un closure a la pila
	 * para uso interno
	 * @param Closure $fn
	 */
	public function addClosure (\Closure $fn) {
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