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
	public static $name			= 'controller'; 	# nombre alías del módulo, simple y de una sóla palabra
	public static $use_routes	= false; 	# determina si el controlador tiene rutas de cliente
	public static $depends		= []; 		# dependencias de otros modulos namespaces
	public static $views 		= 'views'; 	# directorio de vistas

	# Valores de control interno
	private $_state; # archivo de configuración del módulo, persistencia del estado del módulo
	private $_namespace; # espacio de nombre único en la aplicación, e: DemoApps/Sample1
	private $_directory; # directorio fisico donde se encuentra el módulo
	private $_actions = false; # almacena las rutas en este controlador

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
		$this->_namespace = implode('/', $k); # IrbisApps/Base
		$this->_directory = BASE_PATH.$s.implode($s, $k);
	}

	public function getMatchedActions (string $path) : array {
		# $path, ruta solicitada por el cliente
		# este método devuelve una lista de acciones [Irbis/Action]
		# que coincidan con la solicitud del cliente
		$matches = [];

		if (!$this::$use_routes)
			return $matches;
		$this->registerActions();

		foreach ($this->_actions as $action) {
			if ($action->match($path)) {
				$matches[] = $action;
			}
		}

		return $matches;
	}

	private function registerActions () {
		# si las acciones ya fueron registradas, no se vuelve a ejecutar
		if ($this->_actions) return;

		# registra las acciones declaradas de este controlador
		# son acciones que el cliente puede ejecutar
		$this->_actions = [];
		$klass = new \ReflectionClass($this);

		$reduce = function ($pm, $mode) {
			foreach ($pm[0] as $k => $r)
				if ($pm[1][$k] == $mode)
					$action[] = $pm[2][$k];
			return $action ?? [];
		};

		foreach ($klass->getMethods() as $method) {
			$comment = $method->getDocComment();
			if (preg_match_all('#@(route|verb) (.*?)\R#', $comment, $pm)) {
				if (in_array('route', $pm[1])) {
					$routes = $reduce($pm, 'route');
					$action = new Action($this, $method->name);
					$action->setRoutes($routes);
					if (in_array('verb', $pm[1])) {
						$verbs = $reduce($pm, 'verb');
						$action->setVerb($verbs[0]);
					}
					$this->_actions[] = $action;
				}
			}
		}
	}

	public function namespace () {
		return $this->_namespace;
	}

	// =============================
	// ==== HELPERS & INTERNALS ====
	// =============================

	/**
	 * Sanitiza rutas de archivo para prevenir path traversal
	 * @param string $file
	 * @return string|false
	 */
	private function sanitizeFilePath(string $file) {
		// Eliminar secuencias peligrosas
		$dangerous = ['../', '..\\', '../', '..\\', '..', '//', '\\\\'];
		foreach ($dangerous as $pattern) {
			if (str_contains($file, $pattern)) {
				return false;
			}
		}
		
		// Solo permitir caracteres alfanuméricos, guiones, puntos y separadores
		if (!preg_match('/^[a-zA-Z0-9\-_\.\/\\\\]+$/', $file)) {
			return false;
		}
		
		// Verificar que no empiece con separador absoluto
		if (str_starts_with($file, '/') || str_starts_with($file, '\\')) {
			return false;
		}
		
		return $file;
	}

	public function init () {}

	public function assemble () {}

	public function state ($key, $val=null) {
		# permite tener un estado de esta controlador sin usar bd
		# ayuda en configuraciones y persistencia de datos
		if (!$this->_state) {
			$config_file = $this->filePath("controller_state.ini");
			$this->_state = new ConfigFile($config_file);
		}
		if ($val !== null) {
			# se puede usar la constante REMOVE_STATE 
			# para eliminar un valor
			if ($val == REMOVE_STATE)
				$this->_state->set($key, null);
			else
				$this->_state->set($key, $val);
			return $this;
		} else return $this->_state->get($key);
	}

	function writeFile(string $file, string $content) {
		# escribe un archivo en el directorio del controlador
		$path = $this->filePath($file, Controller::FILE_PATH);
		if (!$path) return false;
		return safe_file_write($path, $content);
	}

	function uploadFile (string $file, callable $fn) {
		$controller = $this;
		if (!Request::hasUploads($file)) return false;
		Request::forEachUpload($file, function ($upload_data) use ($controller, $fn) {
			$path = $fn($upload_data);
			if ($path) {
				$path = $controller->filePath($path);
				$path_data = pathinfo($path);
				if (!is_dir($path_data['dirname']))
					mkdir($path_data['dirname'], 0777, TRUE);
				move_uploaded_file($upload_data['tmp_name'], $path);
			}
		}, true);
		return true;
	}

	public function filePath (string $file = "", $options = 1) {
		if (!$file) return $this->_directory.DIRECTORY_SEPARATOR;
		
		// Seguridad: prevenir path traversal
		$file = $this->sanitizeFilePath($file);
		if ($file === false) {
			throw new \InvalidArgumentException("Ruta de archivo inválida o insegura");
		}
		
		$path = pathcheck($file); # to: path/file.ext
		$path = [$this->_directory.DIRECTORY_SEPARATOR.$path];
		$has_wildcard = str_contains($file, '*');
		if ($has_wildcard)
			$path = glob($path[0], GLOB_NOSORT|GLOB_BRACE);

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
