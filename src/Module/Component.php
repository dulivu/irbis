<?php
namespace Irbis\Module;


abstract class Component 
{
    public readonly Controller $controller;

    public function __construct (Controller $controller) 
    {
        $this->controller = $controller;
    }
}