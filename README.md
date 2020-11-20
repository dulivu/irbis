# Irbis
PHP MVC Micro-Framework.
Fácil de utilizar y enfocado al desarrollo modular conjuntamente con el patrón MVC.

## Como usarlo, principales consideraciones:
+ Renombre el directorio del framework, debe ser simplemente 'Irbis'.
+ La clase principal "Server" debe ser incluida en su archivo principal 'index.php'.
+ Cada módulo de su aplicación llevará un controlador principal, que será agregado a su instancia servidor.
+ El punto de entrada de la aplicación siempre será 'index.php' o su equivalente configurado en su servidor web.

*index.php*
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
- index.php (punto de entrada)

### Módulo, Controlador y el Auto-Cargador
Un módulo es un directorio con un archivo controlador dentro, puede organizar cada módulo con sus propios sub-directorios y archivos; por ejemplo, un carpeta 'views' donde guarde todas las vistas que utilize su módulo.

El controlador será una clase/objeto que la instancia 'Server' administrará. Debe heredar de la clase base \Irbis\Controller y podrá llevar métodos que respondan a rutas que el cliente pueda solicitar.

*\Test\Controller.php*
```php
namespace Test;

use Irbis\Controller as iController;

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

La directiva **'@route'** en los comentarios indican a que ruta debe responder dicho método, los comentarios se deben realizar con el formato estandar de php para métodos de clase (como se ve en el ejemplo, usar // o # no servirá).

Notese que la clase Controller del módulo está dentro de un espacio de nombres igual al nombre del directorio donde se encuentra, **el auto-cargador** utilizará el espacio de nombres igual que una ruta de directorio para buscar las clases no registradas y añadirlas a la ejecución.

Con los pasos realizados hasta aquí, debería poder visualizar en su navegador las palabras "Hola mundo!" (...localhost).

### Administrar peticiones y respuestas

Cada método que responde a una petición cliente recibe 2 parámetros, **$request** y **$response** en ese orden. Si creamos un formulario html y este envia datos a una ruta estos datos se obtienen por medio del objeto **$request**, y para enviar una vista especifica al cliente usamos el objeto **$response**, entre otras carácteristicas.

*/Test/views/index.html*
```html
<form method="POST">
  <input type="text" name="username"/>
  <input type="submit"/>
</form>

<span><?php echo $greeting ?? ''; ?></span>
```
*\Test\Controller.php*
```php
// Método 'Index' dentro de la clase 'Controller'
public function index ($request, $response) {
  if ($request->isMethod('POST')) {
    $response->data['greeting'] = 'Hola '.$request->input('username');
  }
  
  $response->view = '/Test/views/index.html';
}
```

Si nuestro **método enrutado** no devuelve ningún valor, como respuesta se utilizará el objeto $response que se le pasa. Caso contrario, se creará un nuevo objeto $response que tendrá de dato el valor devuelto del método. Por eso, en el ejemplo anterior, al devoler una cadena (*return 'hola mundo!'*) esta se muestra directamente al cliente.

Para obtener datos cliente del cuerpo del documento (POST) usamos el método 'input' sobre el objeto $request, para obtener datos cliente de la url (GET) usamos el método 'query'.

El objeto $response tiene dos atributos principales, **$view** que indica la ruta de la vista a utilizar para mostrar al cliente, **$data** un arreglo cuyos valores se pasarán a la vista como variables.
