<?php
namespace Irbis\Routing;


class Route 
{
    public readonly string $value;
    public array $matches = [];

    public function __construct(string $value) 
    {
        $this->value = $value;
    }

    public function __toString(): string 
    {
        return $this->value;
    }

    public function match(string $path): bool 
    {
        return $this->value === $path || static::matching($path, $this->value, $this->matches);
    }

    /* -= statics =- */

    private static array $patterns = [
        'int'    => '[0-9]+',
        'string' => '[^/]+',
        'all'    => '.*',
        #'uuid'   => '[0-9a-fA-F\-]{36}',
    ];

    /**
     * Agrega un nuevo patrón de coincidencia para las rutas.
     * @param string $key, nombre del patrón
     * @param string $regex, expresión regular para el patrón
     */
    public static function pattern(string $key, string $regex) 
    {
        self::$patterns[$key] = $regex;
    }

    /**
     * Convierte una ruta con parámetros en una expresión regular.
     * @param string $route, ruta definida en la acción
     * @return string, expresión regular para comparar con la ruta solicitada
     */
    private static function compile(string $route): string 
    {
        return preg_replace_callback(
            '#\{(\w+)(?::(\w+))?\}#',
            function ($matches) {
                $name = $matches[1];
                $type = $matches[2] ?? 'string';
                $regex = self::$patterns[$type] ?? self::$patterns['string'];
                return '(?P<' . $name . '>' . $regex . ')';
            },
            $route
        );
    }

    /**
     * @param string $path, ruta solicitada por el cliente
     * @param string $route, ruta definida en la acción
     * @param array &$matches, rellena los patrones encontradas
     */
    public static function matching(string $path, string $route, array &$matches = []): bool 
    {
        $regex = self::compile($route);
        if (preg_match('#^' . $regex . '$#', $path, $found)) {
            // solo parámetros nombrados
            $matches = array_filter(
                $found,
                fn($k) => !is_int($k),
                ARRAY_FILTER_USE_KEY
            );
            return true;
        }
        return false;
    }

    public static function fromString(string $value): static 
    {
        return new self($value);
    }
}