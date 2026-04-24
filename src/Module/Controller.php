<?php
namespace Irbis\Module;
use RuntimeException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionAttribute;
use Irbis\Server;
use Irbis\Http\Request;
use Irbis\Routing\Action;
use Irbis\Interfaces\HooksInterface;


abstract class Controller 
{

    // características del módulo
    public static $name			= ''; 	    # nombre alías del módulo, simple y de una sóla palabra
    public static $depends		= []; 		# dependencias de otros modulos namespaces
    public static $unpackage	= false; 	# true, para aplicaciones que no cumplan con PSR-4

    public readonly Server $server; # instancia del servidor de la aplicación
    
    private $_components = []; # instancias de componentes del controlador
    private $_namespace; # espacio de nombre único en la aplicación, e: Package/App
    private $_directory; # directorio fisico donde se encuentra el módulo
    private $_actions = []; # almacena las acciones mapeadas como rutas de cliente

    public function __construct (Server $server) 
    {
        $klass = get_class($this);
        $s = DIRECTORY_SEPARATOR;
        $k = array_slice(explode('\\', $klass), 0, -1);
        $d = BASE_PATH.$s.implode($s, $k);

        $this->server = $server;
        $this->_namespace = implode('/', $k); # Package/App
        $this->_directory = $d;

        if (static::$unpackage) {
            $this->_directory = dirname((new ReflectionClass($this))->getFileName());
        }
    }

    public function namespace (string $format = '') 
    {
        $namespace = $this->_namespace;
        if ($format == 'php')
            return '\\'.str_replace('/', '\\', $namespace).'\\';
        if ($format == 'snake')
            return strtolower(str_replace('/', '_', $namespace)).'_';
        if ($format == 'dir')
            return $this->_directory.DIRECTORY_SEPARATOR;
        return $namespace;
    }

    public function super () 
    {
        return $this->server->router->pop();
    }

    public function actions() 
    {
        if ($this->_actions) return $this->_actions;

        $klass = new ReflectionClass($this);
        $klass_methods = $klass->getMethods(
            ReflectionMethod::IS_FINAL
        );

        foreach ($klass_methods as $method) {
            $action = $method->getAttributes(Action::class);

            if ($action) {
                $action = $action[0]->newInstance();
                $action->action([$this, $method->name]);
                $this->_actions[] = $action;
            }
        }

        return $this->_actions;
    }

    /**
     * @Factory, @singleton
     * crea un componente del controlador, maneja una sola instancia por clase
     *   - facilita la inyección de dependencias entre componentes
     *   - asegura que solo haya una instancia de cada componente
     *   - separa la lógica del controlador en partes reutilizables y organizadas
     */
    public function component ($klass, $i = null) 
    {
        if (!isset($this->_components[$klass])) {
            // crea la instancia del componente
            $instance = $this->namespace('php') . $klass;
            $instance = class_exists($instance) ? new $instance() : null;

            if ($instance === null) return $instance;
            
            // validar que la clase use el trait Component
            // ó implemente un método para establecer el controlador
            if (!is_callable([$instance, 'setController'])) {
                throw new RuntimeException(
                    "'$klass' is not a valid component, must extend Component"
                );
            }

            $instance->setController($this);

            // validar que la instancia cumpla con una interfaz
            if ($i && !$instance instanceof $i) {
                throw new RuntimeException(
                    "'$klass' must be an instance of $i"
                );
            }

            $this->_components[$klass] = $instance;
        }

        return $this->_components[$klass];
    }

    public function install () {
        $apps = $this->server->state->get('apps') ?: [];
        
        // validar dependencias
        if ($this::$depends) {
            // filtrar dependencias faltantes
            $missing = array_filter(
                $this::$depends, 
                function ($depend) use ($apps) {
                    return !in_array($depend, $apps);
                }
            );
            // instalar dependencias faltantes
            if ($missing) {
                foreach ($missing as $depend) {
                    $ctrl = $this->server->controllers->push($depend);
                    $ctrl->install();
                }
            }
        }

        // agregar controlador y ejecutar hook de instalación
        if ($hook = $this->component('HooksComponent', HooksInterface::class)) {
            $hook->install();
        }

        // actualizar estado del servidor
        $this->server->state->set(
            "apps.{$this::$name}", 
            $this->namespace()
        );
    }

    public function uninstall () {
        $namespace = $this->namespace();

        // validar dependencias inversas
        $this->server->controllers->walk(function($ctrl) use ($namespace) {
            if (in_array($namespace, $ctrl::$depends)) {
                $ns = $ctrl->namespace();
                throw new Exception(
                    "Se encontró una dependencia hacia '{$namespace}'. ".
                    "Primero desinstale '{$ns}' para poder continuar."
                );
            }
        });

        // ejecutar hook de desinstalación
        if ($hook = $this->component('HooksComponent', HooksInterface::class)) {
            $hook->uninstall();
        }
        // actualizar estado del servidor
        $this->server->state->set("apps.{$this::$name}", null);
    }
}
