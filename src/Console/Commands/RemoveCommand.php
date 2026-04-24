<?php
namespace Irbis\Console\Commands;

use Irbis\Console\Command;
use Irbis\Console\Output;
use Irbis\Console\Bootstrap;
use Irbis\Interfaces\SetupInterface;

class RemoveCommand extends Command
{
    public function getName(): string
    {
        return 'remove';
    }

    public function getDescription(): string
    {
        return 'Desinstala un módulo/aplicación';
    }

    public function getUsage(): string
    {
        return 'irbis remove <namespace>';
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
            $apps = $bootstrap->state->get('apps') ?: [];
            
            if (!in_array($namespace, $apps)) {
                Output::warning("La aplicación '{$namespace}' no está instalada");
                return 1;
            }

            // Cargar el controlador
            $controller = $bootstrap->controllers->fromNamespace($namespace);
            
            // Ejecutar el hook uninstall si existe
            $setup = $controller->component('Setup', SetupInterface::class);
            
            if ($setup && method_exists($setup, 'uninstall')) {
                Output::info("Ejecutando desinstalación de '{$namespace}'...");
                $setup->uninstall();
            }

            // Remover de la lista de apps instaladas
            $apps = array_filter($apps, fn($app) => $app !== $namespace);
            $bootstrap->state->set('apps', array_values($apps));
            $bootstrap->state->save();

            $appName = $controller::$name ?? $namespace;
            Output::success("Aplicación '{$appName}' desinstalada correctamente");

            return 0;

        } catch (\Throwable $e) {
            Output::error("Error al desinstalar: " . $e->getMessage());
            return 1;
        }
    }
}
