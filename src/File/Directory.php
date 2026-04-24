<?php
namespace Irbis\File;
use RuntimeException;


class Directory extends System 
{
    public function exists(): bool 
    {
        return is_dir($this->path(true));
    }

    public function create(int $permissions = 0755, bool $recursive = true): bool 
    {
        if ($this->exists()) {
            return true;
        }

        if (!mkdir($this->path(true), $permissions, $recursive)) {
            throw new RuntimeException("Unable to create directory: {$this->path()}");
        }

        return true;
    }

    public function delete(bool $recursive = false): bool 
    {
        if (!$this->exists()) return true;

        if (!$recursive) {
            return rmdir($this->path(true));
        }

        return $this->deleteRecursive($this->path(true));
    }

    private function deleteRecursive(string $dir): bool 
    {
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($fullPath)) {
                $this->deleteRecursive($fullPath);
            } else {
                unlink($fullPath);
            }
        }

        return rmdir($dir);
    }

    public function move(string $newPath): void 
    {
        if (!$this->exists()) {
            throw new RuntimeException("Directory not found: {$this->path()}");
        }

        $old = $this->path(true);
        $this->setPath($newPath);

        if (!rename($old, $this->path(true))) {
            throw new RuntimeException("Unable to move directory to: {$newPath}");
        }
    }

    public function copy(string $newPath): static 
    {
        if (!$this->exists()) {
            throw new RuntimeException("Directory not found: {$this->path()}");
        }

        $copy = new static($newPath);
        if (!$copy->exists()) $copy->create();

        $this->copyRecursive($this->path(true), $copy->path(true));

        return $copy;
    }

    private function copyRecursive(string $source, string $destination): void 
    {
        $items = scandir($source);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = $source . DIRECTORY_SEPARATOR . $item;
            $dstPath = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($srcPath)) {
                $this->copyRecursive($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }

    public function files(): array 
    {
        return array_map(
            fn($path) => new File($path),
            array_filter($this->all(), fn($item) => is_file($item))
        );
    }

    public function directories(): array 
    {
        return array_map(
            fn($path) => new Directory($path),
            array_filter($this->all(), fn($item) => is_dir($item))
        );
    }

    public function all(): array 
    {
        if (!$this->exists()) {
            return [];
        }

        $this->create(); // asegurar que el directorio existe

        $items = scandir($this->path(true));
        $path = $this->path();

        return array_map(
            fn($item) => $path . DIRECTORY_SEPARATOR . $item,
            array_diff($items, ['.', '..'])
        );
    }

    public function file(string $path): File 
    {
        $this->create(); // asegurar que el directorio existe
        $filePath = $this->path(true) . DIRECTORY_SEPARATOR 
            . ltrim($path, DIRECTORY_SEPARATOR);
        return new File($filePath);
    }
}