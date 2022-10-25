<?php
namespace Irbis\RecordSet;

use Irbis\RecordSet;


/**
 * Representa un unico registro de un modelo especifico
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
class Record {

	/**
	 * Referencia al conjunto de registros
	 * @var \Irbis\RecordSet\RecordSet
	 */
	private $recordset;

	/**
	 * Almacena los valores del registro
	 * @var array
	 */
	private $values = [];
	private $values_previous = [];

	/**
	 * Auxiliar, almacena los métodos que se estén ejecutando
	 * @var array
	 */
	private $methods_cache = [];

	/**
	 * Constructor
	 * @param array $values
	 * @param \Irbis\RecordSet\RecordSet $recordset
	 */
	public function __construct (array $values, RecordSet $recordset) {
		$this->values = $values;
		$this->recordset = $recordset;
	}

	/**
	 * Método mágico, determina si una propiedad existe
	 * @param string $prop_name
	 */
	public function __isset ($prop_name) {
		return !!$this->recordset->{$prop_name};
	}

	/** 
	 * Método mágico, devuelve el valor de una propiedad
	 * @param string $prop_name
	 */
	public function __get ($prop_name) {
		if ($prop_name == 'ids') return [$this->id];
		$prop = $this->recordset->{$prop_name};
		if ($prop->target_model) {
			$this->values[$prop_name] = $prop->ensureRetrievedValue(
				$this->values[$prop_name] ?? null, 
				$this, 
				$this->recordset->newRecordSet($prop->target_model)
			);
		}
		return $prop->compute('retrieve', $this, $this->values);
	}

	/**
	 * Método mágico, establece el valor de una propiedad
	 * @param string $prop_name
	 * @param mix $prop_value
	 */
	public function __set ($prop_name, $prop_value) {
		if ($prop_name == 'id') return;
		$this->update([$prop_name => $prop_value]);
	}

	/**
	 * Método mágico, ejecuta un método existente en las definiciones
	 * @param string $method
	 * @param array $args
	 */
	public function __call ($method, $args) {
		if (!array_key_exists($method, $this->methods_cache)) {
			if (!$this->methods_cache[$method] = $this->recordset->getMethod($method)) {
				throw new \Exception("recordset: llamada a metodo no definido '$method'");
			}
		}

		$re = $this->methods_cache[$method]->call($args, $this);
		unset($this->methods_cache[$method]);
		return $re;
	}

	public function __toString () { return "[".$this->id."]"; }
	public function __debugInfo () { return $this->values; }

	/**
	 * Actualiza los valores del registro
	 * @param array $update
	 */
	public function update ($update) {
		$this->recordset->update($update, $this);
		return $this;
	}

	/**
	 * Elimina el registro
	 */
	public function delete () {
		$this->recordset->delete($this->id);
		return $this;
	}

	/**
	 * Establece/obtiene el valor o valores sin procesar
	 * @param array $values | string $prop_name
	 * @param mix $value, no es necesario si se asigna por arreglo
	 */
	public function raw ($prop_name, $value = null) {
		if (is_array($prop_name)) {
			foreach ($prop_name as $k => $v)
				$this->raw($k, $v);
		} elseif ($value === null) {
			return $this->values[$prop_name] ?? null;
		} else {
			$this->values_previous[$prop_name] = $this->values[$prop_name] ?? null;
			$this->values[$prop_name] = $value;
		} return $this;
	}

	/**
	 * Obtiene el último valor de la propiedad
	 * @param string $prop_name
	 */
	public function getPreviousValue ($prop_name) {
		return $this->values_previous[$prop_name] ?? null;
	}

	/**
	 * Obtiene el nombre del modelo
	 * @return string
	 */
	public function getName () {
		return $this->recordset->getName();
	}

	/**
	 * Obtiene las propiedades del modelo
	 * @return array[\Irbis\RecordSet\Property]
	 */
	public function getProperties () {
		return $this->recordset->getProperties();
	}
}