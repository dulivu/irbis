<?php
namespace Irbis\Http;


enum Method: string 
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD = 'HEAD';

    public static function fromGlobals(): self 
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        return self::from($method);
    }
}