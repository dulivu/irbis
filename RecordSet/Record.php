<?php
namespace Irbis\RecordSet;

use Irbis\RecordSet;
use Irbis\RecordSet\Backbone;


/**
 * Representa un unico registro de un modelo especifico
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
class Record {

	private $recordset;
	private $database;
	private $backbone;

	private $is_raw;
	private $values = [];
	private $values_previous = [];

	/**
	 * Auxiliar, almacena los métodos que se estén ejecutando
	 * @var array
	 */
	private $mcache = [];

	public function __construct (array $values, $recordset_data, $is_raw = false) {
		$this->values = $values;
		$this->recordset = $recordset_data['recordset'];
		$this->backbone = $recordset_data['backbone'];
		$this->database = $recordset_data['database'];
		$this->is_raw = $is_raw;
	}

	public function __isset ($key) {
		return !!$this->recordset->{$key};
	}

	public function __get ($key) {
		if ($key == 'id' and !isset($this->values['id'])) return '__newid__';
		if ($key == 'ids') return [$this->id];
		if (str_starts_with($key, '__')) return $this->recordset->{$key};

		$prop = $this->recordset->{$key};
		$value = $this->values[$key] ?? null;
		$value = $prop->ensureRetrievedValue($value, $this);
		$this->values[$key] = $value;
		return $this->values[$key];
	}

	public function __set ($key, $value) {
		if ($key == 'id') return;
		$this->update([$key => $value]);
	}

	public function __call ($key, $args) {
		if (!array_key_exists($key, $this->mcache)) {
			if (!$this->mcache[$key] = $this->backbone->getMethods($key)) {
				throw new \Exception("recordset: llamada a metodo no definido '$key'");
			}
		}

		$r = $this->mcache[$key]->call($args, $this);
		unset($this->mcache[$key]);
		return $r;
	}

	public function __toString () { return "[".$this->id."]"; }
	public function __debugInfo () { return [$this->backbone->name, $this->values]; }

	public function newRecordSet ($name = false) {
		return new RecordSet($name, $this->database->name);
	}

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

	public function isRaw () { 
		return $this->is_raw; 
	}

	// ==========================================================================
	// DML methods
	// ==========================================================================

	public function update ($update) {
		$this->recordset->update($update, $this);
		return $this;
	}

	public function delete () {
		$this->recordset->delete($this->ids);
		return $this;
	}

	/**
	 * Obtiene el último valor de la propiedad
	 * @param string $prop_name
	 */
	public function getPreviousValue ($prop_name) {
		return $this->values_previous[$prop_name] ?? null;
	}
}