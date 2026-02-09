<?php
namespace Irbis\Terminal;
use Irbis\Interfaces\ComponentInterface;
use Irbis\Traits\Component;

class Authorization implements ComponentInterface {
    use Component;

    public function unauthorizedResponse ($response) {
        $response->header('HTTP/1.1 401 Unauthorized');
        $response->header('Cache-Control: no-cache, no-store, must-revalidate');
        $response->header('WWW-Authenticate: Basic realm="Irbis Terminal"');
    }

    public function verifyAuthorization ($auth_header, $stored_username, $stored_password) {
        if (!$auth_header) return false;

        $auth = explode(' ', $auth_header)[1] ?? '';
        $auth = base64_decode($auth);
        $parts = explode(':', $auth, 2);
        $user = $parts[0] ?? '';
        $pass = $parts[1] ?? '';

        return $user === $stored_username && password_verify($pass, $stored_password);
    }
}