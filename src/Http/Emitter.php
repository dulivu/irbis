<?php
namespace Irbis\Http;
use Closure;
use RuntimeException;
use Irbis\Http\Response;
use Irbis\Exceptions\HttpException;
use Irbis\Tools\Json;
use Irbis\Tools\Xml;


/**
 * Esta clase no se documenta ya que es una extensión de Response
 * utilizada exclusivamente para enviar respuestas HTTP al cliente.
 * No es parte de la API pública del framework
 */
class Emitter 
{
    private static $emitters = [
        'data' => [self::class, '__emitData'],
        'binary' => [self::class, '__emitBinary'],
        'template' => [self::class, '__emitTemplate'],
        'file' => [self::class, '__emitFile'],
        'download' => [self::class, '__emitDownload'],
        'stream' => [self::class, '__emitStream']
    ];
    // NOTE: pensado para permitir modificar alguno de los emitters configurados
    // especialmente cuando se planea usar un motor de plantillas e: twig
    // no está pensado para agregar nuevos tipos de emitters por el momento
    public static function register(string $type, callable $callback): void 
    {
        self::$emitters[$type] = $callback;
    }

    public static function from(Response $response, string $type): Closure 
    {
        // establecer código de estado HTTP
        http_response_code($response->status->value);
        
        // establecer encabezados HTTP
        foreach ($response->headers as $key => $value) {
            header("$key: $value");
        }

        // establecer emisor según tipo de respuesta
        $emitter = self::$emitters[$type] ?? null;

        if ($emitter === null) {
            throw new RuntimeException("No emitter registered for type '{$type}'");
        }
        
        return Closure::fromCallable($emitter);
    }

    public static function __emitData ($data): void 
    {
        echo $data;
    }

    public static function __emitBinary ($content): void
    {
        echo $content;
    }

    public static function __emitTemplate ($file, $data): void 
    {
        $file = $file->path(true);

        // resolver alias de directorio de vistas
        // if (str_starts_with($file, '@')) {
        //     $name = explode('/', substr($file, 1), 2)[0];
        //     $ctrl = \Irbis\Server::instance()->getController($name);
        //     $path = $ctrl->namespace('dir').'views';
        //     $file = str_replace("@$name", $path, $file);
        //     unset($name, $ctrl, $path);
        // }

        extract($data);
        include($file);
    }

    public static function __emitFile ($file): void 
    {
        echo $file->read();
    }

    public static function __emitDownload ($file): void 
    {
        echo $file->read();
    }

    public static function __emitStream ($file): void 
    {
        $handle = fopen($file->path(true), 'rb');

        if ($handle === false) {
            throw new HttpException(500, "cannot open file '{$file->path()}'");
        }

        // limpiar buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        set_time_limit(0);

        $chunkSize = 8192; // 8KB por lectura

        while (!feof($handle)) {
            echo fread($handle, $chunkSize);
            flush();
        }

        fclose($handle);
    }
}