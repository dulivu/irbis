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

	/**
	 * Nombre completo y nombre alias de la clase
	 * @var string
	 */
	public $klass;
	public $name;
	public $directory;
	public $state;

	/**
	 * Almacena las rutas encontradas en la clase
	 * @var array
	 */
	public $routes = false;

	/**
	 * Constructor
	 * Inicializa las variables 'klass' y 'directory'
	 */
	public function __construct () {
		$klass = get_class($this);
		$s = DIRECTORY_SEPARATOR;
		$k = array_slice(explode('\\', $klass), 0, -1);

		$this->klass = $klass;
		$this->directory = BASE_PATH.$s.implode($s, $k);
		$this->state = new ConfigFile($this->directory.$s.'controller_state.ini');
	}

	/**
	 * Este método se hereda y puede ser sobreescrito
	 * se llama sólo a la petición del cliente una única vez
	 */
	public function init () {}

	/**
	 * devuelve un arreglo de Rutas que coínciden con la ruta de cliente
	 * llena por primera vez todas las rutas existentes en el controlador
	 * 
	 * @param string $path, ruta de cliente a coincidir
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
	 * Rellena el atributo $routes con los métodos que registren una ruta
	 */
	protected function fillRoutes () {
		$this->routes = [];
		$klass = new \ReflectionClass($this);

		foreach ($klass->getMethods() as $method) {
			$comment = $method->getDocComment();
			if (preg_match_all('#@(route|verb) (.*?)\R#', $comment, $pm)) {
				if (in_array('route', $pm[1])) {
					$route = new Route($this, $method->name);
					foreach ($pm[1] as $i => $m) {
						if ($m == 'route') $route->setPath($pm[2][$i]);
						# TODO: el método ya distingue el verbo, falta 
						# separar los arreglos del controlador con el verbo
						# incluído
						if ($m == 'verb') $route->setVerb($pm[2][$i]);
					}
					$this->routes[] = $route;
				}
			}
		}
	}

	// =============================
	// ==== HELPERS & INTERNALS ====
	// =============================

	/**
	 * intenta llamar un archivo 'php' dentro del directorio del módulo
	 * @param string $path, ruta de archivo a incluir
	 * @param boolean $return, si es 'true' devuelve lo que el directorio retorne
	 * @return mix
	 */
	public function include ($path, $return = false) {
		if (file_exists($this->directory.$path)) {
			$inc = include($this->directory.$path);
			return $return ? $inc : true;
		} return false;
	}

	/**
	 * Envoltura para llamar a la función previa
	 * de otro controlador, simula llamada por herencia
	 */
	protected function super ($fake_path = '') {
		$server = Server::getInstance();
		return $server->respond($fake_path);
	}
	
	/**
	 * devuelve una ruta concatenada con la ruta base del controlador
	 * @param string $path		ruta a concatenar
	 * @return string			ruta concatenada
	 */
	public function directory (string $path = '') : string {
		return $this->directory.$path;
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
			// TODO: validar que si se sube más de un archivo se tenga
			// que determinar un directorio y usar el nombre del archivo subido

			Request::eachUpload($file_key, function ($upload) use ($basepath, $dirname, $basename) {
				move_uploaded_file($upload['tmp_name'], $basepath.$dirname.($basename ?? $upload['name']));
			});
		} else {
			if (!$basename)
				throw new \Exception('Controller: debe determinar un nombre de archivo');
			file_put_contents($basepath.$dirname.$basename, $file_key);
		}
	}

	protected function getFile (string $file_path) {
		$file_path = pathcheck($file_path);
		if (file_exists($this->directory.$file_path))
			return file_get_contents($this->directory.$file_path);
		return False;
	}
}
