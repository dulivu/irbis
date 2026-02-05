<?php
namespace Irbis\Orm;

use Irbis\Server;
use Irbis\Controller;
use Irbis\Traits\SingletonFactory;


/**
 * Controla la estructura central del modelo cargando la definición
 * de propiedades y métodos de cada módulo registrado en el servidor, 
 * esta clase es de uso exclusivo de la clase RecordSet 
 * (se debe entender como una caja negra)
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class Backbone {
    use SingletonFactory;

    public $name;
    public $constans = [];

    private $properties = [];
    private $methods = [];
    private $dbinfo;

    private function __construct (string $name) {
        $this->name = $name;
        $this->loadSkeleton();
        if (count($this->properties) <= 1)
            throw new \Exception("recordset: no se pudo importar una definición para '$name'");
    }

    /**
     * carga las definiciones del modelo de cada controlador
     */
    public function loadSkeleton () {
        $self = $this;
        $self->constans = [];
        $self->properties = [];
        $self->methods = [];

        $self->setProperty('id', [
            'int', 
            'label' => 'ID', 
            'oSQL' => 'UNIQUE AUTO_INCREMENT', 
            'readonly' => true,
            'store' => false,
        ]);

        Server::getInstance()
            ->walkControllers(function ($controller) use ($self) {
                $skeleton = "models".DIRECTORY_SEPARATOR."{$self->name}.php";
                $skeleton = $controller->namespace('dir').$skeleton;
                if (file_exists($skeleton)) {
                    if ($skeleton = include $skeleton) {
                        foreach ($skeleton as $key => $value)
                            $self->setMember($key, $value);
                    }
                }
            });
    }

    /**
     * Determina el tipo de miembro y lo asigna al backbone
     */
    public function setMember ($key, $value = null) {
        if ($key == '@extend') {
            $this->constans[$key] = $value;
            $this->setMember(self::getInstance($value));
        } elseif ($key instanceof self) {
            foreach ($key->getProperties() as $k => $m)
                $this->properties[$k] = $m;
            foreach ($key->getMethods() as $k => $m)
                $this->methods[$k] = $m;
        } elseif (is_callable($value)) {
            $this->setMethod($key, $value);
        } elseif (!str_starts_with($key, '@')) {
            $this->setProperty($key, $value);
        } else {
            $this->constans[$key] = $value;
        }
    }

    /**
     * Establece la definición de una propiedad
     * ó actualiza la definición de una propiedad si ya existe
     */
    public function setProperty ($prop_name, $prop_definition) {
        if (!$this->hasProperty($prop_name))
            $this->properties[$prop_name] = new Property($prop_name, $prop_definition);
        else
            $this->properties[$prop_name]->define($prop_definition);
    }

    /**
     * Establece un nuevo método, y si existe agrega a la pila
     */
    public function setMethod ($method_name, $method_callable) {
        if (!$this->hasMethod($method_name))
            $this->methods[$method_name] = new Method($method_name);
        $this->methods[$method_name]->append($method_callable);
    }

    /**
     * Determina si el backbone tiene la propiedad solicitada
     */
    public function hasProperty ($key) {
        return isset($this->properties[$key]) && 
            $this->properties[$key] instanceof Property;
    }

    /**
     * Determina si el backbone tiene el método solicitado
     */
    public function hasMethod ($key) {
        return isset($this->methods[$key]) &&
            $this->methods[$key] instanceof Method;
    }

    /**
     * Devuelve todas las propiedades o una propiedad específica
     */
    public function getProperties ($key = false) {
        if (is_string($key))
            return $this->cloneProperty($key);

        $key = is_array($key) ? $key : array_keys($this->properties);
        $arr = array_map(function ($prop) {
            return $this->cloneProperty($prop);
        }, $key);
        return array_combine($key, $arr);
    }

    /**
     * Clona una propiedad específica
     * para evitar modificaciones accidentales
     */
    private function cloneProperty ($key) {
        if (isset($this->properties[$key]))
            return clone $this->properties[$key];
        else return null;
    }

    /**
     * Devuelve todos los métodos o un método específico
     */
    public function getMethods ($key = false) {
        if (is_string($key))
            return $this->cloneMethod($key);
        $key = is_array($key) ? $key : array_keys($this->methods);
        $arr = array_map(function ($prop) {
            return $this->cloneMethod($prop);
        }, $key);
        return array_combine($key, $arr);
    }

    /**
     * Clona un método específico
     * para evitar modificaciones accidentales
     */
    private function cloneMethod ($key) {
        if (isset($this->methods[$key]))
            return clone $this->methods[$key];
        else return null;
    }
}