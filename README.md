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

## Módulo, Controlador y el Auto-Cargador
Un módulo es un directorio con un archivo controlador dentro, puede organizar cada módulo con sus propios sub-directorios y archivos; por ejemplo, un carpeta 'views' donde guarde todas las vistas que utilize su módulo.

El controlador será una clase/objeto que la instancia 'Server' administrará. Debe heredar de la clase base \Irbis\Controller y podrá llevar métodos que respondan a rutas que el cliente pueda solicitar.

*/Test/Controller.php*
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

## Métodos enrutados
Los métodos que responden a una ruta solicitada por el cliente se declaran con una directiva (@route) en los comentarios del mismo, el valor que sigue a la directiva es la ruta, existen 3 comodines que se pueden usar para rutas relativas. Ejem.

@route / => enruta a la raiz del dominio (pj. http://localhost ó http://localhost/index.php).  
@route /users => enruta a una dirección (pj. http://localhost/index.php/users).  
*se puede prescindir de 'index.php' si se configura apache o el respectivo servidor y se activa MOD_REWRITE = true*.  
@route /users/(:num), para números, enruta a una dirección de tipo http://localhost/index.php/users/1.  
@route /users/(:any), para cadenas o números, enruta a una dirección de tipo http://localhost/index.php/users/jhon.  
@route /users/(:all), para cadenas o números (incluido el signo '/'), enruta a una dirección tipo http://localhost/index.php/users/jhon/5/admin.  

Si nuestro método responde a una ruta relativa, podemos obtener el valor del comodin con el objeto $request y su método 'path'.

```php
$request->path(0); // 1 para el ejemplo 3
$request->path(0); // 'jhon' para el ejemplo 4
$request->path(0); // 'jhon/5/admin' para el ejemplo 5
```

## Administrar peticiones y respuestas
Cada método que responde a una petición cliente recibe 2 parámetros, **$request** y **$response** en ese orden. Si creamos un formulario html y este envia datos a una ruta, estos datos se obtienen por medio del objeto **$request**, y para enviar una vista especifica al cliente usamos el objeto **$response**, entre otras carácteristicas.

*/Test/views/index.html*
```html
<form method="POST">
  <input type="text" name="username"/>
  <input type="submit"/>
</form>

<span><?php echo $greeting ?? ''; ?></span>
```
*/Test/Controller.php*
```php
// Método 'Index' dentro de la clase 'Controller'
public function index ($request, $response) {
  if ($request->isMethod('POST')) {
    $response->data['greeting'] = 'Hola '.$request->input('username');
  }
  
  $response->view = $this->dir.'/views/index.html';
}
```

Si nuestro **método enrutado** no devuelve ningún valor, como respuesta se utilizará el objeto $response que se le pasa. Caso contrario, se creará un nuevo objeto $response que tendrá de dato el valor devuelto del método. Por eso, en el ejemplo anterior, al devoler una cadena (*return 'hola mundo!'*) esta se muestra directamente al cliente.

Para obtener datos cliente del cuerpo del documento (POST) usamos el método 'input' sobre el objeto $request, para obtener datos cliente de la url (GET) usamos el método 'query'.

El objeto $response tiene dos atributos principales, **$view** que indica la ruta de la vista a utilizar para mostrar al cliente, **$data** un arreglo cuyos valores se pasarán a la vista como variables.

*$response [object]*
- Por defecto el atributo $data es de tipo array, así puede agregar valores rápidamente.
- Si no se asigna un valor al atributo $view, el atributo $data será enviado al cliente como vista (si este es un arreglo se convierte en JSON).
- Si el método enrutado devuelve un valor (return), este valor se pasará directamente al atributo $data
- Si el método enrutado devuelve una cadena de tipo **'/Test/views/index.html'**, este valor se pasará directamente al atributo $view

## Conexión a base de datos
Se utiliza una clase que extiende de la clase PDO, por lo que puede conectar a diferentes motores de bases de datos dependiendo de cuales tenga implementados, para el ejemplo lo haremos con MySQL.

*/Test/views/persons.html*
```html
<table>
  <tbody>
    <?php foreach ($persons as $person): ?>
      <tr>
        <td><?= $person['nombre'] ?></td>
        <td><?= $person['apellido'] ?></td>
        <td><?= $person['telefono'] ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
```

*/Test/Controller.php*
```php
namespace Test;

use Irbis\Controller as iController;
use Irbis\DataBase as DB;

class Controller extends iController {
  public $routes = true;
  
  /**
   * Este método responderá a la ruta base, / ó /index.php
   * @route /
   */
  public function index ($request, $response) {
    if ($request->isMethod('POST')) {
      $response->data['greeting'] = 'Hola '.$request->input('username');
    }

    $response->view = $this->dir.'/views/index.html';
  }
  
  /**
   * Debe tener una base de datos y una tabla llamada 'persons' con registros
   * @route /persons
   */
  public function persons ($request, $response) {
    $db = DB::getInstance('main');
    $stmt = $db->query("SELECT * FROM `persons`");
    
    $response->data['persons'] = $stmt->fetchAll();
    $response->view = $this->dir.'/views/persons.html';
  }
}
```
Para el ejemplo, si accedemos en local a 'http://localhost/index.php/persons', podremos visualizar la lista de usuarios como se programó.

Previamente deberemos tener un archivo de configuración (database.ini) en la raíz de nuestro proyecto, **Se recomienda utilizar reglas de acceso en el servidor web para evitar el acceso accidental a estos archivos por seguridad.**

*database.ini*
```html
[main]
dsn = "mysql:host=127.0.0.1;dbname=test"  
user = root  
pass = root  
```

*Para apache puedes usar la siguiente regla de seguridad, para evitar el acceso a archivos de configuración*
```html
<Files ~ "\.ini$">
  Order allow,deny
  Deny from all
</Files>
```

El método getInstance(), es estático y devuelve la conexión a base de datos cuyo nombre haya sido declarado en el archivo de configuración 'database.ini', puede declarar diferentes conexiones e invocarlas cada una con su respectivo nombre. Esta clase implementa un tipo de patrón Singleton por lo que si se vuelve a invocar una conexión, esta no se vuelve a crear, simplemente devuelve la instancia previamente creada.

## Modularidad
Finalmente el objetivo del framwework es la modularidad, poder generar código a través de capas de módulos, evitando en mayor medida la modificación de código previo. Para el ejemplo agregaremos otro módulo que sobreescribirá la ruta '/users' y añadirá un formulario para agregar usuarios. Primero creamos un nuevo directorio 'Test2' (el nuevo módulo) y dentro un archivo controlador 'Controller.php'. Quedando nuestro proyecto de la siguiente forma:

*directorio*
- Irbis
- Test
- Test2
  - views
    - persons.html
  - Controller.php
- index.php
- database.ini
 
 *index.php*
 ```php
require('Irbis/Server.php');

$server = \Irbis\Server::getInstance();

$server->addController(new \Test\Controller);
$server->addController(new \Test2\Controller);

$server->respond();
 ```
 
*/Test2/Controller.php*
```php
namespace Test2;

use Irbis\Controller as iController;
use Irbis\DataBase as DB;

class Controller extends iController {
  public $routes = true;
  
  /**
   * el método responderá a la misma ruta que en el otro controlador
   * @route /persons
   */
  public function persons ($request, $response) {
    $db = DB::getInstance('main');

    if ($request->isMethod('POST')) {
      $stmt = $db->prepare("INSERT INTO `persons` VALUES (?, ?, ?)");
      $stmt->execute($request->input(['nombre', 'apellido', 'telefono']));
    }
    // por medio de este método ejecutamos la lógica anterior y obtenemos el objeto $response del otro
    // controlador, por último le cambiamos la vista por la nueva y devolvemos el nuevo objeto $response
    $response = $this->getServer()->respond();
    $response->view = $this->dir.'/views/persons.html';
    return $response;
  }
}
```

*/Test2/views/persons.html*
```html
<form method="POST">
  <p>Nombre: <input type="text" name="nombre"/></p>
  <p>Apellido: <input type="text" name="apellido"/></p>
  <p>Telefono: <input type="text" name="telefono"/></p>
  <p><input type="submit"/></p>
</form>

<?php include('Test/views/persons.html'); ?>
```
De esta forma, si el nuevo módulo presentara algún problema de código o quisieramos regresar el sistema a una versión anterior, simplemente comentamos la línea donde se agrega este nuevo módulo y todo funcionaría como antes.

```php
# $server->addController(new \Test2\Controller);
```

**Observaciones:** es importante el orden en el que se agregan los módulos al sistema, los métodos enrutados del último módulo agregado tendrán preferencia para responder al cliente, al llamar al método 'respond()' nuevamente, irá ejecutando cada método para esa ruta en orden inverso. No es obligatorio llamar al método 'respond()', esto se hace cuando queremos ejecutar lógica previa.

## Constantes
El framework declara algunas constantes en caso estas no hayan sido previamente declaradas, podemos controlar su valor en nuestro archivo de entrada 'index.php', deberemos declararlas antes de llamar a nuestro archivo principal 'server.php'. Estas sirven para modificar algunos comportamientos del framework.

*index.php*
```php
// aquí podremos declarar nuestras constantes.
define('MOD_REWRITE', true);
require('Irbis/Server.php');
$server = \Irbis\Server::getInstance();
$server->addController(new \Test\Controller);
$server->respond();
```

**MOD_REWRITE** (por defecto, falso), si es verdadero las rutas no requerirán que se declare explicitamente el archivo 'index.php', para esta característica primero se debe configurar su servidor (para apache el archivo .htaccess por ejemplo).

*.htaccess*
```html
Options +FollowSymLinks
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php?$1 [QSA,L]
```

**DB_INI** (por defecto, 'database.ini'), indica la ruta donde se encuentra el archivo de configuracion de base de datos.  
**REQUEST_EMULATION** (por defecto, falso), si es verdadero el método $request->isMethod(*[string]*) validará también verbos PUT y DELETE que vayan en el cuerpo del documento en una variable '\_method'.  
**DEBUG_MODE** (por defecto, falso), si es verdadero se muestra más información de errores en las respuestas.  
**DEFAULT_VIEW** (por defecto, 'index'), es el valor por defecto que se usa en los métodos $request->query('view') ó $request->path(0), útil para cargar vistas.  
**BASE_PATH** (por defecto, la ruta donde se encuentra la aplicación), no se recomienda cambiar este valor.  
**CRYP_KEY**, clave a usar en los métodos de encriptación y desencriptación.  
**CRYP_METHOD**, método de encriptación a utilizar.  
