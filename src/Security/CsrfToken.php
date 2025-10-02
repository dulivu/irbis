<?php

namespace Irbis\Security;

/**
 * Manejo de tokens CSRF
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.3
 */
class CsrfToken {
    private static $instance;
    private $sessionKey = '_csrf_token';
    private $formFieldName = '_token';

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Generar nuevo token CSRF
     */
    public function generate() {
        $token = bin2hex(random_bytes(32));
        $_SESSION[$this->sessionKey] = $token;
        return $token;
    }

    /**
     * Obtener token actual (o generar uno nuevo)
     */
    public function get() {
        if (!isset($_SESSION[$this->sessionKey])) {
            return $this->generate();
        }
        return $_SESSION[$this->sessionKey];
    }

    /**
     * Validar token CSRF
     */
    public function validate($token = null) {
        // Si no se proporciona token, intentar obtenerlo del request
        if ($token === null) {
            $token = $_POST[$this->formFieldName] ?? $_GET[$this->formFieldName] ?? null;
        }

        // Verificar que existe token en sesión
        if (!isset($_SESSION[$this->sessionKey])) {
            return false;
        }

        // Comparación segura contra timing attacks
        return hash_equals($_SESSION[$this->sessionKey], $token);
    }

    /**
     * Middleware para validar CSRF en requests POST/PUT/DELETE
     */
    public function validateRequest() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            if (!$this->validate()) {
                throw new \Exception('CSRF token mismatch', 419);
            }
        }
    }

    /**
     * Generar campo hidden para formularios
     */
    public function field() {
        $token = $this->get();
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($this->formFieldName),
            htmlspecialchars($token)
        );
    }

    /**
     * Obtener token para AJAX
     */
    public function meta() {
        $token = $this->get();
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token)
        );
    }

    /**
     * Regenerar token (usar después de login/logout)
     */
    public function regenerate() {
        return $this->generate();
    }
}