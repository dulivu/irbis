<?php
namespace Irbis\Console;

use Exception;
use Throwable;

/**
 * Aplicación CLI principal - Router de comandos
 */
class Application
{
    private array $commands = [];
    private const VERSION = '3.0';

    public function __construct()
    {
        // Registrar todos los comandos disponibles
        $this->registerCommand(new Commands\InstallCommand());
        $this->registerCommand(new Commands\RemoveCommand());
        $this->registerCommand(new Commands\UpdateCommand());
        $this->registerCommand(new Commands\ListCommand());
        $this->registerCommand(new Commands\InfoCommand());
        $this->registerCommand(new Commands\ConnCommand());
        $this->registerCommand(new Commands\BindModelCommand());
    }

    /**
     * Registra un comando
     */
    private function registerCommand(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    /**
     * Ejecuta la aplicación
     */
    public function run(array $argv): int
    {
        // Remover el nombre del script
        array_shift($argv);

        // Si no hay argumentos, mostrar ayuda
        if (empty($argv)) {
            $this->showHelp();
            return 0;
        }

        $commandName = array_shift($argv);

        // Comandos especiales
        if (in_array($commandName, ['--version', '-v'])) {
            $this->showVersion();
            return 0;
        }

        if (in_array($commandName, ['--help', '-h', 'help'])) {
            $this->showHelp();
            return 0;
        }

        // Buscar el comando
        if (!isset($this->commands[$commandName])) {
            Output::error("Comando no encontrado: {$commandName}");
            Output::line();
            Output::line("Usa 'irbis --help' para ver los comandos disponibles.");
            return 1;
        }

        $command = $this->commands[$commandName];

        // Parsear argumentos y opciones
        [$arguments, $options] = $this->parseInput($argv);

        // Verificar si se solicita ayuda para el comando
        if (isset($options['help']) || isset($options['h'])) {
            $command->showHelp();
            return 0;
        }

        // Ejecutar el comando
        try {
            $command->setInput($arguments, $options);
            return $command->handle();
        } catch (Throwable $e) {
            Output::error($e->getMessage());
            if (getenv('DEBUG') === 'true') {
                Output::line();
                Output::line($e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Parsea los argumentos de entrada separando argumentos de opciones
     */
    private function parseInput(array $input): array
    {
        $arguments = [];
        $options = [];

        foreach ($input as $arg) {
            if (str_starts_with($arg, '--')) {
                // Opción larga: --name=value o --flag
                $name = substr($arg, 2);
                if (str_contains($name, '=')) {
                    [$name, $value] = explode('=', $name, 2);
                    $options[$name] = $value;
                } else {
                    $options[$name] = true;
                }
            } elseif (str_starts_with($arg, '-') && $arg !== '-') {
                // Opción corta: -f
                $name = substr($arg, 1);
                $options[$name] = true;
            } else {
                // Argumento posicional
                $arguments[] = $arg;
            }
        }

        return [$arguments, $options];
    }

    /**
     * Muestra la versión
     */
    private function showVersion(): void
    {
        Output::line('Irbis Framework CLI v' . self::VERSION);
    }

    /**
     * Muestra la ayuda general
     */
    private function showHelp(): void
    {
        Output::title('Irbis Framework CLI v' . self::VERSION);
        Output::line('Herramienta de línea de comandos para gestionar aplicaciones Irbis');
        Output::line();
        Output::line(Output::color('Uso:', 'yellow'));
        Output::line('  irbis <comando> [argumentos] [opciones]');
        Output::line();
        Output::line(Output::color('Comandos disponibles:', 'yellow'));

        $maxLength = max(array_map(function($cmd) {
            return strlen($cmd->getName());
        }, $this->commands));

        foreach ($this->commands as $command) {
            $name = str_pad($command->getName(), $maxLength + 2);
            Output::line('  ' . Output::color($name, 'green') . $command->getDescription());
        }

        Output::line();
        Output::line(Output::color('Opciones globales:', 'yellow'));
        Output::line('  --help, -h     Muestra esta ayuda');
        Output::line('  --version, -v  Muestra la versión del framework');
        Output::line();
        Output::line('Para obtener ayuda de un comando específico:');
        Output::line('  irbis <comando> --help');
        Output::line();
    }
}
