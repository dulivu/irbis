<?php
namespace Irbis\Routing;
use Attribute;
use Closure;
use InvalidArgumentException;
use Irbis\Routing\Route;
use Irbis\Routing\MethodConstraint;
use Irbis\Http\Method;
use Irbis\Http\Request;
use Irbis\Http\Response;


#[Attribute(Attribute::TARGET_METHOD)]
class Action 
{
    public readonly array $routes;
    public readonly array $methods;
    public readonly array $constraints;
    public readonly int $priority;

    private Closure $action;
    public function action (callable $action): static 
    {
        $this->action = Closure::fromCallable($action);
        return $this;
    }

    public function __construct (
        string|array $routes, 
        string|array $methods = [],
        string|array $constraints = [],
        int $priority = 50
    ) {
        $this->priority = $priority;

        $this->routes = array_map(
            fn($str) => is_string($str) ? Route::fromString($str) : $str, 
            (array) $routes
        );

        $this->methods = array_map(
            fn($str) => is_string($str) ? Method::from(strtoupper($str)) : $str,
            (array) $methods
        );

        $this->constraints = [new MethodConstraint($this)] + array_map(
            fn($class) => is_string($class) ? new $class($this) : $class,
            (array) $constraints
        );
    }

    public function __invoke(?Request $request = null, ?Response $response = null): Response 
    {
        if (!$this->action) {
            throw new InvalidArgumentException("Closure not defined for Action");
        }
        $res = ($this->action)($request, $response ?: new Response());
        return $this->ensureResponse($res);
    }

    private function ensureResponse($res): Response 
    {
        if (!$res) {
            return new Response();
        }

        if ($res instanceof Response) {
            return $res;
        }

        $response = new Response();
        
        if (is_string($res) && str_ends_with($res, '.html')) {
            $response->template($res);
        }
        elseif (
            is_array($res) &&
            is_string($res[0] ?? null) &&
            str_ends_with($res[0], '.html')
        ) {
            $response->template($res[0], $res[1] ?? []);
        } else {
            $response->data($res);
        }

        return $response;
    }

    /* -= statics =- */

    public static function get($routes, $action) 
    {
        return (new self($routes, 'GET'))->action($action);
    }

    public static function post($routes, $action) 
    {
        return (new self($routes, 'POST'))->action($action);
    }

    public static function put($routes, $action) 
    {
        return (new self($routes, 'PUT'))->action($action);
    }

    public static function patch($routes, $action) 
    {
        return (new self($routes, 'PATCH'))->action($action);
    }

    public static function delete($routes, $action) 
    {
        return (new self($routes, 'DELETE'))->action($action);
    }
}