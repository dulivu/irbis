<?php
namespace Irbis\Terminal;
use Exception;
use Irbis\Traits\Component;
use Irbis\Server;
use Irbis\Orm\RecordSet;
use Irbis\Orm\Connector;

class CommandComponent
{
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

    public function set (string $command) 
    {
        $cmd = explode(' ', trim($command));

        if (!$cmd) throw new Exception("Comando vacío");

        if (
            // str_contains($command, ';') || 
            str_contains($command, '&&') || 
            str_contains($command, '||')
        ) {
            throw new Exception("Comando inválido");
        }

        $this->command = array_shift($cmd);
        $this->params = $cmd;
        
        if (!in_array($this->command, $this->allowed)) {
            throw new Exception("Comando no permitido");
        }
    }

    public function execute () 
    {
        $command_name = "cmd" . ucfirst($this->command);
        $command_params = $this->params;

        if (method_exists($this, $command_name)) {
            return $this->{$command_name}(...$command_params);
        } else {
            return $this->cmdDefault();
        }
    }

    private function cmdVersion () 
    {
        return "span.info > Irbis Framework v3.0";
    }

    private function cmdUsermod ($username = '') 
    {
        if (!$username)
            throw new Exception("Debe especificar un nuevo nombre de usuario");

        $this->server->state->set('server.owner', $username);
        
        return "span.info > Nombre de usuario actualizado correctamente";
    }

    private function cmdPasswd ($password = '') 
    {
        if (!$password)
            throw new Exception("Debe especificar una nueva contraseña");

        $this->server->state->set('server.terminal', password_hash($password, PASSWORD_BCRYPT));

        return "span.info > Contraseña actualizada correctamente";
    }

    private function cmdNewapp ($app_name = '') 
    {
        if (!$app_name)
            throw new Exception("Debe especificar un nombre para la nueva aplicación");

        $app_name = str_replace([' ', '/', '\\'], '', ucfirst($app_name));
        $app_path = BASE_PATH . "/MyApps/{$app_name}/Controller.php";

        if (file_exists($app_path))
            throw new Exception("Ya existe una aplicación con ese nombre");

        // crear la estructura de carpetas
        mkdir(BASE_PATH . "/MyApps/{$app_name}", 0755, true);
        mkdir(BASE_PATH . "/MyApps/{$app_name}/assets", 0755, true);
        mkdir(BASE_PATH . "/MyApps/{$app_name}/models", 0755, true);
        mkdir(BASE_PATH . "/MyApps/{$app_name}/views", 0755, true);

        // crear el archivo Controller.php
        $alias = strtolower($app_name);
        $owner = $this->server->state('server.owner') ?: 'Desconocido';
        $template = $this->controller->namespace('dir') . '/templates/new_controller.txt';

        if (!file_exists($template)) {
            throw new Exception("No se encontró la plantilla de generación de controlador");
        }

        $template = file_get_contents($template);
        $replacements = [
            '{{APP_NAME}}' => $app_name,
            '{{ALIAS}}'    => $alias,
            '{{OWNER}}'    => $owner,
        ];

        $code = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        file_put_contents($app_path, $code);

        return "span.info > Aplicación '{$app_name}' creada";
    }

    private function cmdInstall ($app_name = '') 
    {
        if (!$app_name)
            throw new Exception("Debe especificar un nombre de aplicación");
        
        $controller = $this->server->controllers->push($app_name);

        $controller->install();

        return "span.info > Aplicación '{$controller::$name}' instalada correctamente";
    }

    private function cmdInfo ($app_name = '') 
    {
        if (!$app_name)
            throw new Exception("Debe especificar un nombre de aplicación");

        $app = $this->server->controllers->fromNamespace($app_name);

        return "div.text > - versión: " . ($app::$version ?? 'n/a')
            . "\n- alias: " . ($app::$name ?? 'n/a')
            . "\n- autor: " . ($app::$author ?? 'desconocido')
            . "\n- dependencias: " . (implode(', ', $app::$depends ?? []) ?: '')
            . "\n- descripción: " . ($app::$description ?? 'sin descripción');
    }

    private function cmdRemove ($app_name = '') 
    {
        if (!$app_name)
            throw new Exception("Debe especificar un nombre de aplicación");

        // desactivar terminal
        if ($app_name == 'Terminal') {
            $this->server->state->set('server.terminal', false);
            return "span.warning > Terminal desactivada.";
        }

        $ctrl = $this->server->controllers->get($app_name);

        if (!$ctrl)
            throw new Exception("No se encontró la aplicación especificada");

        $ctrl->uninstall();

        return "span.warning > Aplicación desinstalada correctamente";
    }

    private function cmdConn ($subcmd = '', $value = '') 
    {
        if ($subcmd == 'reset') {
            $this->server->state->set('database.dsn', 'sqlite:database.db3');
            $this->server->state->set('database.user', "");
            $this->server->state->set('database.pass', "");
            return "span.info > Conexión a base de datos por defecto restaurada";
        }
            
        if (!in_array($subcmd, ['dsn', 'user', 'pass']))
            throw new Exception("Subcomando no reconocido");
        if (!$value)
            throw new Exception("Parámetros insuficientes");
        
        $this->server->state->set("database.{$subcmd}", $value);
        return "span.info > {$subcmd} actualizado correctamente";
    }

    private function cmdBindmodel () 
    {
        if (!$this->params)
            return "span.error > Debe especificar un nombre de modelo";

        $force = $this->params[1] ?? false;
        if ($force === '--rebuild') {
            $db = Connector::instance();
            $db->query("DROP TABLE IF EXISTS {$this->params[0]}");
        }

        $model_name = $this->params[0];
        $model = RecordSet::bind($model_name);
        return "span.success > Modelo '{$model_name}' enlazado correctamente";
    }

    private function cmdDefault () 
    {
        $server = Server::instance();

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