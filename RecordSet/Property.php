<?php
namespace Irbis\RecordSet;

use Irbis\RecordSet;


/**
 * Representa a una propiedad del modelo
 * un campo de base de datos o un campo del modelo calculado
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version 	2.0
 */
class Property extends Member {
	public $name; # nombre técnico, usado en la base de datos
	public $label; # etiqueta, nombre amigable
	public $type = null; # e: varchar, text, boolean, int, float, date, datetime, time
	public $length = 0; # tamaño especialmnenta para varchar y float
	public $required = false; # creará una sentencia sql NOT NULL
	public $primary_key = false; # no funciona para sqlite
	public $default = null; # un valor por defecto
	public $index = null; # creará una sentencia sql INDEX
	public $on_delete = 'NO ACTION';
	public $on_update = 'NO ACTION';
	# TODO: agregar lógica para que el campo no pueda ser editable desde fuera del modelo
	public $readonly = false; # de uso exclusivo para la vista
	public $clonable = true;
	public $retrieve = false; # método para recuperar el valor
	public $store = true; # método para almacenar el valor
	public $target = null; # para tipos n1,nm,1n el modelo objetivo
	public $oSQL = null; # sentencias SQL no consideradas

	# propiedades para relaciones, no se declaran directamente,
	# si no que la clase los calcula y administra
	public $target_model = null;
	public $target_property = null;
	public $nm_string; # Para relaciones muchos a muchos
	public $nm1; # primer modelo relacion en orden alfabético
	public $nm2; # segundo modelo relacion en orden alfabético

	public function __construct (string $name) {
		$this->name = $name;
		$this->label = snake_to_text($name);
	}

	public function __debugInfo () { return ['Property' => $this->name]; }

	public function define (array $options) {
		# si el primer elemento es de indice 0
		# el primer elemento determina el tipo de dato
		# no se puede redifinir el tipo de dato
		if (isset($options[0])) {
			if (!$this->type) 
				$this->type = $options[0];
			unset($options[0]);
		}

		# establecer los demás valores a al objeto
		# se recorre la opciones y se establecen el objeto
		foreach ($options as $k => $v) 
			$this->{$k} = $v;

		# los tipos varchar por defecto tienen una 
		# longitud de 255 caracteres
		if ($this->type == 'varchar' && !$this->length) 
			$this->length = 255;

		# los tipos relación muchos a 1 
		# se deben almacenar siempre
		if ($this->type == 'n1') {
			$this->store = true;
			$this->target_model = $this->target;
		}

		# los tipo relación muchos a muchos  o uno a muchos
		# se deben reinterpretar para establecer otras propiedades
		if (in_array($this->type, ['1n', 'nm'])) {
			# e: ['target' => 'orders(order)']
			$m = preg_split('/[()]+/', $this->target);
			$this->target_model = $m[0];
			$this->target_property = $m[1] ?? null;

			# se ordena alfabeticamente para asegurar que
			# nm1 y nm2 valgan lo mismo en las propiedades de ambos modelos
			$r = [$this->name, $this->target_property]; sort($r);

			# este campo será de apoyo para crear la tabla intermedia
			# de las relaciones nm, muchos a muchos
			$this->nm_string = "nm_{$r[0]}_{$r[1]}";
			$this->nm1 = $r[0];
			$this->nm2 = $r[1];
			$this->store = $options['store'] ?? false;
		}
	}

	public function ensureStoredValue ($value, $record) {
		if (is_bool($value))
			$value = (int) $value;
		elseif (is_array($value))
			$value = \Irbis\Json::encode($value);
		elseif ($value instanceof Record)
			$value = $value->id;
		elseif ($value instanceof RecordSet)
			$value = \Irbis\Json::encode($value->ids);

		$compute = $this->store;
		if (is_string($compute))
			$value = $record->{$compute}($value);
		return $value;
	}

	public function ensureRetrievedValue ($value, Record $record) {
		if ($this->target_model)
			$emptyRecordSet = $record->newRecordSet($this->target_model);

		if ($this->type == 'n1' && (!$value instanceof Record || $value->isRaw())) {
			$value = $emptyRecordSet->select($value);
			return $value[0] ?? null;
		}

		if ($this->type == '1n' && (!$value instanceof RecordSet || $value->isRaw())) {
			$value = $emptyRecordSet->select(["{$this->target_property}:=" => $record->id]);
			$value->__parent_record = [$record, $this];
		}

		if ($this->type == 'nm' && (!$value instanceof RecordSet || $value->isRaw())) {
			$value = $emptyRecordSet;
			$value->__parent_record = [$record, $this];
			
			$query = "SELECT `{$this->target_model}`.* 
				FROM `{$this->target_model}`
				INNER JOIN `{$this->nm_string}` 
					ON `{$this->nm_string}`.`$this->name` = `{$this->target_model}`.`id`
				WHERE `{$this->nm_string}`.`{$this->target_property}` = ?";
			
			$stmt = $value->execute($query, [$record->id]);
			while ($fetch = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$value[] = $value->newRecord($fetch);
			}
		}

		$compute = $this->retrieve;
		if ($compute)
			$value = $record->{$compute}($value);
		return $value;
	}

	public function ensureValue ($value, $record) {
		if ($value === null) {
			if ($this->required !== false) {
				throw new \Exception($this->required !== true ? 
					$this->required : "Se requiere un valor para '{$this->name}'");
			}
		}
		
		$value = $value === null ? $this->default : $value;
		if ($value && $this->type == 'n1') {
			$value = $this->ensureRecord($value, $record);
		}
		if ($value && ($this->type == '1n' || $this->type == 'nm')) {
			$value = $this->ensureRecordSet($value, $record);
		}
		return $value;
	}

	private function ensureRecord ($value, $record) {
		if ($value instanceof Record) {
			if ($value->__name != $this->target_model) {
				throw new \Exception("recordset: modelo de referencia incompatible");
			}
			return $value;
		}

		$emptyRecordSet = $record->newRecordSet($this->target_model);
		$emptyRecordSet->{is_assoc($value) ? 'insert' : 'raw'}($value);
		return $emptyRecordSet[0] ?? null;
	}

	private function ensureRecordSet ($value, $record) {
		if ($value instanceof RecordSet) {
			if ($value->__name != $this->target_model) {
				throw new \Exception("recordset: modelo de referencia incompatible");
			}
			$value->__parent_record = [$record, $this];
			return $value;
		}

		$emptyRecordSet = $record->newRecordSet($this->target_model);
		$emptyRecordSet->__parent_record = [$record, $this];
		
		$arr = array_reduce($value, function ($carry, $item) {
			$carry[(is_array($item) ? 'i' : 's')][] = $item;
			return $carry;
		}, ['i' => [], 's' => []]);
		
		if ($arr['i']) $emptyRecordSet->insert($arr['i']);
		if ($arr['s']) $emptyRecordSet->raw($arr['s']);
		return $emptyRecordSet;
	}
}