<?php

namespace Irbis;


/**
 * Envoltura del objeto PDO de php, se añade funcionalidad singleton
 * para mantener una sola conexión por nombre, y registrar el log
 * de transacciones solicitadas.
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
class DataBase extends \PDO {

	/**
	 * Almancena todas las instancias de conexión
	 * @var array[\Irbis\DataBase]
	 */
	private static $instances = array();

	/**
	 * Almancena el puntero de base de datos
	 * si se llama a 'getInstance' sin parámetro
	 * @var string
	 */
	public static $pointer = 'main';

	/**
	 * El nombre de la conexión
	 * @var string
	 */
	public $name;
	public $driven;

	/**
	 * crea/devuelve una conexión de base de datos por el nombre
	 * los valores de conexión se pasan en el segundo parámetro
	 * si la conexión ya existiera estos no se consideran
	 * 
	 * @param string $name, nombre de la conexion
	 * @param array $options, valores de conexión
	 * @return \DB, conexión de base de datos
	 */
	public static function &getInstance ($name = null, $o = null) {
		$name = $name ?: self::$pointer;

		if (isset(self::$instances[$name]))
			return self::$instances[$name];
		
		if (!$o) {
			$ini = @parse_ini_file(DB_INI, true);
			$o = $ini[$name] ?? null;
		}

		if (!$o)
			throw new \Exception("database: se requieren valores de conexión para '$name'");

		self::$instances[$name] = new self($o['dsn'], $o['user'] ?? null, $o['pass'] ?? null, $o['attr'] ?? null);
		self::$instances[$name]->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		self::$instances[$name]->driven = self::$instances[$name]->getAttribute(\PDO::ATTR_DRIVER_NAME);
		self::$instances[$name]->name = $name;
		self::$pointer = self::$pointer ?: $name;

		return self::$instances[$name];
	}

	public function createTable ($table_name) {
		if ($this->driven == 'mysql') {
			$query = "CREATE TABLE IF NOT EXISTS `$table_name` ( 
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				UNIQUE INDEX `uid` (`id` ASC)
			) ENGINE=InnoDB";
		} elseif ($this->driven == 'sqlite') {
			$query = "CREATE TABLE IF NOT EXISTS '$table_name'
			('id' INTEGER PRIMARY KEY AUTOINCREMENT)";
		}
		$this->exec($query);
	}

	public function createNmTable ($table_name, $property) {
		$table_order = ($property->nm1 == $property->name) ? 
			[$property->target_model, $table_name] :
			[$table_name, $property->target_model];

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
		$this->exec($query);
	}

	public function setPrimaryKeys ($table_name, $pks_properties) {
		if ($this->driven == 'mysql') {
			try { 
				$this->exec("ALTER TABLE `$table_name` DROP PRIMARY KEY"); 
			} catch (\PDOException $e) {}
			$pks = array_map(function ($pk) { return $pk->name; }, $pks_properties);
			$this->exec("ALTER TABLE `$table_name` ADD PRIMARY KEY (".implode(",", $pks).")");
		}
	}

	public function columnName ($property, $full_defined=false) {
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

	public function addColumn ($table_name, $property) {
		$definition = $this->columnName($property, true);
		if (!$definition) return;
		
		if ($this->driven == 'mysql') {
			$db = parent::query('select database()')->fetchColumn();

			$query = "SELECT count(*) FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = '$db' 
					AND TABLE_NAME = '$table_name' 
					AND COLUMN_NAME = '{$property->name}' LIMIT 1";
			$stmt = $this->query($query);
			$row = $stmt->fetch(\PDO::FETCH_NUM);

			$this->exec("ALTER TABLE `$table_name` ".($row[0] ? "MODIFY COLUMN $definition" : "ADD $definition"));
		} elseif ($this->driven == 'sqlite') {
			$query = "SELECT count(*) FROM
				pragma_table_info('$table_name')
				WHERE name='{$property->name}' LIMIT 1";
			$stmt = $this->query($query);
			$row = $stmt->fetch(\PDO::FETCH_NUM);
			# sqlite no soporta modificación de columnas
			if (!$row[0])
				$this->exec("ALTER TABLE '$table_name' ADD $definition");
		}
	}

	public function setForeignKey($table_name, $property) {
		if ($this->driven == 'mysql') {
			try { 
				$this->exec("ALTER TABLE `$table_name` DROP FOREIGN KEY `fk_{$table_name}_{$property->name}` IF EXISTS");
			} catch (\PDOException $e) {}
	
			$this->exec("ALTER TABLE `$table_name` 
				ADD CONSTRAINT `fk_{$table_name}_{$property->name}` 
					FOREIGN KEY (`{$property->name}`) 
					REFERENCES `{$property->target_model}`(`id`)
					ON DELETE {$property->on_delete}
					ON UPDATE {$property->on_update}");
		}
	}
}
