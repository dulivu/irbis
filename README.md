# Irbis-Framework
PHP MVC Slim Framework.
Fácil de utilizar y enfocado al desarrollo modular conjuntamente con el patrón MVC.

## Como usarlo, principales consideraciones:
+ Renombre el directorio del framework, debe ser simplemente 'Irbis'.
+ La clase principal "Server" debe ser incluida en su archivo principal 'index.php'.
+ Cada módulo de su aplicación llevará un controlador principal.

*Index.php*
```php
// solicitas el archivo "Server" del framework
// para el ejemplo está en el directorio raiz
require('Irbis/Server.php');

// obtienes la instacia única Servidor
$server = \Irbis\Server::getInstance();

// añades el controlador de un módulo llamado 'Test'
$server->addController(new \Test\Controller);

// el servidor brinda una respuesta a la petición del cliente
$server->respond();
```

### Directorios/Módulos
Un módulo básico comprende un directorio con un archivo controlador, ejemplo:
- Irbis (framework)
- Test (módulo)
  - views
    - index.html
    - contact.html
  - Controller.php
- index.php
