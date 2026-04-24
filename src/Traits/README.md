# Irbis\Traits

**Nivel de acoplamiento:** nulo.

Herramientas de clases, que implementan lógica general reutilizable.

## trait Singleton

Convierte una clase en un Singleton, creando un método desde donde se puede acceder a la única instancia de dicha clase.

> Ejemplo
```php
class MySingleton 
{
    use Singleton;
}

// la única forma de obtener una instacia
// siempre apuntará a la misma instancia
$singleton = MySingleton::getInstance();

// indica si ya existe la instancia creada o no
MySingleton::exists();
```

## trait SingletonFactory

Convierte una clase en una fabrica de Singletons, creando un método desde donde se puede acceder a las instancias únicas de cada clase por medio de un nombre.

> Ejemplo
```php
class MyFactory 
{
    use SingletonFactory;
}

// la única forma de obtener una instacia
// siempre apuntará a la misma instancia
$singleton1 = MyFactory::getInstance('instance1');
$singleton2 = MyFactory::getInstance('instance2');

$singleton1 === $singleton2; // false

// indica si ya existe la instancia creada o no
MyFactory::exists('instance1'); // true
MyFactory::exists('instance3'); // false
```

# trait Events

Implementa la gestión de eventos dentro de una clase, permitiendo agregar, quitar o disparar eventos.

> Ejemplo
```php
class MyClass 
{
    use Events;

    public $foo = "bar";
}

$myclass = new MyClass();

// registrar un evento
$myclass->on('user.created', function ($user) { 
    echo $this->foo; // bar
    echo "Usuario creado $user"; 
});

// disparar un evento
$myclass->fire('user.created', 'Jhon');
```