<?php
namespace Irbis\File;
use RuntimeException;

abstract class System
{
    protected string $path;

    // raíz común para el sistema de archivos
    // para restringir el acceso a archivos
    protected static string $root = BASE_PATH;

    // mapeo de alias a rutas físicas
    // para facilitar el acceso a carpetas comunes
    protected static array $mappings = [];

    public static function setRoot(string $root): void 
    {
        $realRoot = realpath($root);
        
        if ($realRoot === false) {
            throw new RuntimeException("Invalid root path: {$root}");
        }

        self::$root = rtrim($realRoot, DIRECTORY_SEPARATOR);
    }

    public static function map(string $alias, string $path): void
    {
        self::$mappings["@{$alias}"] = rtrim($path, DIRECTORY_SEPARATOR);
    }

    public static function from($file): static 
    {
        if ($file instanceof self) {
            return $file;
        }
        return new static($file);
    }

    public function __construct(string $path) 
    {
        $this->setPath($path);
    }

    protected function setPath(string $path): void 
    {
        if (str_starts_with($path, '@')) {
            $keys = array_keys(self::$mappings);
            $values = array_values(self::$mappings);

            $path = str_replace($keys, $values, $path);
        }

        $normalized = $this->normalize($path);

        if (!str_starts_with($normalized, self::$root)) {
            throw new RuntimeException("Path outside allowed root: {$normalized}");
        }

        $this->path = $normalized;
    }

    protected function normalize(string $path): string 
    {
        $ds = DIRECTORY_SEPARATOR;

        if (!str_starts_with($path, self::$root)) {
            $fullPath = self::$root . $ds . ltrim($path, $ds);
        } else {
            $fullPath = $path;
        }
        
        $dir = dirname($fullPath);
        $realDir = realpath($dir); // si el directorio no existe, realpath = false

        if ($realDir === false) {
            throw new RuntimeException("Invalid path: {$path}");
        }

        return $realDir . $ds . basename($fullPath);
    }

    public function path(bool $full = false): string 
    {
        if ($full) {
            return $this->path;
        }
        return substr($this->path, strlen(self::$root) + 1);
    }

    abstract public function exists(): bool;
}