<?php
namespace Irbis\Terminal;
use Irbis\Interfaces\ComponentInterface;
use Irbis\Traits\Component;
use Irbis\Server;
use Irbis\Orm\RecordSet;
use Irbis\Orm\Connector;

class Command implements ComponentInterface {
    use Component;

    public $allowed = [
        'version',
        'usermod', 'passwd', 'uname', 'whoami', 'date', 
        'newapp', 'install', 'ls', 'info', 'remove',
        'git', 'conn', 'bindmodel',
    ];

    public $command;
    public $params;
    public $invalid = "span.error > Comando inválido";

    public function validate ($command) {
        $cmd = explode(' ', trim($command));

        if (!$cmd) return false;

        if (
            // str_contains($command, ';') || 
            str_contains($command, '&&') || 
            str_contains($command, '||')
        ) {
            return false;
        }

        $this->command = array_shift($cmd);
        $this->params = $cmd;
        
        return in_array($this->command, $this->allowed);
    }

    public function execute () {
        $method_cmd = "cmd" . ucfirst($this->command);
        if (method_exists($this, $method_cmd)) {
            return $this->{$method_cmd}();
        } else {
            return $this->cmdDefault();
        }
    }

    private function cmdVersion () {
        return "span.info > Irbis Framework v3.0";
    }

    private function cmdUsermod () {
        if (!count($this->params))
            return "span.error > Debe especificar un nuevo nombre de usuario";
        $server = Server::getInstance();
        $server->setState('server.owner', $this->params[0]);
        return "span.success > Nombre de usuario actualizado correctamente";
    }

    private function cmdPasswd () {
        if (!count($this->params))
            return "span.error > Debe especificar una nueva contraseña";
        $server = Server::getInstance();
        $server->setState('server.terminal', password_hash($this->params[0], PASSWORD_BCRYPT));
        return "span.success > Contraseña actualizada correctamente";
    }

    private function cmdNewapp () {
        if (!count($this->params))
            return "span.error > Debe especificar un nombre para la nueva aplicación";

        $app_name = str_replace([' ', '/', '\\'], '', ucfirst($this->params[0]));
        $app_path = BASE_PATH . "/MyApps/{$app_name}/Controller.php";
        if (file_exists($app_path))
            return "span.error > Ya existe una aplicación con ese nombre";

        // crear la estructura de carpetas
        mkdir(BASE_PATH . "/MyApps/{$app_name}", 0755, true);
        mkdir(BASE_PATH . "/MyApps/{$app_name}/assets", 0755, true);
        mkdir(BASE_PATH . "/MyApps/{$app_name}/views", 0755, true);

        // crear el archivo Controller.php
        $alias = strtolower($app_name);
        $owner = Server::getInstance()->getState('server.owner') ?: 'Desconocido';
        $code = <<<EOL
<?php
namespace MyApps\\{$app_name};
use Irbis\Controller as iController;

class Controller extends iController {
  public static \$name = '{$alias}';
  public static \$version = '1.0';
  public static \$author = '{$owner}';
  public static \$depends = [];
  public static \$description = '';

  /**
   * @route /
   */
  final public function index () {
    return 'Mi nueva aplicación';
  }
}
EOL;
        file_put_contents($app_path, $code);
        return "span.success > Aplicación '{$app_name}' creada";
    }

    private function cmdInstall () {
        $server = Server::getInstance();
        if (!$this->params)
            return "span.error > Debe especificar un namespace de aplicación";
        
        $ctrl = $server->buildController($this->params[0]);
        $server->installApp($ctrl);
        return "span.success > Aplicación '{$ctrl::$name}' instalada correctamente";
    }

    private function cmdInfo () {
        if (!$this->params)
            return "span.error > Debe especificar un namespace de aplicación";

        $server = Server::getInstance();
        $app = $server->buildController($this->params[0]);

        return "div.text > - versión: " . ($app::$version ?? 'n/a')
            . "\n- alias: " . ($app::$name ?? 'n/a')
            . "\n- autor: " . ($app::$author ?? 'desconocido')
            . "\n- dependencias: " . (implode(', ', $app::$depends ?? []) ?: '')
            . "\n- descripción: " . ($app::$description ?? 'sin descripción');
    }

    private function cmdRemove () {
        if (!$this->params)
            return "span.error > Debe especificar un namespace de aplicación";

        $server = Server::getInstance();

        // desactivar terminal
        if ($this->params[0] == 'Terminal') {
            $server->setState('server.terminal', false);
            return "span.warning > Terminal desactivada.";
        }

        $ctrl = $server->getController($this->params[0]);
        if (!$ctrl)
            return "span.warning > La aplicación '{$this->params[0]}' no está instalada";
        $server->uninstallApp($ctrl);
        return "span.warning > Aplicación '{$this->params[0]}' removida correctamente";
    }

    private function cmdConn () {
        $subcmd = $this->params[0] ?? '';
        if ($subcmd == 'reset') {
            $server = Server::getInstance();
            $server->setState('database.dsn', 'sqlite:database.db3');
            $server->setState('database.user', "");
            $server->setState('database.pass', "");
            return "span.warning > Conexión a base de datos por defecto restaurada";
        }

        if (count($this->params) < 2)
            return "span.error > Parámetros insuficientes.";
        if (!in_array($subcmd, ['dsn', 'user', 'pass']))
            return "span.error > Subcomando desconocido.";
        
        $server = Server::getInstance();
        $server->setState("database.{$subcmd}", $this->params[1]);
        return "span.success > {$subcmd} actualizado correctamente";
    }

    private function cmdBindmodel () {
        if (!$this->params)
            return "span.error > Debe especificar un nombre de modelo";

        $force = $this->params[1] ?? false;
        if ($force === '--rebuild') {
            $db = Connector::getInstance();
            $db->query("DROP TABLE IF EXISTS {$this->params[0]}");
        }

        $model_name = $this->params[0];
        $model = RecordSet::bind($model_name);
        return "span.success > Modelo '{$model_name}' enlazado correctamente";
    }

    private function cmdDefault () {
        $server = Server::getInstance();

        if ($this->command == 'ls') {
            $subcmd = $this->params[0] ?? '';
            // comando: ls apps
            if ($subcmd == 'apps') {
                $output = "span.text > Aplicaciones disponibles: \n";
                $iapps = $server->getState('apps') ?: [];
                $oapps = array_map(function ($app) use ($iapps) {
                    $app_name = trim(str_replace([BASE_PATH, 'Controller.php'], '', $app), '/');
                    $flag = in_array($app_name, $iapps) ? '[*]' : '[ ]';
                    return "{$flag} " . $app_name;
                }, glob('*Apps/*/Controller.php'));

                return $output . implode("\n", $oapps);
            }
        }

        $full_cmd = implode(' ', array_merge([$this->command], $this->params));
        return "span.info > " . (shell_exec("$full_cmd 2>&1") ?: '');
    }
}