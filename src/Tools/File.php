<?php
namespace Irbis\Tools;


/**
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class File {
    
    private $directory;
    private $alias;

    public function __construct($directory, $alias = '') {
        # puede apuntar a un archivo o a un directorio
        $this->directory = $this->sanitize($directory);
        $this->alias = $alias;
    }

    public function sanitize(string $file) {
        $dangerous = ['../', '..\\', '../', '..\\', '..', '//', '\\\\'];
        foreach ($dangerous as $pattern) {
            if (str_contains($file, $pattern)) {
                throw new \InvalidArgumentException("Ruta de archivo inválida o insegura");
            }
        }
        return $file;
    }

    private function file($file) {
        if ($file)
            $file = $this->directory . DIRECTORY_SEPARATOR . $file;
        return $file;
    }

    public function exists(string $file='') {
        $file = $this->file($file);
        return file_exists($file);
    }

    public function write(string $content, string $file = '') {
        $file = $this->file($file);
        return safe_file_write($file, $content);
    }

    public function glob(string $pattern = '', int $flags = GLOB_NOSORT|GLOB_BRACE) {
        $pattern = $this->file($pattern);
        return glob($pattern, $flags);
    }

    function uploadFile (string $file, callable $fn) {
        $controller = $this;
        $request = Request::getInstance();
        if (!$request->hasUploadedFiles($file)) return false;
        $request->manageUploadedFiles($file, function ($upload_data) use ($controller, $fn) {
            $path = $fn($upload_data);
            if ($path) {
                $path = $controller->filePath($path);
                $path_data = pathinfo($path);
                if (!is_dir($path_data['dirname']))
                    mkdir($path_data['dirname'], 0777, TRUE);
                move_uploaded_file($upload_data['tmp_name'], $path);
            }
        }, true);
        return true;
    }

    public function filePath (string $file = "", $options = 1) {
        if (!$file) return $this->_directory.DIRECTORY_SEPARATOR;
        
        $path = pathcheck($file); # to: path/file.ext
        $path = [$this->_directory.DIRECTORY_SEPARATOR.$path];
        $has_wildcard = str_contains($file, '*');
        if ($has_wildcard)
            $path = glob($path[0], GLOB_NOSORT|GLOB_BRACE);

        # FILE_PATH = retorna la ruta completa
        # FILE_CONTENT = retorna el archivo binario para ser modificado
        # FILE_INCLUDE = incluye el archivo usando include
        if ($options & Controller::FILE_PATH) {
            return $has_wildcard ? $path : ($path[0] ?? False);
        }

        if ($options & Controller::FILE_CONTENT) {
            $contents = [];
            foreach ($path as $p) {
                if (file_exists($p)) {
                    $contents[$p] = file_get_contents($p);
                } else $contents[$p] = false;
            }
            return count($path) != 1 ? $contents : ($contents[$path[0]] ?? false);
        }
        
        if ($options & Controller::FILE_INCLUDE) {
            $incs = [];
            foreach ($path as $p) {
                if (file_exists($p)) {
                    $inc = include($p);
                    $incs[$p] = $inc ?: true;
                } else $incs[$p] = false;
            }
            return count($path) != 1 ? $incs : ($incs[$path[0]] ?? False);
        }

        return false;
    }
}