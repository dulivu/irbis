<?php
namespace Irbis\Interfaces;


interface ResponseInterface {
    public function toString(): string;
    public function toArray(): array;
    public function setData($data);
    public function merge(ResponseInterface $data); # combina el contenido con otro cuerpo de respuesta
    public function append($key, $value);
    public function remove($key);
}