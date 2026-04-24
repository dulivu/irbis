<?php 
namespace Irbis;

include 'Tools/Constants.php';
include 'Tools/Functions.php';

// autocargador de clases
// no se cruza con vendor/autoload.php
irbis_loader();

use Exception;
use InvalidArgumentException;
use Closure;
use Throwable;
use Irbis\Http\Request;
use Irbis\Http\Response;
use Irbis\Traits\Singleton;
use Irbis\Traits\Events;
use Irbis\Exceptions\HttpException;
use Irbis\Interfaces\RendererInterface;
use Irbis\Interfaces\SessionInterface;
use Irbis\Interfaces\SetupInterface;
use Irbis\Interfaces\HooksInterface;

use Irbis\File\System as FileSystem;
use Irbis\Routing\Handler as HttpHandler;
use Irbis\Module\Handler as ControllerHandler;
use Irbis\File\Config as ConfigFile;
use Irbis\Terminal\Controller as TerminalController;


/**
 * Punto de entrada de la aplicación
 *
 * TODO:
 * i18n
 * Cron Jobs
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
final class Server 
{
    use Singleton, Events;

    public readonly ConfigFile $state;
    public readonly Request $request;
    public readonly HttpHandler $router;
    public readonly ControllerHandler $controllers;

    public SessionInterface $session;
    
    private function __construct () 
    {
        $this->state = ConfigFile::fromDefault(STATE_FILE);
        $this->request = Request::fromGlobals();
        $this->router = new HttpHandler($this->request);
        $this->controllers = new ControllerHandler($this);

        $terminal = $this->state->get('server.terminal');
        $apps = $this->state->get('apps') ?: [];

        if ($terminal || !count($apps)) {
            $terminal_controller = new TerminalController($this);
            $apps = array_merge(['cli' => $terminal_controller], $apps);
        }

        foreach ($apps as $alias => $namespace) {
            $ctrl = $this->controllers->push($namespace, $alias);
            FileSystem::map($alias, $ctrl->namespace('dir'));
        }
    }

    public static function state(string $key) 
    {
        return self::instance()->state->get($key);
    }

    /**
     * @shortcut
     * encaupsula operaciones nescesarias para el arranque
     */
    public static function execute () 
    {
        $self = self::instance();
        
        $self->controllers->walk(function($controller) use ($self) {
            $i = SetupInterface::class;
            if ($setup = $controller->component('Setup', $i)) {
                $setup->setup();
            }

            $actions = $controller->actions();
            foreach ($actions as $action) {
                $self->router->push($action);
            }
        });

        // gestionar respuesta
        $response = $self->router->pop();
        $self->fire('response', $response);

        // guardar estado y enviar respuesta
        $self->state->save();
        $response->send();
    }
}
