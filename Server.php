<?php 

namespace Irbis;

include 'Constants.php';
include 'Functions.php';

irbis_loader();

use Irbis\Traits\Singleton;
use Irbis\Traits\EventsStatic;


/**
 * @package 		Irbis-Framework
 * @author		Jorge Luis Quico C. <GeorgeL1102@gmail.com>
 * @version		1.0
 */

class Server {
	use Singleton, Events;

	/**
	 * Vista por defecto para errores 404
	 * @var string
	 */
	public $view_404 = __DIR__.'/404.html';

	/**
	 * Visto por defecto para errores 500
	 * @var string
	 */
	public $view_500 = __DIR__.'/500.html';

	/**
	 * Función de renderizado
	 * @var \Closure
	 */
	public $render;

	/**
	 * Instancia del objeto Request
	 * @var \Irbis\Request
	 */
	protected $request;

	/**
	 * Arreglo asociativo con todos los controladores 
	 * registrados por el método 'addController'
	 * @var array['class_name' => \Irbis\Controller]
	 */
	private $controllers = [];
	private $controllers_map = [];

	/**
	 * Arreglo asociativo con todas las respuestas 
	 * para cada petición
	 * @var array['path' => \Irbis\Response]
	 */
	private $responses = [];

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->request = Request::getInstance();
	}

	/**
	 * Añade un nuevo controlador al servidor
	 * @param Controller $controller
	 */
	public function addController (Controller $controller, string $alias = '') {
		$this->controllers[$controller->klass] = $controller;
		if ($controller->name) $this->controllers_map[$controller->name] = $controller->klass;
		if ($alias) $this->controllers_map[$alias] = $controller->klass;
		$this->fire('addController', $controller);
	}

	/**
	 * Devuelve un controlador por su nombre de clase
	 * @param string $name
	 * @return Controller
	 */
	public function getController ($name) : Controller {
		if (strpos($name, '\\') === 0)
			$name = substr($name, 1);
		if (array_key_exists($name, $this->controllers_map))
			$name = $this->controllers_map[$name];
		return $this->controllers[$name] ?? null;
	}

	/**
	 * Entrega una respuesta a la petición del cliente
	 * @param string $path (optional)
	 * @return null | \Irbis\Response
	 */
	public function respond (string $path = '') {
		$r = $path ?: $this->request->path;
		$response = null;

		// si no se ha creado una respuesta para la ruta solicitada
		// se crea uno nuevo y se le agrega las rutas coincidentes
		if (!array_key_exists($r, $this->responses)) {
			$this->responses[$r] = new Response($r);
			foreach ($this->controllers as $ctrl) {
				$this->responses[$r]->addRoutes($ctrl->getMatchedRoutes($r));
			}
		}

		// este bloque devolerá una respuesta cuando este metodo
		// es llamado nuevamente dentro de un controlador
		// utilizado para sobreescribir rutas en nuevos modulos
		else $response = $this->responses[$r];
		if (!$response && $path)
			$response = $this->responses[$r];
		if ($response)
			return $response->prepare() ? $response->execute() : false;

		// este bloque controla errores y que la ruta solicitada
		// se encuentre registrada, prepara la respuesta final que
		// el cliente recibirá
		try {
			$response = $this->responses[$r];
			if ($response->prepare()) {
				foreach ($this->controllers as $ctrl) 
					$ctrl->init();
				$response = $response->execute();
			} else {
				header("HTTP/1.0 404 Not Found");
				$response->view = $this->view_404;
				$response->data = [
					'status' => 'error',
					'message' => 'Ruta solicitada no encontrada',
					'error' => [
						'class' => 'HttpNotFound',
						'code' => 404
					]
				];
			}
		} catch (\Throwable $e) {
			header("HTTP/1.0 500 Internal Server Error");
			$response->view = $this->view_500;
			$response->data = [
				'status' => 'error',
				'message' => $e->getMessage(),
				'error' => [
					'class' => get_class($e),
					'code' => DEBUG_MODE ? $e->getCode() : 0,
					'file' => DEBUG_MODE ? $e->getFile() : 'need debug mode',
					'line' => DEBUG_MODE ? $e->getLine() : 0,
					'trace' => DEBUG_MODE ? $e->getTrace() : [],
				],
			];
		} finally {
			$this->fire('response', $this, [$this->request, $response]);
			if (!$response->view)
				die($response."");
			$this->setRenderEnviroment();
			$this->renderView($response);
		}
	}

	/**
	 * Prepara variables de petición y ejecuta
	 * el entorno de renderizado de vista $this->render
	 * @param Response $response
	 */
	private function renderView (Response $response) {
		$request = $this->request;
		$search = [];
		$replace = [];

		foreach ($request->query('*', ['view' => DEFAULT_VIEW]) as $k => $v) {
			if (!is_array($v)) {
				$search[] = "{{$k}}";
				$replace[] = $v;
			}
		}

		foreach ($request->path('*', [0 => DEFAULT_VIEW]) as $k => $v) {
			$search[] = "($k)";
			$replace[] = $v;
		}

		$render = $this->render->bindTo(null);
		$render(
			str_replace($search, $replace, $response->view), 
			(array) $response->data
		);
	}

	/**
	 * Esteblece el entorno de renderizado, si no declaro uno
	 * o este no es un closure esta función establece uno por defecto
	 */
	private function setRenderEnviroment () {
		$this->render = $this->render instanceof \Closure ? 
			$this->render : function ($__path__, $__data__) {
				extract($__data__);
				
				if (!file_exists($__path__)) 
					throw new \Exception("template '{$__path__}' not found");
				include($__path__);
			};
	}

	/**
	 * Ejecuta una retrollamada por cada controlador
	 * registrado en el servidor
	 * @param Closure $fn
	 */
	public function forEachController (\Closure $fn) {
		foreach ($this->controllers as $key => $val) {
			$fn($val, $key);
		}
	}
}
