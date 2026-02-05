<?php
namespace Irbis\Orm;

use Irbis\Server;
use Irbis\Traits\Singleton;
use Irbis\Interfaces\QueryInterface;

/**
 * Envoltura del objeto PDO
 * Usa patron strategy para determinar el builder SQL, por medio del driver
 * Usa patron facade para exponer método SQL sin exponer el builder
 * Usa patron singleton para la conexión única
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class Connector extends \PDO {
    use Singleton;

    private static $builder_registry = [
        'sqlite' => BuilderSqlite::class,
        'mysql' => BuilderMysql::class,
        'pgsql' => BuilderPostgresql::class
    ];
    private static QueryInterface $builder;

    public static function __callStatic ($name, $arguments) {
        return self::$builder->$name(...$arguments);
    }

    private function __construct () {
        $server = Server::getInstance();
        $o = $server->getState('database') ?: null;
        if (!$o)
            throw new \PDOException("Options is required for database config, check server state file");
        parent::__construct($o['dsn'], $o['user'] ?? null, $o['pass'] ?? null, $o['attr'] ?? null);
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        // determinar el builder por medio del driver
        $driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $builder_class = self::$builder_registry[$driver] ?? null;
        if (!$builder_class)
            throw new \PDOException("Database driver '{$driver}' not supported");
        self::$builder = new $builder_class();
        $inits = (array) self::$builder->queryInit();
        foreach ($inits as $init) $this->exec($init);

        // iniciar transaccion
        $this->beginTransaction();
    }

    public function savepoint (string $name): bool {
        $this->exec("SAVEPOINT {$name}");
        return $name;
    }

    public function commit (): bool {
        if ($this->inTransaction()) {
            parent::commit();
            $this->beginTransaction();
        } return true;
    }

    public function close () : bool {
        if ($this->inTransaction()) {
            parent::commit();
        } return true;
    }

    public function rollBack ($savepoint = ''): bool {
        if ($this->inTransaction()) {
            if ($savepoint) {
                return $this->exec("ROLLBACK TO SAVEPOINT {$savepoint}") !== false;
            }
            return parent::rollBack();
        } return false;
    }

    public function release (string $savepoint): void {
        if ($this->inTransaction()) {
            $this->exec("RELEASE SAVEPOINT {$savepoint}");
        }
    }
    
    /**
     * devuelve información sobre la conexión
     */
    public function getInfo(): array {
        $driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $client_version = $this->getAttribute(\PDO::ATTR_CLIENT_VERSION) ?? 'N/A';
        $server_info = 'N/A';
        if ($driver == 'mysql') {
            $server_info = $this->getAttribute(\PDO::ATTR_SERVER_INFO) ?? 'N/A';
        } elseif ($driver == 'pgsql') {
            $server_info = $this->query('SELECT version()')->fetchColumn() ?: 'N/A';
        } elseif ($driver == 'sqlite') {
            $server_info = \SQLite3::version()['versionString'] ?? 'N/A';
        }
        return [
            'driver' => $driver,
            'server_info' => $server_info,
            'client_version' => $client_version
        ];
    }
}
