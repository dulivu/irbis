<?php
namespace Irbis\Orm;

use Irbis\Tools\Json;
use Irbis\Exceptions\RecordException;

/**
 * Representa a una propiedad del modelo
 * un campo de base de datos o un campo del modelo calculado
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version 	3.0
 */
class Property extends Member {
    public $name;
    public $label;
    public $type = null; // tipo de base de datos
    public $readonly = false;

    public $length = 0;
    public $required = false; // true, creará una sentencia sql NOT NULL
    public $default = null;
    public $index = null;
    public $ondelete = 'NO ACTION';
    public $onupdate = 'NO ACTION';
    public $oSQL = null; // sql adicional

    public $clonable = true; // TODO
    public $retrieve = null; // método para recuperar el valor
    public $store = true; // método para almacenar el valor

    // propiedades para relaciones, no se declaran directamente,
    // si no que la clase los calcula y administra
    public $target = null;
    public $target_model = null;
    public $target_property = null;
    public $nm_string; // Para relaciones muchos a muchos
    public $nm1; // primer modelo relacion en orden alfabético
    public $nm2; // segundo modelo relacion en orden alfabético

    public function __construct (string $name, array $define) {
        $this->name = $name;
        $this->label = snake_to_text($name);
        $this->define($define, false);
    }

    public function __debugInfo () { return ['Property' => $this->name]; }

    public function define (array $options, $redefine = true) {
        // el primer elemento (indice 0) determina el tipo de dato
        if (isset($options[0])) {
            if (!$this->type) 
                $this->type = $options[0];
            unset($options[0]);
        }

        // establecer los demás valores a el objeto
        // se recorre la opciones y se establecen el objeto
        foreach ($options as $k => $v) {
            $this->{$k} = $v;
        }

        // los tipos varchar por defecto tienen una 
        // longitud de 255 caracteres
        if ($this->type == 'varchar' && !$this->length) {
            $this->length = 255;
        }

        // los tipos relación muchos a 1 
        // se deben almacenar siempre
        if ($this->type == 'n1') {
            $this->store = true;
            $this->target_model = $this->target;
        }

        // los tipo relación muchos a muchos  o uno a muchos
        // se deben reinterpretar para recalcular otros atributos
        if (in_array($this->type, ['1n', 'nm'])) {
            // e: ['target' => 'orders(order)']
            $m = preg_split('/[()]+/', $this->target);
            $this->target_model = $m[0];
            $this->target_property = $m[1] ?? null;

            if (!$this->target_property) {
                throw new RecordException("target property not defined on field {$this->name}:{$this->type}");
            }

            // se ordena alfabeticamente para asegurar que
            // nm1 y nm2 valgan lo mismo en las propiedades de ambos modelos
            $r = [$this->name, $this->target_property]; sort($r);

            // este campo será de apoyo para crear la tabla intermedia
            // de las relaciones nm, muchos a muchos
            $this->nm_string = "nm_{$r[0]}_{$r[1]}";
            $this->nm1 = $r[0];
            $this->nm2 = $r[1];
            $this->store = false;
        }
    }

    public function ensureStoredValue ($value, Record $record) {
        if (is_bool($value))
            $value = (int) $value;
        elseif (is_array($value))
            $value = Json::encode($value);
        elseif ($value instanceof Record)
            $value = $value->id;
        elseif ($value instanceof RecordSet)
            $value = Json::encode($value->ids);

        $compute = $this->store;
        if (is_string($compute))
            $value = $record->{$compute}($value);
        return $value;
    }

    public function ensureRetrievedValue ($value, Record $record) {
        if ($this->target_model)
            $emptyRecordSet = new RecordSet($this->target_model);

        if ($this->type == 'n1' && is_numeric($value)) {
            $value = $emptyRecordSet->select($value);
            return $value[0] ?? null;
        }

        if ($this->type == '1n' && (!$value instanceof RecordSet)) {
            $value = $emptyRecordSet->select(["{$this->target_property}:=" => $record->id]);
            $value->{'$parent_record'} = $record;
            $value->{'$parent_property'} = $this;
        }

        if ($this->type == 'nm' && (!$value instanceof RecordSet)) {
            $value = $emptyRecordSet;
            $value->{'$parent_record'} = $record;
            $value->{'$parent_property'} = $this;
            $stmt = $value->statement(Connector::querySelectNm([
                'property' => $this,
                'parent_id' => $record,
            ]));
            while ($fetch = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $value[] = new Record($fetch);
            }
        }

        switch ($this->type) {
            case 'boolean': $value = (bool) $value; break;
            case 'int': 
            case 'integer':
            case 'tintyint':
            case 'smallint':
                $value = (int) $value; break;
            case 'float': 
            case 'decimal':
            case 'double':
                $value = (float) $value; break;
        }

        $compute = $this->retrieve;
        if ($compute)
            $value = $record->{$compute}($value);
        return $value;
    }

    public function ensureValue ($value, $parent_record) {
        if ($value === null) {
            if ($this->required !== false) {
                throw new RecordException($this->required !== true ? 
                    $this->required : "Se requiere un valor para '{$this->name}'");
            }
        }
        
        $value = $value === null ? $this->default : $value;
        if ($value && $this->type == 'n1') {
            $value = $this->ensureValueRecord($value);
        }
        if ($value && ($this->type == '1n' || $this->type == 'nm')) {
            $value = $this->ensureValueRecordSet($value, $parent_record);
        }
        return $value;
    }

    private function ensureValueRecord ($value) {
        if ($value instanceof Record) {
            if ($value->{'@name'} != $this->target_model) {
                throw new RecordException("recordset: modelo de referencia incompatible");
            }
            return $value;
        }

        $empty_rs = new RecordSet($this->target_model);
        if (is_assoc($value))
            $empty_rs->insert($value);
        else
            $empty_rs->select($value);
        if (count($empty_rs) == 0)
            throw new RecordException("No se encontró un valor para '{$this->name}', {$value}");
        return $empty_rs[0];
    }

    private function ensureValueRecordSet ($value, $parent_record) {
        if ($value instanceof RecordSet) {
            if ($value->{'@name'} != $this->target_model) {
                throw new RecordException("recordset: modelo de referencia incompatible");
            }
            $value->{'$parent_record'} = $parent_record;
            $value->{'$parent_property'} = $this;
            return $value;
        }

        $empty_rs = new RecordSet($this->target_model);
        $empty_rs->{'$parent_record'} = $parent_record;
        $empty_rs->{'$parent_property'} = $this;
        
        $arr = array_reduce($value, function ($carry, $item) {
            $carry[(is_assoc($item) ? 'i' : 's')][] = $item;
            return $carry;
        }, ['i' => [], 's' => []]);
        
        if ($arr['i']) $empty_rs->insert(...$arr['i']);
        if ($arr['s']) $empty_rs->select($arr['s']);
        return $empty_rs;
    }
}