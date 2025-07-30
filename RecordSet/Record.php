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
	private $values = [];
	private $values_previous = [];
	private $mcache = [];

	public function __construct (array $raw, $recordset) {
		$this->values = $raw;
		$this->recordset = $recordset;
	}

	public function __isset ($key) {
		return isset($this->recordset->{$key});
	}

	public function __get ($key) {
		if ($key == 'id') return $this->values['id'] ?? '__newid__';
		if ($key == 'ids') return [$this->id];
		if (str_starts_with($key, '@')) return $this->recordset->{$key};
		if (str_starts_with($key, '$')) return $this->recordset->{$key};
		
		$property = $this->recordset->{$key};
		$value = $this->values[$key] ?? null;
		if (!$property) {
			if ($this->{'@delegate'}) {
				$delegate = $this->{'@delegate'};
				return $this->{$delegate}->{$key};
			}
			throw new \Exception("recordset: propiedad '$key' no definida");
		}
		return $this->values[$key] = $property->ensureRetrievedValue($value, $this);
	}

	public function __set ($key, $value) {
		if (str_starts_with($key, '$')) 
			$this->recordset->{$key} = $value;
		$this->update([$key => $value]);
	}

	public function __call ($key, $args) {
		$backbone = $this->{'@backbone'};
		if (!array_key_exists($key, $this->mcache)) {
			if (!$this->mcache[$key] = $backbone->getMethods($key)) {
				if ($delegate = $this->{'@delegate'})
					return $this->{$delegate}->{$key}(...$args);
				throw new \Exception("recordset: llamada a metodo no definido '$key'");
			}
		}

		$r = $this->mcache[$key]->call($args, $this);
		unset($this->mcache[$key]);
		return $r;
	}

	public function __toString () { 
		return "".$this->id;
	}

	public function data ($max_deep = 0) {
		$properties = $this->recordset->{'@properties'};
		$debug = []; $current_deep = 0; $max_deep = $max_deep < 0 ? 0 : $max_deep;
		foreach ($properties as $key => $property) {
			$debug[$key] = $this->values[$key] ?? null;
			if ($debug[$key] instanceof Record or $debug[$key] instanceof RecordSet) {
				if ($current_deep < $max_deep) {
					$debug[$key] = $debug[$key]->data($max_deep - 1);
				} else {
					$debug[$key] = $debug[$key] instanceof Record ? $debug[$key]->id: $debug[$key]->ids;
				}
				$current_deep++;
			}
		}
		return $debug;
	}

	public function __debugInfo () { 
		return $this->data();
	}

	public function newRecordSet ($name = false) {
		return $this->recordset->newRecordSet($name);
	}

	public function newRecord ($raw = []) {
		return $this->recordset->newRecord($raw);
	}

	public function execute ($query, $params = []) {
		return $this->recordset->execute($query, $params);
	}

	public function exec ($query) {
		return $this->recordset->exec($query);
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