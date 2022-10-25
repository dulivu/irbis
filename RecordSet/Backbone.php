<?php
namespace Irbis\RecordSet;
use Irbis\Server;


/**
 * Controla la estructura central del modelo cargando los miembros definidos, 
 * esta clase es de uso exclusivo dentro de otra, RecordSet
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
class Backbone {

	/**
	 * Nombre de la definición
	 * @var string
	 */
	public $name;

	/**
	 * Arreglo donde se almacena la definición
	 * @var array
	 */
	private $members;

	/**
	 * Instancias creadas por nombre
	 * @var \Irbis\RecordSet\members
	 */
	private static $instances = [];

	/**
	 * Verifica que un nombre de instancia exista, si no, lo crea
	 * devuelve el nombre de instancia
	 * @param string $name
	 * @return \Irbis\RecordSet\members
	 */
	public static function getInstance (string $name) {
		self::$instances[$name] = self::$instances[$name] ?? new self($name);
		return self::$instances[$name];
	}

	/**
	 * Contructor privado recorre todos los archivos /models/{name} 
	 * dentro de cada controlador registrado
	 * @param string $name
	 */
	private function __construct (string $name) {
		$this->name = $name;
		$this->addMember('id', [
			'int', 
			'label' => 'ID', 
			'oSQL' => 'UNIQUE AUTO_INCREMENT', 
			'readonly' => true,
			'store' => false,
		]);

		$instance = $this;
		Server::getInstance()
			->forEachController(function ($controller) use ($instance) {
				$s = DIRECTORY_SEPARATOR;
				$file = "models".$s."{$instance->name}.php";
				if ($spinal_disc = $controller->include($file, true)) {
					foreach ($spinal_disc as $key => $value)
						$instance->addMember($key, $value);
				}
			});

		if (count($this->members) <= 1)
			throw new \Exception("recordset: no se pudo importar una definición para '$name'");
	}

	/**
	 * Combina una definición, determina automáticamente
	 * si la definición es un método o una propiedad
	 * @param string $key
	 * @param mix $value
	 */
	public function addMember ($key, $value = null) {
		if ($key == '__extend') {
			$this->addMember(self::getInstance($value));
		} elseif ($key instanceof self) {
			foreach ($key->getMembers() as $k => $m) 
				$this->members[$k] = clone $m;
		} elseif (is_callable($value)) {
			$this->getMethod($key, true)->stack($value);
		} else {
			$this->getProperty($key, true)->alter($value);
		}
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasProperty ($key) {
		return isset($this->members[$key]) && 
			$this->members[$key] instanceof Property;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasMethod ($key) {
		return isset($this->members[$key]) &&
			$this->members[$key] instanceof Method;
	}

	/**
	 * @param string $key
	 * @return Irbis\RecordSet\Property
	 */
	public function getProperty ($prop_name, $add_create = false) {
		if ($this->hasProperty($prop_name)) {
			return $this->members[$prop_name];
		} else {
			if ($add_create)
				return $this->members[$prop_name] = new Property($prop_name);
			return false;
		}
	}

	/**
	  * @param string $name
	  * @return Irbis\RecordSet\Method
	  */
	public function getMethod ($method_name, $add_create = false) {
		if ($this->hasMethod($method_name)) {
			return $this->members[$method_name];
		} else {
			if ($add_create)
				return $this->members[$method_name] = new Method($method_name);
			return false;
		}
	 }

	/**
	 * @return array[Irbis\RecordSet\Property]
	 */
	public function getProperties () {
		return array_filter($this->members, function ($member) {
			return $member instanceof Property;
		});
	}

	/**
	 * @return array[Irbis\RecordSet\Method]
	 */
	public function getMethods () {
		return array_filter($this->members, function ($member) {
			return $member instanceof Method;
		});
	}

	/**
	 * @return array
	 */
	public function getMembers () {
		return $this->members;
	}
}