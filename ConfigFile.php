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
    private $ini_array;
    private $file_name;

    public function __construct ($file) {
        $this->file_name = $file;
		if (!file_exists($this->file_name))
			file_put_contents($this->file_name, '');
    }

    public function get ($key) {
		if (!$this->ini_array)
			$this->ini_array = parse_ini_file($this->file_name, true);
		return array_get($this->ini_array, $key);
	}

	public function set ($key, $value) {
		if (!$this->ini_array)
			$this->ini_array = parse_ini_file($this->file_name, true);
		if ($value === null) array_unset($this->ini_array, $key);
		else array_set($this->ini_array, $key, $value);
		write_ini_file($this->file_name, $this->ini_array);
	}
}