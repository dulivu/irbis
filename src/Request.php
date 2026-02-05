<?php

namespace Irbis;

use Irbis\Action;
use Irbis\Tools\Json;
use Irbis\Traits\Singleton;
use Irbis\Exceptions\HttpException;
use Irbis\Interfaces\SessionInterface;

/**
 * Gestiona la solicitud del cliente
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class Request {
    use Singleton;

    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';
    const DELETE = 'DELETE';
    const PATCH = 'PATCH';
    
    private $uri = [];
    private $headers = [];
    public ?Action $action;
    public ?SessionInterface $session;

    /**
     * @exclusive, singleton
     */
    private function __construct() {
        $this->parseRequest();
        $this->parseHeaders();
    }

    /**
     * @exclusive, __construct
     * rellena el arreglo $uri con los componentes de la URL
     */
    private function parseRequest() {
        $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
        // desglosa la URL en un array con sus componentes, quitando index.php si existe
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/');
        $uri['path'] = preg_replace('/\/(i|I)ndex\.php(\/)?/', '/', $uri['path'] ?? '/', 1); # /path/to/resource
        $uri['query'] = $uri['query'] ?? ''; # ?param=value
        $uri['method'] = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri['host'] = $scheme . "://" .($_SERVER['HTTP_HOST'] ?? 'localhost'); # http://localhost:port
        $this->uri = $uri;
    }

    /**
     * @exclusive, __construct
     * rellena las cabeceras HTTP en $this->headers
     */
    private function parseHeaders() {
        if(function_exists('getallheaders')) {
            $this->headers = getallheaders();
        } else {
            $this->headers = [];
            foreach($_SERVER as $key => $value) {
                if(substr($key, 0, 5) === 'HTTP_') {
                    $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                    $this->headers[$headerName] = $value;
                }
            }
        }
    }

    /**
     * convierte el objeto en una URL completa
     */
    public function __toString() {
        $uri = $this->uri;
        $query =($uri['query'] ? '?' : '') . $uri['query'];
        return $uri['host'] . $uri['path'] . $query;
    }

    /**
     * para validar los valores de $uri ó $_SERVER
     */
    public function __isset(string $name): bool { 
        return array_key_exists($name, $this->uri) || 
            array_key_exists(strtoupper($name), $_SERVER) ||
            ($name == 'user');
    }

    /**
     * para obtener los valores de $uri o $_SERVER
     */
    public function __get(string $name) { 
        # obtener propiedades no especificadas de la clase
        if (array_key_exists($name, $this->uri)) {
            return $this->uri[$name];
        }
        if (array_key_exists(strtoupper($name), $_SERVER)) {
            return $_SERVER[strtoupper($name)];
        }
        if ($name == 'user') {
            return $this->session ? $this->session->getUser() : null;
        }
        return null;
    }
    
    /**
     * obtiene un valor de un arreglo, con soporte para claves múltiples
     * @param $key == '*', devuelve todo el arreglo
     * @param $key == '*!', devuelve todo el arreglo filtrado (sin valores nulos o vacíos)
     * @param $key == [keys], devuelve un arreglo con las claves especificadas
     */
    private function getFromArray($arr, $key) {
        if ($key === '*') return $arr;
        if ($key === '*!') return array_filter($arr, function ($v) { return $v; });
        if (is_array($key)) {
            if (is_assoc($key)) {
                throw new \InvalidArgumentException("The key array cannot be associative.");
            } else {
                return array_combine($key, array_map(function($i) use ($arr) {
                    return $arr[$i] ?? null;
                }, $key));
            }
        }
        return $arr[$key] ?? null;
    }

    /**
     * obtiene el valor de una cabecera HTTP
     */
    public function header(string $key): ?string {
        foreach($this->headers as $name => $value) {
            if(strcasecmp($name, $key) === 0) {
                return $value;
            }
        }
        return null;
    }

    /**
     * obtiene la IP del cliente
     */
    public function getClientIp(): string {
        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP', 
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach($ipKeys as $key) {
            if(!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Si hay múltiples IPs, tomar la primera
                if(strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validar que sea una IP válida
                if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? false;
    }

    /**
     * indica si existen archivos subidos
     */
    public function hasUploads(?string $key = null): bool {
        if($key === null) return !empty($_FILES);
        return isset($_FILES[$key]);
    }

    /**
     * genera un error HTTP basado en el código de error de subida
     */
    private function validateUpload(int $error): void {
        switch($error) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new HttpException(400, 'No se ha subido ningún archivo.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new HttpException(400, 'El archivo excede el límite de tamaño.');
            case UPLOAD_ERR_PARTIAL:
                throw new HttpException(400, 'El archivo se subió parcialmente.');
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new HttpException(500, 'Falta directorio temporal.');
            case UPLOAD_ERR_CANT_WRITE:
                throw new HttpException(500, 'Error al escribir archivo.');
            case UPLOAD_ERR_EXTENSION:
                throw new HttpException(400, 'Extensión de archivo bloqueada.');
            default:
                throw new HttpException(500, 'Error desconocido al subir archivo.');
        }
    }
    
    /**
     * itera sobre los archivos subidos por medio de un callback
     */
    public function walkUploads(string $key, \Closure $callback): void {
        if(!isset($_FILES[$key])) {
            return;
        }

        if(is_array($_FILES[$key]['name'])) {
            foreach($_FILES[$key]['name'] as $k => $val) {
                $file = [
                    'name' => $_FILES[$key]['name'][$k],
                    'type' => $_FILES[$key]['type'][$k],
                    'tmp_name' => $_FILES[$key]['tmp_name'][$k],
                    'error' => $_FILES[$key]['error'][$k],
                    'size' => $_FILES[$key]['size'][$k],
                ];
                $this->validateUpload($file['error']);
                $callback($file);
            }
        } else {
            $this->validateUpload($_FILES[$key]['error']);
            $callback($_FILES[$key]);
        }
    }

    /* -= basic use =- */
    public function upload(string $key, string $save_to) {
        $this->walkUploads($key, function($file) use ($save_to) {
            move_uploaded_file($file['tmp_name'], $save_to);
        });
    }
    
    public function isGet(): bool { return $this->method === self::GET; }
    public function isPost(): bool { return $this->method === self::POST; }
    public function isPut(): bool { return $this->method === self::PUT; }
    public function isDelete(): bool { return $this->method === self::DELETE; }
    public function isPatch(): bool { return $this->method === self::PATCH; }
    public function isJson(): bool { return str_contains($this->header('Content-Type') ?? '', 'application/json'); }
    public function query($key) { return $this->getFromArray($_GET, $key); }
    public function input($key) { return $this->getFromArray($_POST, $key); }
    public function cookie($key) { return $this->getFromArray($_COOKIE, $key); }
    public function rawContent(): string { return file_get_contents('php://input') ?: ''; }
    public function path(int $key) { return $this->action ? $this->action->getPathMatch($key) : null; }
}
