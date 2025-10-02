<?php

namespace Irbis;

/**
 * Sistema de cache simple pero eficiente
 * Soporta file cache y memory cache
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.3
 */
class Cache {
    private static $instance;
    private $memoryCache = [];
    private $cacheDir;
    private $defaultTtl = 3600; // 1 hora

    public function __construct($cacheDir = null) {
        $this->cacheDir = $cacheDir ?: BASE_PATH . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public static function getInstance($cacheDir = null) {
        if (!self::$instance) {
            self::$instance = new self($cacheDir);
        }
        return self::$instance;
    }

    /**
     * Obtener del cache
     */
    public function get($key, $default = null) {
        // Primero revisar memoria
        if (isset($this->memoryCache[$key])) {
            $item = $this->memoryCache[$key];
            if ($item['expires'] > time()) {
                return $item['data'];
            } else {
                unset($this->memoryCache[$key]);
            }
        }

        // Revisar archivo
        $filepath = $this->getFilePath($key);
        if (file_exists($filepath)) {
            $content = file_get_contents($filepath);
            $item = unserialize($content);
            
            if ($item['expires'] > time()) {
                // Guardar en memoria para siguientes accesos
                $this->memoryCache[$key] = $item;
                return $item['data'];
            } else {
                unlink($filepath);
            }
        }

        return $default;
    }

    /**
     * Guardar en cache
     */
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?: $this->defaultTtl;
        $expires = time() + $ttl;
        
        $item = [
            'data' => $data,
            'expires' => $expires,
            'created' => time()
        ];

        // Guardar en memoria
        $this->memoryCache[$key] = $item;

        // Guardar en archivo
        $filepath = $this->getFilePath($key);
        file_put_contents($filepath, serialize($item), LOCK_EX);

        return true;
    }

    /**
     * Cache con callback (patrón remember)
     */
    public function remember($key, $callback, $ttl = null) {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        
        return $value;
    }

    /**
     * Eliminar del cache
     */
    public function forget($key) {
        unset($this->memoryCache[$key]);
        
        $filepath = $this->getFilePath($key);
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * Limpiar todo el cache
     */
    public function flush() {
        $this->memoryCache = [];
        
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Limpiar cache expirado
     */
    public function cleanup() {
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $item = unserialize($content);
            
            if ($item['expires'] <= time()) {
                unlink($file);
            }
        }
    }

    private function getFilePath($key) {
        $safeKey = md5($key);
        return $this->cacheDir . DIRECTORY_SEPARATOR . $safeKey . '.cache';
    }
}