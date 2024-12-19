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
	private $routes = [];
	private $verb = '';

	public function __construct (Controller $controller, string $method) {
		$this->controller = $controller;
		$this->method = $method;
	}

	public function setRoute ($route) {
		$this->routes[] = $route;
	}
	public function setVerb ($verb) {
		$this->verb = $verb;
	}

	/**
	 * Valida si la petición coíncide con la ruta
	 * @param string $path
	 */
	public function match (string $path) : bool {
		// si la ruta solicita es diferente a la ruta del 
		// cliente, es una solicitud interna
		$sm = $path == Request::$path;
		if ($this->verb && $this->verb != Request::$method)
			return false;
		foreach ($this->routes as $route) {
			if ($path == $route || Request::compare($path, $route, $sm))
				return true;
		}
		return false;
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