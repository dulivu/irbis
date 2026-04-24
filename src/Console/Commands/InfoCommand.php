<?php
namespace Irbis\Console\Commands;

use Irbis\Console\Command;
use Irbis\Console\Output;
use Irbis\Console\Bootstrap;

class InfoCommand extends Command
{
    public function getName(): string
    {
        return 'info';
    }

    public function getDescription(): string
    {
        return 'Muestra información detallada de una aplicación';
    }

    public function getUsage(): string
    {
        return 'irbis info <namespace>';
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
            $controller = $bootstrap->controllers->fromNamespace($namespace);
            
            // Verificar si está instalada
            $apps = $bootstrap->state->get('apps') ?: [];
            $isInstalled = in_array($namespace, $apps);

            Output::title("Información de la aplicación");

            Output::line(Output::color('Nombre:', 'yellow') . '        ' . ($controller::$name ?? 'n/a'));
            Output::line(Output::color('Namespace:', 'yellow') . '     ' . $namespace);
            Output::line(Output::color('Versión:', 'yellow') . '       ' . ($controller::$version ?? 'n/a'));
            Output::line(Output::color('Autor:', 'yellow') . '         ' . ($controller::$author ?? 'desconocido'));
            Output::line(Output::color('Estado:', 'yellow') . '        ' . 
                ($isInstalled ? Output::color('Instalada', 'green') : Output::color('No instalada', 'gray')));

            // Dependencias
            $depends = $controller::$depends ?? [];
            if (!empty($depends)) {
                Output::line(Output::color('Dependencias:', 'yellow') . '  ' . implode(', ', $depends));
            } else {
                Output::line(Output::color('Dependencias:', 'yellow') . '  Ninguna');
            }

            // Descripción
            $description = $controller::$description ?? 'Sin descripción';
            Output::line();
            Output::line(Output::color('Descripción:', 'yellow'));
            Output::line($description);
            Output::line();

            // Componentes disponibles
            $componentsDir = $controller->namespace('dir') . 'components';
            if (is_dir($componentsDir)) {
                $components = array_diff(scandir($componentsDir), ['.', '..']);
                if (!empty($components)) {
                    Output::line(Output::color('Componentes:', 'yellow'));
                    foreach ($components as $component) {
                        if (pathinfo($component, PATHINFO_EXTENSION) === 'php') {
                            Output::line('  • ' . pathinfo($component, PATHINFO_FILENAME));
                        }
                    }
                    Output::line();
                }
            }

            return 0;

        } catch (\Throwable $e) {
            Output::error("Error al obtener información: " . $e->getMessage());
            return 1;
        }
    }
}
