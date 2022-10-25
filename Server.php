<?php 

namespace Irbis;

include 'Constants.php';
include 'Functions.php';

irbis_loader();

use Irbis\Traits\Singleton;
use Irbis\Traits\Events;


/**
 * El objeto server es el punto de entrada de la aplicación
 * este objeto será el que contenga todos los elementos necesarios
 * y principales para la ejecución de tu aplicación
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */

class Server {
	use Singleton, Events;

	/**
	 * Vista por defecto para errores 404
	 * @var string
	 */
	public $view_404 = __DIR__.'/404.html';

	/**
	 * Vista por defecto para errores 500
	 * @var string
	 */
	public $view_500 = __DIR__.'/500.html';

	/**
	 * Función de renderizado, será el closure que
	 * utilizará el servidor en reemplazo del por
	 * defecto, esto en caso se quiera usar otros
	 * motores de renderizado, ejem. twig
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
	 * Realiza operaciones de encendido del servidor
	 * @param Controller [$controllers]
	 *  - crea el objeto 'request'
	 *  - agrega todos los módulos a utilizar
	 */
	public function start (array $controllers) {
		$this->request = Request::getInstance();
		foreach ($controllers as $alias => $controller) {
			$alias = gettype($alias) == 'string' ? $alias : '';
			$this->addController($controller, $alias);
		}
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
	 * Devuelve un controlador por su nombre de clase o alias
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
	 * @param string $fake_path (optional)
	 * @return null | \Irbis\Response
	 */
	public function respond (string $fake_path = '') {
		$path = $fake_path ?: $this->request->path;
		$response = $this->responsesPrepare($path, $fake_path);

		if ($response)
			return $response->prepare() ? $response->execute() : new Response();

		// este bloque controla errores y que la ruta solicitada
		// se encuentre registrada, prepara la respuesta final que
		// el cliente recibirá
		try {
			$response = $this->responses[$path];
			if ($response->prepare()) {
				$this->forEachController(function ($ctrl) { $ctrl->start(); });
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
			$this->fire('response', [$this->request, $response]);
			if (!$response->view)
				die($response."");
			$this->setRender();
			$this->doRender($response);
		}
	}

	/**
	 * Si la ruta no existe en las respuestas, se crea una nueva
	 * y devuelve falso, caso contrario, devuelve la respuesta 
	 * coíncidente
	 * @param string $path
	 */
	private function responsesPrepare ($path, $fake_path = '') {
		if (!array_key_exists($path, $this->responses)) {
			$this->responses[$path] = new Response($path);
			foreach ($this->controllers as $ctrl) {
				$routes = $ctrl->getMatchedRoutes($path);
				$this->responses[$path]->addRoutes($routes);
			}
			// si la petición no viene del cliente
			// forzará la devolución de una respuesta
			if (!$fake_path)
				return False;
		}
		// esta devolución sirve en caso el método respond
		// sea llamado nuevamente dentro de algún controlador
		// así se puede sobreescribir las rutas
		return $this->responses[$path];
	}

	/**
	 * Esteblece el entorno de renderizado, si no declaro uno
	 * o este no es un closure esta función establece uno por defecto
	 */
	private function setRender () {
		$this->render = $this->render instanceof \Closure ? 
			$this->render : function ($__path__, $__data__) {
				extract($__data__);
				
				if (!file_exists($__path__)) 
					throw new \Exception("template '{$__path__}' not found");
				include($__path__);
			};
	}

	/**
	 * Prepara variables de petición y ejecuta
	 * el entorno de renderizado de vista $this->render
	 * @param Response $response
	 */
	private function doRender (Response $response) {
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
