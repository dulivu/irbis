<?php
namespace Irbis\Traits;
use Irbis\Module\Controller;
use Irbis\Server;


trait Component {
    public readonly Controller $controller;
    public readonly Server $server;

    public function setController (?Controller $controller) {
        $this->controller = $controller;
        $this->server = $controller->server;
    }
}