<?php
namespace Irbis\Terminal;
use Irbis\Routing\Action;
use Irbis\Exceptions\HttpException;
use Irbis\Module\Controller as iController;


class Controller extends iController {
	public static $name         = 'cli';
    public static $unpackage    = true;

    #[Action('/', priority: 0)]
    final public function indexAction ($request, $response) {
        redirect('/terminal');
    }

    #[Action('/Irbis/Terminal/assets/{asset}')]
    final public function assetAction ($request, $response) {
        $asset = $request->match('asset');
        $asset = "@cli/assets/{$asset}";
        $response->file($asset);

        return $response;
    }

    #[Action('/terminal', 'GET', constraints: AuthorizationConstraint::class)]
    final public function webTerminal ($request, $response) {
        $response->template('@cli/views/index.html');
        
        return $response;
    }

    #[Action('/terminal/command', 'POST', constraints: AuthorizationConstraint::class)]
    final public function command ($request, $response) {
        $command_text = $request->input('command') ?: '';
        $command_object = $this->component('CommandComponent');

        try {
            $command_object->set($command_text);
            return $command_object->execute();
        } catch (\Throwable $e) {
            return "span.error > {$e->getMessage()}";
        }
    }

    #[Action('/terminal/nano', ['GET', 'POST'])]
    final public function nano ($request, $response) {
        // validar autorización
        $authorization = $this->component('AuthorizationConstraint');
        $username = $this->server->getState('server.owner') ?: 'irbis';
        $password = $this->server->getState('server.terminal');

        if ($password) {
            $auth = $request->getHeader('AuthorizationConstraint');
            if (!$authorization->verifyAuthorizationConstraint($auth, $username, $password)) {
                $authorization->unauthorizedResponse($response);
                return 'No autorizado';
            }
        }

        // gestionar archivo
        $file = $request->query('file') ?? null;
        if (!$file)
            return 'Archivo no especificado';
        $file = base64_decode($file);

        if (
            str_contains($file, '..') || 
            str_contains($file, './') || 
            str_contains($file, '\\')
        ) {
            return 'Ruta de archivo no válida';
        }

        $file = str_replace("MyApps/", '', $file);
        $file = "/MyApps/" . ltrim($file, '/');
        $file = BASE_PATH . $file;

        $dirname = dirname($file);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }

        if (!file_exists($file)) {
            if (str_ends_with($file, '.html')) {
                file_put_contents($file, <<<EOL
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title>Nuevo Archivo</title>
    </head>
    <body>

    </body>
</html>
EOL);
            } elseif (str_ends_with($file, '.php') && !str_contains($file, 'models/')) {
                $o = base64_decode($request->query('file'));
                $namespace = explode('/', $o)[0];
                $klass = basename($o, '.php');

                file_put_contents($file, <<<EOL
<?php
namespace MyApps\\{$namespace};
use Irbis\Interfaces\ComponentInterface;
use Irbis\Traits\Component;

class {$klass} implements ComponentInterface {
  use Component;


}
EOL);
            } else {
                file_put_contents($file, '');
            }
        }

        if ($request->isPost()) {
            if ($request->input('save')) {
                $content = $request->input('file_content');
                if ($content) {
                    file_put_contents($file, $content);
                }
            }
            
            if ($request->input('delete')) {
                unlink($file);
                return "Archivo eliminado";
            }
        }
        

        // para compatibilidad con otros motores de
        // renderizado se usa esta forma de cargar la vista
        $file_content = file_get_contents($file);
        $template = file_get_contents($this->namespace('dir') . 'views/nano.html');
        $template = str_replace('{file_content}', $file_content, $template);
        
        $response->setHeader('Content-Type: text/html');
        $response->body($template);
    }

    #[Action('/terminal/sql')]
    final public function sql ($request, $response) {
        // validar autorización
        $authorization = $this->component('AuthorizationConstraint');
        $username = $this->server->getState('server.owner') ?: 'irbis';
        $password = $this->server->getState('server.terminal');

        if ($password) {
            $auth = $request->setHeader('AuthorizationConstraint');
            if (!$authorization->verifyAuthorizationConstraint($auth, $username, $password)) {
                $authorization->unauthorizedResponse($response);
                return 'No autorizado';
            }
        }

        $connector = \Irbis\Orm\Connector::instance();
        $db_info = $connector->getInfo();

        if ($db_info['driver'] !== 'sqlite') {
            return "DSN no soportado";
        }

        // ejecutar consulta
        $query = '';
        $result = '';
        if ($request->isPost()) {
            $query = $request->input('query') ?: '';
            if ($query) {
                try {
                    $result = $connector->query($query);
                    if (str_starts_with(strtolower(trim($query)), 'select')) {
                        $result = $result->fetchAll(\PDO::FETCH_ASSOC);
                        $result = json_encode($result, JSON_PRETTY_PRINT);
                    } else {
                        $result = "Consulta ejecutada correctamente. Filas afectadas: " . $result->rowCount();
                    }
                } catch (\Throwable $e) {
                    $result = $e->getMessage(); 
                }
            }
        }

        // para compatibilidad con otros motores de
        // renderizado se usa esta forma de cargar la vista
        $template = file_get_contents($this->namespace('dir') . 'views/sql.html');
        $template = str_replace('{query_content}', $query, $template);
        $template = str_replace('{result_content}', $result, $template);
        
        $response->setHeader('Content-Type: text/html');
        $response->body($template);
    }
}
