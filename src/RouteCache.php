<?php

namespace Irbis;

/**
 * Cache de rutas para evitar reflexión en cada request
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.3
 */
class RouteCache {
    private static $instance;
    private $cache;
    private $cacheKey = 'irbis_routes_cache';

    public function __construct() {
        $this->cache = Cache::getInstance();
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener rutas cacheadas de un controlador
     */
    public function getControllerRoutes($controllerClass) {
        $cacheKey = $this->cacheKey . '_' . str_replace('\\', '_', $controllerClass);
        
        return $this->cache->remember($cacheKey, function() use ($controllerClass) {
            return $this->compileControllerRoutes($controllerClass);
        }, 86400); // Cache por 24 horas
    }

    /**
     * Compilar rutas usando reflexión (solo cuando no están en cache)
     */
    private function compileControllerRoutes($controllerClass) {
        $routes = [];
        $reflection = new \ReflectionClass($controllerClass);

        foreach ($reflection->getMethods() as $method) {
            $comment = $method->getDocComment();
            if (preg_match_all('#@(route|verb) (.*?)\R#', $comment, $matches)) {
                $routeData = [];
                
                foreach ($matches[1] as $index => $type) {
                    if ($type === 'route') {
                        $routeData['routes'][] = trim($matches[2][$index]);
                    } elseif ($type === 'verb') {
                        $routeData['verb'] = trim($matches[2][$index]);
                    }
                }
                
                if (!empty($routeData['routes'])) {
                    $routes[$method->name] = $routeData;
                }
            }
        }

        return $routes;
    }

    /**
     * Invalidar cache de rutas
     */
    public function invalidate($controllerClass = null) {
        if ($controllerClass) {
            $cacheKey = $this->cacheKey . '_' . str_replace('\\', '_', $controllerClass);
            $this->cache->forget($cacheKey);
        } else {
            // Invalidar todo el cache de rutas
            $this->cache->flush();
        }
    }

    /**
     * Compilar todas las rutas del sistema y cachearlas
     */
    public function warmup($controllers) {
        foreach ($controllers as $controller) {
            $controllerClass = get_class($controller);
            $this->getControllerRoutes($controllerClass);
        }
    }
}