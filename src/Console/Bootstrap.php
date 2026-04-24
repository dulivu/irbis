<?php
namespace Irbis\Console;

use Irbis\File\Config as ConfigFile;
use Irbis\Module\Handler as ControllerHandler;
use Exception;

/**
 * Bootstrap para inicializar el framework desde CLI
 * sin necesidad del servidor web
 */
class Bootstrap
{
    private static ?self $instance = null;
    
    public readonly ConfigFile $state;
    public readonly ControllerHandler $controllers;

    private function __construct()
    {
        // Verificar que las constantes estén definidas
        if (!defined('BASE_PATH')) {
            throw new Exception('BASE_PATH no está definido');
        }

        if (!defined('STATE_FILE')) {
            throw new Exception('STATE_FILE no está definido');
        }

        // Validar instalación
        $this->validateInstallation();

        // Cargar configuración
        $this->state = ConfigFile::fromDefault(STATE_FILE);
        
        // Crear handler de controladores (sin pasar Server)
        // Necesitamos una versión simplificada para CLI
        $this->controllers = new ControllerHandler($this);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Valida que el framework esté correctamente instalado
     */
    private function validateInstallation(): void
    {
        // Verificar que exista el directorio del framework
        if (!is_dir(BASE_PATH . '/vendor/dulivu/irbis')) {
            throw new Exception('Framework Irbis no encontrado en vendor/');
        }

        // Verificar que exista composer.json
        if (!file_exists(BASE_PATH . '/composer.json')) {
            throw new Exception('composer.json no encontrado. Asegúrate de estar en la raíz del proyecto.');
        }

        // Verificar permisos de escritura en state.ini
        $state_file = STATE_FILE;
        if (file_exists($state_file) && !is_writable($state_file)) {
            throw new Exception("No hay permisos de escritura en: {$state_file}");
        }

        // Verificar que el directorio sea escribible si el archivo no existe
        $state_dir = dirname($state_file);
        if (!file_exists($state_file) && !is_writable($state_dir)) {
            throw new Exception("No hay permisos de escritura en el directorio: {$state_dir}");
        }
    }

    /**
     * Obtiene un valor del estado
     */
    public function getState(string $key)
    {
        return $this->state->get($key);
    }

    /**
     * Guarda el estado
     */
    public function saveState(): void
    {
        $this->state->save();
    }
}
