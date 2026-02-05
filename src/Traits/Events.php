<?php
namespace Irbis\Traits;

/**
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		3.0
 */
trait Events {
    private $events = [];

    /**
     * @param string $event Nombre del evento
     * @param callable $callback Función a ejecutar
     * @param int $priority Prioridad (menor número = mayor prioridad)
     */
    public function on(string $event, callable $callback, int $priority = 100) {
        $this->events[$event] = $this->events[$event] ?? [];
        $this->events[$event][] = [
            'callback' => $callback,
            'priority' => $priority
        ];
        
        usort($this->events[$event], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * @param string $event Nombre del evento
     * @param mixed ...$params Parámetros a pasar a los callbacks
     */
    public function fire(string $event, ...$params) {
        $return = null;
        if (isset($this->events[$event])) {
            foreach ($this->events[$event] as $listener) {
                $callback = $listener['callback'];
                
                if (is_callable($callback)) {
                    if ($callback instanceof \Closure) {
                        $return = $callback->call($this, ...$params);
                    } else {
                        $return = ($callback) (...$params);
                    }
                }
            }
        }
        return $return;
    }

    /**
     * @param string $event Nombre del evento
     * @param callable|null $callback Callback específico a remover (null = todos)
     */
    public function off(string $event, ?callable $callback = null) {
        if (isset($this->events[$event])) {
            if ($callback === null) {
                unset($this->events[$event]);
            } else {
                $events = $this->events[$event];
                $events = array_filter($events, function($listener) use ($callback) {
                    return $listener['callback'] !== $callback;
                });
                $this->events[$event] = array_values($events);
                if (empty($this->events[$event])) {
                    unset($this->events[$event]);
                }
            }
        }
    }
}