<?php
namespace Irbis\Http;
use CurlHandle;
use CURLFile;
use RuntimeException;
use Irbis\File\File;


/**
 * Esta clase no se documenta ya que es una extensión de Request
 * utilizada exclusivamente para enviar peticiones HTTP a otros servidores.
 * No es parte de la API pública del framework
 */
class Curl 
{
    private CurlHandle|false $handle;
    private array $options = [];

    public function __construct(string $url) 
    {
        $this->handle = curl_init();
        
        // Configuraciones básicas predeterminadas
        $this->setOption(CURLOPT_URL, $url);
        $this->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->setOption(CURLOPT_HEADER, true);
        $this->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->setOption(CURLOPT_MAXREDIRS, 5);
        $this->setOption(CURLOPT_TIMEOUT, 30);
        $this->setOption(CURLOPT_CONNECTTIMEOUT, 10);
        
        // Para desarrollo: deshabilitar verificación SSL
        // En producción, configura curl.cainfo en php.ini
        $this->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $this->setOption(CURLOPT_SSL_VERIFYHOST, false);
    }

    public function setOption(int $option, mixed $value): self 
    {
        $this->options[$option] = $value;
        curl_setopt($this->handle, $option, $value);
        return $this;
    }

    public function setMethod(Method $method): self 
    {
        switch ($method) {
            case Method::POST:
                $this->setOption(CURLOPT_POST, true);
                break;
            case Method::PUT:
                $this->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case Method::DELETE:
                $this->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case Method::PATCH:
                $this->setOption(CURLOPT_CUSTOMREQUEST, 'PATCH');
                break;
            case Method::HEAD:
                $this->setOption(CURLOPT_NOBODY, true);
                break;
            case Method::OPTIONS:
                $this->setOption(CURLOPT_CUSTOMREQUEST, 'OPTIONS');
                break;
            case Method::GET:
            default:
                $this->setOption(CURLOPT_HTTPGET, true);
                break;
        }
        return $this;
    }

    private function prepareHeaders(Headers $headers): array 
    {
        $prepared = [];
        foreach ($headers as $key => $value) {
            $prepared[] = ucwords($key, '-') . ': ' . $value;
        }
        return $prepared;
    }

    public function setHeaders(Headers $headers): self 
    {
        $headerLines = $this->prepareHeaders($headers);
        if (!empty($headerLines)) {
            $this->setOption(CURLOPT_HTTPHEADER, $headerLines);
        }
        return $this;
    }

    public function setBody(mixed $data): self 
    {
        $this->setOption(CURLOPT_POSTFIELDS, $data);
        return $this;
    }

    public function setFiles($files, $inputData = []): void 
    {
        $postData = [];
            
        foreach ($inputData as $key => $value) {
            $postData[$key] = $value;
        }
        
        foreach ($files as $key => $fileGroup) {
            // Archivo individual
            if ($fileGroup instanceof File) {
                $postData[$key] = new CURLFile(
                    $fileGroup->path(true),
                    $fileGroup->info('mime'),
                    $fileGroup->info('basename')
                );
            } 
            // Array de archivos
            elseif (is_array($fileGroup)) {
                $index = 0;
                foreach ($fileGroup as $file) {
                    if (!$file instanceof File) {
                        throw new RuntimeException("Error sending file on request, unexpected file type.");
                    }
                    
                    // Para arrays, agregar índice numérico al nombre del campo
                    // Ejemplo: documentos[0], documentos[1], etc.
                    $fieldName = $key . "[$index]";
                    
                    $postData[$fieldName] = new CURLFile(
                        $file->path(true),
                        $file->info('mime'),
                        $file->info('basename')
                    );
                    
                    $index++;
                }
            }
            // Tipo inválido
            else {
                throw new RuntimeException("Error sending file on request, unexpected file type.");
            }
        }

        $this->setBody($postData);
    }

    public function execute(): array 
    {
        $response = curl_exec($this->handle);
        
        // Verificar errores de cURL
        if ($response === false) {
            $error = curl_error($this->handle);
            $errno = curl_errno($this->handle);
            $this->close();
            throw new RuntimeException("cURL Error ({$errno}): {$error}");
        }

        // Obtener información de la respuesta
        $httpCode = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
        
        $this->close();

        // Separar headers y body
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        return [
            'code' => $httpCode,
            'headers' => $this->parseHeaders($headers),
            'body' => $body
        ];
    }

    private function parseHeaders(string $raw_headers): array 
    {
        $headers = [];
        $lines = explode("\r\n", $raw_headers);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        return $headers;
    }

    public function getInfo(int $option): mixed 
    {
        return curl_getinfo($this->handle, $option);
    }

    private function close(): void 
    {
        // curl_close() es deprecated desde PHP 8.5
        // El handle se limpia automáticamente al destruir el objeto
        $this->handle = false;
    }

    public function __destruct() 
    {
        $this->close();
    }
}
