<?php

namespace Irbis;


/**
 * Clase para manejo de archivos de configuración
 * cada controlador podrá manejar un archivo de configuración
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.2
 */
class ConfigFile {
	/**
	 * arreglo que contiene todos
	 * los valores del archivo convertidos
	 * @var string
	 */

    private $ini_array;
	/**
	 * nombre y ubicación del archivo
	 * de configuración
	 * @var array
	 */
    private $file_name;

	/**
	 * recibe un nombre de archivo
	 * si el archivo no existe lo crea
	 */
    public function __construct (string $file) {
        $this->file_name = $file;
		if (!file_exists($this->file_name))
			file_put_contents($this->file_name, '');
    }

	/**
	 * obtiene un valor dentro del arreglo
	 * e: para un archivo tipo
	 * [main]
	 * user = usuario
	 * 
	 * $config->get('main.user') // usuario
	 */
    public function get (string $key) {
		if (!$this->ini_array)
			$this->ini_array = parse_ini_file($this->file_name, true);
		return array_get($this->ini_array, $key);
	}

	/**
	 * bajo la misma logica de get pero establece
	 * un valor en el archivo de configuración
	 */
	public function set ($key, $value) {
		if (!$this->ini_array)
			$this->ini_array = parse_ini_file($this->file_name, true);
		if ($value === null) array_unset($this->ini_array, $key);
		else array_set($this->ini_array, $key, $value);
		write_ini_file($this->file_name, $this->ini_array);
	}
}