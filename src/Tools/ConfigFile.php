<?php
namespace Irbis\Tools;


/**
 * Clase para manejo de archivos de configuración
 * permite al servidor tener un estado persistente
 * 
 * los métodos de obtencion y establecimiento de valores son:
 * usa array_get y array_set para manejo de claves por puntos
 * 
 * get(:string):mixed      => ej: ('section.key') -> :mixed
 * set(:string, :mixed)    => ej: ('section.key', 'value')
 * isEmpty():bool
 * save()
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class ConfigFile {

    private $ini_array;
    private $file_name;
    private $has_changes = false;

    public function __construct (string $file_name) {
        $this->file_name = $file_name;
        if (!file_exists($this->file_name))
            file_put_contents($this->file_name, '');
        $this->ini_array = parse_ini_file($this->file_name, true) ?: [];
    }

    public function __get ($key) {
        return $this->get($key);
    }

    public function __set ($key, $value) {
        $this->set($key, $value);
    }

    public function get (string $key) {
        $value = array_get($this->ini_array, $key);
        switch ($value) {
            case 'on': return true;
            case 'off': return false;
            case is_numeric($value): return floatval($value);
            default: return $value;
        }
    }

    public function set (string $key, $value) {
        $this->has_changes = true;
        if ($value === null) 
            return array_unset($this->ini_array, $key);
        if ($value === true) $value = 'on';
        if ($value === false) $value = 'off';
        array_set($this->ini_array, $key, $value);
    }

    public function isEmpty (): bool {
        return empty($this->ini_array);
    }

    public function save () {
        if ($this->ini_array && $this->has_changes)
            write_ini_file($this->file_name, $this->ini_array);
    }
}