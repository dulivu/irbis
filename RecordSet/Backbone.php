<?php
namespace Irbis\RecordSet;
use Irbis\Server;
use Irbis\Controller;


/**
 * Controla la estructura central del modelo cargando la definición
 * de propiedades y métodos de cada módulo registrado en el servidor, 
 * esta clase es de uso exclusivo de la clase RecordSet 
 * (se debe entender como una caja negra)
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
class Backbone {

	public $name;
	public $statics = [];

	private $properties = [];
	private $methods = [];

	private static $instances = [];

	public static function getInstance (string $name) {
		self::$instances[$name] = self::$instances[$name] ?? new self($name);
		return self::$instances[$name];
	}

	private function __construct (string $name) {
		$this->name = $name;
		$self = $this;

		# todos los modelos llevan un campo ID
		$this->setMember('id', [
			'int', 
			'label' => 'ID', 
			'oSQL' => 'UNIQUE AUTO_INCREMENT', 
			'readonly' => true,
			'store' => false,
		]);

		# se recorre todos los controladores registrados para obtener
		# los modelos que pudiera tener y agregarlos a la definición
		Server::getInstance()
			->forEachController(function ($controller) use ($self) {
				$file = "models/{$self->name}.php";
				if ($skeleton = $controller->file($file, Controller::FILE_INCLUDE)) {
					foreach ($skeleton as $key => $value)
						$self->setMember($key, $value);
				}
			});

		if (count($this->properties) <= 1)
			throw new \Exception("recordset: no se pudo importar una definición para '$name'");
	}

	public function setMember ($key, $value = null) {
		if ($key == '__extend') {
			$this->statics[$key] = $value;
			$this->setMember(self::getInstance($value));
		} elseif ($key instanceof self) {
			foreach ($key->getProperties() as $k => $m)
				$this->properties[$k] = clone $m;
			foreach ($ket->getMethods() as $k => $m)
				$this->methods[$k] = clone $m;
		} elseif (is_callable($value)) {
			if (!$this->hasMethod($key))
				$this->methods[$key] = new Method($key);
			$this->methods[$key]->addClosure($value);
		} elseif (!str_starts_with($key, '__')) {
			if (!$this->hasProperty($key))
				$this->properties[$key] = new Property($key);
			$this->properties[$key]->define($value);
		} else {
			$this->statics[$key] = $value;
		}
	}

	public function hasProperty ($key) {
		return isset($this->members[$key]) && 
			$this->members[$key] instanceof Property;
	}

	public function hasMethod ($key) {
		return isset($this->members[$key]) &&
			$this->members[$key] instanceof Method;
	}

	public function getProperties ($key = false) {
		if (is_string($key))
			return clone $this->properties[$key] ?? false;
		elseif (is_array($key))
			return array_map(function ($prop) {
				return clone $this->properties[$prop] ?? false;
			}, $key);
		else return array_map(function ($prop) {
			return clone $this->properties[$prop] ?? false;
		}, array_keys($this->properties));
	}

	public function getMethods ($key = false) {
		if (is_string($key))
			return isset($this->methods[$key]) ? clone $this->methods[$key] : false;
		elseif (is_array($key))
			return array_map(function ($prop) {
				return isset($this->methods[$prop]) ? clone $this->methods[$prop] : false;
			}, $key);
		else return array_map(function ($prop) {
			return clone $this->methods[$prop];
		}, array_keys($this->methods));
	}
}