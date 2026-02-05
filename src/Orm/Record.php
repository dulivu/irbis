<?php
namespace Irbis\Orm;

use Irbis\Exceptions\RecordException;

/**
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class Record {

    private ?RecordSet $recordset;
    private $values = [];
    private $previous = [];
    private $methods = [];

    /* -= magic methods =- */

    public function __construct (array $raw) {
        $this->values = $raw;
    }

    public function __isset ($key) {
        return isset($this->recordset->{$key});
    }

    public function __get ($key) {
        if ($key == 'id') return isset($this->values['id']) ? intval($this->values['id']) : '__newid__';
        if ($key == 'ids') return [isset($this->values['id']) ? intval($this->values['id']) : '__newid__'];
        if (str_starts_with($key, '@')) return $this->recordset->{$key};
        
        if (str_starts_with($key, '$')) return $this->recordset->{$key};
        if (str_starts_with($key, '-')) return $this->previous[substr($key, 1)] ?? null;
        
        $property = $this->recordset->{$key};
        $value = $this->values[$key] ?? null;
        if (!$property) {
            if ($delegate = $this->{'@delegate'}) {
                return $this->{$delegate}->{$key};
            }
            throw new RecordException("property $key not defined");
        }
        return $this->values[$key] = $property->ensureRetrievedValue($value, $this);
    }

    public function __set ($key, $value) {
        if (str_starts_with($key, '$')) 
            $this->recordset->{$key} = $value;
        else
            $this->update([$key => $value]);
    }

    public function __call ($key, $args) {
        $backbone = $this->{'@backbone'};
        if (!array_key_exists($key, $this->methods)) {
            if (!$this->methods[$key] = $backbone->getMethods($key)) {
                throw new \Exception("undefined method $key");
            }
        }

        $r = $this->methods[$key]->call($args, $this);
        unset($this->methods[$key]);
        return $r;
    }

    public function __toString () { 
        return "".$this->id;
    }

    public function __debugInfo () { 
        return $this->toArray();
    }

    /* -= methods =- */

    // convierte el objeto en un arreglo
    public function toArray ($max_deep = 0): array {
        $properties = $this->recordset->{'@properties'};
        $debug = []; $current_deep = 0; $max_deep = $max_deep < 0 ? 0 : $max_deep;
        foreach ($properties as $key => $property) {
            $debug[$key] = $this->values[$key] ?? null;
            if ($debug[$key] instanceof Record or $debug[$key] instanceof RecordSet) {
                if ($current_deep < $max_deep) {
                    $debug[$key] = $debug[$key]->toArray($max_deep - 1);
                } else {
                    $debug[$key] = $debug[$key] instanceof Record ? $debug[$key]->id: $debug[$key]->ids;
                }
                $current_deep++;
            }
        }
        return $debug;
    }

    // obtiene o asigna un valor crudo
    public function raw ($prop_name, $value = null) {
        if (is_array($prop_name)) {
            foreach ($prop_name as $k => $v)
                $this->raw($k, $v);
        } elseif ($value === null) {
            return $this->values[$prop_name] ?? null;
        } else {
            $this->previous[$prop_name] = $this->values[$prop_name] ?? null;
            $this->values[$prop_name] = $value;
        } return $this;
    }

    // asigna el recordset al que pertenece este record
    public function recordset (?RecordSet $newrecordset): void {
        $this->recordset = $newrecordset;
    }

    /* -= dml methods =- */

    // actualiza el registro en bd
    public function update ($update) {
        $this->recordset->update($update, $this);
        return $this;
    }

    // elimina el registro de bd
    public function delete () {
        $this->recordset->delete($this);
    }

    // --== shortcuts ==--

    // captura un solo registro de bd y devuelve un record
    public static function find ($model_name, $query, $order = []): ?Record {
        $rs = new RecordSet($model_name);
        $rs->select($query, $order, '0-1');
        return $rs->count() ? $rs[0] : null;
    }

    // agrega un registro de bd y devuelve un record
    public static function add ($model_name, $data): Record {
        $rs = new RecordSet($model_name);
        $rs->insert($data);
        return $rs[0];
    }
}