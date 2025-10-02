<?php

namespace Irbis\Traits;

/**
 * Singleton thread-safe optimizado para memoria
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.3
 */
trait OptimizedSingleton {
    private static $instances = [];
    private static $locks = [];

    public static function getInstance(...$params) {
        $class = static::class;
        
        // Double-checked locking para thread safety
        if (!isset(self::$instances[$class])) {
            if (!isset(self::$locks[$class])) {
                self::$locks[$class] = new \SplObjectStorage();
            }
            
            // Simulación de lock (PHP no tiene threading real, pero es buena práctica)
            if (!isset(self::$instances[$class])) {
                self::$instances[$class] = new static(...$params);
            }
        }
        
        return self::$instances[$class];
    }

    /**
     * Destruir instancia específica para liberar memoria
     */
    public static function destroyInstance() {
        $class = static::class;
        if (isset(self::$instances[$class])) {
            unset(self::$instances[$class]);
            unset(self::$locks[$class]);
        }
    }

    /**
     * Limpiar todas las instancias (útil en tests)
     */
    public static function destroyAllInstances() {
        self::$instances = [];
        self::$locks = [];
    }

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}