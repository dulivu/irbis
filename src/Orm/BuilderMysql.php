<?php
namespace Irbis\Orm;

use Irbis\Interfaces\QueryInterface;


/**
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class BuilderMysql implements QueryInterface {
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

    public function calcInsertedIds ($current, $first_id, $last_id) {
        $arr = [];
        for ($i = $first_id; $i < $last_id; $i++) {
            $arr[$i] = $current++;
        }
        return $arr;
    }

    public function statementInsert ($table, $fields, $to_insert) {
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

    /* -= sentencias para enlazar modelos a tablas =- */

    public function existsTable ($options) {
        extract($options);

        $query = "SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
                AND table_name = '{$table}' LIMIT 1;";

        return $query;
    }

    public function columnDefinition ($options) {
        extract($options);

        $column = "`{$property->name}`";
        if (in_array($property->type, ['1n','nm']))
            $column .= " JSON";
        else if ($property->type == 'n1')
            $column .= " INT UNSIGNED";
        else
            $column .= " {$property->type}"
                .($property->length ? "($property->length)" : '')
                .($property->required ? ' NOT NULL' : '')
                .($property->oSQL ? " {$property->oSQL}" : '');
        
        return $column;
    }

    public function queryCreateTable ($options) {
        extract($options);

        $fks = array_map(function ($fk) use ($table) {
            return "FOREIGN KEY (`{$fk->name}`)
                REFERENCES `{$fk->target_model}` (`id`)
                ON DELETE {$fk->ondelete}
                ON UPDATE {$fk->onupdate}";
        }, $foreign_keys);

        $uniques = implode(", ", array_map(function($u) {
            return "`$u`";
        }, $uniques));

        $uniques = $uniques ? "UNIQUE KEY `uk_{$table}` ($uniques)," : '';

        $query = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            ".implode(",\n", $columns).",
            {$uniques}
            ".implode(",\n", $fks)."
        );";
        
        return $query;
    }

    private function queryCreateNmTable ($options) {
        extract($options);

        $table_order = ($property->nm1 == $property->name) ? 
            [$property->target_model, $this->name] :
            [$this->name, $property->target_model];

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

        return $query;
    }

    public function existsColumnTable ($options) {
        extract($options);
        
        $query = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '{$table}' 
                AND COLUMN_NAME = '{$property->name}' LIMIT 1;";

        return $query;
    }

    public function queryAlterColumn ($options) {
        extract($options);

        $query = "ALTER TABLE `{$table}` 
            MODIFY COLUMN $definition;";

        return $query;
    }

    public function queryAddColumn ($options) {
        extract($options);

        $query = "ALTER TABLE `{$table}` 
            ADD COLUMN $definition;";

        return $query;
    }

    public function queryAddForeignKey($options) {
        extract($options);

        $query = "ALTER TABLE `{$table}` 
            ADD CONSTRAINT `fk_{$table}_{$property->name}` 
                FOREIGN KEY (`{$property->name}`) 
                REFERENCES `{$property->target_model}`(`id`)
                ON DELETE {$property->ondelete}
                ON UPDATE {$property->onupdate}";

        return $query;
    }
}