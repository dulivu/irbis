<?php

namespace Irbis;

use Irbis\Exceptions\HttpException;
use Irbis\Interfaces\ResponseInterface;
use Irbis\Tools\Body;


/**
 * Gestiona la respuesta hacia el cliente
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
class Response {
    
    private ?string $path; // la solicitud que originó esta respuesta
    private ?array $actions; // acciones disponibles
    private ?Action $action; // acción a ejecutar
    private ?string $view; // plantilla a renderizar
    private ?ResponseInterface $body; // cuerpo de la respuesta
    /**
     * @exclusive, \Irbis\Server
     * las respuesta se creen dentro del flujo del servidor
     * las acciones vienen seleccionadas de los controladores
     */
    public function __construct (string $path = '', array $actions = []) {
        $this->path = $path;

        if (!empty($actions)) {
            $this->sortActions($actions);
        }

        $this->actions = $actions;
        $this->body = new Body();
    }

    /**
     * @exclusive, \Irbis\Server
     * convierte la respuesta a texto
     * server lo usará cuando no haya una vista
     */
    public function __toString(): string {
        return $this->body->toString();
    }

    /**
     * @exclusive, \Irbis\Server
     * convierte la respuesta a un array
     * server lo usa cuando haya una vista
     */
    public function __invoke(): array {
        return [
            "view" => $this->view,
            "data" => $this->body->toArray()
        ];
    }

    /**
     * crea y ordena la pila de acciones
     * la pila se ejecuta en orden LIFO
     */
    public function sortActions (array &$actions): void {
        usort($actions, function ($a, $b) {
            if (!($a instanceof Action) || !($b instanceof Action))
                throw new \InvalidArgumentException(
                    "Los elementos a ordenar deben ser instancias de Action."
                );
            // opcionales primero
            if ($a->isOptional() && !$b->isOptional()) return -1;
            if (!$a->isOptional() && $b->isOptional()) return 1;
            // importantes al final
            if ($a->isImportant() && !$b->isImportant()) return 1;
            if (!$a->isImportant() && $b->isImportant()) return -1;
            // mantener orden
            return 0;
        });
    }

    /**
     * establece una cabecera de respuesta HTTP
     */
    public function header ($header, $replace = true, $response_code = 0): void {
        header($header, $replace, $response_code);
    }

    /**
     * indica si la respuesta tiene una vista asignada
     */
    public function hasView(): bool {
        return !empty($this->view);
    }

    /**
     * determina si el valor es una plantilla (termina en .html)
     */
    public function isView ($template): bool {
        return (
            is_string($template) && 
            substr($template, -5) == '.html'
        );
    }

    /**
     * @setter
     * establece la plantilla a renderizar
     */
    public function view (?string $view): void {
        if ($view === null) {
            $this->view = null;
        } else {
            if (!$this->isView($view))
                throw new HttpException(
                    500, 
                    "invalid response view {$view}."
                );
            $this->view = $view;
        }
    }

    /**
     * @setter
     * establece el cuerpo de la respuesta
     */
    public function body ($data): void {
        if (!( $data instanceof ResponseInterface )) {
            $body = new Body();
            $body->setData($data);
        } else { $body = $data; }

        $body->merge($this->body);
        $this->body = $body;
    }

    /**
     * agrega un valor a la respuesta
     */
    public function append ($key, $value = null): void {
        $this->body->append($key, $value);
    }

    /**
     * elimina un valor de la respuesta
     */
    public function remove ($key): void {
        $this->body->remove($key);
    }

    /**
     * @exclusive, \Irbis\Server
     * se saca de la pila la siguiente acción a ejecutar
     * cada acción se valida antes de ejecutarse
     * si la accion devuelve un throwable, vuelve a preparar la acción
     * si no hay acciones disponibles, lanza el error capturado o 404 en su defecto
     */
    private function prepareAction($throw = null): void {
        $this->action = array_pop($this->actions);
        if (!$this->action)
            throw $throw ?: new HttpException(404);
        $throw = $this->action->validate();
        if ($throw instanceof \Throwable)
            $this->prepareAction($throw);
    }

    /**
     * @exclusive, \Irbis\Server
     * ejecuta la acción preparada
     */
    public function execute() : Response {
        $this->prepareAction(); // recursivo
        
        $x = $this->action->execute($this);

        if ($x !== null) {
            // si es otro objeto Response, lo retorna
            if ($x instanceof Response) return $x;
            // si es un texto (plantilla), establece la vista
            if ($this->isView($x)) $this->view($x);
            // si es un array [view, data], establece la vista y los datos
            elseif (
                is_array($x) &&
                count($x) <= 2 &&
                $this->isView($x[0] ?? null)
            ) {
                $this->view($x[0]);
                $this->body($x[1] ?? []);
            } else {
                // cualquier otro valor, lo establece como contenido
                $this->view(null);
                $this->body($x);
            }
        }

        return $this;
    }
}
