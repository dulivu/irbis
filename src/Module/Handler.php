<?php
namespace Irbis\Module;
use InvalidArgumentException;
use Closure;
use Irbis\Server;
use Irbis\Console\Bootstrap;


class Handler 
{
    private Server|Bootstrap $server;
    private array $controllers = [];
    private array $controllers_map = [];

    public function __construct (Server|Bootstrap $server)
    {
        $this->server = $server;
    }

    public function push(string|Controller $controller, string $name = ''): Controller
    {
        if (is_string($controller))
            $controller = $this->fromNamespace($controller);

        $name = $name ?: $controller::$name;
        $key = $controller->namespace();
        if (!$name) 
            throw new InvalidArgumentException("Controller name not declared for $key");
        if (array_key_exists($name, $this->controllers_map))
            throw new InvalidArgumentException("Controller name '$name' already in use");
        if (array_key_exists($key, $this->controllers))
            throw new InvalidArgumentException("Controller '$key' already registered");

        $this->controllers_map[$name] = $key;
        $this->controllers[$key] = $controller;

        return $controller;
    }

    public function get(string $name) : ?Controller
    {
        if (array_key_exists($name, $this->controllers_map))
            $name = $this->controllers_map[$name];
        return $this->controllers[$name] ?? null;
    }

    public function walk(callable $fn): void 
    {
        $fn = Closure::fromCallable($fn)->bindTo(null);
        foreach ($this->controllers as $key => $controller) 
            $fn($controller, $key);
    }

    public function fromNamespace(string $namespace): Controller 
    {
        $namespace = str_replace('/', '\\', $namespace).'\Controller';
        $namespace = '\\'.trim($namespace, '\\');
        return new $namespace($this->server);
    }
}