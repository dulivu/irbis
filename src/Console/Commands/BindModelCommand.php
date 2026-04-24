<?php
namespace Irbis\Console\Commands;

use Irbis\Console\Command;
use Irbis\Console\Output;
use Irbis\Orm\RecordSet;
use Irbis\Orm\Connector;

class BindModelCommand extends Command
{
    public function getName(): string
    {
        return 'model:bind';
    }

    public function getDescription(): string
    {
        return 'Enlaza un modelo a la base de datos (crea la tabla)';
    }

    public function getUsage(): string
    {
        return 'irbis model:bind <nombre> [--rebuild]';
    }

    public function handle(): int
    {
        $modelName = $this->argument(0);

        if (!$modelName) {
            Output::error('Debe especificar un nombre de modelo');
            Output::line();
            Output::line($this->getUsage());
            return 1;
        }

        try {
            $rebuild = $this->hasOption('rebuild');

            if ($rebuild) {
                Output::warning("Reconstruyendo tabla '{$modelName}'...");
                
                // Eliminar tabla existente
                $db = Connector::instance();
                $db->query("DROP TABLE IF EXISTS {$modelName}");
                
                Output::info('Tabla eliminada');
            }

            // Enlazar el modelo (crea la tabla)
            Output::info("Enlazando modelo '{$modelName}'...");
            $model = RecordSet::bind($modelName);

            Output::success("Modelo '{$modelName}' enlazado correctamente");
            
            if ($rebuild) {
                Output::warning('La tabla ha sido recreada. Los datos anteriores se han perdido.');
            }

            return 0;

        } catch (\Throwable $e) {
            Output::error("Error al enlazar modelo: " . $e->getMessage());
            return 1;
        }
    }
}
