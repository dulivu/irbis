<?php
namespace Irbis\Traits;
use Irbis\Controller;

/**
 * Componente de un controlador, clase que puede ser instanciada
 * y gestionada por un controlador, lazy singleton pattern.
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
trait Component {
    protected ?Controller $controller;

    public function setController (?Controller $controller) {
        $this->controller = $controller;
    }
}