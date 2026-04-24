<?php
namespace Irbis\Console\Commands;

use Irbis\Console\Command;
use Irbis\Console\Output;
use Irbis\Console\Bootstrap;

class ConnCommand extends Command
{
    public function getName(): string
    {
        return 'conn';
    }

    public function getDescription(): string
    {
        return 'Configura la conexión a base de datos';
    }

    public function getUsage(): string
    {
        return 'irbis conn <subcommand> [value]' . PHP_EOL .
               '  Subcomandos:' . PHP_EOL .
               '    dsn <value>   - Establece el DSN de conexión' . PHP_EOL .
               '    user <value>  - Establece el usuario de BD' . PHP_EOL .
               '    pass <value>  - Establece la contraseña de BD' . PHP_EOL .
               '    reset         - Restaura la configuración por defecto' . PHP_EOL .
               '    show          - Muestra la configuración actual';
    }

    public function handle(): int
    {
        $subcommand = $this->argument(0);
        $value = $this->argument(1);

        if (!$subcommand) {
            Output::error('Debe especificar un subcomando');
            Output::line();
            $this->showHelp();
            return 1;
        }

        try {
            $bootstrap = Bootstrap::instance();

            switch ($subcommand) {
                case 'reset':
                    return $this->resetConnection($bootstrap);

                case 'show':
                    return $this->showConnection($bootstrap);

                case 'dsn':
                case 'user':
                case 'pass':
                    if (!$value) {
                        Output::error("Debe especificar un valor para '{$subcommand}'");
                        return 1;
                    }
                    return $this->setConnection($bootstrap, $subcommand, $value);

                default:
                    Output::error("Subcomando no reconocido: {$subcommand}");
                    Output::line();
                    $this->showHelp();
                    return 1;
            }

        } catch (\Throwable $e) {
            Output::error("Error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Restaura la configuración de conexión por defecto
     */
    private function resetConnection(Bootstrap $bootstrap): int
    {
        $bootstrap->state->set('database.dsn', 'sqlite:database.db3');
        $bootstrap->state->set('database.user', '');
        $bootstrap->state->set('database.pass', '');
        $bootstrap->state->save();

        Output::success('Conexión a base de datos restaurada a valores por defecto');
        Output::info('DSN: sqlite:database.db3');

        return 0;
    }

    /**
     * Muestra la configuración actual
     */
    private function showConnection(Bootstrap $bootstrap): int
    {
        Output::title('Configuración de base de datos');

        $dsn = $bootstrap->state->get('database.dsn') ?? 'No configurado';
        $user = $bootstrap->state->get('database.user') ?? '';
        $pass = $bootstrap->state->get('database.pass') ?? '';

        Output::line(Output::color('DSN:', 'yellow') . '      ' . $dsn);
        Output::line(Output::color('Usuario:', 'yellow') . '  ' . ($user ?: '(vacío)'));
        Output::line(Output::color('Password:', 'yellow') . ' ' . ($pass ? '********' : '(vacío)'));
        Output::line();

        return 0;
    }

    /**
     * Establece un valor de configuración
     */
    private function setConnection(Bootstrap $bootstrap, string $param, string $value): int
    {
        $bootstrap->state->set("database.{$param}", $value);
        $bootstrap->state->save();

        $displayValue = $param === 'pass' ? '********' : $value;
        Output::success("Parámetro '{$param}' actualizado: {$displayValue}");

        return 0;
    }
}
