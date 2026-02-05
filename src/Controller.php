<?php

namespace Irbis;

use Irbis\Server;
use Irbis\Tools\ConfigFile;
use Irbis\Interfaces\ComponentInterface;

/**
 * Esta clase se debe heredar para implementar la lógica de la aplicación
 * Los métodos enrutados '@route' serán accesibles por el cliente
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
abstract class Controller {

    // características del módulo
    public static $name			= ''; 	    # nombre alías del módulo, simple y de una sóla palabra
    public static $routable		= false; 	# determina si el controlador tiene rutas de cliente
    public static $depends		= []; 		# dependencias de otros modulos namespaces
    public static $unpackage	= false; 	# true, para aplicaciones que no cumplan con PSR-4

    public ?Server $server = null; # instancia del servidor de la aplicación
    private $_components = []; # instancias de componentes del controlador
    private $_namespace; # espacio de nombre único en la aplicación, e: DemoApps/Sample
    private $_directory; # directorio fisico donde se encuentra el módulo
    private $_actions; # almacena las acciones mapeadas como rutas de cliente

    public function __construct () {
        $klass = get_class($this);
        $s = DIRECTORY_SEPARATOR;
        $k = array_slice(explode('\\', $klass), 0, -1);
        $d = BASE_PATH.$s.implode($s, $k);

        $this->server = Server::getInstance();
        $this->_namespace = implode('/', $k); # Package/App
        $this->_directory = static::$unpackage ? 
            (dirname((new \ReflectionClass($this))->getFileName())) : $d;
    }

    /**
     * devuelve el namespace del controlador
     */
    public function namespace (string $format = '') {
        $namespace = $this->_namespace;
        if ($format == 'php')
            return '\\'.str_replace('/', '\\', $namespace).'\\';
        if ($format == 'snake')
            return strtolower(str_replace('/', '_', $namespace)).'_';
        if ($format == 'dir')
            return $this->_directory.DIRECTORY_SEPARATOR;
        return $namespace;
    }

    /**
     * construye una instancia de la clase
     * declarada dentro de la aplicación del controlador
     */
    public function component ($klass, ...$args) {
        if (!isset($this->_components[$klass])) {
            $instance = $this->namespace('php') . $klass;
            $instance = class_exists($instance) ? new $instance(...$args) : null;
            if ($instance instanceof ComponentInterface) {
                $instance->setController($this);
                $this->_components[$klass] = $instance;
            } else return $instance;
        }
        return $this->_components[$klass];
    }

    /**
     * shortcut para obtener otro controlador de la aplicación
     */
    public function application (string $key) {
        return Server::getInstance()->getController($key);
    }

    /**
     * simula una petición HTTP interna
     * ya que los métodos enrutados deber ser 'final', no pueden ser sobreescritos
     * por lo que si se desea extender la funcionalidad de un método enrutado
     * se declaran otros controladores y se usa este método para llamar al original
     * e: return $this->super('/ruta/original');
     */
    protected function super (string $fake_path = '') {
        $server = Server::getInstance();
        return $server->executeFake($fake_path);
    }

    /**
     * @exclusive, \Irbis\Server
     * devuelve las acciones que coinciden con la solicitud del cliente
     */
    public function getActionsFor (string $path) : array {
        $matches = [];
        if (!$this::$routable)
            return $matches;
        if (!$this->_actions)
            $this->registerActions();

        foreach ($this->_actions as $action) {
            if ($action->match($path)) {
                $matches[] = $action;
            }
        }

        return $matches;
    }

    /**
     * @exclusive, \Irbis\Controller
     * registra las acciones enrutadas de este controlador
     * son acciones que el cliente puede ejecutar
     */
    private function registerActions () {
        $this->_actions = [];
        $klass = new \ReflectionClass($this);

        foreach ($klass->getMethods(\ReflectionMethod::IS_FINAL) as $method) {
            $comment = $method->getDocComment();
            if ($comment && preg_match_all("#@(\w+)\s+(.*?)\R#", $comment, $pm)) {
                if (in_array('route', $pm[1])) {
                    $action = new Action([$this, $method->name]);
                    $action->pushDecorators($pm[1], $pm[2]);
                    $this->_actions[] = $action;
                }
            }
        }
    }
}
