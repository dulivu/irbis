<?php

namespace Irbis\Exceptions;

/**
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class HttpException extends \Exception {
    
    private static array $httpMessages = [
        // 2xx Success
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        
        // 3xx Redirection
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        
        // 4xx Client Errors
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        
        // 5xx Server Errors
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout'
    ];
    
    public function __construct(int $code = 500, ?string $message = null, ?\Throwable $previous = null) {
        if ($message === null) {
            $message = self::$httpMessages[$code] ?? 'Unknown HTTP Error';
        }
        
        if ($code < 100 || $code > 599) {
            throw new \InvalidArgumentException("Invalid HTTP status code: $code");
        }
        
        parent::__construct($message, $code, $previous);
    }
    
    public static function getHttpMessage(int $code): string {
        return self::$httpMessages[$code] ?? 'Unknown HTTP Error';
    }
    
    public function getHttpStatus(): string {
        return "HTTP/1.1 {$this->code} {$this->message}";
    }
}