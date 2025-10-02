<?php

namespace Irbis\Cache;

/**
 * Adapter de Redis para el sistema de cache de Irbis
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.3
 */
class RedisCache {
    private $redis;
    private $prefix;

    public function __construct($host = 'cavia-redis', $port = 6379, $prefix = 'irbis:') {
        $this->redis = new \Redis();
        $this->redis->connect($host, $port);
        $this->prefix = $prefix;
    }

    /**
     * Ejemplo: Cache de rutas compiladas
     */
    public function cacheCompiledRoutes($controllerClass, $routes) {
        $key = $this->prefix . 'routes:' . str_replace('\\', '_', $controllerClass);
        $this->redis->setex($key, 3600, serialize($routes)); // Cache por 1 hora
    }

    /**
     * Ejemplo: Cache de sesiones de usuario
     */
    public function cacheUserSession($userId, $sessionData) {
        $key = $this->prefix . 'session:' . $userId;
        $this->redis->setex($key, 1800, serialize($sessionData)); // 30 minutos
    }

    /**
     * Ejemplo: Cache de consultas de base de datos
     */
    public function cacheDbQuery($queryHash, $results) {
        $key = $this->prefix . 'db:' . $queryHash;
        $this->redis->setex($key, 300, serialize($results)); // 5 minutos
    }

    /**
     * Obtener del cache
     */
    public function get($key) {
        $data = $this->redis->get($this->prefix . $key);
        return $data ? unserialize($data) : null;
    }

    /**
     * Cache de vistas renderizadas (muy útil para tu framework)
     */
    public function cacheRenderedView($viewPath, $data, $html) {
        $key = $this->prefix . 'view:' . md5($viewPath . serialize($data));
        $this->redis->setex($key, 600, $html); // Cache por 10 minutos
    }
}

// Uso en tu Controller:
/*
class MiController extends \Irbis\Controller {
    public function index($request, $response) {
        $cache = new \Irbis\Cache\RedisCache();
        
        // Intentar obtener vista desde cache
        $cacheKey = 'productos_lista';
        $html = $cache->get($cacheKey);
        
        if (!$html) {
            // Si no está en cache, renderizar y guardar
            $productos = $this->getProductos(); // consulta costosa
            $html = $this->renderView('productos.html', ['productos' => $productos]);
            $cache->cacheRenderedView($cacheKey, ['productos' => $productos], $html);
        }
        
        return $html;
    }
}
*/