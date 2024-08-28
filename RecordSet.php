<?php
namespace Irbis;
use Irbis\RecordSet\Backbone;
use Irbis\RecordSet\Member;
use Irbis\RecordSet\Method;
use Irbis\RecordSet\Property;
use Irbis\RecordSet\Record;



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
	private static $binds = [];

	public $__selecting = false;
	public $__inserting = false;
	public $__updating = false;
	public $__deleting = false;
	public $__index_before_insert = 0;
	public $__index_after_insert = 0;
	public $__parent_record = [false,false]; # [/Irbis/RecordSet/Record, /Irbis/RecordSet/Property]
	public $__parent_record_execute = [false,false, []]; # [string (sql query), array (values)]
	public $__children_records = [];

	public function __construct ($name, $pointer = null) {
		$this->backbone = Backbone::getInstance($name);
		$this->database = DataBase::getInstance($pointer);
	}

	public function __isset ($key) {
		if ($key == 'ids') return true;
		return $this->backbone->hasProperty($key);
	}

	/**
	 * @return [ids]
	 * @return mix, e: $o->__properties : [\Irbis\RecordsSet\Property]
	 * @return \Irbis\RecordSet\Property
	 */
	public function __get ($key) {
		if ($key == 'ids') {
			return array_map(function ($i) { 
				return (int) $i->id;
			}, (array) $this);
		}
		if (str_starts_with($key, '__')) {
			if ($key == '__properties')
				return $this->backbone->getProperties();
			if ($key == '__methods')
				return $this->backbone->getMethods();
			if ($key == '__name')
				return $this->backbone->name;
			return $this->backbone->statics[$key];
		}

		if (!$prop = $this->backbone->getProperties($key))
			throw new \Exception("recordSet: la propiedad '$key' no existe");
		return $prop;
	}

	public function __set ($key, $value) {
		throw new \Exception("recordset: está tratando de modificar la propiedad '$key'".
			" de un conjunto de registros, en su lugar utilice el método 'update'");
	}

	public function __call ($key, $args) {
		$key = '@'.$key;
		if (!array_key_exists($key, $this->mcache)) {
			if (!$this->mcache[$key] = $this->backbone->getMethods($key)) {
				throw new \Exception("recordset: llamada a metodo no definido '$key'");
			}
		}

		$r = $this->mcache[$key]->call($args, $this);
		unset($this->mcache[$key]);
		return $r;
	}

	public function __toString () { return \Irbis\Json::encode($this->ids); }
	public function __debugInfo () {
		$debug = [
			'model' => $this->backbone->name,
			'records' => $this->ids,
		];
		if ($this->__parent_record[0])
			$debug['parent'] = [
				$this->__parent_record[0]->__name, 
				$this->__parent_record[1]->name
			];
		return $debug;
	}

	public function newRecordSet ($name = false) {
		if (!$name) $name = $this->backbone->name;
		return new self($name, $this->database->name);
	}

	public function newRecord ($values = [], $is_raw = false) {
		return new Record($values, [
			'recordset' => $this,
			'backbone' => $this->backbone,
			'database' => $this->database
		], $is_raw);
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

	public function raw ($ids, $values = []) {
		$ids = (array) $ids;
		$count = $this->count();
		foreach ($ids as $id) {
			$values = array_merge($values, ['id' => $id]);
			$this[$count] = $this->newRecord($values, true);
			$count++;
		}
		return $this;
	}

	public function isRaw () {
		foreach ($this as $record) {
			if ($record->isRaw()) return true;
		} return false;
	}

	/**
	 * Ejecuta una consulta y devuelve el statement
	 * utiliza el conector de base de datos interno
	 * @param string $query
	 * @param array $params
	 * @return DataBaseStatement
	 */
	public function execute (string $query, array $params = []) {
		$stmt = $this->database->prepare($query);
		$stmt->execute($params);
		return $stmt;
	}

	/**
	 * Ejecuta una consulta SQL directamente
	 * para consultas directas, e: CREATE TABLE
	 * @param string $query
	 */
	public function exec (string $query) {
		return $this->database->exec($query);
	}

	// ==========================================================================
	// DML methods
	// ==========================================================================

	/**
	 * Convierte un arreglo de busqueda en parte de la sentencia SQL
	 * devuelve un arreglo con dos valores, el primero es SQL
	 * el segundo con los valores del arreglo
	 *
	 * @param array $arr
	 *
	 * @return array[string SQL, array values]
	 */
	protected function parseSearchArrayToSQL (array $arr) {
		if (!$arr) return ['', []];
		$union = 'and'; $q = []; $w = [];
		$table = $this->backbone->name;
	
		foreach ($arr as $k => $v) {
			if (gettype($k) == 'integer') {
				if (is_array($v)) {
					$pp = $this->parseSearchArrayToSQL($v);
					$w[] = $pp[0];
					$q = array_merge($q, $pp[1]);
				} else $union = $v;
			} else {
				$k = explode(":", $k); 
				$k[1] = $k[1] ?? '=';
	
				if ($k[1] == 'between') {
					$q[] = $v[0];
					$q[] = $v[1];
					$w[] = "`$table`.`$k[0]` $k[1] ? and ?";
				} elseif ($k[1] == 'not in' || $k[1] == 'in') {
					foreach ($v as $index => $value) $q[] = $value;
					$w[] = "`$table`.`$k[0]` $k[1] (".implode(', ', array_fill(0, count($v), '?')).")";
				} else {
					$q[] = $v;
					$w[] = "`$table`.`$k[0]` $k[1] ?";
				}
			}
		}
	
		return ["(".implode(" $union ", $w).")", $q];
	}

	/**
	 * crea registros en la base de datos, realiza un analisis previo
	 * dato por dato, ejecuta funciones de computación previas y
	 * calcula valores por defecto.
	 * 
	 * para este ejemplo se puede ver que se están agregando dos nuevos
	 * usuarios en una sola llamada.
	 * 
	 * (new RecordSet('user'))
	 * 		->insert(
	 * 			['name' => 'Juan'], 
	 * 			['name' => 'Pedro']
	 * 		);
	 * 
	 * la creación de registros permite anidaciones de registros, 
	 * permitiendo crear al vuelo registros relacionados.
	 * 
	 * (new RecordSet('user'))
	 * 		->insert(
	 * 			['name' => 'Juan', 'roles' => [
	 * 				['name' => 'Administrador'],
	 * 				['name' => 'Vendedor']
	 * 			]],
	 * 			['name' => 'Pedro']
	 * 		);
	 */
	public function insert ($inserts) {
		if (!$inserts) return $this;
		if (!is_array($inserts)) throw new \Exception("insert: datos mal formateados");
		if (is_assoc($inserts)) $inserts = [$inserts];
		$this->__inserting = true;

		// ==============================================================
		// establecer variables auxiliares
		$fields = []; // campos a insertar
		$to_insert = []; // valores a insertar
		$quotes = []; // se llena de ?'s por cada inserción
		$parent = $this->__parent_record[0];
		$parent_prop = $this->__parent_record[1];
		$children = []; // almacena los modelos relacionados
		$props = $this->backbone->getProperties();
		$x = $this->count(); // el índice para agregar a la pila
		
		$this->__index_before_insert = $x;
		// ==============================================================
		// valida y calcula los campos a insertar
		foreach ($inserts as $i => $insert) {			
			if ($parent and $parent_prop->type == '1n') {
				$key = $parent_prop->target_property;
				$insert[$key] = $insert[$key] ?? $parent;
			}

			$record = $this->newRecord();
			
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
				# condición para evitar que se dupliquen los nombres de campos
				if (!$i) $fields[] = "`{$key}`"; // $i == 0
				$to_insert[$x][] = $prop->ensureStoredValue($insert[$key], $record);
			}

			// genera cadenas de inserción: [['?','?'],['?','?']]
			$quotes[] = implode(", ", array_fill(0, count($to_insert[$x]), '?'));
			$x++;
		}
		$this->__index_after_insert = $x-1;

		// ==============================================================
		// crear la sentencia SQL para inserción de registros
		// y los valores que se van a insertar
		$query = "INSERT INTO `{$this->backbone->name}`".
			" (".implode(", ", $fields).") VALUES ".
			" (".implode("), (", $quotes).")";

		$values = array_reduce($to_insert, function ($carry, $item) {
			return array_merge($carry, $item);
		}, []);

		if ($parent) {
			if (!$parent->__inserting)
				$this->__insert($query, $values, $children);
			else $this->__parent_record_execute = [$query, $values, $children];
		} else $this->__insert($query, $values, $children);
		
		$this->__inserting = false;
		return $this;
	}

	public function __insert ($query = false, $values = false, $children = []) {
		// se preparan y validan valores a ejecutar
		$query = $query ?: $this->__parent_record_execute[0];
		$values = $values ?: $this->__parent_record_execute[1];
		$children = $children ?: $this->__parent_record_execute[2];

		if (!$query || !$values) return $this;

		if ($this->__parent_record[0]) {
			$values = array_map(function ($i) {
				return $i == '__newid__' ? $this->__parent_record[0]->id : $i;
			}, $values);
		}
		$this->execute($query, $values);

		// ==============================================================
		// actualiza los ultimos ids ingresados
		// MYSQL devuelve el primer ID de los ultimos registros ingresados
		if ($this->database->driven == 'mysql') {
			$id = $this->database->lastInsertId();
			for ($i = $this->__index_before_insert; $i < $this->__index_after_insert; $i++) {
				$this[$i]->raw('id', $id++);
			}
		} else {
			$id = $this->database->lastInsertId();
			for ($i = $this->__index_after_insert; $i >= $this->__index_before_insert; $i--) {
				$this[$i]->raw('id', $id--);
			}
		}

		$this->__parent_record_execute = [false,false,[]];
		$this->__update_nm();

		foreach ($children as $child) {
			$child->__insert();
			$child->filter(function ($r) { return $r->isRaw(); })->__select();
		}

		return $this;
	}

	/**
	 * carga registros desde la base de datos, los instancia como
	 * objetos Irbis\RecordSet\Record y los apila.
	 * 
	 * existe un función 'rs' expuesta que envuelve la creación
	 * de este objeto, para operaciones en una sola linea.
	 * 
	 * rs('user')->select(1)
	 * rs('user')->select([1,2,3,4])
	 * rs('skel')->select(['or', 
	 * 		[name:like' => '%pedro%'], 
	 * 		[age:>=' => 25]
	 * ])
	 * 
	 * @param int|array $where 				id, [ids] o [condiciones]
	 * @param string|array $order			ordenamiento de registros
	 * @param string|array $limit 			[0,80] o '0-80'
	 * @return Irbis\RecordSet\RecordSet
	 */
	public function select ($where = null, $order = [], $limit = []) {
		if ($this->__parent_record[0] && !$where)
			throw new \Exception("{$this->__name}: este conjunto requiere un condición
				porque está relacionado a {$this->__parent_record->__name}");

		if ($where == '__newid__') {
			$this[0] = $this->newRecord();
			return $this;
		}
		// ignorar, código de ayuda, no tiene un proposito
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

		// =====================================================================
		// preparar la consulta de selección
		$query = "SELECT `{$this->__name}`.* FROM `{$this->__name}`";
		$where = $this->parseSearchArrayToSQL($where ?: []);
		$query .= 
			($where[0] ? " WHERE {$where[0]}" : "").
			($order ? " ORDER BY ".implode(", ", $order) : "").
			($limit ? " LIMIT ".$limit[0].", ".$limit[1] : "");

		// =====================================================================
		// ejecutar consulta y añadir al conjunto sólo los registros nuevos
		$stmt = $this->execute($query, $where[1]);
		$count = $this->count();
		$ids = $this->ids;

		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			if (!in_array($row['id'], $ids)) {
				$this[$count] = $this->newRecord($row);
				$count++;
			}
		}
		$this->__select();
		$this->__update_nm();
		return $this;
	}

	public function __select () {
		$parent = $this->__parent_record[0];
		$parent_prop = $this->__parent_record[1];
		if ($parent) {
			if ($parent_prop->type == '1n') {
				$this->update([
					$parent_prop->target_property => $parent
				]);
			}
		}
	}

	/**
	 * elimina registros de la base de datos, si no se 
	 * le pasa parámetros, toma el conjunto de registros actual
	 * rs('user')->delete([5, 6, 8]);
	 * 
	 * @param mixed $ids (optional),		id, [ids] a eliminar
	 * @return Irbis\RecordSet
	 */
	public function delete ($ids = []) {
		$ids = $ids ? (array) $ids : $this->ids;
		if (!$ids) return $this;

		$query = "DELETE FROM `{$this->__name}` 
			WHERE id in (".implode(", ", array_fill(0, count($ids), '?')).")";
		$stmt = $this->execute($query, $ids);
		# TODO: mejorar la eliminación de registros del arreglo
		foreach ($this as $k => $i)
			if (in_array($i->id, $ids))
				delete($this, $k);

		return $this;
	}

	/**
	 * actualiza de forma masiva todos los registros cargados
	 * 
	 * si se estableció previamente el registro actual con 'record'
	 * los cambios sólo se aplican sobre ese registro
	 * 
	 * si se le envía como segundo parámetro un conjunto de ids
	 * los cambios sólo se aplican sobre esos registros
	 * 
	 * @param array $update,			los datos que deben ser modificados
	 * @param array $ids,				los ids que deben modificarse
	 * @return Irbis\RecordSet
	 */
	public function update ($update, $ids = []) {
		$records = $ids ? ($ids instanceof Record ? [$ids] : (array) $ids) : $this;
		$ids = $ids ? ($ids instanceof Record ? [$ids->id] : (array) $ids) : $this->ids;
		if (!$update || !$ids) return $this;

		// =====================================================================
		// establecer variables auxiliares
		$fields = []; // campos a actualizar
		$to_update = []; // valores a actualizar
		$children = [];
		$props = $this->backbone->getProperties();

		// =====================================================================
		// recorre todos los registros solicitados, puede ocasionar lentitud en muchos
		// registros, pero es necesario para los campos computados
		foreach ($records as $i => $record) {
			if (!$record instanceof Record)
				$record = $this->newRecord()->raw('id', intval($record));

			foreach ($props as $prop) {
				if (!array_key_exists($prop->name, $update)) continue;
				$key = $prop->name;
				$update[$key] = $prop->ensureValue($update[$key], $record);
				$record->raw($key, $update[$key]);

				$has_children = in_array($prop->type, ['1n','nm']);
				$has_children = $has_children && $update[$key] && $update[$key]->count();
				if ($has_children) $children[] = $update[$key];
			}

			// preparar valores a actualizar
			foreach ($props as $prop) {
				if (!array_key_exists($prop->name, $update)) continue;
				if (!$prop->store) continue;
				if (!$i) $fields[] = "`$prop->name` = ?";
				if (!$i) $to_update[] = $prop->ensureStoredValue($update[$prop->name], $record);
			}
		}

		// ==============================================================
		// ejecuta la sentencia de actualizacion
		if ($fields) {
			$query = "UPDATE `{$this->backbone->name}` SET ".implode(", ", $fields).
				" WHERE id in (".implode(", ", array_fill(0, count($ids), '?')).")";
			$this->execute($query, array_merge($to_update, $ids));
		}

		// ==============================================================
		foreach ($children as $child) {
			$child->__select();
			$child->__update_nm(true);
		}

		return $this;
	}

	public function __update_nm ($clean_previous=false) {
		$parent = $this->__parent_record[0];
		$prop = $this->__parent_record[1];
		if ($parent && $prop->type == 'nm') {
			if ($clean_previous) {
				$field = $prop->nm1 == $prop->name ? $prop->nm2 : $prop->nm1;
				$query = "DELETE FROM `{$prop->nm_string}` WHERE `{$field}` = ?";
				$this->execute($query, [$parent->id]);
			}

			$ins = array_map(function ($i) use ($parent, $prop) {
				return ($prop->nm1 == $prop->name) ? "$i, {$parent->id}": "{$parent->id}, $i";
			}, $this->ids);
			
			$query = "INSERT OR IGNORE INTO `{$prop->nm_string}` ".
				"(`{$prop->nm1}`, `{$prop->nm2}`) ".
				"VALUES (".implode("), (", $ins).")";
			
			$this->execute($query);
		}
		return $this;
	}

	/**
	 * Determina si el nombre del modelo está enlazado, si no está
	 * lo agrega a la lista, devuelve true o false
	 */
	private function isBinded ($model_name) {
		$is_binded = False;
		if (in_array($model_name, self::$binds)) 
			$is_binded = True;
		else
			self::$binds[] = $model_name;
		return $is_binded;
	}

	/**
	 * Enlaza el modelo a la base de datos, crea las tablas y relaciones
	 * necesarias para que el modelo exista en base de datos. 
	 */
	public function bind () {
		$database = $this->database;
		$table_name = $this->backbone->name;

		if ($this->isBinded($table_name)) 
			return $this;
		$pks = []; $fks = [];
		
		$database->createTable($table_name);

		foreach ($this->backbone->getProperties() as $prop) {
			if ($prop->primary_key) $pks[] = $prop;

			if (in_array($prop->type, ['1n','nm','n1'])) {
				(new self($prop->target_model, $this->database->name))->bind();
				if ($prop->type == 'nm')
					$database->createNmTable($table_name, $prop);
				if ($prop->type == 'n1')
					$fks[] = $prop;
			}

			if ($prop->store)
				$database->addColumn($table_name, $prop);
		}

		if ($pks)
			$database->setPrimaryKeys($table_name, $pks);

		foreach ($fks as $prop)
			$database->setForeignKey($table_name, $prop);

		return $this;
	}
}