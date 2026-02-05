<?php 

namespace Irbis;

include 'Tools/Constants.php';
include 'Tools/Functions.php';

// autocargador de clases
// no se cruza con vendor/autoload.php
irbis_loader();

use Irbis\Traits\Singleton;
use Irbis\Traits\Events;
use Irbis\Exceptions\HttpException;
use Irbis\Tools\ConfigFile;
use Irbis\Terminal\Controller as TerminalController;
use Irbis\Interfaces\SetupInterface;
use Irbis\Interfaces\HooksInterface;
use Irbis\Orm\Connector;


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
class Server {
    use Singleton, Events;

    private $http_error_code_views = [];
    private $setup_was_called = false;
    private $persistent_state_file;
    private $render_closure;
    private $session_resolver;
    private $request;
    private $controllers = [];
    private $controllers_map = [];
    private $responses = [];

    /**
     * singleton
     */
    private function __construct () {
        $this->request = Request::getInstance();
    }

    /* --== MÉTODOS DE UTILIDAD ==-- */

    /**
     * cambia un estado del servidor
     */
    public function setState (string $key, $val) {
        $this->persistent_state_file->{$key} = $val;
    }

    /**
     * obtiene un estado del servidor
     */
    public function getState (string $key) {
        return $this->persistent_state_file->{$key};
    }

    /**
     * guarda el estado del servidor
     */
    public function saveState () {
        $this->persistent_state_file->save();
    }

    /**
     * un controlador tiene dos formas de ser referenciado:
     * - por su namespace
     * - por un nombre corto
     * ambos valores deben ser únicos
     */
    public function addController (Controller $controller, string $name = '') {
        $name = $name ?: $controller::$name;
        $key = $controller->namespace();
        if (!$name) 
            throw new \Exception("Controller name not declared for $key");
        if (array_key_exists($name, $this->controllers_map))
            throw new \Exception("Controller name '$name' already in use");
        if (array_key_exists($key, $this->controllers))
            throw new \Exception("Controller '$key' already registered");
        $this->controllers_map[$name] = $key;
        $this->controllers[$key] = $controller;
    }

    /**
     * devuelve un controlador registrado en el servidor
     * puede ser referenciado por su namespace o por su nombre corto
     */
    public function getController (string $name) : ?Controller {
        if (array_key_exists($name, $this->controllers_map))
            $name = $this->controllers_map[$name];
        return $this->controllers[$name] ?? null;
    }

    /**
     * itera sobre los controladores registrados en el servidor
     * y ejecuta un closure para gestionarlos uno a uno
     */
    public function walkControllers (\Closure $fn) {
        foreach ($this->controllers as $k => $v) $fn($v, $k);
    }

    /**
     * @exclusive, \Irbis\Server
     * fabrica un controlador a partir de un namespace
     * este controlador no se registra en el servidor
     * para ello use ->addController(:Controller)
     */
    public static function buildController (string $namespace) : Controller {
        $namespace = "\\" . str_replace('/', '\\', $namespace).'\Controller';
        return new $namespace;
    }

    /* --== MÉTODOS DE FLUJO ==-- */

    /**
     * establece la configuración por defecto
     * permite re-configurar el servidor de forma especifica
     * permite establecer configuraciones desde un único punto de entrada
     * 
     * ej:
     * - $server->setup(); // configuración por defecto
     * - $server->setup('renderEnvironment', $renderer);
     * - $server->setup('errorView', 404, '/path/to/view.html);
     */
    public function setup (string $setup = '', ...$args) {
        if (!$this->setup_was_called) {
            $this->setup_was_called = true;

            $this->setupErrorView(404, '@cli/404.html');
            $this->setupErrorView(500, '@cli/500.html');
            $this->setupRenderEnvironment(null);
            $this->setupSessionResolver(null);
            
            $this->setupPersistentState();
            $this->setupControllers();
            $this->setupMiddlewares();
        }

        if ($setup) {
            $method = 'setup'.ucfirst($setup);
            $this->{$method}(...$args);
        }
    }

    /**
     * @exclusive, Irbis\Server
     * establece la configuración persistente del servidor
     * crea el archivo de configuración si no existe
     */
    private function setupPersistentState () {
        // inicializa el archivo de configuración, y establece valores por defecto
        $this->persistent_state_file = new ConfigFile(STATE_FILE);
        $default = [
            'server' => [
                'debug' => 'on',
                'terminal' => 'on'
            ],
            'database' => [
                'dsn' => 'sqlite:database.db3',
                'user' => '',
                'pass' => ''
            ]
        ];

        if ($this->persistent_state_file->isEmpty()) {
            // inicializa el estado por defecto
            foreach ($default as $section => $values)
                foreach ($values as $k => $v)
                    $this->setState("{$section}.{$k}", $v);
        } else {
            // asegurar que exista una conexion a base de datos
            if (!$this->getState('database.dsn'))
                foreach ($default['database'] as $k => $v)
                    $this->setState("database.{$k}", $v);
        }
    }

    /**
     * @exclusive, Irbis\Server
     * agrega los controladores por persistencia
     */
    private function setupControllers () {
        $controllers = $this->getState('apps') ?: [];
        // agrega un controlador de configuracion inicial
        if ($this->getState('server.terminal') || !count($controllers)) {
            $this->addController(new TerminalController);
        }
        // agrega todos los controladores configurados
        foreach ($controllers as $name => $namespace) {
            $controller = $this->buildController($namespace);
            $this->addController($controller, $name);
        }
    }

    /**
     * @exclusive, Irbis\Server
     * establece los middlewares de los controladores que tengan uno configurado
     * útil para establecer configuraciones adicionales, como autenticación, api rest, etc.
     */
    private function setupMiddlewares () {
        $this->walkControllers(function($controller) {
            $middleware = $controller->component('Setup');
            if ($middleware instanceof SetupInterface)
                $middleware->setup();
        });
    }

    /**
     * @reusable, setup('errorView', :int, :string)
     * establece la vista asociada a un código de error HTTP
     */
    private function setupErrorView ($key, $view) {
        $this->http_error_code_views[intval($key)] = $view;
    }

    /**
     * @reusable, setup('renderEnvironment', :Closure|null)
     * establece el entorno de renderizado
     */
    private function setupRenderEnvironment (callable $render = null) {
        $this->render_closure = $render ?:
            function ($__view__, $__data__) {
                // resolver alias de directorio de vistas
                if (str_starts_with($__view__, '@')) {
                    $__name__ = explode('/', substr($__view__, 1), 2)[0];
                    $__ctrl__ = Server::getInstance()->getController($__name__);
                    $__path__ = $__ctrl__->namespace('dir').'views';
                    $__view__ = str_replace("@$__name__", $__path__, $__view__);
                }

                extract($__data__);
                if (!file_exists($__view__)) 
                    throw new \Exception("template '{$__view__}' not found");
                include($__view__);
            };
    }

    /**
     * @reusable, setup('sessionResolver', :Closure|null)
     * establece el resolvedor de sesión, closure que debe
     * entregar el usuario autenticado o null si no hay sesión activa
     */
    private function setupSessionResolver (callable $resolver = null) {
        $this->session_resolver = $resolver ?: function() {
            return null;
        };
    }

    /**
     * @exclusive Irbis\Server
     * punto de lanzamiento de la aplicación
     * ejecuta la solicitud del cliente
     */
    public function execute () {
        $path = $this->request->path;
        $response = $this->buildResponse($path);
        try {
            $response = $response->execute();

            Connector::getInstance()->close();

            $this->saveState();
        } catch (\Throwable $error) {
            Connector::getInstance()->rollBack();
            $response->body($error);

            if ($error instanceof HttpException) {
                $errcode = intval($error->getCode());
                $response->header($error->getHttpStatus());
                $response->view($this->http_error_code_views[$errcode] ?? null);
            } else {
                $response->header("HTTP/1.1 500 Internal Server Error");
                $response->view($this->http_error_code_views[500] ?? null);
            }
        } finally {
            $this->fire('response', $response);
            $this->executeRenderEnvironment($response);
        }
    }

    /**
     * @exclusive, \Irbis\Controller
     * simula la ejecución de una solicitud de cliente
     */
    public function executeFake (string $fake_path) : Response {
        // para llamadas internas
        $path = $fake_path ?: $this->request->path;
        $response = $this->buildResponse($path);
        return $response->execute();
    }

    /**
     * @exclusive, \Irbis\Server
     * fabrica un objeto de respuesta para una ruta solicitada
     */
    private function buildResponse (string $path) : Response {
        if (!array_key_exists($path, $this->responses)) {
            // captura las acciones de todos los controladores que coincidan con la ruta
            $actions = array_merge(...array_map(function ($controller) use ($path) {
                return $controller->getActionsFor($path);
            }, array_values($this->controllers)));
            // crea el objeto respuesta
            $this->responses[$path] = new Response($path, $actions);
        }
        return $this->responses[$path];
    }

    /**
     * @exclusive, \Irbis\Server
     * ejecuta el entorno de renderizado configurado
     */
    private function executeRenderEnvironment (Response $response) {
        if (!$response->hasView()) {
            die($response."");
        } else {
            # e: /test?view=index -> views/{view}.html -> views/index.html
            $all_query = $this->request->query('*!');
            $all_query = array_merge(['view' => DEFAULT_VIEW], $all_query);
            $all_query = array_filter($all_query, function ($v) { return !is_array($v); });

            $search = array_map(function ($v) { return "{{$v}}"; }, array_keys($all_query));

            $data = $response(); // ['view' => ..., 'data' => ...]
            $data['view'] = str_replace($search, array_values($all_query), $data['view']);

            $render = $this->render_closure->bindTo(null);
            $render($data['view'], $data['data']);
        }
    }

    /**
     * @exclusive, Irbis\Request
     * ejecuta el resolusor de sesión configurado
     */
    public function executeSessionResolver () {
        $resolver = $this->session_resolver->bindTo(null);
        return $resolver();
    }

    /**
     * @shourtcut
     * punto de entrada simplificado a la aplicación
     */
    public static function listen () {
        $server = Server::getInstance();
        $server->setup();
        $server->execute();
        return $server;
    }

    /**
     * @exclusive Irbis\Terminal
     * instala una aplicación (controlador) en el servidor
     * instala las dependencias del controlador si es necesario
     * ejecuta el hook de instalación si la aplicación tiene uno
     */
    public function installApp (Controller $controller) {
        $apps = $this->getState('apps') ?: [];
        // validar dependencias
        if ($controller::$depends) {
            // filtrar dependencias faltantes
            $missing = array_filter(
                $controller::$depends, 
                function ($depend) use ($apps) {
                    return !in_array($depend, $apps);
                }
            );
            // instalar dependencias faltantes
            if ($missing) {
                foreach ($missing as $depend) {
                    $ctrl = $this->buildController($depend);
                    $this->installApp($ctrl);
                }
            }
        }
        // agregar controlador y ejecutar hook de instalación
        $this->addController($controller);
        $hook = $controller->component('Hooks');
        if ($hook instanceof HooksInterface) { $hook->install(); }
        // actualizar estado del servidor
        $this->setState(
            "apps.{$controller::$name}", 
            $controller->namespace()
        );
    }

    /**
     * @exclusive Irbis\Terminal
     * desinstala una aplicación (controlador) del servidor
     * ejecuta hooks de desinstalación si la aplicación tiene uno
     */
    public function uninstallApp (Controller $controller) {
        $namespace = $controller->namespace();
        // validar dependencias inversas
        $this->walkControllers(function($ctrl) use ($namespace) {
            if (in_array($namespace, $ctrl::$depends)) {
                $ns = $ctrl->namespace();
                throw new \Exception(
                    "Se encontró una dependencia hacia '{$namespace}'. ".
                    "Primero desinstale '{$ns}' para poder continuar."
                );
            }
        });
        // ejecutar hook de desinstalación
        $hook = $controller->component('Hooks');
        if ($hook instanceof HooksInterface) { $hook->uninstall(); }
        // actualizar estado del servidor
        $this->setState("apps.{$controller::$name}", null);
    }
}
