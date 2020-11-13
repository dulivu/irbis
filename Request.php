<?php

namespace Irbis;

use Irbis\Traits\Singleton;


/**
 * Administra los datos de petición del cliente
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <GeorgeL1102@gmail.com>
 * @version		1.0
 */
class Request {
	use Singleton;

	/**
	 * La consulta de petición
	 * @var string
	 */
	public static $query;

	/**
	 * La ruta de petición
	 * @var string
	 */
	public static $path;

	/**
	 * El verbo de petición
	 * @var string (POST|GET)
	 */
	public static $method;

	/**
	 * El host completo, incluido el esquema
	 * @var string (ej. http://domain.com)
	 */
	public static $host;

	/**
	 * La ruta base, en caso se muente sobre un alias
	 * @var string (ej. http://domain.com/web)
	 */
	public static $base;

	/**
	 * Abreviaciones de los patrones de busqueda
	 * @var array
	 */
	public static $patterns = [
		':any' => '[^/]+',
		':num' => '[0-9]+',
		':all' => '.*',
	];

	/**
	 * Guarda las coíncidencias encontradas por los patrones
	 * entregados, se puede obtener por el método 'path'
	 * @var array
	 */
	private static $matchs = [];

	/**
	 * Constructor
	 */
	private function __construct () {
		$uri = parse_url($_SERVER['REQUEST_URI']);
		$uri['path'] = preg_replace('/\/index.php(\/)?/', '/', strtolower($uri['path']), 1);
		# en caso se muente sobre un alias de directorio, se extrae la ruta base
		$base = substr($_SERVER['SCRIPT_NAME'], 0, strpos($_SERVER['SCRIPT_NAME'], '/index.php'));
		self::$path = $base ? preg_replace('/'.preg_quote($base, '/').'/', '', $uri['path'], 1) : $uri['path'];
		# asegura que las rutas de tipo /ruta/archivo/ siempre sean /ruta/archivo
		self::$path = (strlen(self::$path)>1 && substr(self::$path,-1)=='/') ? substr(self::$path,0,-1) : self::$path;
		self::$query = $uri['query'] ?? '';
		self::$method = strtoupper($_SERVER['REQUEST_METHOD']);
		self::$host = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'];
		self::$base = self::$host.$base.
			(MOD_REWRITE ? 
				(strpos(strtolower($_SERVER['REQUEST_URI']), 'index.php') !== false ? 
					'/index.php' : '') : '/index.php');
	}

	public function __toString () {
		return self::$base.self::$path.(self::$query ? '?' : '').self::$query;
	}

	/**
	 * Si un miembro existe como estatico 
	 * o dentro del global $_SERVER
	 * @param string $name
	 */
	public function __isset ($name) { 
		return array_key_exists(strtoupper($name), $_SERVER) || isset(self::${$name});
	}

	/**
	 * Obtiene un miembro inaccesible buscandolo
	 * como estatico o dentro del global $_SERVER
	 * @param string $name
	 */
	public function __get ($name) { 
		if (isset(self::${$name}))
			return self::${$name};
		if (array_key_exists(strtoupper($name), $_SERVER))
			return $_SERVER[strtoupper($name)];
		return self::${$name};
	}

	/**
	 * funciones para obtener datos enviados por el cliente
	 * query: datos en la cadena de consulta
	 * input: datos en el cuerpo de consulta
	 * cookie: datos de las cookies
	 * path: datos de la ruta
	 * @param string|array $key
	 * @param mix|array $def (optional)
	 */
	public static function query ($key, $def = null) {
		return self::getFromArray($_GET, $key, $def);
	}
	public static function input ($key, $def = null) {
		return self::getFromArray($_POST, $key, $def);
	}
	public static function cookie ($key, $def = null) {
		return self::getFromArray($_COOKIE, $key, $def);
	}
	public static function path ($key, $def = null) {
		return self::getFromArray(self::$matchs, $key, $def);
	}

	/**
	 * se le envía un arreglo, y se obtiene el valor
	 * de la clave solicitada, sino existiera devuelve le valor
	 * por defecto.
	 * 
	 * @param array $arr, arreglo a evaluar
	 * @param string $key, clave solicitada
	 * 		si se le envía un arreglo devuelve este 
	 * 		mismo fusionado con el primer arreglo dando
	 * 		prioridad al primero
	 * @param mix $def, valor a devolver por defecto, es
	 *		ignorado si el anterior parámetro es un arreglo
	 *
	 * @return mix
	 */
	private static function getFromArray ($arr, $key, $def) {
		if ($key === '*') return $arr + (array) $def;
		if (is_array($key)) {
			$key = array_values($key);
			$def = (array) $def;
			return array_map(function ($i) use ($arr, $def) { 
				return $arr[$i] ?? $def[$x] ?? null;
			}, $key);
		}
		return $arr[$key] ?? $def;
	}

	/**
	 * recibe dos cadenas, la segunda contendrá comodines
	 * que se evaluarán contra los '$patterns' registrados
	 * y devuelve 'true' si las cadenas coínciden.
	 * 
	 * request::compare('/blog/name/2', '/blog/(:any)/(:num)'); // true
	 * request::compare('/products/name/1', '/products/([a-z]+)/(\d+)') // true
	 * 
	 * @param string $path la cadena a evaluar
	 * @param string $match la condición de evaluación
	 * @param bool $saveMatches ignorar, sólo lo usa el framework
	 *
	 * @return bool
	 */
	public static function compare (string $path, string $match, bool $saveMatches = false) {
		$searches = array_keys(self::$patterns);
		$replaces = array_values(self::$patterns);
		$matched = [];

		$j = str_replace($searches, $replaces, $match);
		if (preg_match('#^' . $j . '$#', $path, $matched)) {
			array_shift($matched);
			$matchs = array_map(function ($i) {
				return urldecode($i);
			}, $matched);
			if ($saveMatches) 
				self::$matchs = $matchs;
			return true;
		}
		return false;
	}

	/**
	 * Determina si un valor está presente
	 * en la cabecera CONTENT_TYPE
	 * @return bool
	 */
	public static function hasContent ($type) {
		return isset($_SERVER['CONTENT_TYPE']) && 
			strpos($_SERVER['CONTENT_TYPE'], $type) !== false;
	}

	/**
	 * Trabaja en conjunto con la global 'REQUEST_EMULATION' comprueba si un
	 * método http fue enviado, con emulación activa para métodos PUT o DELETE
	 * se compara con una variable '_method' en el cuerpo de un método POST
	 *
	 * @param string $method
	 * @return bool
	 */
	public static function isMethod (string $method) {
		$method = strtoupper($method);
		if (REQUEST_EMULATION && ($method == 'PUT' || $method == 'DELETE')) {
			return strtoupper(self::input('_method', '')) == $method;
		} else {
			return self::$method == strtoupper($method);
		}
	}
}