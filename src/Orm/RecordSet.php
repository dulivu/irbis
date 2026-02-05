<?php
namespace Irbis\Orm;

use Irbis\Exceptions\RecordException;
use Irbis\Tools\Json;


/**
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class RecordSet extends \ArrayObject {
    private $database;
    private $backbone;
    private $methods = [];
    private $hiddens = [];

    /* -= magic methods =- */

    public function __construct ($name) {
        $this->database = Connector::getInstance();
        $this->backbone = Backbone::getInstance($name);
    }

    public function __isset ($key) {
        if ($key == 'ids') return true;
        if (str_starts_with($key, '@')) return true;
        if (str_starts_with($key, '$')) return true;
        if (str_starts_with($key, '-')) return true;
        $has = $this->backbone->hasProperty($key);
        if (!$has && $delegate = $this->{'@delegate'}) {
            return true;
        } return $has;
    }

    //? ejemplos de uso:
    // $recordset->{'@constant'};   desde el backbone
    // $recordset->{'@name'};       nombre del modelo
    // $recordset->ids;             [1,2,3]
    // $recordset->prop_name;       propiedad del modelo
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
            return $this->hiddens[$key] ?? null;
        }

        return $this->backbone->getProperties($key);
    }

    public function __set ($key, $value) {
        if (str_starts_with($key, '$'))
            $this->hiddens[$key] = $value;
        else throw new RecordException(
            "cant set property '$key' on recordset,
            use update() method instead"
        );
    }

    public function __call ($key, $args) {
        $skey = '@'.$key;
        if (!array_key_exists($skey, $this->methods)) {
            $method = $this->backbone->getMethods($skey);
            if (!$this->methods[$skey] = $method) {
                throw new RecordException("undefined method $key");
            }
        }

        $r = $this->methods[$skey]->call($args, $this);
        unset($this->methods[$skey]);
        return $r;
    }

    public function __toString () { 
        return Json::encode($this->ids); 
    }

    public function __debugInfo () : array {
        return $this->toArray();
    }

    /* -= methods =- */

    // asegura que solo se agreguen registros válidos
    public function offsetSet($key, $value): void {
        if (!$value instanceof Record) {
            throw new \InvalidArgumentException(
                'only Record object instances are allowed on RecordSet'
            );
        }

        $id = $value->id;
        $exists = array_search($id, $this->ids, true);

        if ($id !== '__newid__' && $exists !== false) {
            $key = $exists;
        }

        $value->recordset($this);
        parent::offsetSet($key, $value);
    }

    // convierte el objeto a un arreglo
    public function toArray (): array {
        $debug = [];
        if ($this->count() == 0) return $debug;
        $debug[] = array_map(function ($i) {
            return $i->toArray();
        }, (array) $this);
        return $debug;
    }

    // devuelve una lista con los valores de una propiedad
    // los campos 1n y nm, devuelven un recordset huerfano
    public function map ($prop_name) {
        $prop = $this->backbone->getProperties($prop_name);
        if (!$prop)
            throw new RecordException(
                "property '$prop_name' undefined"
            );

        if ($prop->type == 'n1') {
            $newset = new RecordSet($prop->target_model);
            foreach ((array) $this as $record) {
                $record = $record->{$prop_name};
                $newset[] = $record;
            }
            return $newset;
        }

        if (in_array($prop->type, ['1n','nm'])) {
            $newset = new RecordSet($prop->target_model);
            foreach ((array) $this as $record) {
                $records = $record->{$prop_name};
                foreach ($records as $r) {
                    if (!in_array($r->id, $newset->ids)) {
                        $newset[] = $r;
                    }
                }
            }
            return $newset;
        }

        return array_map(function ($i) use ($prop_name) {
            return $i->{$prop_name};
        }, (array) $this);
    }

    // quita registros del recordset según una función de filtro
    public function flush (?callable $fn = null, $orphan = false): self {
        $fn = $fn ?: fn() => true;

        foreach ((array) $this as $k => $record) {
            if ($fn($record, $k)) { delete($this, $k); }
        }

        if ($orphan) {
            $this->{'$parent_record'} = null;
            $this->{'$parent_property'} = null;
        }

        return $this;
    }

    // filtra registros del recordset según una función de filtro
    public function filter (callable $fn, $orphan = true): RecordSet {
        $model = $this->{'@name'};
        $newset = new RecordSet($model);
        $newset->{'$filtering_recordset_origin'} = $this;

        if (!$orphan) {
            $newset->{'$parent_record'} = $this->{'$parent_record'};
            $newset->{'$parent_property'} = $this->{'$parent_property'};
        }

        foreach ((array) $this as $k => $record) {
            if ($fn($record, $k)) { $newset[] = $record; }
        }
        return $newset;
    }

    /* -= exec db query methods =- */

    // ejecuta una sentencia preparada con parámetros
    public function statement (string $query, array $params = []) {
        // ejecuta sentencia preparada con parámetros
        if (!$query) return false;
        $stmt = $this->database->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    // ejecuta una o más consultas sql
    public function query ($query) {
        // ejecuta una consulta simple o múltiple
        if (!$query) return false;
        $query = (array) $query;
        foreach ($query as $q)
            if ($q && is_string($q)) $this->database->exec($q);
    }

    // verifica la existencia de registros según una consulta
    public function exists (string $query) {
        // este método recibe consultas que implementan count(*)
        if (!$query) return false;
        $stmt = $this->database->query($query);
        $row = $stmt->fetch(\PDO::FETCH_NUM);
        return (bool) $row[0];
    }

    /* -= dml methods =- */

    // inserta uno o más registros en la base de datos
    public function insert (...$inserts) {
        $this->{'$inserting'} = true;

        $fields = []; // campos a insertar
        $to_insert = []; // valores a insertar en db
        $parent = $this->{'$parent_record'}; // padre
        $property = $this->{'$parent_property'}; // propiedad del padre
        $children = []; // almacena los modelos relacionados
        $props = $this->{'@properties'}; // propiedades del modelo
        $table = $this->{'@name'}; // nombre de la tabla
        $x = $this->count(); // el índice para agregar a la pila
        
        $this->{'$index_before_insert'} = $x;

        foreach ($inserts as $i => $insert) {
            if ($parent and $property->type == '1n') {
                // se fuerza la relación con el padre, si el padre aún no
                // ha sido insertado, se agrega un marcador '__newid__'
                $key = $property->target_property;
                $insert[$key] = $parent;
            }

            if ($delegate = $this->{'@delegate'}) {
                // de existir un delegado, se inserta primero
                if (!array_key_exists($delegate, $insert)) {
                    $prop = $this->{$delegate};

                    $insert[$delegate] = Record::add(
                        $prop->target_model,
                        $insert
                    );
                }
            }

            $this[$x] = $record = new Record([]);
            
            foreach ($props as $prop) {
                $key = $prop->name;

                // asegura valores por defecto, y relaciones
                $insert[$key] = $insert[$key] ?? null;
                $insert[$key] = $prop->ensureValue($insert[$key], $record);
                
                $record->raw($key, $insert[$key]);

                // si tiene hijos, se guardan en un arreglo para su post procesamiento
                $has_children = in_array($prop->type, ['1n','nm']);
                $has_children = $has_children && $insert[$key] && $insert[$key]->count();
                if ($has_children) $children[] = $insert[$key];
            }

            foreach ($props as $prop) {
                $key = $prop->name;

                if (!$prop->store) continue;
                if (!$i) $fields[] = $prop; // para un $i == 0

                // asegura el valor y su tipo de dato a insertar en db
                // y si es que tiene configurado, su valor computado 'store'
                $to_insert[$x][] = $prop->ensureStoredValue($insert[$key], $record);
            }
            $x++;
        }

        $this->{'$index_after_insert'} = $x-1;

        [$query, $params] = Connector::statementInsert([
            'table' => $this->{'@name'},
            'properties' => $fields, 
            'values' => $to_insert
        ]);
        
        if ($parent && $parent->{'$inserting'}) {
            $this->{'$save_insert_query'} = $query;
            $this->{'$save_insert_params'} = $params;
            $this->{'$save_insert_children'} = $children;
        } else $this->__insert($query, $params, $children);
        
        $this->{'$inserting'} = false;
        return $this;
    }

    // realiza la inserción de registros en la base de datos
    public function __insert ($query = false, $params = false, $children = []) {
        $query = $query ?: $this->{'$save_insert_query'};
        $params = $params ?: $this->{'$save_insert_params'};
        $children = $children ?: $this->{'$save_insert_children'};
        $parent = $this->{'$parent_record'};
        $property = $this->{'$parent_property'};
        $inserted_ids = [];

        // el marcador '__newid__' colocalo al inicio
        // es reemplazado por el id del padre insertado
        if ($parent) {
            $params = array_map(function ($i) use ($parent) {
                return $i === '__newid__' ? $parent->id : $i;
            }, $params);
        }

        $this->statement($query, $params);

        // calcular y rellenar los ids insertados
        $ids = Connector::calcInsertedIds([
            'current' => $this->database->lastInsertId(),
            'first' => $this->{'$index_before_insert'},
            'last' => $this->{'$index_after_insert'}
        ]);
        foreach ($ids as $i => $new_id) {
            $this[$i]->raw('id', $new_id);
            $inserted_ids[] = $new_id;
        }

        // con el padre insertado, procesa los hijos
        if ($children) {
            foreach ($children as $child) {
                if ($child->{'$save_insert_query'})
                    $child->__insert();
                if ($child->{'$save_select_ids'})
                    $child->__select();
            }
        }

        // procesados lo hijos, actualiza relaciones nm
        if ($parent && $property->type == 'nm') {
            $this->query(Connector::queryInsertNm([
                'property' => $property, 
                'n_record' => $parent, 
                'm_set' => $inserted_ids
            ]));
        }

        // si la inserción es precedida de una actualización
        // e: $record->update([ 'nm_prop' => [ [], [] ] ]);
        // este ejemplo inicia como una actualización
        // pero los elementos de 'nm_prop' son inserciones
        if ($parent && $parent->{'$updating'}) {
            $this->{'$last_inserted_ids'} = $inserted_ids;
            $this->__clearRelation($inserted_ids, 'insert');
        }

        // limpia variables de inserción
        $this->{'$index_before_insert'} = null;
        $this->{'$index_after_insert'} = null;
        $this->{'$save_insert_query'} = null;
        $this->{'$save_insert_params'} = null;
        $this->{'$save_insert_children'} = null;
    }

    // elimina relaciones 1n y nm no incluidas en el arreglo de ids
    public function __clearRelation($exclude_ids, $mode='') {
        $parent = $this->{'$parent_record'};
        $property = $this->{'$parent_property'};
        $table = $this->{'@name'};
        $last_ids = [];

        if ($mode == 'insert') {
            $last_ids = $this->{'$last_selected_ids'} ?? [];
            $exclude_ids = array_merge($exclude_ids, $last_ids);
            $this->{'$last_selected_ids'} = null;
        }

        if ($mode == 'select') {
            $last_ids = $this->{'$last_inserted_ids'} ?? [];
            $exclude_ids = array_merge($exclude_ids, $last_ids);
            $this->{'$last_inserted_ids'} = null;
        }

        if ($property->type == '1n') {
            // intentar dejar huerfanos los registros
            $query = Connector::queryOrphan1n([
                'table' => $table,
                'property' => $property, 
                'parent_id' => $parent, 
                'exclude_ids' => $exclude_ids
            ]);
            try { $this->query($query); }
            catch (\PDOException $e) {
                // si no se logra dejar huerfanos
                // se procede a eliminar la relación
                $query = Connector::queryClear1n([
                    'table' => $table,
                    'property' => $property, 
                    'parent_id' => $parent, 
                    'exclude_ids' => $exclude_ids
                ]);
                $this->query($query);
            }
        }

        if ($property->type == 'nm') {
            $this->query(Connector::queryClearNm([
                'property' => $property,
                'parent_id' => $parent,
                'exclude_ids' => $exclude_ids
            ]));
        }
    }

    // captura registro de la base de datos y lo carga en el recordset
    public function select ($where = null, $order = [], $limit = []) {
        $parent = $this->{'$parent_record'};
        $property = $this->{'$parent_property'};
        $table = $this->{'@name'};
        $selected_ids = [];

        // esto evita relacionar todos los elementos a un padre
        // e: $record->1n_prop->select();
        // esta acción ataría todos los registros 1n_prop al padre
        if ($parent && !$where)
            throw new RecordException(
                "cannot perform select() on related recordset
                without where condition"
            );
        
        // si el id == '__newid__', se agrega un registro vacio
        if ($where === '__newid__') {
            $this[] = new Record([]);
            return $this;
        }

        if ($where instanceof Record) {
            $where = $where->id;
        }
        
        // mapea condiciones cortas, ejemplos:
        // $rs->select(1) => ['id:=' => 1]
        // $rs->select('juan') => ['name:=' => 'juan']
        // $rs->select([1,2,3]) => ['id:in' => [1,2,3]]
        if ($where !== null && !is_array($where))
            if (is_numeric($where))
                $where = ['id:=' => intval($where)]; 
            else
                $where = ['name:=' => $where];
        elseif ($where !== null && !is_assoc($where))
            $where = ['id:in' => $where];

        [$query, $params] = Connector::statementSelect([
            'table' => $table,
            'where' => $where, 
            'order' => $order, 
            'offset' => $limit
        ]);

        $stmt = $this->statement($query, $params);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this[] = new Record($row);
            $selected_ids[] = (int) $row['id'];
        }
        
        if ($parent && $parent->{'$inserting'})
            $this->{'$save_select_ids'} = $selected_ids;
        else $this->__select($selected_ids);

        return $this;
    }

    // si la seleccion tiene un padre, procesa las relaciones
    public function __select ($ids=[]) {
        $parent = $this->{'$parent_record'};
        $property = $this->{'$parent_property'};
        $selected_ids = $ids ?: $this->{'$save_select_ids'};
        $table = $this->{'@name'};
        
        // sin padre no hay relaciones a procesar
        if (!$parent) return;

        if ($property->type == '1n') {
            $this->update([ 
                $property->target_property => $parent->id 
            ]);
        }

        if ($property->type == 'nm') {
            $this->query(Connector::queryInsertNm([
                'property' => $property, 
                'n_record' => $parent, 
                'm_set' => $selected_ids
            ]));
        }

        if ($parent && $parent->{'$updating'}) {
            $this->{'$last_selected_ids'} = $selected_ids;
            $this->__clearRelation($selected_ids, 'select');
        }

        $this->{'$save_select_ids'} = false;
    }

    // prepara e inicia el proceso de actualizacion en la base de datos
    public function update ($update, ?Record $record = null) {
        $records = $record ? [$record] : (array) $this;
        if (!$update || !count($records)) return $this;

        $props = $this->{'@properties'};
        $this->{'$updating'} = true;

        if ($delegate = $this->{'@delegate'}) {
            if ($record) {
                $record->{$delegate}->update($update);
            } else {
                // de existir un delegado, se actualizan primero
                $map = $this->map($delegate);
                $map->update($update);
            }
        }

        foreach ($records as $r) {
            foreach ($props as $prop) {
                $key = $prop->name;
                if (!array_key_exists($key, $update)) continue;

                // asegura valores por defecto, y relaciones
                $value = $prop->ensureValue($update[$key], $r);
                $r->raw($key, $value);
            }
        }

        // si alguna propiedad es computada existe la 
        // posibilidad que los valores varien en cada registro
        $has_computed = array_any($props, function ($prop) use ($update) {
            return array_key_exists($prop->name, $update) && 
                is_string($prop->store);
        });
 
        if ($has_computed) {
            // actualiza cada registro individualmente
            foreach ($records as $r) {
                $this->__update(array_keys($update), [$r], $props);
            }
        } else {
            // actualiza todos los registros en una sola sentencia
            $this->__update(array_keys($update), $records, $props);
        }

        $this->{'$updating'} = false;

        return $this;
    }

    // ejecuta sentencias de actualización en la base de datos
    public function __update ($update_keys, $records, $props) {
        $fields = [];
        $to_update = [];
        $table = $this->{'@name'};

        foreach ($props as $prop) {
            $key = $prop->name;
            if (!in_array($key, $update_keys)) continue;
            if (!$prop->store) continue;

            $fields[] = $prop;
            $value = $records[0]->raw($key);
            $to_update[] = $prop->ensureStoredValue($value, $records[0]);
        }

        if ($fields) {
            [$query, $params] = Connector::statementUpdate([
                'table' => $table, 
                'properties' => $fields, 
                'values' => $to_update, 
                'set_ids' => $records
            ]);

            $this->statement($query, $params);
        }
    }

    // elimina los registros capturados de base de datos
    public function delete (?Record $record = null) {
        $records = $record ? [$record] : (array) $this;
        if (!count($records)) return;

        $this->query(Connector::queryDelete([
            'table' => $this->{'@name'}, 
            'set_ids' => $records
        ]));

        // si es un filtrado, limpieza del recordset origen
        //?NOTE: no se considera el caso de múltiples niveles de filtrado
        if ($origin = $this->{'$filtering_recordset_origin'}) {
            $origin->flush(fn($r) => in_array($r, $records));
        }
        // limpieza del recordset actual
        $this->flush(fn($r) => in_array($r, $records));

        return $this;
    }

    // crea o actualiza la estructura del modelo en la base de datos
    // este método debe ser llamdo desde el statico bind()
    public function bindModel () {
        // TODO: revisar si este commit es correcto en este punto
        $this->database->commit();
        $this->backbone->loadSkeleton();
        
        self::$binded_models[] = $this->{'@name'};

        $queries = [];
        $table = $this->{'@name'};
        $unique = $this->{'@unique'} ?: [];
        $delegate = $this->{'@delegate'};
        $properties = $this->backbone->getProperties();

        $exists_table = Connector::existsTable(['table' => $table]);
        $exists_table = $this->exists($exists_table);

        if (!$exists_table) {
            $props = []; $fks = [];

            // definidir las columnas del modelo y relaciones
            foreach ($properties as $prop) {
                if (in_array($prop->type, ['1n','nm','n1'])) {
                    self::bind($prop->target_model);
                }

                if ($prop->store) {
                    $props[] = $prop;
                }

                if ($prop->type == 'n1') $fks[] = $prop;
            }

            // query para creacion de tabla
            $queries[] = Connector::queryCreateTable([
                'table' => $table,
                'uniques' => $unique,
                'properties' => $props,
                'foreign_keys' => $fks
            ]);
        } else {
            foreach ($properties as $property) {
                if (!$property->store) continue;

                $exists_column = Connector::existsColumnTable([
                    'table' => $table,
                    'property' => $property
                ]);
                
                if ($this->exists($exists_column)) {
                    $queries[] = Connector::queryAlterColumn([
                        'table' => $table,
                        'property' => $property
                    ]);
                } else {
                    $queries[] = Connector::queryAddColumn([
                        'table' => $table,
                        'property' => $property
                    ]);

                    if ($property->target && $property->type == 'n1') {
                        $queries[] = Connector::queryAddForeignKey([
                            'table' => $table,
                            'property' => $property
                        ]);
                    }
                }
            }
        }

        // crear tablas intermedias para relaciones nm
        foreach ($properties as $prop) {
            if ($prop->type == 'nm') {
                $queries[] = Connector::queryCreateNmTable([
                    'property' => $prop,
                    'table' => $table
                ]);
            }
        }

        // genera triggers para delegados
        if ($delegate) {
            $delegate = $this->backbone->getProperties($delegate);
            if ($delegate && strtoupper($delegate->ondelete) == 'CASCADE') {
                $queries[] = Connector::queryCreateDelegateTriggers([
                    'table' => $table,
                    'delegate' => $delegate
                ]);
            }
        }
        
        $this->query($queries);
    }

    // --== shorcouts ==--

    private static $binded_models = [];
    // enlaza la estructura del modelo en la base de datos y devuelve el recordset
    public static function bind (string $model_name): RecordSet {
        $rs = new self($model_name);

        if (!in_array($model_name, self::$binded_models)) {
            self::$binded_models[] = $model_name;
            $rs->bindModel();
        }

        return $rs;
    }

    // resetea el listado de modelos enlazados
    public static function reset ($keys): void {
        if (!$keys) {
            self::$binded_models = [];
        } else {
            $keys = (array) $keys;
            foreach ($keys as $k) {
                $index = array_search($k, self::$binded_models);
                if ($index !== false) {
                    unset(self::$binded_models[$index]);
                }
            }
        }
    }
}