<?php

namespace Irbis;

/**
 * Sistema de logging compatible con Docker y PSR-3
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.3
 */
class Logger {
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    private static $instance;
    private $logPath;
    private $enableConsoleOutput;

    public function __construct($logPath = null, $enableConsoleOutput = true) {
        $this->logPath = $logPath ?: BASE_PATH . DIRECTORY_SEPARATOR . 'logs';
        $this->enableConsoleOutput = $enableConsoleOutput;
        
        // Crear directorio de logs si no existe
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    public static function getInstance($logPath = null, $enableConsoleOutput = true) {
        if (!self::$instance) {
            self::$instance = new self($logPath, $enableConsoleOutput);
        }
        return self::$instance;
    }

    /**
     * Log genérico
     */
    public function log($level, $message, array $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        // Formato para archivo
        $logEntry = sprintf(
            "[%s] %s: %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $contextString
        );

        // Escribir a archivo
        $this->writeToFile($level, $logEntry);

        // Escribir a stdout/stderr para Docker (si está habilitado)
        if ($this->enableConsoleOutput) {
            $this->writeToConsole($level, $logEntry);
        }
    }

    private function writeToFile($level, $logEntry) {
        $filename = $this->logPath . DIRECTORY_SEPARATOR . date('Y-m-d') . '-' . $level . '.log';
        file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function writeToConsole($level, $logEntry) {
        // Docker recomienda escribir logs a stdout/stderr
        $errorLevels = [self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR];
        
        if (in_array($level, $errorLevels)) {
            fwrite(STDERR, $logEntry);
        } else {
            fwrite(STDOUT, $logEntry);
        }
    }

    // Métodos de conveniencia
    public function emergency($message, array $context = []) {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []) {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical($message, array $context = []) {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []) {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning($message, array $context = []) {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice($message, array $context = []) {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info($message, array $context = []) {
        $this->log(self::INFO, $message, $context);
    }

    public function debug($message, array $context = []) {
        // Solo logear debug si está en modo desarrollo
        if (DEBUG_MODE) {
            $this->log(self::DEBUG, $message, $context);
        }
    }

    /**
     * Logear excepciones con stack trace completo
     */
    public function exception(\Throwable $e, $level = self::ERROR) {
        $context = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'trace' => DEBUG_MODE ? $e->getTraceAsString() : 'Stack trace hidden (DEBUG_MODE = false)'
        ];

        $this->log($level, $e->getMessage(), $context);
    }
}