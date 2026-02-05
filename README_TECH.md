# Request :Singleton

Permite acceder a todos los datos de la solicitud del cliente.

### Métodos Públicos

- **getInstance()** → devuelve la instancia única
- **isGet(): bool**
- **isPost(): bool**
- **isPut(): bool**
- **isDelete(): bool**
- **isPatch(): bool**
- **isJson(): bool**
- **query($key): mix** → devuelve un valor, en los parámetros de la url
- **input($key): mix** → devuelve un valor, en los parámetros del input body
- **cookie($key): mix** → devuelve un valor, en los parámetros de las cookies
- **path(int $key): mix** → devuelve un valor, en los comodines de la url
- **header(string $key): str** → devuelve una cabecera de petición
- **upload(string $key, string $path): void** → guarda un archivo subido por el cliente
- **rawContent(): string** → obtiene el valor crudo del cuerpo
- **hasUploads([string $key]): bool**
- **walkUploads(string $key, Closure $fn): void** → recorre cada uno de los archivos subidos

#### e: `input|query (string|array $key) : mixed`

**Patrones especiales:**
- `'*'` → Array completo
- `'*!'` → Array filtrado (sin nulls/empty)
- `['key1', 'key2']` → `['key1' => val1, 'key2' => val2]`
- `'key'` → Valor específico o null

**Ejemplos:**
```php
// URL: /search?q=irbis&page=2&filter=
$request->query('q');           // 'irbis'
$request->query('*');           // ['q' => 'irbis', 'page' => '2', 'filter' => '']
$request->query('*!');          // ['q' => 'irbis', 'page' => '2']
$request->query(['q', 'page']); // ['q' => 'irbis', 'page' => '2']
```

```php
// guardar un archivo subido
$request->upload('file', '/path/to/file');
```

### Métodos Mágicos

#### `__toString() : string`
**Retorna:** URL completa reconstruida  
**Ejemplo:** `"http://localhost/blog/post?id=5"`

#### `__get(string $name) : mixed`

**Fuentes (en orden):**
1. `$this->uri[$name]` → path, query, method, host
2. `$_SERVER[UPPER($name)]` → request_uri, server_name, etc.
3. `$name == 'user'` → Ejecuta session resolver del Server

**Ejemplos:**
```php
$request->path;    // '/blog/post'
$request->host;    // 'http://localhost'
$request->user;    // User|null desde sessionResolver
```


# Server :Singleton :Events

Orquestador central del framework.

### Métodos Públicos

- **setState(string $key, $val): void**
- **getState(string $key): mix|null**
- **saveState(): void**
- **getController(string $alias | $namespace): Controller|null**
- **setup(string $setup, ...[mix $args]): void** → opciones: errorView, renderEnvironment, sessionResolver

**Ejemplos:**
```php
$server->setup('errorView', 404, '@cli/debug-404.html');
$server->setup('renderEnvironment', function ($view, $data) { /* renderizar */ });
$server->setup('sessionResolver', function () { /* devolver usuario */ });
```


# Controller :abstract

Controlador de aplicación, es la clase que identifica y gestiona a una aplicación.

```php
class Controller extends \Irbis\Controller {
    public static $name = 'blog';           // Alias único
    public static $routable = true;         // ¿Tiene @routes?
    public static $depends = ['Apps/Auth']; // Dependencias
}
```

```php
$ctrl->namespace();             // 'Apps/Blog'
$ctrl->namespace('php');        // '\Apps\Blog\'
$ctrl->namespace('snake');      // 'apps_blog_'
$ctrl->namespace('dir');        // '/var/www/Apps/Blog'

$ctrl->super('/other/path');    // Internal fake request
$ctrl->application('Apps/Auth') // shorcut to getController
$ctrl->component('Model');      // Lazy singleton, components factory
```


# Action

Clase que envuelve a un método de un controlador para ser llamado desde el cliente.
Implementa @annotations para determinar como se debe comportar el objeto.

```php
class Controller {
    /**
     * @verb GET,POST
     * @route /blog/post/:num
     * @route /blog/posts
     * @auth user
     */
    final public function viewPost() {
        $id = Request::getInstance()->path(0); // Extrae :num
        $record = Record::find($id);
        return "record found {$record->id}";
    }
}
```

**Prefijos de ruta:**

- `@route /path/(:num)` - Normal
- `@route !/path/(:any)` - Importante, se colocan al inicio de la pila
- `@route ?/path/(:all)` - Opcional, se colocan al final de la pila

**Placeholders:**

- `:num` → `[0-9]+`
- `:any` → `[^/]+` (cualquier cosa menos /)
- `:all` → `.*` (todo hasta el final)

**Registro de anotaciones**, usualmente se configuran en un middleware

```php
// Customize your annotations
Action::setValidator('auth', function ($mode) {
    $user = $request->user;
    if (!$user) throw new Error('Usuario no autenticado');
});
```


# Response

Objeto que se usa para entregar una respuesta al cliente.
Este va ejecutando los `Action` apilados que el controlador le comunica al server.

**Devolviendo acciones**, dentro de un método `Action`, es decir, dentro de un método de un controlador.

```php
return 'texto a devolver';
// Caso 1: Solo vista
return 'views/post.html';
// Caso 2: Vista + data
return ['views/post.html', ['post' => $post]];
// Caso 4: Otro Response
return new Response;
```

### Métodos Públicos

- **view(string $path): void**
- **hasView(): boolean**
- **header(string $key, string $value): void**
- **body(mix $data): void**
- **append(string $key, mix $value): void**
- **remove(string $key): void**


# Extendiendo y Personalizando a medida

## Component Interface

Se usa dos elementos para crear un componente de backend `\Irbis\Interfaces\ApplicationComponentInterface` y `\Irbis\Traits\Component`.

El primero implementa un contrato para que la clase pueda ser usada como un componente, 
el segundo inyecta lógica que requiere el contrato.

```php
// Apps/Blog/Model.php
use \Irbis\Interfaces\ComponentInterface;
use \Irbis\Traits\Component;

class Model implements ComponentInterface {
    use Component
}
```

```php
// Uso
$model = $controller->component('Model'); // return new or existing instance Model object
```

## Setup Interface

Un contrato para un componente que pueda aplicar configuraciones principales al sistema, como middlewares o validaciones.

```php
use \Irbis\Interfaces\ComponentInterface;
use \Irbis\Interfaces\SetupInterface;
use \Irbis\Traits\Component;

class Setup implements ComponentInterface, SetupInterface {
    use Component;
    
    public function setup() {
        // aqui puedes implementar lógica antes de la ejecución
        // de respuesta al cliente, configuraciones, validaciones o middlewares
        Action::setValidator('auth', function);
    }
}
```

## Hooks Interface

Un contrato para un componente que pueda aplicar lógica durante la instalación o desinstalación de una aplicación.

```php
use \Irbis\Interfaces\ComponentInterface;
use \Irbis\Interfaces\HooksInterface;
use \Irbis\Traits\Component;

class Hooks implements ComponentInterface, HooksInterface {
    use Component;
    
    public function install() {
        // aqui puedes implmentar lógica de instalación
        // enlazar nuevos modelos a base de datos, configuraciones
    }

    public function uninstall () {
        // aqui puedes implementar lógica de desinstalación
        // eliminar registros de configuración, archivos, etc.
    }
}
```

## Session Interface

Un contrato para un componente que pueda resolver la sesion activa y entregar un usuario se sesion.

```php
use \Irbis\Interfaces\ComponentInterface;
use \Irbis\Interfaces\SessionInterface;
use \Irbis\Traits\Component;

class Session implements ComponentInterface, SessionInterface {
    use Component;
    
    public function getUser() {
        // devolver un objeto usuario capturado de la sesion
    }
}
```

```php
// Si se asigna en la configuración al atributo session del objeto 'Request'
$request = Request::getInstance();
// se puede acceder directamente por el atributo 'user'
$request->session = new Session();
// luego
$request->user; // llama automaticamente a $session->getUser();
```