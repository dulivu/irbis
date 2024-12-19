# Irbis
PHP MVC Micro-Framework.
Fácil de utilizar y enfocado al desarrollo modular conjuntamente con el patrón MVC.

## Instalación
+ [Composer](https://getcomposer.org/download/) (recomendada):
```sh
composer require dulivu/irbis
```
+ Manual: descargue los fuentes y coloque la carpeta del framework en su proyecto, renombrela como 'Irbis'

## Como usarlo, principales consideraciones:
Si realiza una instalación manual, en su archivo de entrada 'index.php', debe llamar al archivo principal 'Server'. Este archivo usará un autocargador pre-configurado para llamar a todas las clases del framework.
```php
require('Irbis/Server.php');
```
Si está utilizando Composer, este ya implementa un autocargador por lo que basta con llamar al autoload de composer como indica su [documentación](https://getcomposer.org/doc/01-basic-usage.md#autoloading).
```php
require('vendor/autoload.php');
```
El punto de entrada de la aplicación siempre será 'index.php' o su equivalente configurado en su servidor web. Siendo su archivo principal de la siguiente forma.

*index.php*
```php
// Aquí llamamos a nuestro autocargador: 
// require('Irbis/Server.php')
require('vendor/autoload.php')

// Obtenemos la instancia única del servidor
$server = \Irbis\Server::getInstance();

// añadimos nuestro primer módulo llamado 'Test'
// dentro de cada módulo siempre debe haber una clase controladora
// puede llamarla como desee, pero se recomienda el nombre 'Controller'
$server->addController(new \Test\Controller);
// la lógica de nuestra aplicación irá dentro de estos controladores
// y puede ir agregando más módulos con funcionalidades específicas

// Finalmente ejecutamos el servidor
$server->execute();
```

### Módulos y estructura
Un módulo estará encapsulado dentro de un directorio con su respectivo controlador.
Siguiendo el ejemplo del módulo 'Test' el directorio del sitio web tendría esta apariencia:
```plaintext
- Test (nombre del módulo)
  - views (directorio para guardar vistas html)
    - index.html
    - contact.html
  - Controller.php (Controlador del módulo Test)
- index.php (punto de entrada)
```

### Módulos, Controlador y el Auto-Cargador
Un módulo es un directorio con un archivo controlador dentro, puede organizar cada módulo con sus propios sub-directorios y archivos; por ejemplo, un carpeta 'views' donde guarde todas las vistas que utilice su módulo.

El controlador será un objeto que la instancia 'Server' administrará. Debe heredar de la clase base \Irbis\Controller y podrá llevar métodos que respondan a rutas que el cliente pueda solicitar.

*/Test/Controller.php*
```php
namespace Test;

use Irbis\Controller as iController;

class Controller extends iController {
  // es importante aunque no obligatorio declarar estos atributos
  public $name          = 'test';   // nombre alias del módulo
  public $has_routes    = true;     // si este controlador gestionará rutas de cliente
  
  /**
   * Este método responderá a la ruta base
   * localhost ó localhost/index.php
   * @route /
   */
  public function index () {
    return 'Hola mundo!';
  }

  /**
   * @route /contact
   */
  public function contact () {

  }
}
```

La directiva **'@route'** en los comentarios indica a que ruta debe responder dicho método, los comentarios se deben realizar con el formato [estandar](https://manual.phpdoc.org/HTMLframesConverter/default/) de php para métodos de clase (como se ve en el ejemplo, usar // o # no servirá).

Notese que la clase **Controller** del módulo está dentro de un espacio de nombres igual al nombre del directorio donde se encuentra, **el auto-cargador** utilizará el espacio de nombres igual que una ruta de directorio para buscar las clases no registradas y añadirlas a la ejecución.

**Con los pasos realizados hasta aquí, debería poder visualizar en su navegador las palabras "Hola mundo!"** ([http://localhost](http://localhost)).

## Métodos enrutados
Los métodos que responden a una ruta solicitada por el cliente se declaran con una directiva (@route) en los comentarios del mismo, el valor que sigue a la directiva es la ruta, existen 3 comodines que se pueden usar para rutas relativas. Ejemplos:

> @route / => enruta a la raiz del dominio http://localhost ó http://localhost/index.php.

> @route /users => http://localhost/index.php/users.


*se puede prescindir de 'index.php' si se configura el archivo .htaccess y se activa MOD_REWRITE = true.*  

> @route /users/(:num) => http://localhost/index.php/users/1.

> @route /users/(:any) => http://localhost/index.php/users/jhon.

> @route /users/(:all) => http://localhost/index.php/users/jhon/5/admin.


*esta última incluye signos especiales como '/' para casos especiales, no se recomienda usar ya que puede causar conflictos con otras rutas*

> @route /users/(:any)/(:num) => http://localhost/index.php/users/jhon/5

*es posible combinar comodines para un mejor control de las rutas*

Si nuestro método responde a una ruta relativa, podemos obtener el valor del comodin con el objeto **$request** y su método 'path' que recibe como parámetro el índice donde se ubica el comodín como si de un arreglo se tratase.

```php
$val = $request->path(0); // 1, para el ejemplo 3
$val = $request->path(0); // 'jhon', para el ejemplo 4
$val = $request->path(0); // 'jhon/5/admin', para el ejemplo 5
```

```php
$val1 = $request->path(0); // 'jhon', para el ejemplo 6
$val2 = $request->path(1); // 5, para el ejemplo 6
```

## Administrar peticiones y respuestas
Cada método que responde a una petición cliente recibe 2 parámetros, **$request** y **$response** en ese orden. Si creamos un formulario html y este envia datos a una ruta, estos datos se capturan por medio del objeto **$request**.

*/Test/views/index.html*
```html
<form method="POST">
  <input type="text" name="username"/>
  <input type="submit"/>
</form>

<!-- Esta variable se creará automáticamente desde el controlador al hacer POST -->
<span><?php echo $greeting ?? ''; ?></span>
```
*/Test/Controller.php*
```php
namespace Test;

use Irbis\Controller as iController;

class Controller extends iController {
  public $name          = 'test';
  public $has_routes    = true;
  
  /**
   * @route /
   */
  public function index ($request, $response) {
    $data = [];
    // validamos que el verbo de la petición sea POST
    if ($request->is('POST')) {
      // obtenemos el valor de lo enviando por el cliente del objeto '$request'
      $data['greeting'] = 'Hola '.$request->input('username');
    }
    
    return ['Test/views/index.html', $data];
  }
}
```

El método **método enrutado** puede devolver 3 formas diferentes de respuesta.
- puede devolver un texto que coíncida con la ruta de una vista html para mostrarla
- puede devolver un arreglo de 2 elementos, el primero la ruta de una vista, y el segundo los datos que usará dicha vista.
- si devuelve cualquier otra forma de valor, se intentará mostrar directamente ese valor al cliente.

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
use Irbis\DataBase as DB; // agregar el espacio de nombre

class Controller extends iController {
  public $name          = 'test';
  public $has_routes    = true;
  
  /**
   * @route /persons
   */
  public function persons ($request, $response) {
    $db = DB::getInstance(); // obtener la instancia de base de datos
    try {
      $stmt = $db->query("SELECT * FROM `persons`");
    } catch (\PDOException $e) {
      $this->createTable();
      $this->filltable();

      $stmt = $db->query("SELECT * FROM `persons`");
    }

    $data['persons'] = $stmt->fetchAll();

    return ["TestApps/WebSite/views/persons.html", $data];
  }

  private function createTable () {
    $db = DB::getInstance();
    $db->query("CREATE TABLE IF NOT EXISTS `persons` (
      `id` INTEGER PRIMARY KEY AUTOINCREMENT,
      `name` TEXT NOT NULL,
      `age` INTEGER NOT NULL
    )");
  }

  private function fillTable () {
    $db = DB::getInstance();
    $db->query("INSERT INTO persons (nombre, apellido, telefono) VALUES ('Jhon', 'Doe', '12345678')");
    $db->query("INSERT INTO persons (nombre, apellido, telefono) VALUES ('Jane', 'Doe', '87654321')");
    $db->query("INSERT INTO persons (nombre, apellido, telefono) VALUES ('Mario', 'Bross', '74125835')");
  }
}
```

Observará que agregamos dos métodos, uno para crear la tabla y otro para llenar los datos, en caso la consulta nos devuelva un error (no existe la tabla persons).

También si observa su directorio se habrá creado un archivo 'database.ini', que contiene la conexión por defecto a la base de datos, por defecto utiliza sqlite pero puede reconfigurar este archivo y conectar a otros gestores de base de datos que PDO acepte.

*database.ini ejemplo de conexión a mysql*
```html
[main]
dsn = "mysql:host=127.0.0.1;dbname=test"  
user = root  
pass = ****  
```

*Para apache puedes usar la siguiente regla de seguridad, para evitar el acceso a archivos de configuración en tu archivo .htaccess*
```html
<Files ~ "\.ini$">
  Order allow,deny
  Deny from all
</Files>
```

Para el ejemplo, si accedemos en local a [http://localhost/index.php/persons](http://localhost/index.php/persons), podremos visualizar la lista de personas registradas.

## Modularidad
Finalmente el principal objetivo del framwework es la modularidad, poder generar código a través de capas de módulos, evitando en mayor medida la modificación de código previo. Para el ejemplo añadiremos otro módulo que sobreescribirá la ruta '/persons' y añadirá un formulario para agregar personas. Primero creamos un nuevo directorio 'Test2' (el nuevo módulo), y dentro, un archivo controlador 'Controller.php'. Quedando nuestro proyecto de la siguiente forma:

*directorio*
```plaintext
- Test (modulo 1)
  - views
    - index.html
    - persons.html
  - Controller.php
- Test2 (modulo 2)
  - views
    - persons.html
  - Controller.php
- index.php (punto de entrada)
- database.ini (nuestro archivo de configuracion)
```

En nuestro archivo 'index.php' registraremos nuestro nuevo módulo añadiendo su controlador respectivo.

*index.php*
 ```php
require('vendor/autoload.php');

$server = \Irbis\Server::getInstance();

$server->addController(new \Test\Controller);
$server->addController(new \Test2\Controller); // registramos nuestro nuevo controlador

$server->execute();
 ```
 
*/Test2/Controller.php*
```php
namespace Test2;

use Irbis\Server;
use Irbis\Controller as iController;
use Irbis\DataBase as DB;

class Controller extends iController {
  public $name = 'test2';
  public $has_routes = true;
  
  /**
   * sobreescribimos la ruta y el método del otro controlador
   * @route /persons
   */
  public function persons ($request) {
    $db = DB::getInstance();

    if ($request->is('POST')) {
      $stmt = $db->prepare("INSERT INTO `persons` (nombre, apellido, telefono) VALUES (?, ?, ?)");
      // capturamos los valores del cliente por el objeto '$request'
      $stmt->execute($request->input(['nombre', 'apellido', 'telefono']));
    }
    
    // obtenemos la respuesta del anterior controlador
    $response = $this->super();
    // cambiamos la vista a mostrar por una nueva
    $response->view = "Test2/views/persons.html";
    // devolvemos la respuesta
    return $response;
  }
}
```

*/Test2/views/persons.html*
```html
<!-- formulario para registrar personas nuevas -->
<form method="POST">
  <p>Nombre: <input type="text" name="nombre"/></p>
  <p>Apellido: <input type="text" name="apellido"/></p>
  <p>Telefono: <input type="text" name="telefono"/></p>
  <p><input type="submit"/></p>
</form>

<!-- añadimos la vista del primer módulo -->
<?php include('Test/views/persons.html'); ?>
```
De esta forma, si el nuevo módulo presentara algún problema de código o quisieramos regresar el sistema a una versión anterior, simplemente comentamos la línea donde se agrega este nuevo módulo y todo funcionaría como antes. Y al final todo dependerá de que tan desacoplados podemos codificar nuestros módulos.

```php
require('vendor/autoload.php');

$server = \Irbis\Server::getInstance();

$server->addController(new \Test\Controller);
// comentamos la línea y el nuevo controlador no se agregará
// $server->addController(new \Test2\Controller);

$server->execute();
```

**NOTA:** es importante el orden en el que se agregan los módulos al sistema, los métodos enrutados del último módulo agregado tendrán preferencia para responder al cliente.

## Constantes
El framework declara algunas constantes en caso estas no hayan sido previamente declaradas, podemos controlar su valor en nuestro archivo de entrada 'index.php', deberemos declararlas al inicio. Estas sirven para modificar algunos comportamientos del framework.

*index.php*
```php
// aquí podremos declarar nuestras constantes.
define('MOD_REWRITE', true);
require('vendor/autoload.php');
$server = \Irbis\Server::getInstance();
$server->addController(new \Test\Controller);
$server->execute();
```

**MOD_REWRITE** (por defecto, falso), si es verdadero las rutas no requerirán que se declare explicitamente el archivo 'index.php', para esta característica primero se debe configurar el servidor (para apache el archivo .htaccess por ejemplo).

*.htaccess*
```html
Options +FollowSymLinks
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php?$1 [QSA,L]
```

**DB_INI** (por defecto, 'database.ini'), indica la ruta donde se encuentra el archivo de configuracion de base de datos.  
**REQUEST_EMULATION** (por defecto, falso), si es verdadero el método $request->is(*$string*) validará también verbos PUT y DELETE que vayan en el cuerpo del documento en una variable '\_method'.  
**DEBUG_MODE** (por defecto, falso), si es verdadero se muestra más información del error que se pueda producir.  
**DEFAULT_VIEW** (por defecto, 'index'), es posible devolver o asignar de forma dinámica la ruta de la vista a responder. el valor de esta constante se utilizará en caso no haya un valor enviado desde el cliente.

```php
/**
 * @route /
 */
public function index ($request, $response) {
  // la ruta de la vista se armará de forma dinámica en función de lo enviado por GET
  // ejem. /?view=persons, la vista será /Test/persons.html
  return '/Test/{view}.html';
  // si el valor de 'view' no es enviado se usará 'index' por defecto
  // ejem. para / ó /?param=val la vista será /Test/index.html
}
```

```php
/**
 * @route /(:all)
 */
public function index ($request, $response) {
  // la ruta de la vista se armará de forma dinámica en función de la petición
  // ejem. /, la vista será /Test/index.html
  // ejem. /persons, la vista será /Test/persons.html
  // ejem. /users/jhon, la vista será /Test/users/jhon.html
  return '/Test/(0).html';
  // este caso sólo aplica para el comodín (:all) que captura todo lo registrado
  // el comodín (:any) siempre buscará un valor, por lo que no coíncide con la ruta /
}
```

**BASE_PATH** (por defecto, el directorio donde se encuentra la aplicación), no se recomienda cambiar este valor.  
**CRYP_KEY**, clave a usar en los métodos de encriptación y desencriptación.  
**CRYP_METHOD**, método de encriptación a utilizar.  

```php
$val = encrypt('hola mundo'); // valor encriptado
$val = decrypt($val); // recuperando valor
```

## Administración de Modelos

El tema de creación y manejo de modelos se detalla en la siguiente [guía](https://github.com/Dulivu/irbis/tree/main/RecordSet).
