<?php
namespace Irbis\Terminal;
use Irbis\Server;
use Irbis\Exceptions\HttpException;
use Irbis\Controller as iController;

class Controller extends iController {
	public static $name         = 'cli';
    public static $unpackage    = true;

    /**
     * @route ?/
     */
    final public function index ($request, $response) {
        $response->header('Cache-Control: no-cache, no-store, must-revalidate');
        $response->header('Location: /terminal');
    }

    /**
     * @route /terminal
     */
    final public function webTerminal ($request, $response) {
        $server = Server::getInstance();
        $authorization = $this->component('Authorization');
        $username = $server->getState('server.owner') ?: 'irbis';
        $password = $server->getState('server.terminal');

        if ($password) {
            $auth = $request->header('Authorization');
            if (!$authorization->verifyAuthorization($auth, $username, $password)) {
                $authorization->unauthorizedResponse($response);
                return 'No autorizado';
            }
        }

        return '@cli/{view}.html';
    }

    /**
     * @route /Irbis/Terminal/assets/(:any)
     */
    final public function asset ($request, $response) {
        $path = $this->namespace('dir') . "assets/" . $request->path(0);
        if (!file_exists($path)) throw new HttpException(404);
        $response->header('Content-Length: ' . filesize($path));
        $response->header('Content-Type: ' . mime_type($path));
        $response->body(file_get_contents($path));
    }

    /**
     * @verb POST
     * @route /terminal/command
     */
    final public function command ($request, $response) {
        // validar autorización
        $server = Server::getInstance();
        $authorization = $this->component('Authorization');
        $username = $server->getState('server.owner') ?: 'irbis';
        $password = $server->getState('server.terminal');

        if ($password) {
            $auth = $request->header('Authorization');
            if (!$authorization->verifyAuthorization($auth, $username, $password)) {
                $authorization->unauthorizedResponse($response);
                return 'span.error > No autorizado';
            }
        }

        // validar y ejecutar el comando
        $command = $this->component('Command');
        if (!$command->validate($request->input('command') ?: ''))
            return $command->invalid;
        else {
            try {
                return $command->execute();
            } catch (\Throwable $e) {
                return "span.error > {$e->getMessage()}";
            }
        }
    }

    /**
     * @route /terminal/nano
     */
    final public function nano ($request, $response) {
        // validar autorización
        $server = Server::getInstance();
        $authorization = $this->component('Authorization');
        $username = $server->getState('server.owner') ?: 'irbis';
        $password = $server->getState('server.terminal');

        if ($password) {
            $auth = $request->header('Authorization');
            if (!$authorization->verifyAuthorization($auth, $username, $password)) {
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
        
        $response->header('Content-Type: text/html');
        $response->body($template);
    }

    /**
     * @route /terminal/sql
     */
    final public function sql ($request, $response) {
        // validar autorización
        $server = Server::getInstance();
        $authorization = $this->component('Authorization');
        $username = $server->getState('server.owner') ?: 'irbis';
        $password = $server->getState('server.terminal');

        if ($password) {
            $auth = $request->header('Authorization');
            if (!$authorization->verifyAuthorization($auth, $username, $password)) {
                $authorization->unauthorizedResponse($response);
                return 'No autorizado';
            }
        }

        $connector = \Irbis\Orm\Connector::getInstance();
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
        
        $response->header('Content-Type: text/html');
        $response->body($template);
    }
}
