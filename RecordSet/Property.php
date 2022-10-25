<?php
namespace Irbis\RecordSet;

use Irbis\RecordSet;


/**
 * Representa a una propiedad del modelo
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version 	2.0
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
	public $nm_models;
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
	 * Modifica el comportamiendo y estructura de la propiedad
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
	 * Valida que exista el valor de la propiedad dentro del 
	 * arreglo entregado, valida que este valor sea requerido
	 * o tenga un valor por defecto
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
	public function testRecordValue ($row, $emptyRecordSet) {
		$value = $row[$this->name] ?? null;

		if ($this->type == 'n1' && $value) {
			if ($value instanceof Record) {
				if ($value->getName() != $emptyRecordSet->getName()) {
					throw new \Exception("recordset: modelo de referencia incompatible");
				}
			} else {
				$emptyRecordSet->{is_assoc($value) ? 'insert' : 'select'}($value);
				return $emptyRecordSet[0] ?? null;
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
	public function testRecordSetValue ($row, $record, $emptyRecordSet) {
		$value = $row[$this->name] ?? null;

		if (($this->type == '1n' || $this->type == 'nm')) {
			if ($value instanceof RecordSet) {
				if ($value->getName() != $emptyRecordSet->getName()) {
					throw new \Exception("recordset: modelo de referencia incompatible");
				}
			}

			$id = $record instanceof Record ? $record->id : $record;

			if ($id) {
				if ($this->type == '1n') {
					$emptyRecordSet
						->select([$this->target_property => $id])
						->update([$this->target_property => null]);
					$emptyRecordSet->flush();
				} elseif ($this->type == 'nm') {
					$query = "DELETE FROM `{$this->nm_string}` ".
						"WHERE {$this->target_property} = {$id}";
					$emptyRecordSet->execute($query);
				}
			}

			// TODO
			// Si son varias inserciones los registros nuevos se duplican
			// ["nm_field" => ["nombre" => "juan"]], ["nm_field" => ["nombre" => "juan"]]
			// se intentará crear dos registros nombre => juan
			if ($value) {
				if ($value instanceof RecordSet) $value = $value->ids;
				$emptyRecordSet->setRelatedRecord($record, $this);
				$arr = array_reduce($value, function ($carry, $item) {
					$carry[(is_array($item) ? 'i' : 's')][] = $item;
					return $carry;
				}, ['i' => [], 's' => []]);
				
				if ($arr['i']) $emptyRecordSet->insert(...$arr['i']);
				if ($arr['s']) $emptyRecordSet->select($arr['s']);
			}

			return $emptyRecordSet;
		}

		return $value;
	}
}