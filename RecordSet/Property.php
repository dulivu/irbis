<?php
namespace Irbis\RecordSet;


/**
 * Representa a una propiedad del modelo
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <GeorgeL1102@gmail.com>
 * @version 	1.0
 */
class Property extends Member {
	public $name;
	public $label;
	public $type = null;
	public $length = 0;
	public $required = false;
	public $primary_key = false;
	public $default = null;
	public $index = null;
	public $on_delete = 'NO ACTION';
	public $on_update = 'NO ACTION';
	public $readonly = false;
	public $clonable = true;
	public $retrieve = false;
	public $store = true;
	public $target = null;
	public $oSQL = null;

	public $target_model = null;
	public $target_property = null;
	public $nm_string; # Para relaciones muchos a muchos
	public $nm1;
	public $nm2;

	/**
	 * Constructor
	 * @param string $name
	 */
	public function __construct (string $name) {
		$this->name = $name;
		$this->label = $name;
	}

	/**
	 * Modifica el comportamiendo de la propiedad
	 * @param array $options
	 */
	public function alter (array $options) {
		if (isset($options[0])) {
			if (!$this->type) $this->type = $options[0];
			unset($options[0]);
		}

		foreach ($options as $k => $v) $this->{$k} = $v;

		if ($this->type == 'varchar' && !$this->length) $this->length = 255;
		if ($this->type == 'n1') $this->store = true;
		if (array_key_exists('target', $options) && in_array($this->type, ['1n', 'nm', 'n1'])) {
			$m = preg_split('/[()]+/', $this->target);

			$this->target_model = $m[0];
			$this->target_property = $m[1] ?? null;

			$r = [$this->name, $this->target_property]; sort($r);

			$this->nm_string = "nm_{$r[0]}_{$r[1]}";
			$this->nm1 = $r[0];
			$this->nm2 = $r[1];

			if ($this->type != 'n1' && !array_key_exists('store', $options))
				$this->store = false;
		}
	}

	public function compute ($compute, $record, $row) {
		$compute = $this->{$compute};
		if (is_string($compute))
			return $record->{$compute}($row[$this->name] ?? null);
		return $row[$this->name] ?? null;
	}

	public function ensureStoredValue ($value) {
		if (is_bool($value))
			$value = (int) $value;
		elseif (is_array($value))
			$value = \Irbis\Json::encode($value, JSON_UNESCAPED_UNICODE);
		elseif ($value instanceof Record)
			$value = $value->id;
		elseif ($value instanceof RecordSet)
			$value = \Irbis\Json::encode($value->ids);

		return $value;
	}

	public function ensureRetrievedValue ($value, Record $record, RecordSet $recordset) {
		if ($this->type == 'n1' && !$value instanceof Record) {
			$value = $recordset->select($value);
			return $value[0] ?? null;
		}

		if ($this->type == '1n' && !$value instanceof RecordSet) {
			$value = $recordset
				->select(["{$this->target_property}:=" => $record->id])
				->setRelatedRecord($record, $this);
		}

		if ($this->type == 'nm' && !$value instanceof RecordSet) {
			$value = $recordset
				->setRelatedRecord($record, $this);
			
			$query = "SELECT `{$this->target_model}`.* 
				FROM `{$this->target_model}`
				INNER JOIN `{$this->nm_string}` 
					ON `{$this->nm_string}`.`$this->name` = `{$this->target_model}`.`id`
				WHERE `{$this->nm_string}`.`{$this->target_property}` = ?";

			$stmt = $recordset->execute($query, [$record->id]);
			while ($fetch = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$value[] = new Record($fetch, $value);
			}
		}

		return $value;
	}

	/**
	 * Valida el registro lanzando error en caso sea requerido,
	 * devuelve el valor por defecto si no estuviera en el arreglo
	 *
	 * @param array $row
	 * @return mix
	 */
	public function testValue ($row) {
		$has = array_key_exists($this->name, $row);
		if (!$has || $row[$this->name] === null) {
			if ($this->required !== false)
				throw new \Exception($this->required !== true ? 
					$this->required : "Se requiere un valor para '{$this->name}'");
		}

		return $row[$this->name] ?? $this->default;
	}

	/**
	 * Revisa y prepara un dato relacionado n1
	 * valida que el dato ingresado coíncida con la relacion,
	 * inserta o selecciona un registro y devuelve la instancia
	 *
	 * @param array $row
	 * @return \Irbis\RecordSet\Record | mix
	 */
	public function testRecordValue ($row) {
		$value = $row[$this->name] ?? null;

		if ($this->type == 'n1' && $value) {
			$recordset = $this->recordset->newRecordSet($this->target_model);
			if ($value instanceof Record) {
				if ($value->getName() != $recordset->getName()) {
					throw new \Exception("recordset: modelo de referencia incompatible");
				}
			} elseif ($value) {
				$recordset->{is_array($value) ? 'insert' : 'select'}($value);
				return $recordset[0] ?? null;
			}
		}

		return $value;
	}

	/**
	 * Revisa y prepara un dato relacionado 1n o nm
	 * valida que el dato ingresado coíncida con la relacion
	 * inserta o selecciona los registros y devuelve la instancia
	 *
	 * @param array $row
	 * @param int | \Irbis\RecordSet\Record $record, para generar la relación
	 *
	 * @return RecordSet|null
	 */
	public function testRecordSetValue ($row, $record) {
		$value = $row[$this->name] ?? null;

		if (($this->type == '1n' || $this->type == 'nm')) {
			$recordset = $this->recordset->newRecordSet($this->target_model);
			if ($value instanceof RecordSet) {
				if ($value->getName() != $recordset->getName()) {
					throw new \Exception("recordset: modelo de referencia incompatible");
				}
			}

			$id = $record instanceof Record ? $record->id : $record;

			if ($id) {
				if ($this->type == '1n') {
					$recordset
						->select([$this->target_property => $id])
						->update([$this->target_property => null]);
					$recordset->flush();
				} elseif ($this->type == 'nm') {
					$query = "DELETE FROM `{$this->nm_string}` ".
						"WHERE {$this->target_property} = {$id}";
					$recordset->execute($query);
				}
			}

			if ($value) {
				if ($value instanceof RecordSet) $value = $value->ids;
				$recordset->setRelatedRecord($record, $this);
				$arr = array_reduce($value, function ($carry, $item) {
					$carry[(is_array($item) ? 'i' : 's')][] = $item;
					return $carry;
				}, ['i' => [], 's' => []]);

				if ($arr['i']) $recordset->insert(...$arr['i']);
				if ($arr['s']) $recordset->select($arr['s']);
			}

			return $recordset;
		}

		return $value;
	}
}