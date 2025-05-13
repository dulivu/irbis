<?php
namespace Irbis\RecordSet;

use Irbis\Server;
use Irbis\Controller;
use Irbis\RecordSet;


/**
 * Controla la estructura central del modelo cargando la definición
 * de propiedades y métodos de cada módulo registrado en el servidor, 
 * esta clase es de uso exclusivo de la clase RecordSet 
 * (se debe entender como una caja negra)
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
class Backbone {

	public $name;
	public $constans = [];

	private $properties = [];
	private $methods = [];
	private $database = null;
	private $driven = 'sqlite';

	private static $instances = [];

	public static function getInstance (string $name) {
		self::$instances[$name] = self::$instances[$name] ?? new self($name);
		return self::$instances[$name];
	}

	public function __construct (string $name) {
		$self = $this;
		$self->name = $name;

		# todos los modelos llevan un campo ID
		$this->setMember('id', [
			'int', 
			'label' => 'ID', 
			'oSQL' => 'UNIQUE AUTO_INCREMENT', 
			'readonly' => true,
			'store' => false,
		]);

		# se recorre todos los controladores registrados para obtener
		# los modelos que pudiera tener y agregarlos a la definición
		Server::getInstance()
			->forEachController(function ($controller) use ($self) {
				$file = "models/{$self->name}.php";
				if ($skeleton = $controller->file($file, Controller::FILE_INCLUDE)) {
					foreach ($skeleton as $key => $value)
						$self->setMember($key, $value);
				}
			});

		if (count($this->properties) <= 1)
			throw new \Exception("recordset: no se pudo importar una definición para '$name'");
	}

	public function setMember ($key, $value = null) {
		if ($key == '@extend') {
			$this->constans[$key] = $value;
			$this->setMember(self::getInstance($value));
		} elseif ($key instanceof self) {
			foreach ($key->getProperties() as $k => $m)
				$this->properties[$k] = clone $m;
			foreach ($ket->getMethods() as $k => $m)
				$this->methods[$k] = clone $m;
		} elseif (is_callable($value)) {
			if (!$this->hasMethod($key))
				$this->methods[$key] = new Method($key);
			$this->methods[$key]->append($value);
		} elseif (!str_starts_with($key, '@')) {
			if (!$this->hasProperty($key))
				$this->properties[$key] = new Property($key);
			$this->properties[$key]->define($value);
		} else {
			$this->constans[$key] = $value;
		}
	}

	public function hasProperty ($key) {
		return isset($this->properties[$key]) && 
			$this->properties[$key] instanceof Property;
	}

	public function hasMethod ($key) {
		return isset($this->methods[$key]) &&
			$this->methods[$key] instanceof Method;
	}

	public function getProperties ($key = false) {
		if (is_string($key))
			return $this->cloneProperty($key);

		$key = is_array($key) ? $key : array_keys($this->properties);
		$arr = array_map(function ($prop) {
			return $this->cloneProperty($prop);
		}, $key);
		return array_combine($key, $arr);
	}

	private function cloneProperty ($key) {
		# las propiedades se clonan para salvaguardar su integridad
		# de esta forma se evita que se modifiquen accidentalmente
		if (isset($this->properties[$key]))
			return clone $this->properties[$key];
		else return null;
	}

	public function getMethods ($key = false) {
		if (is_string($key))
			return $this->cloneMethod($key);
		$key = is_array($key) ? $key : array_keys($this->methods);
		$arr = array_map(function ($prop) {
			return $this->cloneMethod($prop);
		}, $key);
		return array_combine($key, $arr);
	}

	private function cloneMethod ($key) {
		if (isset($this->methods[$key]))
			return clone $this->methods[$key];
		else return null;
	}

	/* METODOS DML */

	/**
	 * Convierte un arreglo de busqueda en parte de la sentencia SQL
	 * devuelve un arreglo con dos valores, el primero es SQL
	 * el segundo con los valores del arreglo
	 *
	 * @param array $arr
	 *
	 * @return array[string SQL, array values]
	 */
	public function parseSearchArrayToSQL (array $arr) {
		if (!$arr) return ['', []];
		$union = 'and'; $q = []; $w = [];
		$table = $this->name;
	
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

	public function insertQuery ($fields, $to_insert) {
		$quotes = array_map(function ($item) {
			return implode(", ", array_fill(0, count($item), '?'));
		}, $to_insert);

		$query = "INSERT INTO `{$this->name}`".
			" (".implode(", ", $fields).") VALUES ".
			" (".implode("), (", $quotes).")";

		$values = array_reduce($to_insert, function ($carry, $item) {
			return array_merge($carry, $item);
		}, []);

		return [$query, $values];
	}

	public function update1nQuery ($prop, $rec, $n_set) {
		$rec = $rec instanceof Record ? $rec->id : $rec;
		$n_set = $n_set instanceof RecordSet ? $n_set->ids : $n_set;
		$query = "UPDATE `{$this->name}` set {$prop->target_property} = null 
			WHERE {$prop->target_property} = {$rec} AND id not in (".
			implode(", ", array_fill(0, count($n_set), '?')).")";
		return $query;
	}

	public function deleteNmQuery ($prop) {
		$field = $prop->nm1 == $prop->name ? $prop->nm2 : $prop->nm1;
		$query = "DELETE FROM `{$prop->nm_string}` WHERE `{$field}` = ?";
		return $query;
	}

	public function insertNmQuery ($prop, $n_rec, $m_set, $clear_set=false) {
		$m_set = $m_set instanceof RecordSet ? $m_set->ids : $m_set;

		$ins = array_map(function ($i) use ($n_rec, $prop) {
			return ($prop->nm1 == $prop->name) ? "$i, {$n_rec->id}": "{$n_rec->id}, $i";
		}, $m_set);
		
		$query = "INSERT OR IGNORE INTO `{$prop->nm_string}` ".
			"(`{$prop->nm1}`, `{$prop->nm2}`) ".
			"VALUES (".implode("), (", $ins).")";
		
		return $query;
	}

	public function selectQuery ($where, $order, $limit) {
		$query = "SELECT `{$this->name}`.* FROM `{$this->name}`";
		[$where, $values] = $this->parseSearchArrayToSQL($where ?: []);
		$query .= 
			($where ? " WHERE {$where}" : "").
			($order ? " ORDER BY ".implode(", ", $order) : "").
			($limit ? " LIMIT ".$limit[0].", ".$limit[1] : "");
		return [$query, $values];
	}

	public function selectNmQuery($prop) {
		$query = "SELECT `{$prop->target_model}`.* 
			FROM `{$prop->target_model}`
			INNER JOIN `{$prop->nm_string}` 
				ON `{$prop->nm_string}`.`{$prop->name}` = `{$prop->target_model}`.`id`
			WHERE `{$prop->nm_string}`.`{$prop->target_property}` = ?";
		return $query;
	}

	public function deleteQuery ($ids) {
		$query = "DELETE FROM `{$this->name}` 
			WHERE id in (".implode(", ", array_fill(0, count($ids), '?')).")";
		return $query;
	}

	public function fieldQuery ($prop) {
		$query = "`$prop->name` = ?";
		return $query;
	}

	public function updateQuery ($fields, $ids) {
		$query = "UPDATE `{$this->name}` SET ".implode(", ", $fields).
			" WHERE id in (".implode(", ", array_fill(0, count($ids), '?')).")";
		return $query;
	}

	/* METODOS PARA ENLACE A BASE DE DATOS */

	public function bind ($database) {
		$this->database = $database;
		$this->driven = $database->driven;
		$pks = []; $fks = [];
		$this->createTable();

		foreach ($this->getProperties() as $prop) {
			if ($prop->primary_key) $pks[] = $prop;
			if (in_array($prop->type, ['1n','nm','n1'])) {
				(new RecordSet($prop->target_model, $database->name))->bind();
				if ($prop->type == 'nm')
					$this->createNmTable($prop);
				if ($prop->type == 'n1')
					$fks[] = $prop;
			}
			if ($prop->store)
				$this->addColumn($prop);
		}

		if ($pks) $this->setPrimaryKeys($pks);
		foreach ($fks as $prop) $this->setForeignKey($prop);
		$this->database = null;
		$this->driver = null;
		return $this;
	}

	private function createTable () {
		if ($this->driven == 'mysql') {
			$query = "CREATE TABLE IF NOT EXISTS `{$this->name}` ( 
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				UNIQUE INDEX `uid` (`id` ASC)
			) ENGINE=InnoDB";
		} elseif ($this->driven == 'sqlite') {
			$query = "CREATE TABLE IF NOT EXISTS '{$this->name}'
			('id' INTEGER PRIMARY KEY AUTOINCREMENT)";
		}
		$this->database->exec($query);
	}

	private function createNmTable ($property) {
		$table_order = ($property->nm1 == $property->name) ? 
			[$property->target_model, $this->name] :
			[$this->name, $property->target_model];

		if ($this->driven == 'mysql') {
			$query = "CREATE TABLE IF NOT EXISTS `{$property->nm_string}` (
				`{$property->nm1}` INT UNSIGNED NOT NULL,
				`{$property->nm2}` INT UNSIGNED NOT NULL,
				PRIMARY KEY (`{$property->nm1}`, `{$property->nm2}`),
					CONSTRAINT `fk_nm_{$property->nm_string}_{$property->nm1}`
						FOREIGN KEY (`{$property->nm1}`)
						REFERENCES `{$table_order[0]}` (`id`)
						ON DELETE CASCADE
						ON UPDATE CASCADE,
					CONSTRAINT `fk_nm_{$property->nm_string}_{$property->nm2}`
						FOREIGN KEY (`{$property->nm2}`)
						REFERENCES `{$table_order[1]}` (`id`)
						ON DELETE CASCADE
						ON UPDATE CASCADE
			) ENGINE=InnoDB";
		} elseif ($this->driven == 'sqlite') {
			$query = "CREATE TABLE IF NOT EXISTS '$property->nm_string' (
				'$property->nm1' INTEGER NOT NULL,
				'$property->nm2' INTEGER NOT NULL,
				PRIMARY KEY ({$property->nm1}, {$property->nm2}),
				FOREIGN KEY ({$property->nm1})
					REFERENCES {$table_order[0]} (id)
					ON DELETE CASCADE
					ON UPDATE CASCADE,
				FOREIGN KEY ({$property->nm2})
					REFERENCES {$table_order[1]} (id)
					ON DELETE CASCADE
					ON UPDATE CASCADE
			)";
		}
		$this->database->exec($query);
	}

	private function addColumn ($property) {
		$definition = $this->columnName($property, true);
		if (!$definition) return;
		
		if ($this->driven == 'mysql') {
			$db = $this->database->query('select database()')->fetchColumn();

			$query = "SELECT count(*) FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = '$db' 
					AND TABLE_NAME = '{$this->name}' 
					AND COLUMN_NAME = '{$property->name}' LIMIT 1";
			$stmt = $this->database->query($query);
			$row = $stmt->fetch(\PDO::FETCH_NUM);

			$this->database->exec("ALTER TABLE `{$this->name}` ".($row[0] ? "MODIFY COLUMN $definition" : "ADD $definition"));
		} elseif ($this->driven == 'sqlite') {
			$query = "SELECT count(*) FROM
				pragma_table_info('{$this->name}')
				WHERE name='{$property->name}' LIMIT 1";
			$stmt = $this->database->query($query);
			$row = $stmt->fetch(\PDO::FETCH_NUM);
			# sqlite no soporta modificación de columnas
			if (!$row[0])
				$this->database->exec("ALTER TABLE '{$this->name}' ADD $definition");
		}
	}

	private function columnName ($property, $full_defined=false) {
		$quote = $this->driven == 'mysql' ? '`' : ($this->driven == 'sqlite' ? "'": "'");
		$column = "$quote{$property->name}$quote";
		if ($full_defined) {
			if ($this->driven == 'mysql') {
				if (in_array($property->type, ['1n','nm']))
					$column .= " JSON";
				else if ($property->type == 'n1')
					$column .= " INT UNSIGNED";
				else
					$column .= " {$property->type}"
						.($property->length ? "($property->length)" : '')
						.($property->required ? ' NOT NULL' : '')
						.($property->oSQL ? " {$property->oSQL}" : '')
						.($property->default ? " DEFAULT '{$property->default}'" : '');
			} elseif ($this->driven == 'sqlite') {
				if (in_array($property->type, ['1n','nm']))
					return false;
				else if ($property->type == 'n1')
					$column .= " INTEGER";
				else
					$column .= " {$property->type}"
						.($property->length ? "($property->length)" : '')
						.($property->required ? ' NOT NULL' : '')
						.($property->oSQL ? " {$property->oSQL}" : '')
						.($property->default ? " DEFAULT '{$property->default}'" : '');
			}
		}
		return $column;
	}

	private function setPrimaryKeys ($pks_properties) {
		if ($this->driven == 'mysql') {
			try { 
				$this->exec("ALTER TABLE `{$this->name}` DROP PRIMARY KEY"); 
			} catch (\PDOException $e) {}
			$pks = array_map(function ($pk) { return $pk->name; }, $pks_properties);
			$this->exec("ALTER TABLE `{$this->name}` ADD PRIMARY KEY (".implode(",", $pks).")");
		}
	}

	private function setForeignKey($property) {
		if ($this->driven == 'mysql') {
			try { 
				$this->exec("ALTER TABLE `{$this->name}` DROP FOREIGN KEY `fk_{$this->name}_{$property->name}` IF EXISTS");
			} catch (\PDOException $e) {}
	
			$this->exec("ALTER TABLE `{$this->name}` 
				ADD CONSTRAINT `fk_{$this->name}_{$property->name}` 
					FOREIGN KEY (`{$property->name}`) 
					REFERENCES `{$property->target_model}`(`id`)
					ON DELETE {$property->on_delete}
					ON UPDATE {$property->on_update}");
		}
	}
}