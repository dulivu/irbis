<?php

namespace Irbis;

use Irbis\Exceptions\HttpException;
use Irbis\Request;

/**
 * Representa una acción a ejecutar por el cliente
 * Es una envoltura para el método de un controlador
 * 
 * => métodos utilizables:
 * ::setDecorator(:string), añade un nuevo patrón de búsqueda para las anotaciones
 * 
 * El uso de esta clase fuera del framework, debería estar limitado a ser usado
 * dentro de middlewares para validar la acción antes de ser ejecutada
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class Action {
    private $callable;
    private $decorators;
    private $is_important = false;
    private $is_optional = false;

    private $path_patterns = [
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    ];
    private $path_matches = [];

    private static array $validators = [
        'verb' => [Action::class, 'verbValidator']
    ];

    /**
     * @exclusive, \Irbis\Controller
     */
    public function __construct(callable $callable) {
        $this->callable = $callable; // e: [$controller, 'method']
    }

    public function isImportant() : bool {
        return $this->is_important;
    }

    public function isOptional() : bool {
        return $this->is_optional;
    }

    /**
     * @exclusive, \Irbis\Controller
     * añade todos los '@decorator' que tenga un método
     */
    public function pushDecorators(array $keys, array $values) {
        $combined = [];
        // combina las claves y valores en un solo array
        foreach ($keys as $i => $key) { 
            if (!isset($combined[$key])) $combined[$key] = [];
            $combined[$key][] = $values[$i];
        }

        $ordered = [];
        // ordena los decoradores según el orden de los validadores
        foreach (array_keys(self::$validators) as $pattern) {
            if (isset($combined[$pattern])) {
                $ordered[$pattern] = $combined[$pattern];
                unset($combined[$pattern]);
            }
        }

        // añade los decoradores restantes
        foreach ($combined as $k => $v) {
            $ordered[$k] = $v;
        }
        $this->decorators = $ordered;
    }

    /**
     * @exclusive, \Irbis\Controller
     * valida si la solicitud coíncide con alguna de
     * las rutas definidas en esta acción
     */
    public function match(string $path) : bool {
        $this->path_matches = [];
        $routes = $this->decorators['route'];
        foreach ($routes as $r) {
            $route = preg_replace('/^[?!]/', '', $r);
            if ($path == $route || $this->matchRoute($path, $route)) {
                if (str_starts_with($r, '!'))
                    $this->is_important = true;
                elseif (str_starts_with($r, '?'))
                    $this->is_optional = true;
                return true;
            }
        }
        return false;
    }

    /**
     * @exclusive, \Irbis\Action
     * compara una ruta con los patrones definidos
     */
    private function matchRoute(string $path, string $route): bool {
        # path: lo que el cliente solicita /cliente/1
        # route: la ruta registrada en la acción /cliente/(:num)
        $searches = array_keys($this->path_patterns);
        $replaces = array_values($this->path_patterns);
        $matched = [];

        $pattern = str_replace($searches, $replaces, $route);
        if (preg_match('#^' . $pattern . '$#', $path, $matched)) {
            array_shift($matched); // quitar el elemento completo
            $this->path_matches = array_map('urldecode', $matched);
            return true;
        }
        return false;
    }

    /**
     * añade un nuevo patrón de búsqueda para las anotaciones
     * ej: verb => function($val) { ... }
     */
    public static function setValidator(string $pattern, callable $validator) {
        self::$validators[$pattern] = $validator;
    }

    /**
     * @exclusive, \Irbis\Response
     * las acciones previo a ejecutarse se validan con los decoradores
     * siempre que exista un validador para el decorador
     */
    public function validate() {
        $throw = null;
        foreach ($this->decorators as $key => $value) {
            $validator = self::$validators[$key] ?? null;
            if ($validator) {
                foreach ($value as $val)
                    $newthrow = ($validator)($val);
                    if ($newthrow instanceof \Throwable)
                        $throw = $newthrow;
            }
            if ($throw) break;
        }
        return $throw;
    }

    /**
     * @exclusive, \Irbis\Response
     * ejecuta el método del controlador, callable
     */
    public function execute(Response $response) {
        $request = Request::getInstance();
        $request->action = $this;

        return ($this->callable)($request, $response);
    }

    /**
     * @exclusive, \Irbis\Request
     */
    public function getPathMatch(int $key) {
        return $this->path_matches[$key] ?? null;
    }

    /**
     * método para validar si el verbo solicitado coíncide con esta acción
     * sirve de ejemplo para crear nuevos decoradores
     */
    public static function verbValidator($verb) {
        $request = Request::getInstance();
        $verb = array_map('trim', explode(',', strtoupper($verb)));
        if (!in_array($request->method, $verb))
            return new HttpException(405);
    }
}