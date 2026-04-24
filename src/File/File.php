<?php
namespace Irbis\File;
use RuntimeException;


class File extends System 
{

    public function exists(): bool 
    {
        return is_file($this->path(true));
    }

    public function read(): string 
    {
        return file_get_contents($this->path(true));
    }

    public function write(string $content): bool 
    {
        return file_put_contents($this->path(true), $content, FILE_APPEND | LOCK_EX);
    }

    public function truncate(): bool 
    {
        return file_put_contents($this->path(true), '', LOCK_EX);
    }

    public function delete(): bool 
    {
        if (!$this->exists()) return true;
        if (!unlink($this->path(true))) {
            throw new RuntimeException("Unable to delete file: {$this->path()}");
        }
        return true;
    }

    public function move(string $newPath): void 
    {
        if (!$this->exists()) {
            throw new RuntimeException("File not found: {$this->path()}");
        }

        $old = $this->path(true);
        $this->setPath($newPath);

        if (!rename($old, $this->path(true))) {
            throw new RuntimeException("Unable to move file to: {$newPath}");
        }
    }

    public function copy(string $newPath): static 
    {
        if (!$this->exists()) {
            throw new RuntimeException("File not found: {$this->path()}");
        }

        $copy = new static($newPath);

        if (!copy($this->path(true), $copy->path(true))) {
            throw new RuntimeException("Unable to copy file to: {$newPath}");
        }

        return $copy;
    }

    // info: dirname, basename, extension, filename, mime, size
    public function info(string $key = ''): array|string 
    {
        if (!$this->exists()) {
            if ($key) return '';
            return [];
        }

        if ($key) {
            if ($key == 'mime') {
                return $this->mimeType();
            }

            if ($key == 'size') {
                return filesize($this->path());
            }

            $info = pathinfo($this->path());
            return $info[$key] ?? '';
        }

        $info = pathinfo($this->path());
        $info['mime'] = $this->mimeType();
        $info['size'] = filesize($this->path());
        return $info;
    }

    private function mimeType(): ?string 
    {
        $mime = 'application/octet-stream'; // default
        
        if (function_exists('finfo_open') && $this->exists()) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $this->path());
                finfo_close($finfo);
                
                if ($detected) {
                    $mime = $detected;
                }
            }
        }

        // Correcciones específicas por extensión
        $extension = pathinfo($this->path(), PATHINFO_EXTENSION);
        switch ($extension) {
            case 'css': $mime = 'text/css'; break;
            case 'js': $mime = 'application/javascript'; break;
            default: break;
        }
        
        return $mime;
    }
}