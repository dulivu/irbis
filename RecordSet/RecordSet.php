<?php
namespace Irbis\RecordSet;
use Irbis\DataBase;


/**
 * Representa un DataSet vacio, por medio de sus métodos DML
 * se permite recoger los datos de su tabla a la que representa
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <GeorgeL1102@gmail.com>
 * @version		1.0
 */
class RecordSet extends \ArrayObject {

	/**
	 * Columna vertebral de definiciones
	 * @var \Irbis\RecordSet\Backbone
	 */
	private $backbone;

	/**
	 * Instancia de conexión a la base de datos
	 * @var \Irbis\DataBase
	 */
	private $database;

	/**
	 * Últimos IDs agregados al registro
	 * @var array
	 */
	private $last_records_ids = [];

	/**
	 * Registro unico relacionado
	 * @var \Irbis\RecordSet\Record
	 */
	private $related_record;

	/**
	 * Propiedad por la que se genera la relacion
	 * @var \Irbis\RecordSet\Property
	 */
	private $related_property;

	/**
	 * Almacena los métodos que se estén ejecutando
	 * @var array
	 */
	private $methods_cache = [];

	/**
	 * Guarda una lista de modelos que ya fueron enlazados
	 * con la base de datos, para evitar doble enlace
	 * @var array
	 */
	private static $binds = [];

	/**
	 * Contructor
	 * @param string $name
	 * @param string $pointer [optional], DataBase pointer
	 */
	public function __construct ($name, $pointer = null) {
		$this->backbone = Backbone::getInstance($name);
		$this->database = DataBase::getInstance($pointer);
	}

	/**
	 * @return bool
	 */
	public function __isset ($prop_name) {
		if ($prop_name == 'ids') return true;
		return $this->backbone->hasProperty($prop_name);
	}

	/**
	 * Devuelve una propiedad existente en su arbol
	 * de definición
	 * @param string $prop_name
	 * @return \Irbis\RecordSet\Property
	 */
	public function __get ($prop_name) {
		if ($prop_name == 'ids') {
			return array_map(function ($i) { 
				return (int) $i->id;
			}, (array) $this);
		}

		if (!$prop = $this->backbone->getProperty($prop_name, $this))
			throw new \Exception("recordSet: la propiedad '$prop_name' no existe");
		return $prop;
	}

	public function __toString () { return \Irbis\Json::encode($this->ids); }

	/**
	 * Hace una llamada a un método dentro de las definiciones
	 * @return mix
	 */
	public function __call ($method, $args) {
		$method = '@'.$method;
		if (!array_key_exists($method, $this->methods_cache)) {
			if (!$this->methods_cache[$method] = $this->backbone->getMethod($method)) {
				throw new \Exception("recordset: llamada a metodo no definido '$method'");
			}
		}

		$re = $this->methods_cache[$method]->call($args, $this);
		unset($this->methods_cache[$method]);
		return $re;
	}

	/**
	 * Establece un nuevo puntero para la base de datos
	 * la base de datos debe ser previamente instanciada
	 * o estar en la lista de conexión del archiv .ini
	 * @param string $pointer
	 */
	public function setDataBasePointer (string $pointer) {
		$this->database = DataBase::getInstance($pointer);
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
	 * Devuelve el método del esqueleto
	 * @param string $method_name
	 * @return Irbis\RecordSet\Method
	 */
	public function getMethod (string $method_name) {
		return $this->backbone->getMethod($method_name, $this);
	}

	/**
	 * Limpia el buffer de almacenamiento
	 * @return self
	 */
	public function flush () {
		$this->last_records_ids = [];
		$this->related_record = null;
		$this->related_property = null;
		foreach ((array) $this as $k => $v) {
			delete($this, $k);
		}
	}

	/**
	 * Crea y devuelve un nuevo objeto RecordSet
	 * utiliza el puntero de base de datos interno
	 * @param string $model
	 * @param array $options
	 * @return RecordSet
	 */
	public function newRecordSet (string $model) {
		$pointer = $this->database->name;
		return new self($model, $pointer);
	}

	/**
	 * Devuelve el nombre del modelo
	 * @return string
	 */
	public function getName () {
		return $this->backbone->name;
	}

	/**
	 * Establece una relación entre este conjunto de registros
	 * y un registro unico, se usa en campos tipo 1n y nm
	 * @param \Irbis\RecordSet\Record || int(id)
	 * @param \Irbis\RepordSet\Property
	 */
	public function setRelatedRecord ($record, Property $prop) {
		$this->related_record = $record;
		$this->related_property = $prop;
	}

	/**
	 * Devuelve todas las propiedades del backbone
	 * asociadas al recordset actual
	 * @return array[Property]
	 */
	public function getProperties () {
		return $this->backbone->getProperties($this);
	}

	/**
	 * Valida que una cadena corresponda con el nombre de una propiedad,
	 * devuelve el nombre de la propiedad o falso
	 * @param string $prop_name
	 * @return string | false
	 */
	public function testFieldName ($prop_name) {
		$prop = $this->backbone->getProperty(trim($prop_name));
		return $prop ? $prop->name : false;
	}

	// ==========================================================================
	// DML methods
	// ==========================================================================

	/**
	 * Actualiza los campos de relacion de este conjunto con el id del registro relacionado,
	 * esto sucede cuando este conjunto selecciona o inserta nuevos elementos estando relacionado
	 * con un registro único.
	 */
	public function updateRelation () {
		if (!$this->last_records_ids) return;
		if (!$this->related_record) return;

		$id = is_int($this->related_record) ? 
			$this->related_record : $this->related_record->id;
		if (!$id) return;

		if ($this->related_property->type == '1n') {
			$this->update([ 
				$this->related_property->target_property => $id 
			], $this->last_records_ids);
		} elseif ($this->related_property->type == 'nm') {
			$ins = array_map(function ($i) use ($id) {
				return "$i, $id";
			}, $this->last_records_ids);

			$query = "INSERT INTO `{$this->related_property->nm_string}` ".
				"(`{$this->related_property->nm1}`, `{$this->related_property->nm2}`) ".
				"VALUES (".implode("), (", $ins).")";
			$this->execute($query);
		}

		$this->last_records_ids = [];
		return $this;
	}

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
	 * new RecordSet('user')->insert(['name' => 'Juan'], ['name' => 'Pedro'])
	 */
	public function insert (...$inserts) {
		if (!$inserts) return $this;

		// ==============================================================
		// establecer variables auxiliares
		$fields = []; // campos a insertar
		$to_insert = []; // valores a insertar
		$quotes = []; // se llena de ?'s por cada inserción
		$relateds = []; // almacena los modelos relacionados
		$props = $this->getProperties();
		$y = $x = $this->count(); // el índice para agregar a la pila

		// ==============================================================
		// valida y calcula los campos a insertar
		foreach ($inserts as $i => $insert) {
			$this[$x] = new Record($insert, $this);

			// calcula los valores
			foreach ($props as $prop) {
				$insert[$prop->name] = $prop->testValue($insert);
				$insert[$prop->name] = $prop->testRecordValue($insert);
				if (($insert[$prop->name] = $prop->testRecordSetValue($insert, $this[$x])) instanceof RecordSet)
					$relateds[] = $insert[$prop->name];
				$insert[$prop->name] = $prop->compute('store', $this[$x], $insert);
				$this[$x]->raw($prop->name, $insert[$prop->name]);
			}

			// prepara inserciones
			foreach ($props as $prop) {
				if (!$prop->store) continue;
				if (!$i) $fields[] = "`{$prop->name}`";
				$to_insert[$i][] = $prop->ensureStoredValue($insert[$prop->name]);
			}

			// genera una cadena por cada inserción, ?, ?, ?, ?, ...
			$quotes[] = implode(", ", array_fill(0, count($to_insert[$i]), '?'));
			$x++;
		}

		// ==============================================================
		// crea y ejecuta la sentencia de inserción
		$query = "INSERT INTO `{$this->backbone->name}`".
			" (".implode(", ", $fields).") VALUES ".
			" (".implode("), (", $quotes).")";

		$this->execute($query, array_reduce($to_insert, function ($carry, $item) {
			return array_merge($carry, $item);
		}, []));

		// ==============================================================
		// actualiza los ultimos ids ingresados
		$id = $this->database->lastInsertId();
		for ($i = $y; $i < $x; $i++) {
			$this[$i]->raw('id', $id++);
			$this->last_records_ids[] = $this[$i]->id;
		}

		// ==============================================================
		// actualiza campos relacionados
		foreach ($relateds as $related)
			$related->updateRelation();
		$this->updateRelation();

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
		if ($this->related_record && !$where)
			throw new \Exception("recordset: existe una relación con otro registro, 
				no puede ejecutar selecciones sin condicionales");

		// =====================================================================
		// prepara las variables de busqueda, ordenamiento y limites
		if ($where !== null && !is_array($where))
			$where = ['id:=' => intval($where)];
		elseif ($where !== null && !is_assoc($where))
			$where = ['id:in' => $where];
		$order = is_string($order) ? explode(",", $order) : $order; $self = $this;
		$order = array_map(function ($i) use ($self) { return $self->testFieldName($i); }, $order);
		$order = array_filter($order, function ($i) { return $i; }) ?: ['id'];
		$limit = is_string($limit) ? explode("-", $limit) : $limit;
		$limit = array_map('intval', $limit);

		// =====================================================================
		// preparar la consulta de selección
		$query = "SELECT `{$this->backbone->name}`.* FROM `{$this->backbone->name}`";
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
				$this[$count] = new Record($row, $this);
				$this->last_records_ids[] = $this[$count]->id;
				$count++;
			}
		}

		// =====================================================================
		// actualiza campos relacionados
		$this->updateRelation();
		return $this;
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

		$table = $this->getName();
		$query = "DELETE FROM `$table` WHERE id in (".implode(", ", array_fill(0, count($ids), '?')).")";
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
	 * dbo('skel')->select(5)->update([vals])
	 * dbo('skel')->update(['name' => 'juan'], 5)
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
		$props = $this->backbone->getProperties($this);

		// =====================================================================
		// recorre todos los registros solicitados, puede ocasionar lentitud en muchos
		// registros, pero es necesario para los campos computados
		foreach ($records as $i => $record) {
			if (!$record instanceof Record)
				$record = (new Record([], $this))->raw('id', intval($record));

			foreach ($props as $prop) {
				if (!array_key_exists($prop->name, $update)) continue;
				$prop->testValue($update);
				$update[$prop->name] = $prop->testRecordValue($update);
				$update[$prop->name] = $prop->testRecordSetValue($update, $record);
				$record->raw($prop->name, $update[$prop->name]);
				$update[$prop->name] = $prop->compute('store', $record, $update);
			}

			// preparar valores a actualizar
			foreach ($props as $prop) {
				if (!array_key_exists($prop->name, $update)) continue;
				if (!$prop->store) continue;
				if (!$i) $fields[] = "`$prop->name` = ?";
				if (!$i) $to_update[] = $prop->ensureStoredValue($update[$prop->name]);
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

		return $this;
	}

	public function bind ($pointer) {
		if (in_array($this->backbone->name, self::$binds)) return $this;
		self::$binds[] = $this->backbone->name;

		$root = DataBase::getInstance($pointer);
		$db = $this->database->dbName();
		if (!$db) throw new \Exception("recordset: debe seleccionar una base de datos");
		$tbl = $this->backbone->name;
		$pks = []; $fks = [];
		
		$query = "CREATE TABLE IF NOT EXISTS `$db`.`$tbl` ( 
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			UNIQUE INDEX `uid` (`id` ASC)
		) ENGINE=InnoDB";
		$root->exec($query);

		foreach ($this->backbone->getProperties($this) as $prop) {
			if ($prop->primary_key) $pks[] = "`$prop->name`";

			if ($prop->type == '1n') {
				(new RecordSet($prop->target_model))->bind($pointer);
				$col = "`$prop->name` JSON";
			} elseif ($prop->type == 'nm') {
				(new RecordSet($prop->target_model))->bind($pointer);
				$order = ($prop->nm1 == $prop->name) ? 
					[$prop->target_model, $tbl] :
					[$tbl, $prop->target_model];
				$query = "CREATE TABLE IF NOT EXISTS `$db`.`{$prop->nm_string}` (
					`{$prop->nm1}` INT UNSIGNED NOT NULL,
					`{$prop->nm2}` INT UNSIGNED NOT NULL,
					PRIMARY KEY (`{$prop->nm1}`, `{$prop->nm2}`),
						CONSTRAINT `fk_nm_{$prop->nm_string}_{$prop->nm1}`
							FOREIGN KEY (`{$prop->nm1}`)
							REFERENCES `{$order[0]}` (`id`)
							ON DELETE CASCADE
							ON UPDATE CASCADE,
						CONSTRAINT `fk_nm_{$prop->nm_string}_{$prop->nm2}`
							FOREIGN KEY (`{$prop->nm2}`)
							REFERENCES `{$order[1]}` (`id`)
							ON DELETE CASCADE
							ON UPDATE CASCADE
				) ENGINE=InnoDB";
				$root->exec($query);
				$col = "`$prop->name` JSON";
			} elseif ($prop->type == 'n1') {
				(new RecordSet($prop->target_model))->bind($pointer);
				$col = "`$prop->name` INT UNSIGNED";
				$fks[] = $prop;
			} else {
				$col = "`$prop->name` {$prop->type}"
					.($prop->length ? "($prop->length)" : '')
					.($prop->required ? ' NOT NULL' : '')
					.($prop->oSQL ? " {$prop->oSQL}" : '')
					.($prop->default ? " DEFAULT '{$prop->default}'" : '');
			}

			if ($prop->store) {
				$query = "SELECT count(*) FROM INFORMATION_SCHEMA.COLUMNS 
					WHERE TABLE_SCHEMA = '$db' 
						AND TABLE_NAME = '$tbl' 
						AND COLUMN_NAME = '{$prop->name}' LIMIT 1";
				$stmt = $root->query($query);
				$row = $stmt->fetch(\PDO::FETCH_NUM);

				$query = "ALTER TABLE `$db`.`$tbl` ".
					($row[0] ? "MODIFY COLUMN $col" : "ADD $col");
				$root->exec($query);
			}
		}

		if ($pks) {
			try { 
				$root->exec("ALTER TABLE `$db`.`$tbl` DROP PRIMARY KEY"); 
			} catch (\PDOException $e) {}
			
			$root->exec("ALTER TABLE `$db`.`$tbl` ADD PRIMARY KEY (".implode(",", $pks).")");
		}

		foreach ($fks as $prop) {
			$stmt = $root->query("SELECT count(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
				WHERE table_schema = '$db'
					AND table_name = '$tbl'
					AND constraint_name = 'fk_{$tbl}_{$prop->name}'
					AND constraint_type = 'FOREIGN KEY' LIMIT 1");
			$row = $stmt->fetch(\PDO::FETCH_NUM);

			if ($row[0]) {
				$root->exec("ALTER TABLE `$db`.`$tbl` 
					DROP FOREIGN KEY `fk_{$tbl}_{$prop->name}`");
			}

			$root->exec("ALTER TABLE `$db`.`$tbl` 
				ADD CONSTRAINT `fk_{$tbl}_{$prop->name}` 
					FOREIGN KEY (`{$prop->name}`) 
					REFERENCES `{$prop->target_model}`(`id`)
					ON DELETE {$prop->on_delete}
					ON UPDATE {$prop->on_update}");
		}

		return $this;
	}
}