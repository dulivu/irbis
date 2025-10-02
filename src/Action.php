<?php

namespace Irbis;


/**
 * Representa una acción a ejecutar por el cliente
 * Es una envoltura para un método de un controlador
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.2
 */
class Action {
	# el controlador a la que pertenece esta acción
	private $controller;
	# el nombre del método que se ejecutará
	private $method = '';
	# se registra las rutas a las que esta acción response
	private $routes = [];
	# se registra el verbo HTTP a la que esta acción responde
	private $verb = '';
	# si no tiene verbo, se asume que responde a todos los verbos HTTP

	public function __construct (Controller $controller, string $method) {
		$this->controller = $controller;
		$this->method = $method;
	}

	public function setRoute ($route) {
		$this->routes[] = $route;
	}
	public function setRoutes ($routes) {
		foreach ($routes as $route) 
			$this->setRoute($route);
	}
	public function setVerb ($verb) {
		$this->verb = $verb;
	}

	/**
	 * Valida si una solicitud coíncide con alguna de
	 * las rutas registradas en esta acción
	 * @param string $path
	 */
	public function match (string $path) : bool {
		# si la ruta solicitada es diferente a la ruta del 
		# cliente, es una solicitud fake
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
	 * Ejecuta la acción registrada de la ruta
	 * @param $response \Irbis\Response
	 * @return mix, lo que el método devuelva
	 */
	public function execute (Response $response) {
		return $this->controller->{$this->method}(
			Request::getInstance(), 
			$response
		);
	}
}