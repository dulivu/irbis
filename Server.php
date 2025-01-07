<?php 

namespace Irbis;

include 'Constants.php';
include 'Functions.php';

irbis_loader();

use Irbis\Traits\Singleton;
use Irbis\Traits\Events;


/**
 * Punto de entrada de la aplicación
 * TODO LIST
 * middlewares
 * i18n
 * Cron Jobs
 * 
 * 
 * @package 	irbis
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.2
 */

class Server {
	use Singleton, Events;

	public $render;

	/**
	 * Vistas por defecto para errores
	 * @var string
	 */
	public $view_404 = __DIR__.'/404.html';
	public $view_500 = __DIR__.'/500.html';

	/**
	 * Arreglo asociativo con todos los controladores 
	 * registrados por el método 'addController'
	 * @var array['class_name' => \Irbis\Controller]
	 */
	protected $controllers = [];
	protected $controllers_map = [];

	/**
	 * Arreglo asociativo con las respuesta a la petición,
	 * las respuestas son objetos que gestionan lo que los 
	 * controladores calcularon en función de la petición 
	 * del cliente
	 * @var array['path' => \Irbis\Response]
	 */
	protected $responses = [];
	protected $responded = False; # status

	protected $request;
	
	protected $middlewares = []; # TODO

	private function ensureRequest () {
		if (!$this->request)
			$this->request = Request::getInstance();
	}

	/**
	 * Realiza operaciones de encendido del servidor
	 * @param Controller [$controllers]
	 *  - inicializa el objeto 'request'
	 *  - registra todos los módulos a utilizar
	 */
	public function setup (array $applications) {
		$this->ensureRequest();

		foreach ($applications as $alias => $application) {
			if (gettype($application) == 'string') {
				$namespace = path_to_namespace($application)."\\Controller";
				$applications[$alias] = new $namespace;
			} else {
				$applications[$alias] = $application;
			}
		}

		foreach ($applications as $alias => $application) {
			$alias = gettype($alias) == 'string' ? $alias : '';
			$this->addController($application, $alias);
		}
	}

	/**
	 * Añade un nuevo módulo (por medio de su controlador) al servidor
	 * @param Controller $controller
	 * @param string $alias, nombre alternativo del controlador
	 * @fire 'addController'
	 */
	public function addController (Controller $controller, string $alias = '') {
		$this->ensureRequest();
		$alias = $alias ?: $controller->name;

		# TODO: control de dependencias, valida que los controladores requeridos
		# estén previamente registrados, sino lanza un error.
		# Esto puede causar problemas al usar módulos dinámicos no agregados en el index
		foreach ($controller->depends as $depend)
			if (!$this->getController($depend))
				throw new \Exception("{$controller->key()} requiere previamente: $depend");
		
		$this->controllers[$controller->key()] = $controller->assembleTo($this);
		if ($alias) $this->controllers_map[$alias] = $controller->key();
		$this->fire('addController', [$controller, $alias]);
	}

	/**
	 * Devuelve un controlador por su nombre de clase o alias
	 * @param string $name
	 */
	public function getController (string $name) : ?Controller {
		$name = str_replace('\Controller', '', $name);
		if (array_key_exists($name, $this->controllers_map))
			$name = $this->controllers_map[$name];
		elseif (strpos($name, '/') === 0)
			$name = substr($name, 1);
		return $this->controllers[$name] ?? null;
	}

	/**
	 * Devuelve un objeto response para la ruta cliente
	 * recorre todos los controladores registrados y les solicita
	 * entregar las Rutas registradas coíncidentes con la solicitud
	 * @param string $path
	 */
	protected function getResponse (string $path) : Response {
		if (!array_key_exists($path, $this->responses)) {
			$this->responses[$path] = new Response($path);
			foreach ($this->controllers as $ctrl) {
				$routes = $ctrl->getMatchedRoutes($path);
				$this->responses[$path]->addRoutes($routes);
			}
		}
		return $this->responses[$path];
	}

	/**
	 * Entrega una respuesta a la petición del cliente
	 * @param string $fake_path (optional), en caso se quiera obtener 
	 * 										una respuesta dentro del código 
	 * 										de un controlador para simular
	 * 										herencia de rutas
	 * @return null | \Irbis\Response
	 */
	public function execute (string $fake_path = '') {
		$path = $fake_path ?: $this->request->path;

		$response = $this->getResponse($path);
		$prepared = $response->prepareRoute();

		// si la petición no viene del cliente
		if ($this->responded || $fake_path) {
			return $response->executeRoute();
		}

		// este bloque controla errores y que la ruta solicitada
		// se encuentre registrada, prepara la respuesta final que
		// el cliente recibirá
		try {
			$this->responded = True;
			if ($prepared) {
				foreach ($this->controllers as $ctrl) { $ctrl->init(); }
				$response = $response->executeRoute();
			} else {
				header("HTTP/1.0 404 Not Found");
				$response->view = $this->view_404;
				$response->data = [
					'error' => [
						'code' => 404,
						'message' => 'Ruta solicitada no encontrada',
						'class' => 'HttpNotFound'
					]
				];
			}
		} catch (\Throwable $e) {
			header("HTTP/1.0 500 Internal Server Error");
			$response->view = $this->view_500;
			$response->data = [
				'error' => [
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
					'class' => get_class($e),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'trace' => DEBUG_MODE ? $e->getTrace() : [],
				]
			];
		} finally {
			$this->fire('response', [&$response]);
			if (!$response->view)
				die($response."");
			$this->setRender();
			$this->doRender($response);
		}
		return null;
	}

	/**
	 * Esteblece el entorno de renderizado '$this->render'
	 * entorno: función que llama y ejecuta la vista
	 */
	protected function setRender () {
		$this->render = $this->render instanceof \Closure ? 
			$this->render : function ($__path__, $__data__) {
				extract($__data__);
				
				if (!file_exists($__path__)) 
					throw new \Exception("template '{$__path__}' not found");
				include($__path__);
			};
	}

	/**
	 * usa el entorno de renderizado de vista '$this->render'
	 * DEFAULT_VIEW (index) y las variables del cliente son usadas para reemplazar en la vista
	 * ejem 1: /test?view=index -> views/{view}.html -> views/index.html
	 * ejem 2: /test/(:index) -> views/(0).html -> views/index.html
	 * @param Response $response
	 */
	protected function doRender (Response $response) {
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
	 * Ejecuta una retrollamada por cada controlador registrado
	 * @param Closure $fn
	 */
	public function forEachController (\Closure $fn) {
		foreach ($this->controllers as $key => $val) {
			$fn($val, $key);
		}
	}
}
