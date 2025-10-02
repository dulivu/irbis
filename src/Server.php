<?php 

namespace Irbis;

include 'Constants.php';
include 'Functions.php';

irbis_loader();

use Irbis\Traits\Singleton;
use Irbis\Traits\Events;
use Irbis\Exceptions\HttpException;


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

	# closure de renderizado de vistas
	public $render;
	# vistas de error HTTP
	private $http_error_views = [
		404 => __DIR__.'/404.html',
		500 => __DIR__.'/500.html',
	];
	# Lista de controladores registrados
	protected $controllers = [];
	protected $controllers_map = [];
	# Lista de respuestas a cliente
	protected $responses = [];
	protected $responded = False; # FLAG
	# objeto \Request
	protected $request;
	# TODO: opción a implementar
	protected $middlewares = [];

	private function ensureRequest () {
		# asegura que el objeto 'request' esté inicializado
		if (!$this->request)
			$this->request = Request::getInstance();
	}

	public function setViewError ($key, $views=false) {
		# Establece una vista para un error HTTP
		if (is_array($key)) {
			foreach ($key as $k => $v) {
				$this->setViewError($k, $v);
			}
		} else {
			$this->http_error_views[$key] = $views;
		}
	}

	public function getViewError ($key) {
		# obtiene una vista para un error HTTP
		if (array_key_exists($key, $this->http_error_views)) {
			return $this->http_error_views[$key];
		}
		# por defecto devuelve la vista de error 500
		return $this->http_error_views[500];
	}

	public function addController (Controller $controller, string $alias = '') {
		$this->ensureRequest();
		$alias = $alias ?: $controller::$name;
		if (!$alias) throw new \Exception("Un controlador debe tener un nombre ó alias");

		# TODO: control de dependencias, valida que los controladores requeridos
		# estén previamente registrados, si no lanza un error. Por mejorar
		foreach ($controller::$depends as $depend) {
			if (!$this->getController($depend))
				throw new \Exception("{$controller->key()} requiere previamente: $depend");
		}
		
		# Registrar el controlador en el servidor
		$key = $controller->namespace();
		$this->controllers_map[$alias] = $key;
		$this->controllers[$key] = $controller;
		# FIRE: lanza evento 'addController'
		$this->fire('addController', [$controller, $alias]);
	}

	public function getController (string $name) : ?Controller {
		# Busca el modulo por su alias, si es que existe
		if (array_key_exists($name, $this->controllers_map))
			$name = $this->controllers_map[$name];
		# Devuelve el controlador registrado por su nombre
		# e: Package/Module => IrbisApps/Base
		return $this->controllers[$name] ?? null;
	}

	public function baseController (Controller $setupController, $preHooks, $postHooks) {
		# patrón para agregar un controlador maestro
		$this->ensureRequest();
		foreach ($preHooks as $pre) {
			if (!method_exists($setupController, $pre)) {
				throw new \Exception("El controlador '{$setupController->namespace()}' no tiene el método '{$pre}'");
			}
			$setupController->{$pre}();
		}

		$this->addController($setupController);

		foreach ($postHooks as $post) {
			if (!method_exists($setupController, $post)) {
				throw new \Exception("El controlador '{$setupController->namespace()}' no tiene el método '{$post}'");
			}
			$setupController->{$post}();
		}
	}

	protected function getResponse (string $path) : Response {
		if (!array_key_exists($path, $this->responses)) {
			# Crea un objeto \Response para la ruta
			$this->responses[$path] = new Response($path);
			foreach ($this->controllers as $ctrl) {
				# Obtiene las acciones de cada controlador
				# que coincidan con la ruta solicitada
				$actions = $ctrl->getMatchedActions($path);
				# Y las agrega a la respuesta
				$this->responses[$path]->addActions($actions);
			}
		}
		# Devuelve el objeto \Response para la ruta
		return $this->responses[$path];
	}

	public function execute (string $fake_path = '') {
		# fake_path, se usará para simular una llamada a una ruta
		$path = $fake_path ?: $this->request->path;
		
		$response = $this->getResponse($path);
		$prepared = $response->prepareAction();
		# si la petición no viene del cliente, ejecuta y finaliza
		if ($this->responded || $fake_path) {
			return $response->executeAction();
		}

		try {
			$this->responded = True; # FLAG: on
			foreach ($this->controllers as $ctrl) { $ctrl->init(); }
			# si 'prepared' es false, no hay acciones que ejecutar
			if (!$prepared) throw new HttpException('Not Found', 404);
			$response = $response->executeAction();
		} catch (\Throwable $e) {
			# En caso de error, prepara la vista por defecto
			# y formatea los detalles del error
			if ($e instanceof HttpException) {
				$response->setHeader("HTTP/1.1 {$e->getCode()} {$e->getMessage()}");
				$response->setView($this->getViewError($e->getCode()));
			} else {
				$response->setHeader("HTTP/1.1 500 Internal Server Error");
				$response->setView($this->getViewError(500));
			}
			
			$response->setData([
				'error' => [
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
					'class' => get_class($e),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'trace' => DEBUG_MODE ? $e->getTrace() : [],
				]
			]);
		} finally {
			# FIRE: lanza el evento 'response'
			$this->fire('response', [&$response]);
			# Si no hay una vista definida, lanza lo que tenga en datos
			if (!$response->getView()) die($response."");
			$this->setRender();
			$this->doRender($response);
		}
		return null;
	}

	protected function setRender () {
		# Establece el entorno de renderizado '$this->render'
		$this->render = $this->render instanceof \Closure ? 
			$this->render : function ($__path__, $__data__) {
				extract($__data__);
				
				if (!file_exists($__path__)) 
					throw new \Exception("template '{$__path__}' not found");
				include($__path__);
			};
	}

	protected function doRender (Response $response) {
		$request = $this->request;
		$search = [];
		$replace = [];

		# e: /test?view=index -> views/{view}.html -> views/index.html
		foreach ($request->query('*', ['view' => DEFAULT_VIEW]) as $k => $v) {
			if (!is_array($v)) {
				$search[] = "{{$k}}";
				$replace[] = $v;
			}
		}
		# e: /test/(:index) -> views/(0).html -> views/index.html
		foreach ($request->path('*', [0 => DEFAULT_VIEW]) as $k => $v) {
			$search[] = "($k)";
			$replace[] = $v;
		}

		$render = $this->render->bindTo(null);
		$render(
			str_replace($search, $replace, $response->getView()), 
			$response->getData()
		);
	}
	
	public function forEachController (\Closure $fn) {
		# Ejecuta una retrollamada por cada controlador registrado
		foreach ($this->controllers as $key => $val) {
			$fn($val, $key);
		}
	}
}
