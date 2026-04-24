<?php
namespace Irbis\Exceptions;
use Exception;
use Throwable;
use InvalidArgumentException;


class HttpException extends Exception {

    private array $headers = [];
    
    public function __construct(int $code = 500, $message = null, ?Throwable $previous = null) {
        if (is_array($message)) {
            $this->headers = $message;
            $message = self::$http_status[$code] ?? 'Unknown HTTP Error';
        } elseif ($message === null) {
            $message = self::$http_status[$code] ?? 'Unknown HTTP Error';
        }
        
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException("Invalid HTTP status code: $code");
        }
        
        parent::__construct($message, $code, $previous);
    }

    public function getHeaders(): array {
        return $this->headers;
    }
}