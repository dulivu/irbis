<?php
namespace Irbis\Console\Commands;

use Irbis\Console\Command;
use Irbis\Console\Output;
use Irbis\Console\Bootstrap;
use Irbis\Interfaces\SetupInterface;

class UpdateCommand extends Command
{
    public function getName(): string
    {
        return 'update';
    }

    public function getDescription(): string
    {
        return 'Actualiza un módulo/aplicación ejecutando su hook update()';
    }

    public function getUsage(): string
    {
        return 'irbis update <namespace>';
    }

    public function handle(): int
    {
        $namespace = $this->argument(0);

        if (!$namespace) {
            Output::error('Debe especificar el namespace de la aplicación');
            Output::line();
            Output::line($this->getUsage());
            return 1;
        }

        try {
            $bootstrap = Bootstrap::instance();

            // Verificar que la app esté instalada
            $apps = $bootstrap->getState('apps') ?: [];
            
            if (!in_array($namespace, $apps)) {
                Output::warning("La aplicación '{$namespace}' no está instalada");
                Output::info("Usa 'irbis install {$namespace}' para instalarla primero");
                return 1;
            }

            // Cargar el controlador
            $controller = $bootstrap->controllers->fromNamespace($namespace);
            
            // Ejecutar el hook update si existe
            $setup = $controller->component('Setup', SetupInterface::class);
            
            if (!$setup) {
                Output::warning("La aplicación no tiene un componente Setup");
                return 1;
            }

            if (!method_exists($setup, 'update')) {
                Output::warning("La aplicación no implementa el método update()");
                return 1;
            }

            Output::info("Ejecutando actualización de '{$namespace}'...");
            $setup->update();

            $appName = $controller::$name ?? $namespace;
            Output::success("Aplicación '{$appName}' actualizada correctamente");

            return 0;

        } catch (\Throwable $e) {
            Output::error("Error al actualizar: " . $e->getMessage());
            return 1;
        }
    }
}
