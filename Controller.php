<?php

namespace Irbis;


/**
 * Clase abstracta, esta se debe heredar para implementar
 * la lógica de la aplicación a construir, los controladores
 * son los bloques de código que se registran en el servidor,
 * sus métodos pueden representar acciones a rutas cliente
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <GeorgeL1102@gmail.com>
 * @version		1.0
 */
abstract class Controller {

	/**
	 * Nombre completo y nombre alias de la clase
	 * @var string
	 */
	public $klass;
	public $name;

	/**
	 * Directorio donde se ubica la clase
	 * @var string
	 */
	public $dir;

	/**
	 * Almacena las rutas encontradas en la clase
	 * @var array
	 */
	public $routes = false;

	/**
	 * Instancia del objeto servidor
	 * @var \Irbis\Server
	 */
	private $server;

	/**
	 * Constructor
	 */
	public function __construct () {
		$klass = get_class($this);
		$ds = DIRECTORY_SEPARATOR;
		$k = array_slice(explode('\\', $klass), 0, -1);

		$this->klass = $klass;
		$this->dir = BASE_PATH.$ds.implode($ds, $k);
	}

	/**
	 * Este método se hereda y puede ser sobreescrito
	 * por el controlador hijo, es llamado siempre
	 * en cada petición del cliente
	 */
	public function init () {}

	/**
	 * Establece la referencia al objeto servidor
	 * si esta no está establecida
	 * @param \Irbis\Server $server
	 */
	public function setServer (Server $server) {
		if (!$this->server)
			$this->server = $server;
	}

	/**
	 * Obtiene el objeto servidor enlazado
	 * al controlador actual
	 * @return \Irbis\Server
	 */
	public function getServer () : Server {
		return $this->server;
	}

	/**
	 * Devuelve un arreglo de rutas que coíncidan
	 * con el modelo de ruta enviado por parámetro
	 * @param string $path
	 * @return array[\Irbis\Route]
	 */
	public function getMatchedRoutes (string $path) {
		$matchs = [];

		if (!$this->routes)
			return $matchs;
		if ($this->routes === true)
			$this->fillRoutes();

		foreach ($this->routes as $route) {
			if ($route->match($path)) {
				$matchs[] = $route;
			}
		}

		return $matchs;
	}

	/**
	 * Rellena el arreglo $routes con los métodos
	 * que registren una ruta
	 */
	private function fillRoutes () {
		$this->routes = [];
		$klass = new \ReflectionClass($this);

		foreach ($klass->getMethods() as $method) {
			$comment = $method->getDocComment();
			if (preg_match_all('#@(route|verb) (.*?)\r#', $comment, $pm)) {
				if (in_array('route', $pm[1])) {
					$route = new Route($this, $method->name);
					foreach ($pm[1] as $i => $m) {
						if ($m == 'route') $route->path = $pm[2][$i];
						if ($m == 'verb') $route->verb = $pm[2][$i];
					}
					$this->routes[] = $route;
				}
			}
		}
	}

	/**
	 * intenta llamar un archivo 'php' dentro del directorio del módulo
	 * @param string $path, ruta de archivo a incluir
	 * @param boolean $return, si es 'true' devuelve lo que el directorio retorne
	 * @return mix
	 */
	public function include ($path, $return = false) {
		if (file_exists($this->dir.$path)) {
			$inc = include($this->dir.$path);
			return $return ? $inc : true;
		} return false;
	}

	/**
	 * Atajo para obtener un controlador del servidor
	 * @return \Irbis\Controller 
	 */
	protected function getController ($name) : Controller {
		return $this->getServer()->getController($name);
	}

	/**
	 * Atajo para obtener la respuesta a la misma u otra ruta
	 * para sobreescribir rutas previamente configuradas
	 * @return \Irbis\Response
	 */
	protected function respond (string $path = '') : Response {
		return $this->getServer()->respond($path);
	}
}