<?php
namespace Irbis\Terminal;

use Irbis\Server;
use Irbis\Exceptions\HttpException;
use Irbis\Controller as iController;

class Controller extends iController {
	public static $name         = 'cli';
    public static $routable     = true;
    public static $unpackage    = true;

    public $allow = [
        'help', 'uname', 'whoami',
        'clear', 'version', 'date', 'ls',
        'git', 'install', 'remove',
    ];

    /**
     * @route ?/
     */
    final public function index () {
        redirect('/cli');
    }

    /**
     * @route /cli
     */
    final public function webTerminal ($request, $response) {
        return '@cli/{view}.html';
    }

    /**
     * @route /Irbis/Terminal/assets/(:any)
     */
    final public function asset ($request, $response) {
        $path = $this->namespace('dir') . "assets/" . $request->path(0);
        if (!file_exists($path)) throw new HttpException(404);
        $response->header('Content-Length: ' . filesize($path));
        $response->header('Content-Type: ' . mime_type($path));
        $response->body(file_get_contents($path));
    }

    private function span ($text, $class = '') {
        $class = $class ? " class=\"$class\"" : '';
        return "<span{$class}>$text</span>";
    }

    /**
     * @verb POST
     * @route /cli/command
     */
    final public function command ($request, $response) {
        $cmd = trim($request->input('command') ?: '');

        if (!$cmd) return $this->span("Comando vacío", "error");

        if (str_contains($cmd, ';') || str_contains($cmd, '&&') || str_contains($cmd, '||')) {
            return $this->span("Comando no permitido", "error");
        }

        $scmd = explode(' ', $cmd);
        if (!in_array($scmd[0], $this->allow))
            return $this->span("Comando no reconocido", "error");
        
        $method_cmd = "command" . ucfirst($scmd[0]);
        
        try {
            if (method_exists($this, $method_cmd)) {
                return $this->{$method_cmd}($scmd, $cmd);
            } return $this->commandDefault($scmd, $cmd);
        } catch (\Throwable $e) {
            return $this->span($e->getMessage(), "error");
        }
    }

    private function commandVersion () {
        return "Irbis Framework v3.0";
    }

    private function commandHelp ($cmd) {
        // mostrar las caracteristicas de la aplicación
        if (count($cmd) > 1) {
            $app_name = $cmd[1];
            $server = Server::getInstance();
            $app = $server->buildController($app_name);
            return "- versión: " . $this->span($app::$version ?? 'n/a')
                . "<br> - autor: " . $this->span($app::$author ?? 'desconocido')
                . "<br> - dependencias: " . $this->span(implode(', ', $app::$depends ?? []) ?: 'ninguna')
                . "<br> - descripción: " . $this->span($app::$description ?? 'ninguna');
        }
    }

    private function commandInstall ($cmd) {
        $server = Server::getInstance();
        if (count($cmd) < 2)
            return $this->span("Debe especificar una aplicación", "error");
        $ctrl = $server->buildController($cmd[1]);
        $server->installApp($ctrl);
        return $this->span("Aplicación '{$ctrl::$name}' instalada correctamente", "success");
    }

    private function commandRemove ($cmd) {
        $server = Server::getInstance();
        if (count($cmd) < 2)
            return $this->span("Debe especificar un nombre de aplicación", "error");
        if ($cmd[1] == 'cli') {
            $server->setState('server.terminal', false);
            return $this->span("El terminal se ha desactivado correctamente", "warning");
        }
        $ctrl = $server->getController($cmd[1]);
        if (!$ctrl)
            return $this->span("La aplicación '{$cmd[1]}' no está instalada", "error");
        $server->uninstallApp($ctrl);
        return $this->span("Aplicación '{$cmd[1]}' removida correctamente", "warning");
    }

    private function commandDefault ($cmd, $full_cmd) {
        $server = Server::getInstance();
        // sobrecarga del comando: ls apps, para mostrar las aplicaciones disponibles
        if (count($cmd) > 1 && $cmd[0] == 'ls' && $cmd[1] == 'apps') {
            $output = $this->span("Aplicaciones disponibles:", "info");
            $iapps = $server->getState('apps') ?: [];
            $oapps = array_map(function ($app) use ($iapps) {
                $app_name = trim(str_replace(BASE_PATH, '', $app), '/');
                if (in_array($app_name, $iapps)) 
                    return "<br/> - " . $this->span($app_name, 'success');
                else
                    return "<br/> - " . $this->span($app_name);
            }, glob('*Apps/*', GLOB_ONLYDIR));

            return $output . implode('', $oapps);
        }
        return str_replace("\n", "<br>", shell_exec("$full_cmd 2>&1")) ?: '';
    }
}
