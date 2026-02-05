<?php
namespace Irbis\Traits;


trait SingletonFactory {
    private static $instances = [];

    public static function getInstance (string $name): self {
        if (isset(self::$instances[$name]))
            return self::$instances[$name];
        self::$instances[$name] = new self($name);
        return self::$instances[$name];
    }

    private function __construct(string $name) {}
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}