<?php
namespace Irbis\Routing;
use Throwable;
use Irbis\Server;
use Irbis\Exceptions\HttpException;
use Irbis\Http\Request;
use Irbis\Http\Response;

/**
 * Pila de acciones
 */
class Handler 
{
    private Request $request;
    private Response $response;
    private string $path;
    private array $actions = [];
    private array $matches = [];

    public function __construct (Request $request) 
    {
        $this->request = $request;
        $this->response = new Response();
        $this->path = $request->uri->path;
    }

    public function withRequest(Request $request): self 
    {
        $copy = new self($request);
        foreach ($this->actions as $action) {
            $copy->push($action);
        }
        return $copy;
    }

    public function push(Action $action) 
    {
        $this->actions[] = $action;
        $routes = $action->routes;
        foreach ($routes as $route) {
            if ($route->match($this->path)) {
                $this->matches[] = [$action, $route];
            }
        }
    }

    public function pop(?HttpException $throw = null): Response 
    {
        [$action, $route] = array_pop($this->matches);

        if (!$action) {
            return $this->error($throw ?: new HttpException(404));
        }

        $constraints = $action->constraints;
        foreach ($constraints as $constraint) {
            $throw = $constraint->validate($this->request);
            if ($throw instanceof HttpException) {
                return $this->pop($throw);
            }
        }

        try {
            $this->request->route = $route."";
            $this->request->matches = $route->matches;

            $response = $action(
                $this->request, 
                $this->response
            );
        } catch (Throwable $error) {
            if (!$error instanceof HttpException) {
                $error = new HttpException(
                    500, 
                    $error->getMessage(), 
                    previous: $error
                );
            }
            $response = $this->error($error);
        }

        return $this->response = $response;
    }

    private function error(HttpException $error): Response 
    {
        $debug = False;

        $trace = array_map(function ($trace) {
            return ($trace['file'] ?? $trace['function'])." (".($trace['line'] ?? 0).")";
        }, $debug ? $error->getTrace() : []);

        $response = new Response(
            status: $error->getCode(),
            headers: $error->getHeaders()
        );

        $data = [
            'error' => [
                'code' => $error->getCode(),
                'class' => get_class($error),
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $trace,
            ]
        ];

        $requestHeaders = $this->request->headers;

        if ($requestHeaders->accept('text/html')) {
            $response->headers['Content-Type'] = 'text/html';
            $response->template('directory/error.html', $data);
        }
        elseif ($requestHeaders->accept('application/json')) {
            $response->headers['Content-Type'] = 'application/json';
            $response->data($data);
        }
        elseif ($requestHeaders->accept('application/xml')) {
            $response->headers['Content-Type'] = 'application/xml';
            $response->data($data);
        }
        elseif ($requestHeaders->accept('text/xml')) {
            $response->headers['Content-Type'] = 'text/xml';
            $response->data($data);
        }
        else {
            $response->headers['Content-Type'] = 'text/plain';
            $response->data("Error {$error->getCode()}: {$error->getMessage()}");
        }

        return $this->response = $response;
    }
}