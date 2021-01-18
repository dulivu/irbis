# Irbis
PHP MVC Micro-Framework.
Fácil de utilizar y enfocado al desarrollo modular conjuntamente con el patrón MVC.

## Instalación
+ Composer: **composer require dulivu/irbis**
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
// Aquí llamamos a nuestro autocargador: require('Irbis/Server.php') ó require('vendor/autoload.php')

// Obtenemos la instancia única del servidor
// esta debe ser siempre nuestra primera línea de código
$server = \Irbis\Server::getInstance();

// añadimos nuestro primer módulo llamado 'Test'
// dentro de cada módulo siempre debe haber una clase controladora
// podemos llamarla como queramos, pero por convención la llamamos 'Controller'
$server->addController(new \Test\Controller);
// la lógica de nuestra aplicación irá dentro de estos controladores

// Por último brindamos la respuesta de la petición
// esta debe ser siempre nuestra última línea de código
$server->respond();
```

### Directorios y Módulos
Un módulo básico comprende un directorio con un archivo controlador, ejemplo:
- Irbis (framework, si usamos composer este estará dentro de 'vendor')
- Test (módulo)
  - views (directorio para nuestras vistas)
    - index.html
    - contact.html
  - Controller.php (Controlador del módulo Test)
- index.php (punto de entrada)

### Módulo, Controlador y el Auto-Cargador
Un módulo es un directorio con un archivo controlador dentro, puede organizar cada módulo con sus propios sub-directorios y archivos; por ejemplo, un carpeta 'views' donde guarde todas las vistas que utilice su módulo.

El controlador será un objeto que la instancia 'Server' administrará. Debe heredar de la clase base \Irbis\Controller y podrá llevar métodos que respondan a rutas que el cliente pueda solicitar.

*/Test/Controller.php*
```php
namespace Test;

use Irbis\Controller as iController;

class Controller extends iController {
  // si nuestro controlador debe registrar sus métodos como rutas de petición
  // este atributo se debe declarar 'verdadero'
  public $routes = true;
  
  /**
   * Este método responderá a la ruta base
   * localhost ó localhost/index.php
   * @route /
   */
  public function index () {
    return 'Hola mundo!';
  }
}
```

La directiva **'@route'** en los comentarios indica a que ruta debe responder dicho método, los comentarios se deben realizar con el formato [estandar](https://manual.phpdoc.org/HTMLframesConverter/default/) de php para métodos de clase (como se ve en el ejemplo, usar // o # no servirá).

Notese que la clase **Controller** del módulo está dentro de un espacio de nombres igual al nombre del directorio donde se encuentra, **el auto-cargador** utilizará el espacio de nombres igual que una ruta de directorio para buscar las clases no registradas y añadirlas a la ejecución.

**Con los pasos realizados hasta aquí, debería poder visualizar en su navegador las palabras "Hola mundo!"** ([http://localhost](http://localhost)).

## Métodos enrutados
Los métodos que responden a una ruta solicitada por el cliente se declaran con una directiva (@route) en los comentarios del mismo, el valor que sigue a la directiva es la ruta, existen 3 comodines que se pueden usar para rutas relativas. Ejemplos:

> @route / => enruta a la raiz del dominio http://localhost ó http://localhost/index.php.  
> @route /users => enruta a una dirección tipo http://localhost/index.php/users.  
*se puede prescindir de 'index.php' si se configura el archivo .htaccess y se activa MOD_REWRITE = true.*  
> @route /users/(:num), para números, enruta a una dirección de tipo http://localhost/index.php/users/1.  
> @route /users/(:any), para cadenas o números, enruta a una dirección de tipo http://localhost/index.php/users/jhon.  
> @route /users/(:all), para cadenas o números (incluido signos especiales como: '/'), enruta a una dirección tipo http://localhost/index.php/users/jhon/5/admin.  
> @route /users/(:any)/(:num), tambien podemos combinar comodines (http://localhost/index.php/users/jhon/5), tener cuidado con el comodín (:all) que puede causar conflictos con otras rutas. Según este ejemplo esta ruta nunca se cumpliría ya que tambien coíncide en el ejemplo anterior todo dependiendo del orden en que registramos nuestros módulos.

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
Cada método que responde a una petición cliente recibe 2 parámetros, **$request** y **$response** en ese orden. Si creamos un formulario html y este envia datos a una ruta, estos datos se obtienen por medio del objeto **$request**, y para enviar datos al cliente usamos el objeto **$response**, entre otras carácteristicas.

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
  public $routes = true;
  
  /**
   * @route /
   */
  public function index ($request, $response) {
    // validamos que el verbo de la petición sea POST
    if ($request->isMethod('POST')) {
      // el objeto '$response' tiene una propiedad '$data' que es un arreglo asociativo
      // podemos ir agregando todos los datos que necesitemos mostrar al cliente a dicho arreglo
      $response->data['greeting'] = 'Hola '.$request->input('username');
      // el método 'input' del objeto '$request' obtiene los valores enviados por POST
      // si queremos obtener valores enviados por GET usamos el método 'query'
      // ambos reciben como parámetro el nombre del valor enviado
    }
    
    // Para mostrar una vista usamos la propiedad '$view' del objeto '$response'
    // le asignamos la ruta donde se encuentra la vista a mostrar
    $response->view = 'Test/views/index.html';
    // finalmente los datos que fuimos agregando podrán ser utilizados como variables en la vista
  }
}
```

Si nuestro **método enrutado** no devuelve ningún valor, como respuesta se utilizará el objeto $response que se le pasa. Caso contrario, se creará un nuevo objeto $response que tendrá la propiedad '$data' el valor devuelto del método. Por eso, en nuestro ejemplo anterior, al devoler una cadena (*return 'hola mundo!'*) esta se muestra directamente al cliente.

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
   * @route /
   */
  public function index ($request, $response) {
    if ($request->isMethod('POST')) {
      $response->data['greeting'] = 'Hola '.$request->input('username');
    }

    $response->view = 'Test/views/index.html';
  }
  
  /**
   * para el ejemplo debe tener una base de datos 
   * y una tabla llamada 'persons' con registros
   * @route /persons
   */
  public function persons ($request, $response) {
    // getInstance(), devuelve una conexión a base de datos
    // utiliza el nombre registrado en el archivo 'database.ini'
    $db = DB::getInstance('main');
    $stmt = $db->query("SELECT * FROM `persons`");
    
    $response->data['persons'] = $stmt->fetchAll();

    // para devolver la ruta de la vista a usar, podemos usar
    // la propieda del controlador '$dir' es la ruta del
    // directorio donde se encuetra el controlador actual
    $response->view = $this->dir.'/views/persons.html';
  }
}
```

Previamente deberemos tener un archivo de configuración (database.ini) en la raíz de nuestro proyecto, **Se recomienda utilizar reglas de acceso en el servidor web para evitar el acceso accidental a estos archivos por seguridad.**

*database.ini*
```html
[main]
dsn = "mysql:host=127.0.0.1;dbname=test"  
user = root  
pass = ****  
```

*Para apache puedes usar la siguiente regla de seguridad, para evitar el acceso a archivos de configuración*
```html
<Files ~ "\.ini$">
  Order allow,deny
  Deny from all
</Files>
```

Para el ejemplo, si accedemos en local a [http://localhost/index.php/persons](http://localhost/index.php/persons), podremos visualizar la lista de personas registradas.

## Modularidad
Finalmente el objetivo del framwework es la modularidad, poder generar código a través de capas de módulos, evitando en mayor medida la modificación de código previo. Para el ejemplo agregaremos otro módulo que sobreescribirá la ruta '/persons' y añadirá un formulario para agregar personas. Primero creamos un nuevo directorio 'Test2' (el nuevo módulo) y dentro un archivo controlador 'Controller.php'. Quedando nuestro proyecto de la siguiente forma:

*directorio*
- Irbis (framework, si usamos composer este estará dentro de 'vendor')
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

En nuestro archivo 'index.php' registraremos nuestro nuevo módulo añadiendo su controlador respectivo.

*index.php*
 ```php
require('Irbis/Server.php');

$server = \Irbis\Server::getInstance();

$server->addController(new \Test\Controller);
// registramos nuestro nuevo controlador
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
   * el método responderá a la misma ruta que en el otro controlador, al ser este
   * módulo el último registrado su método será el que tenga preferencia para responder
   * @route /persons
   */
  public function persons ($request, $response) {
    $db = DB::getInstance('main');

    if ($request->isMethod('POST')) {
      $stmt = $db->prepare("INSERT INTO `persons` VALUES (?, ?, ?)");
      // el método 'input' del objeto 'request' puede recibir un arreglo
      // con los nombres de los valores que queremos obtener del POST
      $stmt->execute($request->input(['nombre', 'apellido', 'telefono']));
    }
    
    // el método 'getServer' del controlador nos devuelve la instancia única del objeto 'Server'
    // el método 'respond' del servidor, al ser llamado nuevamente dentro de un controlador
    // ejecutará la lógica del modulo anteriormente registrado, en este caso 'Test' y devolverá
    // el objeto '$response' que procesó
    // por último le cambiamos la vista por la nueva y devolvemos el nuevo objeto $response
    $response = $this->getServer()->respond();
    $response->view = 'Test2/views/persons.html';
    // si en el método enrutado devolvemos un objeto '$response' diferente
    // este será el que se procesará para la respuesta al cliente
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
require('Irbis/Server.php');

$server = \Irbis\Server::getInstance();

$server->addController(new \Test\Controller);
// registramos nuestro nuevo controlador
// $server->addController(new \Test2\Controller);

$server->respond();
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

**MOD_REWRITE** (por defecto, falso), si es verdadero las rutas no requerirán que se declare explicitamente el archivo 'index.php', para esta característica primero se debe configurar el servidor (para apache el archivo .htaccess por ejemplo).

*.htaccess*
```html
Options +FollowSymLinks
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php?$1 [QSA,L]
```

**DB_INI** (por defecto, 'database.ini'), indica la ruta donde se encuentra el archivo de configuracion de base de datos.  
**REQUEST_EMULATION** (por defecto, falso), si es verdadero el método $request->isMethod(*$string*) validará también verbos PUT y DELETE que vayan en el cuerpo del documento en una variable '\_method'.  
**DEBUG_MODE** (por defecto, falso), si es verdadero se muestra más información de errores en las respuestas.  
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
