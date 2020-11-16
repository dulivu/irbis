# Irbis
PHP MVC Slim Framework.
Fácil de utilizar y enfocado al desarrollo modular conjuntamente con el patrón MVC.

## Como usarlo, principales consideraciones:
+ Renombre el directorio del framework, debe ser simplemente 'Irbis'.
+ La clase principal "Server" debe ser incluida en su archivo principal 'index.php'.
+ Cada módulo de su aplicación llevará un controlador principal, que será agregado a su instancia servidor.

*Index.php*
```php
// solicitas el archivo "Server" del framework
// para el ejemplo está en el directorio raiz
require('Irbis/Server.php');

// obtienes la instancia única Servidor
$server = \Irbis\Server::getInstance();

// añades el controlador de un módulo llamado 'Test'
$server->addController(new \Test\Controller);

// brindas la respuesta a la petición del cliente
$server->respond();
```

### Directorios y Módulos
Un módulo básico comprende un directorio con un archivo controlador, ejemplo:
- Irbis (framework)
- Test (módulo)
  - views
    - index.html
    - contact.html
  - Controller.php
- index.php

### Módulo, Controlador y el Auto-Cargador
Un módulo es un directorio con un archivo controlador dentro, puede organizar cada módulo con sus propios sub-directorios y archivos; por ejemplo, un carpeta 'views' donde guarde todas las vistas que utilize su módulo.

El controlador será una clase/objeto que la instancia 'Server' administrará. Debe heredar de la clase base \Irbis\Controller y podrá llevar métodos que representarán respuestas a rutas que el cliente pueda solicitar.

*Controller.php*
```php
namespace Test;

user Irbis\Controller as iController;

class Controller extends iController {
  // este atributo se debe declarar 'verdadero' le indica al controlador
  // que debe registrar sus métodos como rutas de petición para el cliente
  public $routes = true;
  
  /**
   * Este método responderá a la ruta base, / ó /index.php
   * @route /
   */
  public function index () {
    return 'Hola mundo!';
  }
}
```

La directiva '@route' en los comentarios indican a que ruta debe responder dicho método, los comentarios se deben realizar con el formato estandar de php para métodos de clase (como se ve en el ejemplo, usar // o # no servirá).

Notese que la clase Controller del módulo está dentro de un espacio de nombres igual al nombre del directorio donde se encuentra, el auto-cargador utilizará el espacio de nombres igual que una ruta de directorio para buscar las clases no registradas y añadirlas a la ejecución.

Con los pasos realizados hasta aquí, debería poder visualizar en su navegador las palabras "Hola mundo!".
