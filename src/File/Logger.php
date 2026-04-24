<?php
namespace Irbis\File;


class Logger extends File
{
    const INFO = 'INFO';
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';

    public function log(string $message, string $level = self::INFO): void 
    {
        $timestamp = date('Y-m-d H:i:s.u');
        $entry = "{$timestamp} [{$level}] {$message}\n";
        $this->write($entry);
    }

    public function info(string $message): void 
    {
        $this->log($message, self::INFO);
    }

    public function error(string $message): void 
    {
        $this->log($message, self::ERROR);
    }

    public function warning(string $message): void 
    {
        $this->log($message, self::WARNING);
    }
}