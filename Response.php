<?php

namespace Irbis;

use Irbis\RecordSet;
use Irbis\RecordSet\Record;


/**
 * Administra rutas coíncidentes con la solicitud del cliente
 * para procesar las respuestas que se deben de entregar
 *
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.2
 */
class Response {
	# la solicitud que originó esta respuesta
	private $path;
	# las acciones que puede ejecutar esta respuesta
	private $actions;
	# la acción que se ejecutará, se prepara con 'prepareAction()'
	private $action;
	# la vista que se enviará al cliente,
	public $view;
	# la información que se enviará al cliente
	public $data;

	public function __construct (string $path = null) {
		$this->path = $path;
		$this->data = [];
		$this->actions = [];
	}

	public function __toString () {
		# NOTE: normalmente se usa para convertir el objeto a texto
		# este caso, suele presentarse cuando no hay una vista que mostrar
		$data = $this->data;
		if (array_key_exists('error', $data))
			$data = $data['error'];
		elseif (array_key_exists('response', $data))
			$data = $data['response'];
		if (is_array($data))
			return Json::encode($data);
		if ($data instanceof RecordSet)
			return Json::encode($data->data());
		if ($data instanceof Record)
			return Json::encode($data->data());
		return (string)$data;
	}

	public function addAction (Action $action) {
		$this->actions[] = $action;
	}
	public function addActions (array $actions) {
		foreach ($actions as $action)
			$this->addAction($action);
	}

	public function prepareAction () {
		# prepara la acción a ejecutar
		# devuelve 'bool' según haya una acción a ejecutar
		$this->action = array_pop($this->actions);
		return $this->action ? true : false;
	}

	public function executeAction () : Response {
		$is_template = function ($template) {
			# valida que un texto sea una plantilla válida
			return is_string($template) && substr($template, -5) == '.html';
		};

		if ($this->action) {
			$x = $this->action->execute($this);
			$this->action = null; # nullify
			if ($x !== null) {
				# si es un objeto Response, lo retorna
				if ($x instanceof Response) {
					if ($this->data) $x->setData($this->data);
					return $x;
				}
				# si es un texto (plantilla), establece la vista
				if ($is_template($x)) $this->setView($x);
				# si es un array [view, data], establece la vista y los datos
				elseif (is_array($x) && !is_assoc($x) && $is_template($x[0] ?? null)) {
					$this->setView($x[0]);
					$this->setData('__setdata__', $x[1] ?? []);
				} 
				# en otros casos, sólo establece datos
				else {
					$this->setView(null);
					$this->setData('__setdata__', $x);
				}
			}
		}

		return $this;
	}

	// =============================
	// ==== HELPERS & INTERNALS ====
	// =============================

	public function setView ($view) {
		$this->view = $view;
	}
	public function getView () {
		return $this->view;
	}

	public function setData ($key, $value = null) {
		if (is_assoc($key)) {
			foreach ($key as $k => $v)
				$this->setData($k, $v, 1);
		} else {
			if ($key == '__setdata__') {
				if (is_assoc($value))
					foreach ($value as $k => $v) $this->setData($k, $v);
				else array_set($this->data, 'response', $value);
			} elseif ($value === null) array_unset($this->data, $key);
			else array_set($this->data, $key, $value);
		}
	}

	public function getData ($key = null) {
		if ($key)
			return array_get($this->data, $key);
		return $this->data;
	}

	public function clearData () {
		$this->data = [];
	}

	public function setHeader ($header, $replace = true, $response_code = 0) {
		# Establece una cabecera de respuesta
		header($header, $replace, $response_code);
	}
}
