<?php
namespace Irbis\Orm;

use Irbis\Interfaces\QueryInterface;


/**
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class BuilderPostgresql implements QueryInterface {
    public function prepareInsert ($fields, $to_insert) {
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
}