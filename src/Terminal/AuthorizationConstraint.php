<?php
namespace Irbis\Terminal;
use Irbis\Server;
use Irbis\Http\Request;
use Irbis\Exceptions\HttpException;
use Irbis\Routing\Constraint;


class AuthorizationConstraint extends Constraint 
{
    public function validate (Request $request): ?HttpException
    {
        $server = Server::instance();
        $username = $server->state->get('server.owner') ?: 'irbis';
        $password = $server->state->get('server.terminal');

        if (is_string($password)) {
            $header = $request->getHeader('Authorization');
            if (!$this->verify($header, $username, $password)) {
                return new HttpException(401, [
                    'Cache-Control' => 'no-cache, must-revalidate',
                    'WWW-Authenticate' => 'Basic realm="Irbis Terminal"',
                ]);
            }
        }

        return null;
    }

    public function verify ($header, $user, $pass) 
    {
        if (!$header) return false;

        $auth = explode(' ', $header)[1] ?? '';
        $auth = base64_decode($auth);
        $parts = explode(':', $auth, 2);
        $u = $parts[0] ?? '';
        $p = $parts[1] ?? '';

        return $u === $user && password_verify($p, $pass);
    }
}