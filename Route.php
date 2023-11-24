<?php

namespace Irbis;


/**
 * Representa una ruta coíncidente con la petición del cliente,
 * en escencia es una envoltura de un método controlador a ejecutar
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.2
 */
class Route {

	private $controller;
	private $method = '';
	private $path = '';
	private $verb = '';

	public function __construct (Controller $controller, string $method) {
		$this->controller = $controller;
		$this->method = $method;
	}

	public function setPath ($path, $verb = false) {
		$this->path = $path;
		if ($verb) $this->verb = $verb;
	}

	public function setVerb ($verb) {
		$this->verb = $verb;
	}

	/**
	 * Valida si la petición coíncide con la ruta
	 * @param string $path
	 */
	public function match (string $path) : bool {
		$sm = $path == Request::$path; # si la ruta solicita es diferente a la ruta del cliente, es una solicitud interna
		if ($this->verb && $this->verb != Request::$method)
			return false;
		return $path == $this->path || Request::compare($path, $this->path, $sm);
	}

	/**
	 * Ejecuta la acción registrada de la ruta,
	 * un método dentro de un controlador relacionado
	 * @param $response \Irbis\Response
	 * @return mix, lo que el método devuelva
	 */
	public function execute (Response $response) {
		return $this->controller->{$this->method}(Request::getInstance(), $response);
	}
}