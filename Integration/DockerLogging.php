<?php

namespace Irbis\Integration;

use Irbis\Logger;

/**
 * Integración del sistema de logging con Docker
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.3
 */
class DockerLogging {
    private $logger;
    
    public function __construct() {
        $this->logger = Logger::getInstance(
            BASE_PATH . DIRECTORY_SEPARATOR . 'logs',
            true // Habilitar output a consola para Docker
        );
    }

    /**
     * Configurar error handler personalizado para Docker
     */
    public function setupErrorHandlers() {
        // Error handler para errores PHP
        set_error_handler([$this, 'handleError']);
        
        // Exception handler para excepciones no capturadas
        set_exception_handler([$this, 'handleException']);
        
        // Shutdown handler para errores fatales
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError($severity, $message, $file, $line) {
        // Solo logear errores importantes en producción
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $context = [
            'file' => $file,
            'line' => $line,
            'severity' => $this->getSeverityName($severity)
        ];

        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $this->logger->error($message, $context);
                break;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $this->logger->warning($message, $context);
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $this->logger->notice($message, $context);
                break;
            default:
                $this->logger->info($message, $context);
        }

        return true;
    }

    public function handleException($exception) {
        $this->logger->exception($exception, Logger::CRITICAL);
        
        // En producción, mostrar página de error genérica
        if (!DEBUG_MODE) {
            header('HTTP/1.1 500 Internal Server Error');
            echo '<h1>Error interno del servidor</h1>';
            echo '<p>Por favor, inténtalo de nuevo más tarde.</p>';
            exit(1);
        }
    }

    public function handleShutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logger->critical("Fatal error: {$error['message']}", [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $this->getSeverityName($error['type'])
            ]);
        }
    }

    private function getSeverityName($severity) {
        $severities = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];

        return $severities[$severity] ?? 'UNKNOWN';
    }

    /**
     * Logear request HTTP
     */
    public function logRequest($request, $response, $executionTime) {
        $context = [
            'method' => $request->method,
            'path' => $request->path,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'execution_time' => $executionTime . 'ms',
            'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
        ];

        $this->logger->info("HTTP Request", $context);
    }

    /**
     * Logear queries de base de datos
     */
    public function logDatabaseQuery($query, $params, $executionTime) {
        if (DEBUG_MODE) {
            $context = [
                'query' => $query,
                'params' => $params,
                'execution_time' => $executionTime . 'ms'
            ];
            
            $this->logger->debug("Database Query", $context);
        }
    }
}