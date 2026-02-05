<?php
namespace Irbis\Tools;

use Irbis\Interfaces\ResponseInterface;

/**
 * Cuerpo por defecto de la respuesta
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class Body implements ResponseInterface {
    public $data = [];
    public $error = null;

    public function toString(): string {
        return is_array($this->data) ? 
            Json::encode($this->data) : 
            (string) $this->data;
    }

    public function toArray($raw = false): array {
        return is_assoc($this->data) ? 
            $this->data : 
            ['data' => $this->data];
    }

    public function setData ($data) {
        if ($data instanceof \Throwable) {
            $this->setError($data);
        } else $this->data = $data;
    }

    private function setError ($error) {
        $this->error = $error;

        $trace = array_map(function ($trace) {
            return ($trace['file'] ?? $trace['function'])." (".($trace['line'] ?? 0).")";
        }, DEBUG_MODE ? $error->getTrace() : []);

        $this->data = [
            'error' => [
                'code' => $error->getCode(),
                'class' => get_class($error),
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $trace,
            ]
        ];
    }

    // not implemented
    public function merge(ResponseInterface $body) {}

    public function append($key, $data = null) {
        if (is_string($this->data))
            $this->data .= $key;
        elseif (is_array($this->data))
            $this->data[$key] = $data;
        else
            throw new \Exception("Cannot append '{$key}' to body data");
    }

    public function remove($key) {
        if (is_string($this->data))
            $this->data = str_replace($key, '', $this->data);
        elseif (is_array($this->data) && array_key_exists($key, $this->data))
            unset($this->data[$key]);
        else
            throw new \Exception("Cannot remove '{$key}' from body data");
    }
}