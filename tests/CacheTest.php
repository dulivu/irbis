<?php

namespace Irbis\Tests;

use PHPUnit\Framework\TestCase;
use Irbis\Cache;

/**
 * Tests para el sistema de cache
 */
class CacheTest extends TestCase {
    private $cache;
    private $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'irbis_cache_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->cache = new Cache($this->tempDir);
    }

    protected function tearDown(): void {
        // Limpiar archivos de test
        $files = glob($this->tempDir . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function testSetAndGet(): void {
        $key = 'test_key';
        $value = 'test_value';
        
        $this->assertTrue($this->cache->set($key, $value));
        $this->assertEquals($value, $this->cache->get($key));
    }

    public function testGetWithDefault(): void {
        $default = 'default_value';
        $result = $this->cache->get('nonexistent_key', $default);
        
        $this->assertEquals($default, $result);
    }

    public function testRemember(): void {
        $key = 'remember_key';
        $callbackExecuted = false;
        
        $callback = function() use (&$callbackExecuted) {
            $callbackExecuted = true;
            return 'callback_result';
        };
        
        // Primera llamada - debería ejecutar callback
        $result1 = $this->cache->remember($key, $callback);
        $this->assertTrue($callbackExecuted);
        $this->assertEquals('callback_result', $result1);
        
        // Segunda llamada - no debería ejecutar callback (debería usar cache)
        $callbackExecuted = false;
        $result2 = $this->cache->remember($key, $callback);
        $this->assertFalse($callbackExecuted);
        $this->assertEquals('callback_result', $result2);
    }

    public function testExpiration(): void {
        $key = 'expiring_key';
        $value = 'expiring_value';
        
        // Configurar con TTL de 1 segundo
        $this->cache->set($key, $value, 1);
        
        // Inmediatamente debería estar disponible
        $this->assertEquals($value, $this->cache->get($key));
        
        // Esperar a que expire
        sleep(2);
        
        // Ahora debería retornar null
        $this->assertNull($this->cache->get($key));
    }

    public function testForget(): void {
        $key = 'forget_key';
        $value = 'forget_value';
        
        $this->cache->set($key, $value);
        $this->assertEquals($value, $this->cache->get($key));
        
        $this->cache->forget($key);
        $this->assertNull($this->cache->get($key));
    }
}