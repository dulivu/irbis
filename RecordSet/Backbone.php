<?php
namespace Irbis\RecordSet;
use Irbis\Server;


/**
 * Controla la estructura central del modelo cargando los miembros definidos, 
 * esta clase es de uso exclusivo dentro de otra, RecordSet
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <GeorgeL1102@gmail.com>
 * @version		1.0
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
		Server::getInstance()->forEachController(function ($controller) use ($instance) {
			$s = DIRECTORY_SEPARATOR;
			$file = "models".$s."{$instance->name}.php";
			if ($disc = $controller->include($file, true)) {
				foreach ($disc as $key => $value)
					$instance->addMember($key, $value);
			}
		});

		if (count($this->members) <= 1)
			throw new \Exception("recordset: no se pudo importar una definición para '$name'");
	}

	/**
	 * Verifica que un nombre de instancia exista, si no lo crea
	 * devuelve el nombre de instancia
	 * @param string $name
	 * @return \Irbis\RecordSet\members
	 */
	public static function getInstance (string $name) {
		self::$instances[$name] = self::$instances[$name] ?? new self($name);
		return self::$instances[$name];
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
			foreach ($key->getMembers() as $k => $m) $this->members[$k] = $m;
		} elseif (is_callable($value)) {
			$this->members[$key] = $this->members[$key] ?? new Method($key);
			$this->members[$key]->stack($value);
		} else {
			$this->members[$key] = $this->members[$key] ?? new Property($key);
			$this->members[$key]->alter($value);
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
	public function getProperty ($key, $recordset = null) {
		return !$this->hasProperty($key) ? false :
			clone $this->members[$key]->setRecordSet($recordset);
	}

	/**
	  * @param string $name
	  * @return Irbis\RecordSet\Method
	  */
	public function getMethod ($key, $recordset = null) {
		return !$this->hasMethod($key) ? false :
			clone $this->members[$key]->setRecordSet($recordset);
	 }

	/**
	 * @return array[Irbis\RecordSet\Property]
	 */
	public function getProperties ($recordset = null) {
		$prop = [];
		foreach ($this->members as $key => $member) {
			if (!($member instanceof Property)) continue;
			$prop[$key] = clone $member->setRecordSet($recordset);
		} return $prop;
	}

	/**
	 * @return array[Irbis\RecordSet\Method]
	 */
	public function getMethods ($recordset = null) {
		$prop = [];
		foreach ($this->members as $key => $member) {
			if (!($member instanceof Method)) continue;
			$prop[$key] = clone $member->setRecordSet($recordset);
		} return $prop;
	}

	/**
	 * @return array
	 */
	public function getMembers ($recordset = null) {
		$m = [];
		foreach ($this->members as $key => $member) {
			$m[$key] = clone $member->setRecordSet($recordset);
		} return $m;
	}
}