<?php
namespace Irbis\Http;
use RuntimeException;
use Closure;
use Exception;
use Irbis\File\File;
use Irbis\Tools\Json;
use Irbis\Tools\Xml;
use Irbis\Exceptions\HttpException;
use Irbis\Attributes\Route;
use Irbis\Interfaces\SessionInterface;


class Request 
{
    public Uri $uri;
    public Method $method;
    public Headers $headers;
    private string $body = '';
    private array $input = [];
    private array $files = []; // Upload[] || File[]

    // matching params
    public string $route;
    public array $matches = [];

    public static function fromGlobals(): static 
    {
        $request = new static(
            Uri::fromGlobals(), 
            Method::fromGlobals(),
            Headers::fromGlobals()
        );

        $request->__constructor(
            file_get_contents('php://input') ?: '',
            $_POST,
            Upload::fromGlobals()
        );

        $request->body = file_get_contents('php://input') ?: '';
        $request->input = $_POST;
        $request->files = Upload::fromGlobals();

        return $request;
    }

    public function __construct(
        string|Uri $uri, 
        string|Method $method = 'GET', 
        array|Headers $headers = []
    ) {
        $this->method = is_string($method) ? Method::from(strtoupper($method)) : $method;
        $this->uri = is_string($uri) ? Uri::fromString($uri) : $uri;
        $this->headers = is_array($headers) ? new Headers($headers) : $headers;
    }

    public function __constructor(
        string $body, 
        array $input, 
        array $files
    ): void {
        $this->body = $body;
        $this->input = $input;
        $this->files = $files;
    }

    public function isGet(): bool 
    { 
        return $this->method === Method::GET;
    }

    public function isPost(): bool 
    { 
        return $this->method === Method::POST; 
    }

    public function isPut(): bool 
    { 
        return $this->method === Method::PUT; 
    }

    public function isPatch(): bool 
    { 
        return $this->method === Method::PATCH; 
    }

    public function isDelete(): bool 
    { 
        return $this->method === Method::DELETE; 
    }

    public function isOptions(): bool 
    { 
        return $this->method === Method::OPTIONS; 
    }

    public function isHead(): bool 
    { 
        return $this->method === Method::HEAD; 
    }

    public function isJson(): bool 
    { 
        return $this->headers->content('application/json'); 
    }

    public function isXml(): bool 
    { 
        return $this->headers->content('application/xml') || 
            $this->headers->content('text/xml'); // legacy
    }

    public function match (string $key) {
        return $this->matches[$key] ?? null;
    }

    public function query(string $key, $value = null)
    {
        // setter
        if ($value !== null) {
            $this->uri->query[$key] = $value;
            return;
        }
        
        // getter
        return $this->uri->query[$key] ?? null;
    }

    public function input(string $key, $value = null)
    {
        // setter
        if ($value !== null) {
            if ($this->isGet())
                $this->method = Method::POST;
            $this->input[$key] = $value;
            return;
        }

        // getter
        if ($this->body && !$this->input) {
            if ($this->isJson()) {
                $this->input = Json::decode($this->body);
            } 
            
            if ($this->isXml()) {
                $this->input = Xml::decode($this->body);
            }
        }
        return $this->input[$key] ?? null;
    }

    // recibir archivos de cliente
    public function upload(string $key): ?Upload
    {
        // getter
        $files = $this->files[$key] ?? null;
        if (!$files) return null;
        return array_shift($this->files[$key]);
    }

    // enviar archivos a servicio
    public function file(string|File $file, string $key = 'file'): void
    {
        if ($this->isGet())
            $this->method = Method::POST;
        $this->headers['content-type'] = 'multipart/form-data';

        $file = File::from($file);

        // si ya hay un File registrado, convertir en array
        // y permitir múltiples archivos con la misma clave
        $current_file = $this->files[$key] ?? null;

        if ($current_file === null) {
            $this->files[$key] = $file;
        }

        elseif ($current_file instanceof File) {
            $this->files[$key] = [$current_file, $file];
        }

        else {
            $this->files[$key][] = $file;
        }
    }

    // @shortcut para enviar datos json
    public function json(array $data): void 
    {
        if ($this->isGet())
            $this->method = Method::POST;
        $this->headers['content-type'] = 'application/json';
        $this->body = Json::encode($data);
    }

    // @shortcut para enviar datos xml
    public function xml(array $data): void
    {
        if ($this->isGet())
            $this->method = Method::POST;
        $this->headers['content-type'] = 'application/xml';
        $this->body = Xml::encode($data);
    }

    // @shortcut para enviar datos generales
    public function data($data): void
    {
        if ($this->isGet())
            $this->method = Method::POST;
        if (is_assoc($data)) {
            $this->input = $data;
        } else {
            if (is_array($data))
                $data = implode(',', $data);
            $this->body = (string) $data;
        }
    }

    public function send(): Response 
    {
        $url = $this->uri->__toString();

        // Inicializar y configurar Curl
        $curl = new Curl($url);
        $curl->setMethod($this->method);

        // validar tipos de metodo y contenido
        if (
            (
                $this->method === Method::GET ||
                $this->method === Method::HEAD 
                //$this->method === Method::DELETE ||
                //$this->method === Method::OPTIONS
            ) &&
            ($this->body || $this->input)
        ) {
            throw new RuntimeException("GET, HEAD, DELETE, and OPTIONS requests should not have data.");
        }

        // input, tiene prioridad sobre body
        // preparar body y headers según contenido
        if ($this->input) {
            if (!isset($this->headers['content-type'])) {
                $this->headers['content-type'] = 'application/x-www-form-urlencoded';
            }

            if ($this->headers->content('application/json')) {
                $this->body = Json::encode($this->input);
            } elseif (
                $this->headers->content('application/xml') || 
                $this->headers->content('text/xml') // legacy
            ) {
                $this->body = Xml::encode($this->input);
            } else {
                $this->body = http_build_query($this->input);
            }
        }

        if (!isset($this->headers['content-type'])) {
            $this->headers['content-type'] = 'text/plain';
        }

        if ($this->files) {
            $curl->setFiles($this->files, $this->input);
        } elseif ($this->body) {
            $curl->setBody($this->body);
        }

        $curl->setHeaders($this->headers);
        $result = $curl->execute();

        return new Response(
            $result['code'], 
            $result['headers'],
            $result['body'],
            $this
        );
    }
}
