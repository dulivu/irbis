<?php

namespace Irbis;


/**
 * Clase abstracta, esta se debe heredar para implementar la lógica de la aplicación, 
 * los controladores son los bloques de código que se registran en el servidor,
 * sus métodos pueden representar acciones a rutas cliente
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.2
 */
abstract class Controller {

	# Configuraciones del controlador a ser heredadas
	public $name; 						# nombre alías del módulo, simple y de una sóla palabra
	public $has_routes 		= false; 	# determina si el controlador tiene rutas de cliente
	public $installable 	= false; 	# determina si el módulo es instalable
	public $depends 		= []; 		# dependencias de otros modulos namespaces
	public $views 			= 'views'; 	# directorio de vistas

	# Valores de control interno
	protected $namespace; # espacio de nombre único en la aplicación, e: DemoApps/Sample1
	protected $server; # instancia del servidor, si fue ensamblado tiene uno
	private $state; # archivo de configuración del módulo, persistencia del estado del módulo
	private $directory; # directorio fisico donde se encuentra el módulo
	private $routes = false; # almacena las rutas en este controlador

	# Constantes de comportamiento de algunos métodos
	const FILE_PATH = 1; # 0001
	const FILE_CONTENT = 2; # 0010
	const FILE_INCLUDE = 4; # 0100
	const FILE_UPLOAD = 8; # 1000

	public function __construct () {
		$klass = get_class($this);
		$s = DIRECTORY_SEPARATOR;
		$k = array_slice(explode('\\', $klass), 0, -1);
		$d = BASE_PATH.$s.implode($s, $k);

		# inicialización de variables
		$this->namespace = implode('/', $k);
		$this->directory = BASE_PATH.$s.implode($s, $k);
	}

	public function getMatchedRoutes (string $path) : array {
		# $path, ruta solicitada por el cliente
		# este método debe devolver una lista de rutas [Irbis/Route]
		# que coincidan con la solicitud del cliente
		$matchs = [];

		if (!$this->has_routes)
			return $matchs;
		else
			$this->fillRoutes();

		foreach ($this->routes as $route) {
			if ($route->match($path)) {
				$matchs[] = $route;
			}
		}

		return $matchs;
	}

	private function fillRoutes () {
		# si las rutas ya fueron registradas, no se vuelve a ejecutar
		if ($this->routes) return;

		# registra la rutas declaradas de este controlador, busca
		# en todos sus métodos y analiza cuales responden a una ruta
		$this->routes = [];
		$klass = new \ReflectionClass($this);

		$reduce = function ($pm, $mode) {
			foreach ($pm[0] as $k => $r)
				if ($pm[1][$k] == $mode)
					$routes[] = $pm[2][$k];
			return $routes ?? [];
		};

		foreach ($klass->getMethods() as $method) {
			$comment = $method->getDocComment();
			if (preg_match_all('#@(route|verb) (.*?)\R#', $comment, $pm)) {
				if (in_array('route', $pm[1])) {
					$matches = $reduce($pm, 'route');
					$route = new Route($this, $method->name);
					foreach ($matches as $match) {
						$route->setRoute($match);
					}
					if (in_array('verb', $pm[1])) {
						$verbs = $reduce($pm, 'verb');
						$route->setVerb($verbs[0]);
					}
					$this->routes[] = $route;
				}
			}
		}
	}

	public function assembleTo (Server $server) : Controller {
		# se asigna la instancia servidor a este controlador
		$this->server = $server;
		return $this;
	}

	public function key () {
		return $this->namespace;
	}

	// =============================
	// ==== HELPERS & INTERNALS ====
	// =============================

	public function init () {}

	public function assemble () {
		# TODO: buscar otra forma de implementarlo
		# este es un método comodín no tiene una función específica
		# se piensa que otros controladores le puedan encontrar un uso
		# por el momento, un módulo maestro lo usa para ejecutar
		# instalaciones de otros módulos, como un controlador maestro
	}

	public function isAssembled () : bool {
		# TODO: se usa con el de arriba
		return !!$this->server;
	}

	public function state ($key, $val=null) {
		# permite tener un estado de esta controlador sin usar bd
		# ayuda en configuraciones y persistencia de datos
		if (!$this->state) {
			$config_file = $this->file("controller_state.ini");
			$this->state = new ConfigFile($config_file);
		}
		if ($val !== null) {
			# se puede usar la constante REMOVE_STATE 
			# para eliminar un valor
			if ($val == REMOVE_STATE)
				$this->state->set($key, null);
			else
				$this->state->set($key, $val);
			return $this;
		} else return $this->state->get($key);
	}

	public function file (string $file = "", $options = 1) {
		if (!$file) return $this->directory.DIRECTORY_SEPARATOR;
		
		$path = pathcheck($file);
		$path = [$this->directory.DIRECTORY_SEPARATOR.$path];
		$has_wildcard = strpos($file, '*') !== false;
		if ($has_wildcard)
			$path = glob($path[0], GLOB_NOSORT|GLOB_BRACE);

		if (gettype($options) == 'string') {
			$path_data = pathinfo($path[0]);
			if (!is_dir($path_data['dirname']))
				mkdir($path_data['dirname'], 0777, TRUE);
			file_put_contents($path[0], $options);
			return true;
		}

		if (is_callable($options)) {
			$controller = $this;
			if (!Request::hasUploads($file)) return false;
			Request::forEachUpload($file, function ($upload_data) use ($controller, $options) {
				$path = $options($upload_data);
				if ($path) {
					$path = $controller->file($path);
					$path_data = pathinfo($path);
					if (!is_dir($path_data['dirname']))
						mkdir($path_data['dirname'], 0777, TRUE);
					move_uploaded_file($upload_data['tmp_name'], $path);
				}
			}, true);
			return true;
		}

		# FILE_PATH = retorna la ruta completa
		# FILE_CONTENT = retorna el archivo binario para ser modificado
		# FILE_INCLUDE = incluye el archivo usando include
		if ($options & Controller::FILE_PATH) {
			return $has_wildcard ? $path : ($path[0] ?? False);
		}

		if ($options & Controller::FILE_CONTENT) {
			$contents = [];
			foreach ($path as $p) {
				if (file_exists($p)) {
					$contents[$p] = file_get_contents($p);
				} else $contents[$p] = false;
			}
			return count($path) != 1 ? $contents : ($contents[$path[0]] ?? false);
		}
		
		if ($options & Controller::FILE_INCLUDE) {
			$incs = [];
			foreach ($path as $p) {
				if (file_exists($p)) {
					$inc = include($p);
					$incs[$p] = $inc ?: true;
				} else $incs[$p] = false;
			}
			return count($path) != 1 ? $incs : ($incs[$path[0]] ?? False);
		}

		return false;
	}

	// =============================
	// ========= SHORTCUTS =========
	// =============================
	
	protected function super (string $fake_path = '') {
		$server = Server::getInstance();
		return $server->execute($fake_path);
	}

	protected function controller ($name) : ?Controller {
		$server = Server::getInstance();
		return $server->getController($name);
	}
}
