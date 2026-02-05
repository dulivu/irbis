<?php
namespace Irbis\Orm;

use Irbis\Interfaces\QueryInterface;


/**
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class BuilderSqlite implements QueryInterface {
    public function queryInit() {
        return "PRAGMA foreign_keys = ON;";
    }

    public function calcSearchArray ($table, array $arr) {
        if (!$arr) return ['', []];
        $union = 'and'; $q = []; $w = [];
    
        foreach ($arr as $k => $v) {
            if (gettype($k) == 'integer') {
                if (is_array($v)) {
                    $pp = $this->calcSearchArray($table, $v);
                    $w[] = $pp[0];
                    $q = array_merge($q, $pp[1]);
                } else $union = $v;
            } else {
                $k = explode(":", $k); 
                $k[1] = $k[1] ?? '=';
    
                if ($k[1] == 'between') {
                    $q[] = $v[0];
                    $q[] = $v[1];
                    $w[] = "\"$table\".\"$k[0]\" $k[1] ? and ?";
                } elseif ($k[1] == 'not in' || $k[1] == 'in') {
                    foreach ($v as $index => $value) $q[] = $value;
                    $w[] = "\"$table\".\"$k[0]\" $k[1] (".implode(', ', array_fill(0, count($v), '?')).")";
                } else {
                    $q[] = $v;
                    $w[] = "\"$table\".\"$k[0]\" $k[1] ?";
                }
            }
        }
    
        return ["(".implode(" $union ", $w).")", $q];
    }

    // @params: current, first, last
    public function calcInsertedIds ($options) {
        extract($options);

        $arr = [];
        for ($i = $last; $i >= $first; $i--) {
            $arr[$i] = $current--;
        }
        return $arr;
    }

    // @params: table, properties, values
    public function statementInsert ($options) {
        extract($options);

        $fields = array_map(function ($item) {
            return "\"{$item->name}\"";
        }, $properties);

        $quotes = array_map(function ($item) {
            return implode(", ", array_fill(0, count($item), '?'));
        }, $values);

        $query = "INSERT INTO \"{$table}\"".
            " (".implode(", ", $fields).") VALUES ".
            " (".implode("), (", $quotes).")";

        $vals = array_reduce($values, function ($carry, $item) {
            return array_merge($carry, $item);
        }, []);

        return [$query, $vals];
    }

    // @params: property, n_record, m_set
    public function queryInsertNm ($options) {
        extract($options);

        $m_set = $m_set instanceof Record ? [$m_set] : $m_set;

        $ins = array_map(function ($i) use ($n_record, $property) {
            return ($property->nm1 == $property->name) ? 
                "$i, {$n_record->id}": 
                "{$n_record->id}, $i";
        }, $m_set);
        
        $query = "INSERT OR IGNORE INTO \"{$property->nm_string}\" ".
            "(\"{$property->nm1}\", \"{$property->nm2}\") ".
            "VALUES (".implode("), (", $ins).")";
        
        return $query;
    }

    // @params: table, where, order, offset
    public function statementSelect ($options) {
        extract($options);

        // forzar que 'order' sea un arreglo, puede recibir un string separado por comas
        $order = is_string($order) ? explode(",", $order) : $order; $self = $this;
        $order = array_map('trim', $order) ?: ['id'];

        // forzar que 'offset' sea un arreglo, puede recibir un string "offset-limit" o "limit"
        $offset = is_string($offset) ? explode("-", $offset) : $offset;
        $offset = array_map('intval', $offset);

        $query = "SELECT \"{$table}\".* FROM \"{$table}\"";
        [$where, $values] = $this->calcSearchArray($table, $where ?: []);
        $query .= 
            ($where ? " WHERE {$where}" : "").
            ($order ? " ORDER BY ".implode(", ", $order) : "").
            ($offset ? " LIMIT ".$offset[0].", ".$offset[1] : "");

        return [$query, $values];
    }

    // @params: table, property, parent_id, ?exclude_ids
    public function queryOrphan1n ($options) {
        extract($options);

        $exclude_ids = $exclude_ids ?? [];

        $parent_id = $parent_id instanceof Record ? $parent_id->id : $parent_id;
        $exclude_ids = $exclude_ids instanceof RecordSet ? $exclude_ids->ids : $exclude_ids;

        $query = "UPDATE \"{$table}\" set \"{$property->target_property}\" = null 
            WHERE \"{$property->target_property}\" = {$parent_id} AND id not in (".
            implode(", ", $exclude_ids).")";
        
        return $query;
    }

    // @params: table, property, parent_id, ?exclude_ids
    public function queryClear1n ($options) {
        extract($options);

        $exclude_ids = $exclude_ids ?? [];

        $parent_id = $parent_id instanceof Record ? $parent_id->id : $parent_id;
        $exclude_ids = $exclude_ids instanceof RecordSet ? $exclude_ids->ids : $exclude_ids;

        $query = "DELETE FROM \"{$table}\" 
            WHERE \"{$property->target_property}\" = {$parent_id} 
            AND id not in (".implode(", ", $exclude_ids).")";
        
        return $query;
    }

    // @params: property, parent_id, ?exclude_ids
    public function queryClearNm ($options) {
        extract($options);

        $exclude_ids = $exclude_ids ?? [];

        $parent_id = $parent_id instanceof Record ? $parent_id->id : $parent_id;
        $exclude_ids = $exclude_ids instanceof RecordSet ? $exclude_ids->ids : $exclude_ids;
        $field = $property->nm1 == $property->name ? $property->nm2 : $property->nm1;
        $o_field = $property->nm1 == $property->name ? $property->nm1 : $property->nm2;

        $query = "DELETE FROM \"{$property->nm_string}\" 
            WHERE \"{$field}\" = {$parent_id}
                AND \"{$o_field}\" not in (".implode(", ", $exclude_ids).")";

        return $query;
    }

    // @params: property, parent_id
    public function querySelectNm($options) {
        extract($options);

        $parent_id = $parent_id instanceof Record ? $parent_id->id : $parent_id;

        $query = "SELECT \"{$property->target_model}\".* 
            FROM \"{$property->target_model}\"
            INNER JOIN \"{$property->nm_string}\" 
                ON \"{$property->nm_string}\".\"{$property->name}\" = \"{$property->target_model}\".\"id\"
            WHERE \"{$property->nm_string}\".\"{$property->target_property}\" = {$parent_id};";
        
        return $query;
    }

    // @params: table, properties, values, set_ids
    public function statementUpdate ($options) {
        extract($options);

        $fields = array_map(function ($item) {
            return "\"{$item->name}\" = ?";
        }, $properties);

        $set_ids = $set_ids instanceof Record ? [$set_ids] : $set_ids;

        $query = "UPDATE \"{$table}\" SET ".implode(", ", $fields).
            " WHERE id in (".implode(", ", $set_ids).")";

        return [$query, $values];
    }

    // @params: table, ids
    public function queryDelete ($options) {
        extract($options);

        $set_ids = $set_ids instanceof Record ? [$set_ids] : $set_ids;

        $query = "DELETE FROM \"{$table}\" 
            WHERE id in (".implode(", ", $set_ids).");";
        return $query;
    }

    /* -= sentencias para enlazar modelos a tablas =- */

    public function existsTable ($options) {
        extract($options);

        $query = "SELECT count(*) FROM sqlite_master 
            WHERE type='table' AND name=\"{$table}\" LIMIT 1;";

        return $query;
    }

    public function columnDefinition ($property) {
        $column = "\"{$property->name}\"";
        if (in_array($property->type, ['1n','nm']))
            $column .= " TEXT";
        else if ($property->type == 'n1')
            $column .= " INTEGER";
        else
            $column .= " {$property->type}"
                .($property->length ? "($property->length)" : '')
                .($property->required ? ' NOT NULL' : '')
                .($property->oSQL ? " {$property->oSQL}" : '');
        
        return $column;
    }

    public function queryCreateTable ($options) {
        // @params: table, uniques, properties, foreign_keys
        extract($options);

        $columns = array_map(function ($prop) {
            return $this->columnDefinition($prop);
        }, $properties);

        if ($uniques && is_string($uniques)) {
            $uniques = array_map('trim', explode(",", $uniques));
        }

        $uniques = implode(", ", array_map(function($u) {
            return "\"$u\"";
        }, $uniques));

        $uniques = $uniques ? ",\nUNIQUE ($uniques)" : '';

        $fks = array_map(function ($fk) use ($table) {
            return "FOREIGN KEY ({$fk->name})
                REFERENCES {$fk->target_model} (id)
                ON DELETE {$fk->ondelete}
                ON UPDATE {$fk->onupdate}";
        }, $foreign_keys);

        $fks = $fks ? ",\n".implode(",\n", $fks) : '';
        
        $query = "CREATE TABLE IF NOT EXISTS \"{$table}\" (
            \"id\" INTEGER PRIMARY KEY,
            ".implode(",\n", $columns).
            "{$uniques}".
            "{$fks}
        );";
        
        return $query;
    }

    public function queryCreateDelegateTriggers ($options) {
        extract($options);

        $query = "CREATE TRIGGER IF NOT EXISTS \"{$table}_delete_delegate\"
            AFTER DELETE ON \"{$table}\"
            FOR EACH ROW
            BEGIN
                DELETE FROM \"{$delegate->target_model}\"
                WHERE \"id\" = OLD.\"{$delegate->name}\";
            END;";
        
        return $query;
    }

    public function queryCreateNmTable ($options) {
        extract($options);

        $table_order = ($property->nm1 == $property->name) ? 
            [$property->target_model, $table] :
            [$table, $property->target_model];

        $query = "CREATE TABLE IF NOT EXISTS \"{$property->nm_string}\" (
            \"{$property->nm1}\" INTEGER NOT NULL,
            \"{$property->nm2}\" INTEGER NOT NULL,
            PRIMARY KEY (\"{$property->nm1}\", \"{$property->nm2}\"),
            FOREIGN KEY (\"{$property->nm1}\")
                REFERENCES \"{$table_order[0]}\" (id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            FOREIGN KEY (\"{$property->nm2}\")
                REFERENCES \"{$table_order[1]}\" (id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        );";
        
        return $query;
    }

    public function existsColumnTable ($options) {
        extract($options);
        
        $query = "SELECT count(*) FROM
            pragma_table_info('{$table}')
            WHERE name='{$property->name}' LIMIT 1;";

        return $query;
    }

    public function queryAlterColumn ($options) {
        // sqlite no soporta alterar columnas
        return '';
    }

    public function queryAddColumn ($options) {
        // @params property, table
        extract($options);

        $query = "ALTER TABLE \"{$table}\" ADD COLUMN ".
            $this->columnDefinition($property).";";

        return $query;
    }

    public function queryAddForeignKey($property) {
        // sqlite no soporta agregar claves foráneas
        return '';
    }

    /* -= sentencias para rebuild de tabla =- */
    /* -= finalmente no se implementan, problemas al hacer rebuild =- */

    public function queryPragmaFK ($options) {
        extract($options);
        if (isset($options['enable']))
            return "PRAGMA foreign_keys = ON;";
        if (isset($options['disable']))
            return "PRAGMA foreign_keys = OFF;";
    }

    public function queryRenameTable ($options) {
        extract($options);

        $query = "ALTER TABLE \"{$table}\" RENAME TO \"{$table}_old\";";
        
        return $query;
    }

    public function queryCopyData ($options) {
        extract($options);

        $query = "INSERT INTO \"{$table}\" (".implode(", ", $columns).")
            SELECT ".implode(", ", $columns)." FROM \"{$table}_old\";";
        
        return $query;
    }

    public function queryDropCopy ($options) {
        extract($options);

        $query = "DROP TABLE IF EXISTS \"{$table}_old\";";
        
        return $query;
    }
}