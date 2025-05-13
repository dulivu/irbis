<?php
namespace Irbis;

use Irbis\RecordSet\Backbone;
use Irbis\RecordSet\Member;
use Irbis\RecordSet\Method;
use Irbis\RecordSet\Property;
use Irbis\RecordSet\Record;
use Irbis\Exceptions\RecordException;


/**
 * Representa un DataSet vacio, por medio de sus métodos DML
 * se permite recoger los datos de su tabla a la que representa
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
class RecordSet extends \ArrayObject {

	private $backbone;
	private $database;
	private $mcache = [];
	private $hidden_properties = [];
	private static $binds = [];

	public function __construct ($name, $pointer = null) {
		$this->backbone = Backbone::getInstance($name);
		$this->database = DataBase::getInstance($pointer);
	}

	public function __isset ($key) {
		if ($key == 'ids') return true;
		if (str_starts_with($key, '@')) return true;
		$has = $this->backbone->hasProperty($key);
		if (!$has && $delegate = $this->{'@delegate'}) {
			$delegate = $this->{$delegate};
			return isset($delegate->{$key});
		} return $has;
	}

	public function __get ($key) {
		if ($key == 'ids') {
			return array_map(function ($i) { 
				return (int) $i->id;
			}, (array) $this);
		}
		if (str_starts_with($key, '@')) {
			if ($key == '@properties')
				return $this->backbone->getProperties();
			if ($key == '@methods')
				return $this->backbone->getMethods();
			if ($key == '@name')
				return $this->backbone->name;
			if ($key == '@backbone')
				return $this->backbone;
			if ($key == '@database')
				return $this->database;
			return $this->backbone->constans[$key] ?? null;
		}
		if (str_starts_with($key, '$')) {
			return $this->hidden_properties[$key] ?? null;
		}

		return $this->backbone->getProperties($key);
	}

	public function __set ($key, $value) {
		if (str_starts_with($key, '$'))
			$this->hidden_properties[$key] = $value;
		else throw new RecordException("recordset: está tratando de modificar la propiedad '$key'".
			" de un conjunto de registros, en su lugar utilice el método 'update'");
	}

	public function __call ($key, $args) {
		$skey = '@'.$key;
		if (!array_key_exists($skey, $this->mcache)) {
			if (!$this->mcache[$skey] = $this->backbone->getMethods($skey)) {
				throw new RecordException("recordset: llamada a metodo no definido '$key'");
			}
		}

		$r = $this->mcache[$skey]->call($args, $this);
		unset($this->mcache[$skey]);
		return $r;
	}

	public function __toString () { 
		return \Irbis\Json::encode($this->ids); 
	}

	public function debug () {
		$debug = [];
		$debug[$this->backbone->name] = array_map(function ($i) {
			return $i->debug();
		}, (array) $this);
		return $debug;
	}
	
	public function __debugInfo () {
		return $this->debug();
	}

	public function newRecordSet ($name = false) {
		if (!$name) $name = $this->backbone->name;
		return new self($name, $this->database->name);
	}

	public function newRecord ($values = []) {
		return new Record($values, $this);
	}

	public function flush () {
		$this->__parent_record = [false,false];
		foreach ((array) $this as $k => $v) {
			delete($this, $k);
		}
	}

	public function filter ($fn) {
		$newset = $this->newRecordSet();
		$newset->__parent_record = $this->__parent_record;
		foreach ($this as $record) {
			if ($fn($record))
				$newset[] = $record;
		}
		return $newset;
	}

	public function execute (string $query, array $params = []) {
		# Ejecuta una consulta y devuelve el statement
		# utiliza el conector de base de datos interno
		$stmt = $this->database->prepare($query);
		$stmt->execute($params);
		return $stmt;
	}

	public function exec (string $query) {
		# Ejecuta una consulta SQL directamente
		# para consultas directas, e: CREATE TABLE
		return $this->database->exec($query);
	}

	// ==========================================================================
	// DML methods
	// ==========================================================================

	public function insert ($inserts) {
		if (!is_array($inserts)) throw new RecordException("datos mal formateados en la inserción");
		if (is_assoc($inserts)) $inserts = [$inserts];
		$this->{'$inserting'} = true;

		// ==============================================================
		// establecer variables auxiliares
		$fields = []; // campos a insertar
		$to_insert = []; // valores a insertar en db
		$parent = $this->{'$parent_record'};
		$property = $this->{'$parent_property'};
		$children = []; // almacena los modelos relacionados
		$props = $this->{'@properties'}; // propiedades del modelo
		$x = $this->count(); // el índice para agregar a la pila
		
		$this->{'$index_before_insert'} = $x;
		// ==============================================================
		// valida y calcula los campos a insertar
		foreach ($inserts as $i => $insert) {
			# Se agrega el registro padre relacionado, si existiera
			if ($parent and $property->type == '1n') {
				$key = $property->target_property;
				$insert[$key] = $parent; // $insert[$key] ?? null;
			}

			if ($delegate = $this->{'@delegate'}) {
				if (!array_key_exists($delegate, $insert)) {
					$set_name = $this
						->backbone
						->getProperties($delegate)
						->target_model;
					$insert[$delegate] = $this
						->newRecordSet($set_name)
						->insert($insert)[0];
				}
			}

			$record = $this->newRecord();
			
			// ==============================================================
			// prepara los valores ingresados y valida los tipos
			// asegura que los valores sean del tipo correcto
			foreach ($props as $prop) {
				$key = $prop->name;
				$insert[$key] = $insert[$key] ?? null;
				$insert[$key] = $prop->ensureValue($insert[$key], $record);
				
				$record->raw($key, $insert[$key]);
				$has_children = in_array($prop->type, ['1n','nm']);
				$has_children = $has_children && $insert[$key] && $insert[$key]->count();
				if ($has_children) $children[] = $insert[$key];
			}

			$this[$x] = $record;

			// ==============================================================
			// prepara inserciones validando el atributo 'store' de la propiedad
			// sólo pasan a insercion de base de datos lo que sean TRUE
			foreach ($props as $prop) {
				$key = $prop->name;
				if (!$prop->store) continue;
				if (!$i) $fields[] = "`{$key}`"; // para un $i == 0
				$to_insert[$x][] = $prop->ensureStoredValue($insert[$key], $record);
			}
			$x++;
		}
		$this->{'$index_after_insert'} = $x-1;

		[$query, $params] = $this->backbone->insertQuery($fields, $to_insert);
		
		if ($parent && $parent->{'$inserting'}) {
			$this->{'$save_insert_query'} = $query;
			$this->{'$save_insert_params'} = $params;
			$this->{'$save_insert_children'} = $children;
		} else $this->__insert($query, $params, $children);
		
		$this->{'$inserting'} = false;
		return $this;
	}

	public function __insert ($query = false, $params = false, $children = []) {
		// se preparan y validan valores a ejecutar
		$query = $query ?: $this->{'$save_insert_query'};
		$params = $params ?: $this->{'$save_insert_params'};
		$children = $children ?: $this->{'$save_insert_children'};
		$parent = $this->{'$parent_record'};
		$property = $this->{'$parent_property'};
		$inserted_ids = [];
		// NOTE: en caso se llame a un hijo que no ejecutó una inserción
		if (!$query || !$params) return $this;

		if ($parent) {
			$params = array_map(function ($i) use ($parent) {
				return $i === '__newid__' ? $parent->id : $i;
			}, $params);
		}

		$this->execute($query, $params);

		// ==============================================================
		// actualiza los ultimos ids ingresados
		// MYSQL devuelve el primer ID de los ultimos registros ingresados
		if ($this->database->driven == 'mysql') {
			$id = $this->database->lastInsertId();
			for ($i = $this->{'$index_before_insert'}; $i < $this->{'$index_after_insert'}; $i++) {
				$this[$i]->raw('id', $id++);
				$inserted_ids[] = $this[$i]->id;
			}
		} else {
			$id = $this->database->lastInsertId();
			for ($i = $this->{'$index_after_insert'}; $i >= $this->{'$index_before_insert'}; $i--) {
				$this[$i]->raw('id', $id--);
				$inserted_ids[] = $this[$i]->id;
			}
		}

		if ($parent) {
			// NOTE: no require actualizar registros 1n, porque el valor del padre se agrega al insertar
			// if ($property->type == '1n')
			// 	$this->backbone->update_1n($property, $parent, $this, false);
			if ($property->type == 'nm') {
				$query = $this->backbone->insertNmQuery($property, $parent, $inserted_ids);
				$this->exec($query);
			}
		}

		if ($children) {
			foreach ($children as $child) {
				if ($child->{'$save_insert_query'})
					$child->__insert();
				if ($child->{'$selected_ids'})
					$child->__select();
			}
		}

		$this->{'$save_insert_query'} = null;
		$this->{'$save_insert_params'} = null;
		$this->{'$save_insert_children'} = null;
		return $this;
	}

	public function select ($where = null, $order = [], $limit = []) {
		$parent = $this->{'$parent_record'};
		$property = $this->{'$parent_property'};

		if ($parent && !$where)
			throw new RecordException("{$this->{'@name'}}: este conjunto requiere una condición porque está relacionado a {$parent->{'@name'}}");
		
		if ($where === '__newid__') {
			$this[0] = $this->newRecord();
			return $this;
		}

		if ($where instanceof Record) {
			$where = $where->id;
		}
		
		// =====================================================================
		// prepara las variables de busqueda, ordenamiento y limites		
		if ($where !== null && !is_array($where)) // ->select(1)
			$where = ['id:=' => intval($where)];
		elseif ($where !== null && !is_assoc($where)) // ->select([1,2])
			$where = ['id:in' => $where];

		// forzar que 'order' sea un arreglo
		$order = is_string($order) ? explode(",", $order) : $order; $self = $this;
		$order = array_map('trim', $order) ?: ['id'];

		// forzar que 'limit' sea un arreglo
		$limit = is_string($limit) ? explode("-", $limit) : $limit;
		$limit = array_map('intval', $limit);

		[$query, $params] = $this->backbone->selectQuery($where, $order, $limit);
		$selected_ids = [];
		$stmt = $this->execute($query, $params);
		$count = $this->count();
		$ids = $this->ids;

		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			if (!in_array($row['id'], $ids)) {
				$this[$count] = $this->newRecord($row);
				$selected_ids[] = $row['id'];
				$count++;
			}
		}
		
		if ($parent && $parent->{'$inserting'})
			$this->{'$selected_ids'} = $selected_ids;
		else $this->__select($selected_ids);
		return $this;
	}

	public function __select ($ids=[]) {
		$parent = $this->{'$parent_record'};
		$property = $this->{'$parent_property'};
		$selected_ids = $ids ?: $this->{'$selected_ids'};
		
		if (!$parent) return;
		if ($property->type == '1n') {
			if ($parent && $parent->{'$updating'}) {
				$query = $this->backbone->update1nQuery($property, $parent, $selected_ids);
				try { $this->execute($query, $selected_ids); }
				catch (\PDOException $e) {
					# TODO: manejo de errores
					throw new \Exception("Modelo {$this->{'@name'}}: error al actualizar, el elemento {$property->target_property} no admite valores nulos");
				}
			}
			$this->update([ $property->target_property => $parent->id ]);
		}
		if ($property->type == 'nm') {
			if ($parent && $parent->{'$updating'}) {
				$query = $this->backbone->deleteNmQuery($property);
				$this->execute($query, $parent->ids);
			}
			$query = $this->backbone->insertNmQuery($property, $parent, $selected_ids);
			$this->exec($query);
		}

		$this->{'$selected_ids'} = false;
	}

	public function delete ($ids = []) {
		$ids = $ids ? (array) $ids : $this->ids;
		if (!$ids) return $this;

		$query = $this->backbone->deleteQuery($ids);
		$stmt = $this->execute($query, $ids);
		# TODO: mejorar la eliminación de registros del arreglo
		# TODO: plantear la eliminación de registros delegados
		foreach ($this as $k => $i)
			if (in_array($i->id, $ids))
				delete($this, $k);
		# TODO: no elimina los registros relaciones 1n y nm
		return $this;
	}

	public function update ($update, $ids = []) {
		$records = $ids ? ($ids instanceof Record ? [$ids] : (array) $ids) : $this;
		$ids = $ids ? ($ids instanceof Record ? [$ids->id] : (array) $ids) : $this->ids;
		if (!$update || !$ids) return $this;

		// =====================================================================
		// establecer variables auxiliares
		$fields = []; // campos a actualizar
		$to_update = []; // valores a actualizar
		$children = [];
		$props = $this->{'@properties'}; // propiedades del modelo

		$this->{'$updating'} = true;

		// =====================================================================
		// recorre todos los registros solicitados, puede ocasionar lentitud en muchos
		// registros, pero es necesario para los campos computados
		foreach ($records as $i => $record) {
			if (!$record instanceof Record)
				$record = $records[$i] = $this->newRecord()->raw('id', intval($record));

			foreach ($props as $prop) {
				if (!array_key_exists($prop->name, $update)) continue;
				$key = $prop->name;
				$update[$key] = $prop->ensureValue($update[$key], $record);
				$record->raw($key, $update[$key]);

				$has_children = in_array($prop->type, ['1n','nm']);
				$has_children = $has_children && $update[$key] && $update[$key]->count();
				if ($has_children) $children[] = $update[$key];
			}
		}

		// preparar valores a actualizar
		foreach ($props as $prop) {
			if (!array_key_exists($prop->name, $update)) continue;
			if (!$prop->store) continue;
			$fields[] = $this->backbone->fieldQuery($prop);
			$to_update[] = $prop->ensureStoredValue($update[$prop->name], $records[0]);
		}

		// ==============================================================
		// ejecuta la sentencia de actualizacion
		if ($fields) {
			$query = $this->backbone->updateQuery($fields, $ids);
			$this->execute($query, array_merge($to_update, $ids));
		}

		if ($this->{'@delegate'}) {
			$delegate = $this->{'@delegate'};
			foreach ($records as $i => $record) {
				$record->{$delegate}->update($update);
			}
		}

		$this->{'$updating'} = false;
		return $this;
	}

	private function isBinded () {
		# determina si el modelo está enlazado a la base de datos
		# si no está enlazado lo agrega a la lista de enlazados
		$is_binded = False;
		if (in_array($this->{'@name'}, self::$binds))
			$is_binded = True;
		else
			self::$binds[] = $this->{'@name'};
		return $is_binded;
	}

	public function bind () {
		if (!$this->isBinded()) {
			$this->backbone->bind($this->database);
		}
		return $this;
	}
}