<?php
namespace Irbis\Console\Commands;

use Irbis\Console\Command;
use Irbis\Console\Output;
use Irbis\Console\Bootstrap;

class ListCommand extends Command
{
    public function getName(): string
    {
        return 'list';
    }

    public function getDescription(): string
    {
        return 'Lista las aplicaciones instaladas (--all para ver disponibles)';
    }

    public function getUsage(): string
    {
        return 'irbis list [--all]';
    }

    public function handle(): int
    {
        try {
            $bootstrap = Bootstrap::instance();
            $showAll = $this->hasOption('all');

            $installedApps = $bootstrap->state->get('apps') ?: [];

            if ($showAll) {
                // Mostrar todas las aplicaciones disponibles
                $this->showAllApplications($installedApps);
            } else {
                // Mostrar solo las instaladas
                $this->showInstalledApplications($installedApps);
            }

            return 0;

        } catch (\Throwable $e) {
            Output::error("Error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Muestra solo las aplicaciones instaladas
     */
    private function showInstalledApplications(array $installed): void
    {
        if (empty($installed)) {
            Output::warning('No hay aplicaciones instaladas');
            Output::info('Usa "irbis list --all" para ver las aplicaciones disponibles');
            return;
        }

        Output::title('Aplicaciones instaladas');

        $bootstrap = Bootstrap::instance();
        $rows = [];

        foreach ($installed as $namespace) {
            try {
                $controller = $bootstrap->controllers->fromNamespace($namespace);
                $rows[] = [
                    $controller::$name ?? 'n/a',
                    $namespace,
                    $controller::$version ?? 'n/a',
                    $controller::$author ?? 'desconocido',
                ];
            } catch (\Throwable $e) {
                $rows[] = [
                    'Error',
                    $namespace,
                    '-',
                    $e->getMessage(),
                ];
            }
        }

        Output::table(
            ['Nombre', 'Namespace', 'Versión', 'Autor'],
            $rows
        );
    }

    /**
     * Muestra todas las aplicaciones disponibles y marca las instaladas
     */
    private function showAllApplications(array $installed): void
    {
        // Buscar todos los controladores en *Apps/*/Controller.php
        $pattern = BASE_PATH . '/*Apps/*/Controller.php';
        $controllers = glob($pattern);

        if (empty($controllers)) {
            Output::warning('No se encontraron aplicaciones disponibles');
            return;
        }

        Output::title('Aplicaciones disponibles');

        $bootstrap = Bootstrap::instance();
        $rows = [];

        foreach ($controllers as $controllerPath) {
            // Extraer namespace del path
            $namespace = str_replace(
                [BASE_PATH . '/', '/Controller.php'],
                '',
                $controllerPath
            );
            $namespace = str_replace('\\', '/', $namespace);

            $isInstalled = in_array($namespace, $installed);
            $status = $isInstalled ? Output::color('✓', 'green') : Output::color('○', 'gray');

            try {
                $controller = $bootstrap->controllers->fromNamespace($namespace);
                $rows[] = [
                    $status,
                    $controller::$name ?? 'n/a',
                    $namespace,
                    $controller::$version ?? 'n/a',
                ];
            } catch (\Throwable $e) {
                $rows[] = [
                    Output::color('✗', 'red'),
                    'Error',
                    $namespace,
                    '-',
                ];
            }
        }

        Output::table(
            ['', 'Nombre', 'Namespace', 'Versión'],
            $rows
        );

        Output::line('Leyenda: ' . Output::color('✓', 'green') . ' Instalada  ' 
                     . Output::color('○', 'gray') . ' Disponible  '
                     . Output::color('✗', 'red') . ' Error');
        Output::line();
    }
}
