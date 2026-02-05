<?php
namespace Irbis\Interfaces;

interface QueryInterface {
    /**
     * Convierte un arreglo de busqueda en parte de la sentencia SQL
     * devuelve un arreglo con dos valores, el primero es SQL
     * el segundo con los valores del arreglo
     *
     * @param array $arr
     *
     * @return array[string SQL, array values]
     */
    public function calcSearchArray ($table, array $arr);
    public function statementInsert ($options);
}