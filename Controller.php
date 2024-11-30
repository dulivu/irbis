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
	public $name; # nombre alías del módulo, simple y de una sóla palabra
	public $has_routes 		= false; # determina si el controlador tiene rutas de cliente
	public $installable 	= false; # determina si el módulo es instalable
	public $depends 		= []; # dependencias de otros modulos namespaces
	public $views 			= 'views'; # directorio de vistas

	# Valores de control interno
	public $namespace; # espacio de nombre único en la aplicación, e: DemoApps/Sample1
	protected $server; # instancia del servidor
	private $assembled = false; # determina si el controlador ha sido ensamblado al servidor
	private $state; # archivo de configuración del módulo, persistencia del estado del módulo
	private $directory; # directorio fisico donde se encuentra el módulo
	private $routes = false; # almacena las rutas en este controlador

	const FILE_PATH = 1; # 0001
	const FILE_CONTENT = 2; # 0010
	const FILE_INCLUDE = 4; # 0100
	const FILE_BINARY = 8; # 1000

	/**
	 * Constructor
	 * Inicializa las variables 'namespace' y 'directory'
	 */
	public function __construct () {
		$klass = get_class($this);
		$s = DIRECTORY_SEPARATOR;
		$k = array_slice(explode('\\', $klass), 0, -1);
		$d = BASE_PATH.$s.implode($s, $k);

		$this->namespace = implode('/', $k);
		$this->directory = BASE_PATH.$s.implode($s, $k);
	}

	/**
	 * Este método se hereda y puede ser sobreescrito
	 * se llama sólo a la petición del cliente una única vez
	 * al utilizar llamadas 'super' ya no se vuelve a ejectuar
	 */
	public function init () {}

	/**
	 * devuelve un arreglo de Rutas que coínciden con la solicitud del cliente
	 * llena por primera vez todas las rutas existentes en el controlador
	 * uso exclusivo de la clase \Server
	 * 
	 * @param string $path, ruta de cliente a coincidir
	 * @return array[\Irbis\Route]
	 */
	public function getMatchedRoutes (string $path) : array {
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

	/**
	 * Rellena el atributo $routes con los métodos que registren una ruta
	 * uso exclusivo de la clase \Server
	 */
	private function fillRoutes () {
		$this->routes = [];
		$klass = new \ReflectionClass($this);

		$reduce = function ($pm) {
			foreach ($pm[0] as $k => $r)
				$routes[] = $pm[2][$k];
			return $routes ?? [];
		};

		foreach ($klass->getMethods() as $method) {
			$comment = $method->getDocComment();
			if (preg_match_all('#@(route|verb) (.*?)\R#', $comment, $pm)) {
				if (in_array('route', $pm[1])) {
					$matches = $reduce($pm);
					$route = new Route($this, $method->name);
					foreach ($matches as $match) {
						$route->setRoute($match);
						# TODO: manejar multiples rutas por método
						# ya está construido para aceptarlas, falta administrarlas

						// foreach ($pm[1] as $i => $m) {
						// 	if ($m == 'route') $route->setRoute($pm[2][$i]);
						// 	# TODO: el método ya distingue el verbo, falta 
						// 	# separar los arreglos del controlador con el verbo
						// 	# incluído, hacer mas pruebas
						// 	if ($m == 'verb') $route->setVerb($pm[2][$i]);
						// }
						$this->routes[] = $route;
					}
				}
			}
		}
	}

	// =============================
	// ==== HELPERS & INTERNALS ====
	// =============================

	public function assemble () {}

	public function isAssembled () : bool {
		return $this->assembled;
	}

	public function doAssemble (Server $server) : Controller {
		# esté método es utilizado por el servidor
		# cuando el controllador es agregado
		$this->server = $server;
		$this->assembled = true;
		return $this;
	}

	public function file (string $file = "", $options = 1) {
		if (!$file) return $this->directory;
		
		$path = pathcheck($file);
		$path = [$this->directory.DIRECTORY_SEPARATOR.$path];
		if (strpos($file, '*') !== false)
			$path = glob($path[0], GLOB_NOSORT|GLOB_BRACE);

		# option 1 = retorna la ruta completa
		# option 2 = retorna el archivo binario para ser modificado
		# option 3 = incluye el archivo usando include
		if ($options & Controller::FILE_PATH) {
			return strpos($file, '*') !== false ? 
				$path : ($path[0] ?? False);
		}

		if ($options & Controller::FILE_CONTENT) {
			$contents = [];
			foreach ($path as $p) {
				if (file_exists($p)) {
					$contents[] = file_get_contents($p);
				} else $contents[] = false;
			}
			return count($path) != 1 ? $contents : ($contents[0] ?? false);
		}
		
		if ($options & Controller::FILE_INCLUDE) {
			$incs = [];
			foreach ($path as $p) {
				if (file_exists($p)) {
					$inc = include($p);
					$incs[] = $inc ?: true;
				} else $incs[] = false;
			}
			return count($path) != 1 ? $incs : ($incs[0] ?? False);
		}
	}

	public function state ($key, $val=null) {
		if (!$this->state) {
			$config_file = $this->file("controller_state.ini");
			$this->state = new ConfigFile($config_file);
		}
		if ($val !== null) {
			if ($val == REMOVE_STATE)
				$this->state->set($key, null);
			else
				$this->state->set($key, $val);
			return $this;
		} else return $this->state->get($key);
	}

	protected function putFile (string $file_path, $file_key, $permissions=0777) {
		$file_path = pathcheck($file_path); # agrega un '/' al final si no lo tiene
		$path_data = pathinfo($file_path); # extrae la información de la ruta

		$_is_dir = str_ends_with($file_path, '/') || str_ends_with($file_path, '\\'); # si termina en '/' es un directorio
		$basepath = $this->directory;
		$dirname = $_is_dir ? $file_path : $path_data['dirname'].DIRECTORY_SEPARATOR;
		$basename = $_is_dir ? false : $path_data['basename'];

		if (!is_dir($this->directory.$dirname))
			mkdir($this->directory.$dirname, $permissions, TRUE);

		if (Request::hasUploads($file_key)) {
			# TODO: validar que si se sube más de un archivo se tenga
			# que determinar un directorio y usar el nombre del archivo subido

			Request::eachUpload($file_key, function ($upload) use ($basepath, $dirname, $basename) {
				move_uploaded_file($upload['tmp_name'], $basepath.$dirname.($basename ?? $upload['name']));
			});
		} else {
			if (!$basename)
				throw new \Exception('Controller: debe determinar un nombre de archivo');
			file_put_contents($basepath.$dirname.$basename, $file_key);
		}
	}

	protected function super (string $fake_path = '') {
		$server = Server::getInstance();
		return $server->execute($fake_path);
	}

	protected function controller ($name) : ?Controller {
		$server = Server::getInstance();
		return $server->getController($name);
	}
}
