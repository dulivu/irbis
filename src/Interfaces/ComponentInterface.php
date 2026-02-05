<?php
namespace Irbis\Interfaces;
use Irbis\Controller;


interface ComponentInterface {
    public function setController(Controller $controller);
}