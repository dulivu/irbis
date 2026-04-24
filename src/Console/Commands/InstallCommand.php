<?php
namespace Irbis\Console\Commands;

use Irbis\Console\Command;
use Irbis\Console\Output;
use Irbis\Console\Bootstrap;
use Irbis\Interfaces\SetupInterface;

class InstallCommand extends Command
{
    public function getName(): string
    {
        return 'install';
    }

    public function getDescription(): string
    {
        return 'Instala un módulo/aplicación';
    }

    public function getUsage(): string
    {
        return 'irbis install <namespace>';
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

            // Cargar el controlador
            $controller = $bootstrap->controllers->fromNamespace($namespace);
            
            // Registrar el controlador
            $bootstrap->controllers->push($controller);

            // Ejecutar el hook install si existe
            $setup = $controller->component('Setup', SetupInterface::class);
            
            if ($setup && method_exists($setup, 'install')) {
                Output::info("Ejecutando instalación de '{$namespace}'...");
                $setup->install();
            }

            // Agregar a la lista de apps instaladas
            $apps = $bootstrap->getState('apps') ?: [];
            
            if (!in_array($namespace, $apps)) {
                $apps[] = $namespace;
                $bootstrap->state->set('apps', $apps);
                $bootstrap->saveState();
            }

            $appName = $controller::$name ?? $namespace;
            Output::success("Aplicación '{$appName}' instalada correctamente");

            return 0;

        } catch (\Throwable $e) {
            Output::error("Error al instalar: " . $e->getMessage());
            return 1;
        }
    }
}
