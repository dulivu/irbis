<?php
namespace Irbis\Traits;
use Exception;

trait Singleton 
{
    private static $instance;

    public static function instance(...$params): self 
    {
        if (!self::$instance instanceof self)
            self::$instance = new self(...$params);
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    public static function exists() 
    {
        return self::$instance instanceof self;
    }
}