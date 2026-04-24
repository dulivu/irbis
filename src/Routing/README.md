# Irbis\Routing

**Nivel de acoplamiento:** medio
- Irbis\File
- Irbis\Http

## class Action

Es la combinación de una ruta y una acción ejecutable.

Tiene una implementación para ser en gran parte inmutable, no permitiendo modificarse una vez creada.

Implementa 'Attributes' por lo que también puede ser utilizado en método de clase como metadata.

> Propiedades
```php
public array $routes; // [Irbis\Routing\Route]
public array $methods; // [Irbis\Http\Method]
public array $constraints; // [Irbis\Routing\Constraint]
public int $priority;
```

> Especiales
```php
public function __construct (
    string|array $routes, 
    string|array $methods = [],
    string|array $constraints = [],
    int $priority = 50
);

// permite llamar a la clase como un closure
// ejecutando su acción establecida y devolviendo
// un objeto 'Irbis\Http\Response'
public function __invoke(
    ?Request $request = null, 
    ?Response $response = null
): Response
```

> Públicos
```php
// establece una acción ejecutable
public function action (callable $action): static
```

> Estáticos
```php
// shortcuts para crear una acción de un método específico
public static function get($routes, $action);
public static function post($routes, $action);
public static function put($routes, $action);
public static function patch($routes, $action);
public static function delete($routes, $action);
```

> Ejemplos
```php
$action = Action::get('/', function () {
    $response = new Response();
    $response->data('Hola mundo!');
    return $response;
});
$response = $action();

$response->send();
```
```php
// Action, debe asegurar que el objeto devuelto sea siempre un 'Response'
// por lo que es factible devolver otros tipos de valores compatibles
// con las opciones que 'Response' puede ejecutar
$action = Action::get('/', fn() => 'Hola mundo!');
$response = $action();

$response->send();
```

## class Handler

Esta clase gestiona una pila de 'Action' para una petición de cliente.

> Especiales
```php
public function __construct (Request $request);
```

> Públicos
```php
// añade y va filtrando acciones que se pueden ejecutar
public function push(Action $action);

// quita de la pila la ultima acción que hizo match
public function pop(): Response;

// pop, devuelve el objeto Response resultante de ejecutar la acción
// aún si se presenta un error, se convierte en un HttpException y posteriormente
// en un objeto Response con los datos del error.
```

> Ejemplos
```php
// para una solicitud a la ruta raíz
$request = Request::fromGlobals();
$handler = new Handler($request);

$handler->push(Action::get('/', fn() => 'Hola mundo!'));
$handler->push(Action::get('/', fn() => 'Adios mundo!'));

$response = $handler->pop();

$response->send(); // mostrará: Adios mundo!
```