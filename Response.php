<?php

namespace Irbis;


/**
 * Administra rutas coíncidentes con la solicitud del cliente
 * para procesar las respuestas que se deben de entregar
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
class Response {

	/**
	 * La ruta a la que esta respuesta pertence
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
	public $route;

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


	/**
	 * Constructor
	 * @param $path 
	 */
	public function __construct (string $path = null) {
		$this->path = $path;
		$this->routes = [];
		$this->data = [
			'status' => Request::$method == 'POST' ? 'success' : '',
			'message' => Request::$method == 'POST' ? '¡Acción realizada!' : '',
		];
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
		$this->routes = array_merge($this->routes, $routes);
	}

	/**
	 * Captura la última ruta registrada en la pila
	 * si ya no hay rutas en la pila devuelve falso
	 * @return bool
	 */
	public function prepare () : bool {
		$this->route = array_pop($this->routes);
		return $this->route ? true : false;
	}

	/**
	 * Devuelve falso si no está preparada para responder
	 * previamente se debe llamar a 'prepare' y luego verificar
	 * @return bool
	 */
	public function isReady () {
		return !!$this->route;
	}

	/**
	 * Combina los datos entregados con los que ya tiene registrados
	 * @param array $data
	 */
	public function setData (array $data) {
		$this->data = array_merge($this->data, $data);
	}

	/**
	 * Ejecuta la acción del controlador este método
	 * debe ser usado después de llamar a 'prepare', ya que
	 * no hará nada si no tiene un controlador para ejecutar
	 * @return \Irbis\Response
	 */
	public function execute () : Response {
		if ($this->isReady()) {
			$x = $this->route->execute($this);
			if ($x !== null) {
				if ($x instanceof Response)
					return $x;

				if (is_string($x) && substr($x, -5) == '.html') {
					$this->view = $x;
				} else {
					$this->view = null;
					$this->data = $x;
				}
			}
		}

		$this->route = null;
		return $this;
	}

	/**
	 * Devuelve la ruta solicitada y la ruta coíncidente para esta respuesta
	 * @return array[string, string]
	 */
	public function paths () {
		return [$this->path, $this->route ? $this->route->path : null];
	}

	/**
	 * Atajo para redireccionar al cliente a otra ruta
	 * @param string $url
	 */
	public function redirect (string $url) {
		header('Location: '.$url);
		die('redirecting...');
	}
}
