<?php

namespace Irbis;


/**
 * Administra rutas coíncidentes con la solicitud del cliente
 * para procesar las respuestas que se deben de entregar
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.2
 */
class Response {

	/**
	 * La ruta solicitada a la que esta respuesta pertence
	 * @var string
	 */
	private $path;

	/**
	 * Las rutas coincidentes de los controladores
	 * @var array[\Irbis\Route]
	 */
	private $routes;

	/**
	 * La ruta preparada para responder
	 * @var \Irbis\Route
	 */
	private $route;

	/**
	 * La vista que utilizará para responder
	 * @var string
	 */
	public $view;

	/**
	 * Los datos que enviará a la vista, o
	 * en caso que no se establesca una vista,
	 * la información que se enviará como respuesta
	 * @var array|mix
	 */
	public $data;
	public $json;


	/**
	 * Constructor
	 * @param $path 
	 */
	public function __construct (string $path = null) {
		$this->path = $path;
		$this->data = [];
		$this->routes = [];
	}

	/**
	 * Cuando la clase es llamada como una cadena
	 * @return string
	 */
	public function __toString () {
		if (is_array($this->data))
			return Json::encode($this->data);
		return "{$this->data}";
	}

	/**
	 * Agrega una ruta coíncidente a la lista de rutas
	 * @param array $route
	 */
	public function addRoute (Route $route) {
		$this->routes[] = $route;
	}

	/**
	 * Agrega varias rutas coíncidentes a la lista de rutas
	 * @param array $routes
	 */
	public function addRoutes (array $routes) {
		# [1,2] + [3,4] = [1,2,3,4]
		$this->routes = array_merge($this->routes, $routes);
	}

	/**
	 * Captura la última ruta registrada en la pila
	 * si ya no hay rutas en la pila devuelve falso
	 * @return bool
	 */
	public function prepareRoute () : bool {
		$this->route = array_pop($this->routes);
		return $this->route ? true : false;
	}

	/**
	 * Devuelve falso si no está preparada para responder
	 * previamente se debe llamar a 'prepare' y luego verificar
	 * @return bool
	 */
	public function isReady () : bool {
		return !!$this->route;
	}

	/**
	 * Si existe una Ruta lista (se debe usar prepareRoute) se ejecuta esta,
	 * en cualquier caso devuelve un objeto Response
	 * @return \Irbis\Response
	 */
	public function executeRoute () : Response {
		$is_template = function ($template) {
			return is_string($template) && substr($template, -5) == '.html';
		};

		if ($this->isReady()) {
			$x = $this->route->execute($this);
			$this->route = null;
			if ($x !== null) {
				if ($x instanceof Response) {
					$x->view = $x->view ? pathcheck($x->view, '/') : $x->view;
					return $x;
				}
				if ($is_template($x)) {
					$this->view = pathcheck($x, '/');
				} elseif (is_array($x) && !is_assoc($x) && $is_template($x[0] ?? null)) {
					$this->view = pathcheck($x[0], '/');
					$this->data = $x[1] ?? [];
				} else {
					$this->view = null;
					$this->data = $x;
				}
			}
		}

		return $this;
	}

	// =============================
	// ==== HELPERS & INTERNALS ====
	// =============================

	/**
	 * Combina los datos entregados con los que ya tiene registrados
	 * @param array $data
	 */
	public function setData (array $data) {
		$this->data = array_merge_recursive($this->data, $data);
	}

	/**
	 * Atajo para redireccionar al cliente a otra ruta
	 * @param string $url
	 */
	public function redirect (string $url) {
		header('Location: '.$url, true);
		die('redirecting...');
	}

	/**
	 * Establece una cabecera de respuesta
	 */
	public function header ($header, $replace = true, $response_code = 0) {
		header($header, $replace, $response_code);
	}
}
