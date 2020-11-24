<?php

namespace Irbis;


/**
 * Envoltura del objeto PDO de php, se añade funcionalidad singleton
 * para mantener una sola conexión por nombre, y registrar el log
 * de transacciones solicitadas.
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <GeorgeL1102@gmail.com>
 * @version		1.0
 */
class DataBase extends \PDO {

	/**
	 * Almancena todas las instancias de conexión
	 * @var array[\Irbis\DataBase]
	 */
	private static $instances = array();

	/**
	 * Almancena el puntero de base de datos
	 * si se llama a 'getInstance' sin parámetro
	 * @var string
	 */
	public static $pointer;

	/**
	 * El nombre de la conexión
	 * @var string
	 */
	public $name;

	/**
	 * crea/devuelve una conexión de base de datos por el nombre
	 * los valores de conexión se pasan en el segundo parámetro
	 * si la conexión ya existiera estos no se consideran
	 * 
	 * @param string $name, nombre de la conexion
	 * @param array $options, valores de conexión
	 * @return \DB, conexión de base de datos
	 */
	public static function &getInstance ($name = null, $o = null) {
		$name = $name ?: self::$pointer;

		if (isset(self::$instances[$name]))
			return self::$instances[$name];
		
		if (!$o) {
			$ini = @parse_ini_file(DB_INI, true);
			$o = $ini[$name] ?? null;
		}

		if (!$o)
			throw new \Exception("database: se requieren valores de conexión para '$name'");

		self::$instances[$name] = new self($o['dsn'], $o['user'] ?? null, $o['pass'] ?? null, $o['attr'] ?? null);
		self::$instances[$name]->name = $name;
		self::$pointer = self::$pointer ?: $name;

		return self::$instances[$name];
	}
}
