<?php
namespace Irbis\Http;
use RuntimeException;
use Closure;
use DOMdocument;
use DOMXPath;
use Irbis\Http\Status;
use Irbis\Http\Headers;
use Irbis\File\File;
use Irbis\Tools\Json;
use Irbis\Tools\Xml;


class Response 
{
    public Status $status;
    public Headers $headers;

    private string $body; // recepción
    private array $data = []; // envío
    private ?File $file = null; // envío
    private string $type = 'data'; // e: data, template, file, download, stream
    private ?Request $from_request = null; // recepción @hidden

    public function __construct (
        Status|int $status = 200, 
        array|Headers $headers = [],
        string $body = '',
        ?Request $from_request = null
    ) {
        $this->status = is_int($status) ? Status::from($status) : $status;
        $this->headers = is_array($headers) ? new Headers($headers) : $headers;
        $this->body = $body;
        $this->from_request = $from_request;
    }

    public function type(): string 
    {
        return $this->type;
    }

    public function path(): ?string
    {
        return $this->file ? $this->file->path() : null;
    }

    /* -= métodos de envío =- */

    public function template(string|File $path, array $data = []): void 
    {
        $this->type = 'template';
        $this->file = File::from($path);
        $this->headers['content-type'] = 'text/html';
        $this->data = array_merge($this->data, $data);
    }

    public function file(string|File $path): void 
    {
        $this->type = 'file';
        $this->file = File::from($path);
        $this->headers['content-type'] = $this->file->info('mime');
        $this->headers['content-length'] = $this->file->info('size');
    }

    public function download(string|File $path): void 
    {
        $this->type = 'download';
        $this->file = File::from($path);
        $this->headers['content-type'] = $this->file->info('mime');
        $this->headers['content-length'] = $this->file->info('size');
        $this->headers['content-disposition'] = "attachment; filename=\"".$this->file->info('basename')."\"";
    }

    public function stream(string|File $path): void 
    {
        $this->type = 'stream';
        $this->file = File::from($path);
        $this->headers['content-type'] = $this->file->info('mime');
        $this->headers['content-length'] = $this->file->info('size');
        $this->headers['Cache-Control'] = 'no-cache';
        $this->headers['Accept-Ranges'] = 'bytes';
    }

    public function binary(string $content, $options = null): void
    {
        $this->type = 'binary';
        // determinar el tipo mime
        if (is_string($options)) {
            $filename = $options;

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($content);
            if (!$mime) {
                $mime = 'application/octet-stream';
            }
        } elseif (is_array($options)) {
            $filename = $options[0];
            $mime = $options[1];
        } else {
            $filename = 'download.bin';
            $mime = 'application/octet-stream';
        }

        $this->type = 'binary';
        $this->body = $content;
        $this->headers['content-type'] = $mime;
        $this->headers['content-length'] = strlen($content);
        $this->headers['content-disposition'] =
            "attachment; filename=\"{$filename}\"";
    }

    /* -= métodos de recepción =- */

    public function data($data = null) 
    {
        // envío
        if ($data) {
            $this->type = 'data';
            if (is_assoc($data)) {
                // envío de datos por defecto JSON
                if (!isset($this->headers['content-type'])) {
                    $this->headers['content-type'] = 'application/json';
                }

                $this->data = array_merge($this->data, $data);
            } else {
                $this->headers['content-type'] = 'text/plain';
                if (is_array($data)) {
                    $data = implode(',', $data);
                }
                $this->body = (string) $data;
            }
            return;
        }

        // recepción
        if ($this->headers->content('application/json')) {
            return $this->json();
        }
        
        if (
            $this->headers->content('application/xml') || 
            $this->headers->content('text/xml') // legacy
        ) {
            return $this->xml();
        } 
        
        return $this->body;
    }

    public function json(): array 
    {
        return Json::decode($this->body);
    }

    public function xml(): array 
    {
        return Xml::decode($this->body);
    }

    public function html(): DOMDocument
    {
        $doc = new DOMDocument();

        libxml_use_internal_errors(true);
        $doc->loadHTML($this->body);
        libxml_clear_errors();

        return $doc;
    }

    public function xpath(): DOMXPath
    {
        $doc = $this->html();
        return new DOMXPath($doc);
    }

    public function save(string|Directory $destination): File 
    {
        // aplica sólamente cuando la respuesta 
        // se ha generado a partir de una solicitud HTTP
        if (!$this->from_request) {
            throw new RuntimeException("Cannot save response without original request context.");
        }

        $file_name = $this->getSaveFileName();

        if ($destination instanceof Directory) {
            $file = $destination->file($file_name);
        } else {
            $destination = str_replace(['{filename}', '*'], $file_name, $destination);
            $file = new File($destination);
        }

        // Limpiar el archivo si existe y escribir el contenido
        if ($file->exists()) {
            $file->truncate();
        }
        
        $file->write($this->body);
        
        return $file;
    }

    /* -= gestión de envío =- */

    public function send(): void
    {
        $emitter = Emitter::from($this, $this->type);

        if ($this->file) { // file, template, download, stream
            if (!$this->file->exists())
                throw new RuntimeException("file '{$this->file->path(true)}' not found");
            $emitter($this->file, $this->data);
        } else { // data, binary
            $emitter($this->buildBody());
        }
    }

    private function buildBody(): string 
    {
        $body = '';

        if ($this->data && $this->headers->content('application/json')) {
            $body = Json::encode($this->data);
        } elseif (
            $this->data && (
                $this->headers->content('application/xml') ||
                $this->headers->content('text/xml') // legacy
            )
        ) {
            $body = Xml::encode($this->data);
        } else {
            if ($this->data) { // por defecto formato http
                $body = http_build_query($this->data);
            } else {
                $body = $this->body;
            }
        }

        return $body;
    }

    /* -= internos =- */

    private function getSaveFileName(): string 
    {
        $disposition = $this->headers['content-disposition'] ?? null;

        if ($disposition) {
            // filename*=UTF-8''
            if (preg_match('/filename\*\s*=\s*UTF-8\'\'(.+)/i', $disposition, $matches)) {
                return urldecode($matches[1]);
            }

            // filename="..."
            if (preg_match('/filename\s*=\s*"([^"]+)"/i', $disposition, $matches)) {
                return $matches[1];
            }
        }

        $fileName = basename($this->from_request->uri->path);
        if ($fileName && str_contains($fileName, '.')) {
            return $fileName;
        }

        return 'download.' . $this->inferFileExtension();
    }

    private function inferFileExtension(): string 
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($this->body);
        if (!$mimeType) {
            return 'bin';
        }

        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/json' => 'json',
            'text/plain' => 'txt',
            'text/html' => 'html',
            'application/xml' => 'xml',
            'text/xml' => 'xml'
        ];

        return $mimeMap[$mimeType] 
            ?? explode('/', $mimeType)[1] 
            ?? 'bin';
    }
}
