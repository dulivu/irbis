# Irbis Framework

PHP MVC Micro-Framework.

Enfocado en desarrollo modular.

# Contenido

1. [Introducción](#introduccion)
     - Parametrización
     - Modularidad
2. [Instalación](#instalacion)
3. [Configuración](#configuracion)
     - Seguridad
     - Gestión de aplicaciones
4. [Arquitectura](#arquitectura)
     - Controladores
     - Solicitud y Respuesta
     - Base de datos
5. [ORM](#orm)
     - Modelo
     - Recordset y Record
6. [Extensibilidad](#extensibilidad)
     - Interfaces
     - Hooks
     - Setup y Session

<a name="introduccion"></a>

# 1. Introducción, características escenciales

**Irbis** es un framework PHP ligero y modular que combina:

- Un **core MVC minimalista** (Request > Controller > Action > Response)
- Un **sistema de módulos desacoplado**, permitiendo añadir logica por capas
- Un **ORM declarativo propio** con soporte nativo para **SQLite, MySQL y PostgreSQL**

El objetivo es ofrecer una base sólida, extensible y entendible para construir aplicaciones web y APIs.

`index.php` punto de entrada del sistema:
```php
require 'vendor/autoload.php';

Irbis\Server::listen();
```

Esto es todo lo que requieres en tu punto de entrada, el framework está hecho para trabajar con urls amigables y `composer`, asegurate de configurar bien tu servidor web apache o ngix correctamente para esto.

`.htaccess`, de ejemplo para apache
```html
<Files ~ "\.(ini|db3)$">
  Order allow,deny
  Deny from all
</Files>

Options +FollowSymLinks
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php?$1 [QSA,L]
```
> [!TIP]
> Esta configuración deniega el acceso a archivos `.ini` y `.db3` por seguridad.

> [!TIP]
> También hace que toda solicitud de cliente este direccionada al archivo `index.php`.

## 1.1. Parametrización del sistema

El framework genera de forma automática un archivo `state.ini` para persistir su parametrización, este archivo se irá ajustando conforme vayas configurando tu sistema.

`state.ini`
```ini
[server]
debug = "on"
terminal = ""
[database]
dsn = "sqlite:database.db3"
user = ""
pass = ""
```

Para el ejemplo: 
- El modo `debug` está activado, variable que recorre el sistema.
- La conexión de base de datos apuntan a `SQlite` por defecto.

> [!NOTE]
A nivel de código se puede manipular por medio de métodos `setState()` y `getState()` del objeto `Server`.

```php
$server = Irbis\Server::getInstance();
// apuntando a una base de datos mysql
$server->setState('database.dsn', 'mysql:host=localhost;dbname=mydb;charset=utf8mb4');
$server->setState('database.user', 'db_username');
$server->setState('database.pass', 'db_password');
```

## 1.2. Modularidad

El framework permite encapsular lógica de negocio en paquetes llamados módulos o aplicaciones, estos son directorios que contienen una estructura que el objeto `Server` leerá y gestionará.

Cada aplicación añadida, agregará lógica y/o funcionalidad al sistema.

Toda aplicación se estructura en un directorio principal, que es el paquete, y sus sub-directorios que son las aplicaciones. Pudiendo tener así un Paquete llamado `MyApps` y dentro varias aplicaciones como `Test`, `Rest`, etc.

`MyApps/Test` y `MyApps/Rest` ejemplo:
```
MyApps
  > Test
    > assets
      - script.js
      - style.css
    > components
    > models 
      - users.php
      - groups.php
    > views
      - index.html
      - info.html
    - Controller.php
    - Hooks.php
    - Setup.php
  > Rest
    - Controller.php
- index.php
```

> [!IMPORTANT]
> Toda aplicación debe tener una clase `Controller.php` que es el controlador de la aplicación.

> [!TIP]
> Adicionalmente puede implementar las clases `Hooks.php` y `Setup.php` para implementar ganchos de instalación y lógica de configuración como middlewares y otras funcionalidades.

> [!TIP]
> Dentro del sistema una aplicación se identifica por su espacio de nombre `namespace` que sería la combinación de su paquete y su nombre, e: `MyApps/Test`


<a name="instalacion"></a>

# 2. Instalación, el código fuente

> [!IMPORTANT]
> - Se recomienda utilizar `composer` para instalar los fuentes, información **[aqui](https://getcomposer.org/download/)!**
> - Se recomienda instalar `Git`, ya que algunas operaciones pueden requerirlo.

```
composer require dulivu/irbis
```

El comando de arriba instalará los fuentes del framework, luego sólo es necesario preparar el archivo `index.php` con el siguiente contenido y listo, estarás listo para iniciar.

`index.php`
```php
require 'vendor/autoload.php';

Irbis\Server::listen();
```

Una vez instalado puedes ingresas a la **url, direccion IP, o localhost**, dependiendo del ambiente donde lo hayas instalado, podrás observar una pantalla en el navegador parecido a un terminal de comandos. Desde este terminal puedes continuar la configuración del sistema.

![terminal capture](docs/terminal.png)


<a name="configuracion"></a>

# 3. Configuración, usando el terminal

El terminal es una interfaz de texto que permite interactuar directamente con la aplicación mediante la escritura de comandos en lugar de elementos gráficos.

> [!WARNING]
> Esta terminal sólo es una simulación, no tiene acceso real a comandos del equipo donde este instalado el sistema.

## 3.1. Seguridad

Lo primero es asegurar el acceso al terminal.

- El comando `usermod` cambia el nombre de usuario del terminal
- El comando `passwd` cambia la contraseña de acceso.

> [!NOTE]
> cambia `<username>`  y `<password>` por el nombre de usuario y contraseña que desees usar.

```sh
$ usermod <username>
$ passwd <password>
```

> [!WARNING]
> Si sólo cambias la contraseña, el usuario por defecto es `irbis`

- El comando `clear` recarga un nuevo terminal limpio.

```sh
$ clear
```

> [!NOTE]
> Luego de este ultimo paso, el navegador te solicitará ingreses el usuario y contraseña para poder continuar de ahora en adelante.

## 3.2. Gestión de aplicaciones

Como vimos anteriormente, una aplicación tiene un espacio de nombre, compuesto por su directorio paquete y sub-directorio aplicación, resultando en un nombre similar a `MayApps/Test` ó `MyApps/Rest` esta nomenclatura identificará a cada aplicación dentro del sistema de forma única.

> [!TIP]
> *Te comparto un repositorio con aplicaciones del mismo framework.*
> 
> Es un buen ejemplo para revisar como se estructura un paquete de aplicaciones y varias son muy útiles si deseas probarlas.
> 
> [https://github.com/dulivu/IrbisApps](https://github.com/dulivu/IrbisApps) 

- El comando `git clone` replica un repositorio en tu sistema.

```sh
$ git clone https://github.com/dulivu/IrbisApps
```

- El comando `newapp` te permite crear una aplicación vacia.

```sh
$ newapp Test
```

> [!NOTE]
> Se crea dentro del paquete `MyApps` por lo que su namespace sería `MyApps/Test`.
>
> **No debe contener espacios ni carácteres especiales.**

- El comando `install` instala una aplicación disponible.

```sh
$ install MyApps/Test
```

- El comando `ls apps` muestra las aplicaciones disponibles.

```sh
$ ls apps
```

```
[ ] IrbisApps/Base
[ ] IrbisApps/Cms
[*] MyApps/Test
```

> [!NOTE]
> Las aplicaciones ya instaladas se muestran con un **asterisco**.
>
> Si la aplicación instalada tuviera alguna dependencia de otra, las dependencias se intentarán instalarán automáticamente.

- El comando `info` muestra información detallada de alguna aplicación.

```sh
$ info MyApps/Test
```

- El comando `remove` intentará quitar una aplicación del sistema, **pero ten cuidado** que esto no siempre puede ser beneficioso. *Si deseas probar aplicaciones se recomienda hacerlo en un ambiente de prueba.*

```sh
$ remove MyApps/Test
```

> [!TIP]
> Si terminas instalando la aplicación creada y navegas a la raíz del sistema (ip, url o localhost) podrás observar que ya tienes respuesta :).

- El comando `nano` abré una ventana para editar un archivo. Si el archivo no exite lo crea.

```sh
$ nano Test/Controller.php
```

> [!NOTE]
> Sólo puedes editar archivos que estén dentro del paquete `MyApps`.

- Algunos archivos se crean con una plantilla base.

```sh
$ nano Test/views/index.html
```

- Finalmente el comando `show` abre una ventana con la vista principal del sistema.

```sh
$ show
$ show /ruta/amigable
```

<a name="arquitectura"></a>

# 4. Arquitectura, lo básico e importante

A nivel técnico y de forma resumida dentro del patrón MVC implementado, existen 5 objetos que gestionan todo el flujo de una solicitud de cliente.

- **Server**: Orquestador principal del framework
- **Request**: Envoltura a los datos de la solicitud (GET, POST, HEADERS, COOKIES, etc.)
- **Controller**: El manejador principal de cada aplicación.
- **Action**: Envoltura de métodos auto-ejecutables por la solicitud del cliente.
- **Response**: Gestiona los datos y la forma de entregar una respuesta al cliente.

## 4.1. Controladores

Todo controlador debe heredar de la clase `Irbis\Controller` y debe declarar una propiedad `$name` que debe ser única en la lista de aplicaciones instaladas.

`MyApps/Test/Controller.php`
```php
namespace MyApps\Test;
use Irbis\Controller as iController;

class Controller extends iController {
  public static $name = 'test';
  
  /**
   * @route /
   */
  final public function index () {
    return '@test/index.html';
  }
}
```

Cada método del controlador declarado con la palabra clave `final` representa una solicitud (`Action`) que el cliente puede invocar, además tambien debe tener una sección de comentarios correctamente estructurada ([DocBlock](https://docs.phpdoc.org/guide/getting-started/what-is-a-docblock.html)) y dentro un decorador `@route` que indica a que ruta responde dicha acción.

> [!IMPORTANT]
> `Server` revisará la solicitud `Request` y pedirá a cada `Controller` que entregue sus `Action` que coínciden con la solicitud, estos `Action` serán entregados a `Response` para que pueda determinar la respuesta.

### Métodos de utilidad

```php
// siendo $ctrl el objeto Controller
$ctrl->namespace();             // 'MyApps/Test'
$ctrl->namespace('php');        // '\MyApps\Test\'
$ctrl->namespace('snake');      // 'myapps_test_'
$ctrl->namespace('dir');        // '/var/www/MyApps/Test'
```

```php
// ejecuta la última acción de la pila
$ctrl->super();
// obtiene la instancia de un controlador del sistema
$ctrl->application('MyApps/Test')
// fabrica y devuelve un objeto componente
$ctrl->component('Model');
```

## 4.2. Solicitud y Respuesta

El objeto `Request` es un singleton, por lo que sólo puede existir una sola instancia en cada ejecución, si necesitas utilizarlo puedes llamarlo por su método `getInstance()`.

```php
$request = Irbis\Request::getInstance();
```

### Métodos de utilidad

```php
$request->isGet();
$request->isPost();
$request->isPut();
$request->isPatch();
$request->isDelete();
$request->isJson(); // si la solicitud lleva cabecera 'application/json'
$request->cookie($key) // devuelve un valor en las cookies
```

```php
$request->cookie($key) // devuelve un valor de las cookies enviadas
$request->header($key) // devuelve un valor de las cabeceras de solicitud
```

- Leyendo datos de cliente

```php
$request->query($key) // devuelve un valor de la url (GET)
$request->input($key) // devuelve un valor del body (POST)

// otro ejemplos
// URL: /search?q=irbis&page=2&filter=
$request->query('q');           // 'irbis'
$request->query('*');           // ['q' => 'irbis', 'page' => '2', 'filter' => '']
$request->query('*!');          // ['q' => 'irbis', 'page' => '2']
$request->query(['q', 'page']); // ['q' => 'irbis', 'page' => '2']
```

```php
// normalmente datos enviados por formularios POST
$request->input('username');    // el valor del input[@name='username']
$request->input('*');           // todos los valores enviados por POST
```

```php
// @route /users/(:num)
// URL: /users/5
$request->path(0);              // 5

// @route /blog/(:any)/section/(:num)
// URL: /blog/entrada-1/section/7
$request->path(0);              // 'entrada-1'
$request->path(1);              // 7
```

- Leyendo archivos cargados por el cliente

```php
$request->hasUploads(); // true si hay archivos, ó false
$request->hasUploads($key); // validar si se subieron archivon con clave '$key'

// guardar un archivo subido por el cliente
// $key, es la clave con la que se sube el archivo
$request->upload($key, '/path/to/file');
```

- Un ejemplo de un método en un controlador para subir multiples archivos. *Debes crear un directorio `uploads` dentro de tu aplicación.*

```php
/**
 * @route /upload
 */
final function uploadFiles ($request, $response) {
  $key = 'file'; // clave del archivo subido

  if ($request->isPost() && $request->hasUploads()) {
    $request->walkUploads($key, fn($upload) => {
      $basepath = $this->namespace('dir') . 'uploads/';
      $filepath = $basepath.$upload['name'];
      move_uploaded_file($upload['tmp_name'], $filepath);
    });
  }

  return '@test/upload.html';
}
```

> [!NOTE]
> En el último ejemplo se observa que a cada `Action` se le pasa tambien dos parámetros `Request` y `Response` que puedes utilizar dentro de la lógica de la acción para tomar decisiones y determinar la información entregada al cliente.

---

El objeto `Response` es pasado al método del controlador `Action` como un segundo parámetro (opcionalmente utilizable).

### Métodos de utilidad

```php
$response->view($view); // establece una vista para mostrar
$response->hasView(); // true si tiene una vista establecida
```

```php
$response->header($key, $value); // establece una cabecera de respuesta
$response->body($data); // establece los datos para pasar a la vista

$response->append($key, $value); // agrega un valor al cuerpo de respuesta
$response->remove($key); // quita un valor al cuerpo de respuesta
```

- Para simplificar la gestión de la respuesta existe algunas interpretaciones que `Response` reconoce en función de lo que la acción del controlador devuelva.

> Si se devuelve un texto plano u otro tipo de dato, `Response` lo interpreta como `body`

```php
/**
 * @route /
 */
final public function index () {
  return 'Mi nueva aplicación';
}
```

> Si se devuelve una ruta a una vista html, `Response` lo interpreta como `view`
>
> Además el acortador `@test` es reemplazado por la ruta `MyApps/Test/views` de la aplicación, para buscar la plantilla exactamente en ese directorio.

```php
/**
 * @route /
 */
final public function index () {
  return '@test/index.html';
}
```

> Si se devuelve una combinación [`view`,`data`], `Response` establece precisamente esa información usando internamente `view()` y `body()` y dentro de la plantilla `index.html` esos datos estarán disponibles.

```php
/**
 * @route /
 */
final public function index () {
  return ['@test/index.html', ['msg' => 'Hola mundo!']];
}
```

> Lo anterior es lo mismo que aplicar dichos métodos de forma explicita, y usando el objeto `Response` además podemos controlar otros aspectos como las cabeceras de respuesta.

```php
/**
 * @route /
 */
final public function index ($request, $response) {
  $response->header('Content-Type: text/html');
  $response->view('@testweb/index.html');
  $response->data(['msg' => 'Hola mundo!']);
}
```

> [!NOTE]
> Hasta este punto ya puedes levantar un sitio web con información dinámica, controlar el flujo desde la solicitud hasta la entrega de información al cliente, tener ordenadas tus rutas con URLs amigables y las plantillas `html` que necesites.

> [!TIP]
> **Reto:** Crea un archivo `css` y agregalo en `index.html` para aplicar estilos a tu sitio web. Una Pista, para agregar un activo utiliza la ruta completa del módulo e: `<link href="/MyApps/Test/assets/style.css"/>`.

> Comandos:

```bash
$ nano Test/assets/style.css
$ nano Test/views/index.html
```

<a name="base-de-datos"></a>

## 4.3. Base de datos

Ahora vamos adentrandonos en temás cada vez más complejos, el framework maneja de forma automatica una conexión a base de datos, vimos en la [primera parte](#introduccion) que existe un archivo de parametrización `state.ini` que por defecto apunta a `SQLite` ahí puedes modificar si deseas utilizar `MySQL` o `PostgreSQL`.

- El commando `conn` permite configurar los parámetros de conexión a base de datos.

```bash
$ conn reset
$ conn dsn <dsn>
$ conn user <db_user>
$ conn pass <db_pass>
```

- `reset` le indica al sistema que debe reinicializar sus parámetros de conexión, usando por defecto `SQLite`.

---

Así que prácticamente con el mínimo (o nada) de configuración puedes usar el objeto `Irbis\Orm\Connector` para empezar a ejecutar sentencias `SQL`.

- El comando `sql` abre una ventana para ejecutar sentencias SQL. **Sólo funciona con conexiones a SQLite**.

```bash
$ sql
```

> [!NOTE] 
> Para el siguiente ejemplo puedes crear una tabla `todo_list` en tu base de datos.

```sql
CREATE TABLE IF NOT EXISTS "todo_list" (
  "name" VARCHAR NOT NULL,
  "is_done" BOOLEAN
);
```

> Inserta un par de datos a la tabla.

```sql
INSERT INTO "todo_list" ("name") VALUES 
  ('aprender a usar Irbis'),
  ('crear mi propio sistema');
```

> Revisa los datos insertados.

```sql
SELECT * FROM "todo_list";
```

---

Ahora que los datos están listos, vamos a implementar en nuestra aplicación `MyApps/Test` una vista que muestre esa información.

> Primero crearemos la vista `html`

```bash
$ nano Test/views/index.html
```

> Agrega este codigo dentro del `body`

```php
<h1>Lista de cosas por hacer</h1>

<ul>
<?php foreach ($list as $item): ?>
  <li><?= $item['name']; ?></li>
<?php endforeach; ?>
</ul>
```

> Ahora modificaremos el controlador.

```bash
$ nano Test/Controller.php
```

```php
<?php
namespace MyApps\Test;
use Irbis\Orm\Connector;
use Irbis\Controller as iController;


class Controller extends iController {
  public static $name = 'test';

  /**
   * @route /
   */
  final public function todoList () {
    $db = Connector::getInstance();
    $stmt = $db->query("SELECT * FROM todo_list;");
    $list = $stmt->fetchAll();
    
    return ["@test/index.html", [
      'list' => $list
    ]];
  }
}
```

> Veamos como queda nuestro sitio

```bash
$ show
```

> [!NOTE]
> El objeto `Connector` hereda del objeto nativo de php `PDO` por lo que puedes usar todos los métodos que este tenga, además implementa `Singleton` para tener siempre una sóla conexión activa.

<a name="orm"></a>

# 5. ORM, mapeo relacional de objetos

Dentro de cada aplicación puede exitir un directorio llamado `models`, es donde vamos a mapear nuestros modelos de base de datos, una funcionalidad importante y compleja es manejar los datos de nuestra base de datos.

- Crear tablas
- Capturar datos
- Insertar
- Sanitizar
- Actualizar

Son sólo unas cuantas de las muchas operaciones que se pueden hacer con los datos de un sistema y llevar eso a código suele ser caótico, para ello, el framework implementa un motor `ORM` que ayuda en esta gestión.

## 5.1. Modelos

Un modelo se declara como una estructura (arreglo asociativo) en un archivo PHP.

> Primero creamos nuestro modelo `users.php`

```bash
$ nano Test/models/users.php
```

```php
<?php
return [
  "name" => ["varchar", "required" => true],
  "pass" => ["varchar", 
    "required" => true, 
    "store" => '$encryptPassword'
  ],

  '$encryptPassword' => function ($password) {
    return str_encrypt($password);
  },

  'validatePassword' => function ($password) {
    return str_decrypt($this->pass) === $password;
  },

  '@mapNames' => function () {
    $names = [];
    foreach ($this as $user) {
      $names[] = $user->name;
    }
    return $names;
  }
];
```

> [!NOTE]
> **Convenciones a considerar**, todo modelo debe estar dentro del directorio `models`, se recomienda usar nombre en plural y minuscula `users.php`, y el código siempre debe empezar por la palabra reservada `return` para entregar esta estructura cuando sea llamada.

### Propiedades

> Vienen a ser los campos de las tablas en las bases de datos, cada uno describe el tipo de dato y características propias de una columna en una tabla.

```php
"name" => ["varchar", "required" => true]
"pass" => ["varhcar", "required" => true, "store" => '$encryptPassword']
```

- El atributo `store` ejecuta lógica durante la inserción del registro.
- El atributo `retrieve` ejecuta lógica durante la obtención del registro.

### Métodos

> Son las acciones que el modelo puede realizar, se describen como funciones y como se observa tienen acceso a la variable `$this` que apunta al registro actual y todos sus atributos, como si de cualquier otro objeto se tratara.

```php
'validatePassword' => function ($password) {
  $my_pass = str_decrypt($this->pass);
  return $my_pass === $password;
}
```

- Usar el signo `$` en un método complica su acceso, por lo que puede simular métodos *'private'*.
- Usar el signo `@` en un método simula el acceso *'static'* siendo accesible desde un conjunto de registros `RecordSet` y no desde el mismo registro `Record`.

```php
'@mapNames' => function () {
  $names = [];
  foreach ($this as $user) {
    $names[] = $user->name;
  }
  return $names;
}
```

- Dentro de esta función `$this` ya no apunta al registro `Record` sino al conjunto de registros `RecordSet`.

```php
$users = new RecordSet('users');
$users->select(); // captura todos los usuarios

$users->mapNames(); // el conjunto devuelve [...nombres]

$user = $users[0];
$user->validatePassword('my_password'); // validación por registro
```

> [!IMPORTANT]
> Como se observa declarar un modelo, es como declarar un objeto, facilita la organizacion del codigo y encapsula la lógica del modelo en el modelo. De esta forma tus aplicaciones mejoran en cuanto a diseño y mantenibilidad.

## 5.2. RecordSet y Record

Ya declarados los modelos que necesites puedes utilizarlos en la aplicación por medio de las clases `RecordSet` y `Record`.

> [!WARNING]
> A partir de este punto la aplicación `MyApps/Test` se empezará a
> tornar más compleja, con ejemplos más elaborados con el objetivo de 
> llegar a que entiendas la filosofía del framework.

- Vamos a agregar más lógica al controlador.

```bash
$ nano Test/Controller.php
```

```php
<?php
namespace MyApps\Test;
use Irbis\Orm\RecordSet;
use Irbis\Orm\Record;
use Irbis\Exceptions\HttpException;
use Irbis\Controller as iController;


class Controller extends iController {
  public static $name = 'test';

  /**
   * @route /
   */
  final public function todoList () {
    $db = Connector::getInstance();
    $stmt = $db->query("SELECT * FROM todo_list;");
    $list = $stmt->fetchAll();
    
    return ["@test/index.html", ['list' => $list]];
  }

  /**
   * @verb GET
   * @route /users
   */
  final public function usersListAction () {
    $users = new RecordSet('users');
    $users->select();

    return [
      '@test/users.html', 
      ['users' => $users]
    ];
  }

  /**
   * @verb GET,POST
   * @route /users/new
   */
  final public function usersNewAction ($request) {
    if ($request->isPost()) {
      $users = new RecordSet('users');
      $users->insert([
        'name' => $request->input('username'),
        'pass' => $request->input('password')
      ]);
      redirect('/users');
    }
    return '@test/users_form.html';
  }

  /**
   * @verb GET,POST
   * @route /users/(:num)
   */
  final public function usersUpdateAction ($request) {
    $user_id = $request->path(0);
    $users = new RecordSet('users');
    $users->select($user_id);

    if (!count($users)) {
      throw new HttpException(404, "usuario no encontrado");
    }

    if ($request->isPost()) {
      $user = $users[0];
      $user->name = $request->input('username');
      $user->pass = $request->input('password');
    }

    return ['@test/users_form.html', [
      'user' => $users[0]
    ]];
  }
}
```

> Creamos una vista para mostrar los usuarios

```bash
$ nano Test/views/users.html
```

```html
<h1>Lista de usuarios</h1>

[ <a href="/users/new">Nuevo usuario</a> ] <br/>

<table>
  <tr>
    <th>ID</th>
    <th>Nombre</th>
    <th>Contraseña</th>
    <th></th>
  </tr>
<?php foreach ($users as $user): ?>
  <tr>
    <td><?= $user->id; ?></td>
    <td><?= $user->name; ?></td>
    <td><?= $user->pass; ?></td>
    <td>[ <a href="/users/<?= $user->id; ?>">Modificar</a> ]</td>
  </tr>
<?php endforeach; ?>
</table>
```

> Veamos como va

```bash
$ show /users
```

> Podremos observar que sale un error, indicando que la tabla `users` no existe y es correcto.

- El comando `bindmodel` permite enlazar un modelo con la base de datos.

```bash
$ bindmodel users
$ bindmodel users --rebuild
```

- `--rebuild` eliminará la tabla antes de enlazar el modelo, **perderás los datos existentes**.

> Prueba nuevamente con `show /users`.
>
> Ahora crearemos la vista formulario para crear un nuevo usuario.

```bash
$ nano Test/views/users_form.html
```

```html
<form method="POST">
  <p>
    <input type="submit" value="Guardar"/>
    [ <a href="/users">Volver</a> ]
  </p>

  <p>
    <label>Usuario:</label>
    <input type="text" name="username" 
      value="<?= isset($user) ? $user->name : '' ?>"/>
  </p>

  <p>
    <label>Contraseña:</label>
    <input type="password" name="password"/>
  </p>
</form>
```

> Volviendo a probar.

```bash
$ show /users
```

> **ó**

```bash
$ show /users/new
```

> [!NOTE]
> ¡Listo!, acabas de diseñar un pequeño `CRUD` de usuarios. Lo tienes todo organizado, un Controlador que maneja las peticiones, un modelo que gestiona la información, vistas organizadas y separadas de la lógica.
>
> No tocaste nada de codigo `SQL` y lo más resaltante, en cualquier momento puedes quitar la aplicación (usando `remove` en el terminal). Ó incluso instalar otra aplicación que aumente funcionalidad.

> [!TIP]
> Con mayor expertiz, puedes acoplar un motor de plantillas de tu gusto, dentro de las aplicaciones `IrbisApps` la aplicación `Base` implementa `twig` como motor de plantillas por poner ejemplo.

---

### Propiedades y Tipos

> *El ejemplo anterior sirve para entender de forma general como se implementa y maneja un modelo, pero existen más detalles que en un sólo ejemplo es difícil de plantear.*


- Tipos básicos: `varchar`, `text`, `integer`, etc.
- Relaciones:
  - `n1` → many-to-one
  - `1n` → one-to-many
  - `nm` → many-to-many

```php
'name' => ['varchar'] // se recomienda que todo modelo lleve un campo 'name'
'age' => ['integer'] // se puede usar 'int' o lo que soporte el motor de bd
```

- Las relaciones son campos que apuntan a claves de otras tablas

> [!NOTE]
> Si notaste en el ejemplo anterior `users` llevaba un campo `id` sin haberlo declarado.
>
> Todo modelo implementa de forma implícita un campo `id` autoincrementable, y por el que se relacionan con otros modelos.

```php
// esto genera un campo relación con otro modelo llamado 'groups'
'group' => ['n1', 'target' => 'groups']
```

```php
// captura un 'user' con id = 1
$user = Record::find('users', 1);
// crea un nuevo 'group' llamado Admins
$group = Record::add('groups', ['name' => 'Admins']);
// relaciona ambos registros
$user->group = $group;
```

- Relaciones `1n`

`groups.php`
```php
// dentro del modelo 'groups'
// target apunta al modelo (users) y al campo origen (group)
'users' => ['1n', 'target' => 'users(group)']
```

```php
// captura un 'group' con id = 1
$group = Record::find('groups', 1);
$group->users; // devuelve un 'RecordSet' de 'users'
```

- Relaciones `nm`, similar a **1n** pero en doble sentido.

> Asumiendo que un usuario puede pertencer a varios grupos, y que un grupo puede contener varios usuarios.

`users.php`, sería así
```php
return [
  'name' => ['varchar'],
  'groups_ids' => ['nm', 'target' => 'groups(users_ids)']
]
```

`groups.php`, sería así
```php
return [
  'name' => ['varchar'],
  'users_ids' => ['nm', 'target' => 'users(groups_ids)']
]
```

> [!NOTE]
> En este ejemplo se usa `_ids` como nombre de propiedad, para no confundir y diferenciar, en la declaración de `target`, dentro de los `()` debe indicar el campo relación.

```php
$user = Record::find('users', 1);
$user->groups_ids; // Devuelve un recordset de 'groups'

$group = Record::find('groups', 1);
$group->users_ids // Devuelve un recordset de 'users'
```

> [!TIP]
> Las relaciones `nm` cuando se enlazan generan internamente una tabla intermedia (cuyo nombre empieza con `nm_`) para guardar la relación entre registros.

### Constantes, @delegate y @unique

Existe una forma de declarar variables en el modelo, útiles para configuraciones o parametrizaciones.

- `@unique`, sirve para indicar qué columnas de la tabla deben llevar la condición 'única'.
- Puede ser una combinación de varias columnas.

`users.php`
```php
return [
  // esto indica que el campo 'email' debe ser
  // único por cada registro existente
  '@unique' => ['email'],

  'name' => ['varchar'],
  // también hacemos que el campo 'email' no acepte nulos
  'email' => ['varchar', 'required' => true]
]
```

- `@delegate`, genera una relación fuerte entre dos modelos un padre y un hijo, haciendo que el modelo hijo se comporte como si ambos fueran uno sólo.

> Teniendo dos modelos.

`persons.php`
```php
return [
  'name' => ['varchar'],
  'email' => ['varchar'],
  'phone' => ['varchar']
]
```

`users.php`
```php
return [
  '@delegate' => 'person', // apunta al campo delegado

  'name' => ['varchar'],
  // campo delegado
  'person' => ['n1', 'target' => 'persons']
]
```

- Planteando que todo usuario siempre llevará los campos email y teléfono.
- Y que puede haber otros modelos que tambien los necesiten, como `Clientes` por ejemplo.
- Al designar un delegado hace que el modelo hijo `users` tenga comportamientos del modelo padre `persons`

```php
// puedes manejarlo como si fuera un sólo registro
// pero internamente se genera la relación
// y los datos se guardan en ambas tablas
$user = Record::add('users', [
  'name' => 'Juan Perez',
  'email' => 'juan.perez@mail.com',
  'phone' => '123456'
]);

// se puede acceder a las propiedades
// del modelo padre como propias del hijo
$user->email; // juan.perez@mail.com
```

- Otras variables marcadas con `@`, pueden ser accesibles desde el `RecordSet` o `Record`.
- Pueden ser utilizadas por ejemplo para indicar que vista debe usar un modelo por defecto.
- Puedes implementar ese u otros tipo de lógica que necesites.

```php
return [
  '@view' => '@test/user_form.html',

  'name' => ['varchar']
]
```

```php
$users = new RecordSet('users');
$users->select(); // captura todos los usuarios
$users->{'@view'}; // @test/user_form.html

$user = $users[0];
$user->{'@view'} // @test/user_form.html
```

### Métodos DML, manipulación de datos.

En los ejemplos anteriores se ven métodos `select()`, `insert()` ó `find()`, vamos a detallar estos a continuación.

- Estos métodos son descriptivos y funcionan tal cual las sentencias `SQL`.

```php
$users = new RecordSet('users');
$users->select(1); // captura registros cuyo id = 1
$users->select('Juan') // captura registros cuyo name = 'Juan'
$users->select(['group' => '1']) // captura registros cuyo 'group' lleve id=1
$users->select(); // si no se envia 'condición', captura todos los registros
```

- cada `select()` ejecutado va añadiendo registros al recordset.

```php
$users = new RecordSet('users');
$users->insert(['name' => 'Pedro']); // inserta un nuevo registro
$users->insert(
  ['name' => 'Jhon'],
  ['name' => 'Jane']
); // Inserta varios registros simultaneamente
```

- cada `insert()` ejecutado va añadiendo registros al recordset.

```php
$users = new RecordSet('users');
$users->select(); // captura todos los registros

// modifica el campo 'name' en los registros capturados
$users->update(['name' => 'Juan']);

// modifica el campo 'name' en los registros capturados
foreach ($users as $user) {
  $user->name = 'Juan';
}

// modifica el campo 'name' del primer registro capturado
$users[0]->name = 'Juan';
```

- el método `update()` hace una modificación en el conjunto de registros capturados. 
- Como se observa, el objeto `RecordSet` se comporta como un arreglo.
- Pudiendo usar `foreach` como cualquier otro arreglo, cada elemento recorrido es un objeto `Record`.

```php
$users = new RecordSet('users');
$users->select(); // captura todos los registros

$users[0]->delete(); // elimina el primer registro capturado
$users->delete(); // elimina todos los registros capturados
```

- Si el registro tiene configurado un `@delegate`, durante la eliminación y si la relación lleva el parámetro `ondelete='CASCADE` el registro delegado también se eliminará.
- Al determinar `CASCADE` (al revés) si el registro padre se elimina, el registro hijo tambien se elimina.

`users.php`
```php
return [
  '@delegate' => 'person',

  'name' => ['varchar'],
  'person' => ['n1', 'target' => 'persons', 'ondelete'=>'CASCADE']
]
```

```php
$users = new RecordSet('users');
$users->select(); // captura todos los registros

// elimina el primer registro capturado
// pero tambien eliminará el registro 'person' relacionado.
$users[0]->delete(); 
```

**Acortadores**, `find()` y `add()`

- Cuando se requiere capturar o insertar un registro puntal estos métodos acortan pasos.

```php
// capturar un registro cuyo id=3
$users = new RecordSet('users');
$users->select(3);
$user = $users[0];

// de forma corta se puede hacer
$user = Record::find('users', 3);
```

```php
// insertar y usar un registro nuevo
$users = new RecordSet('users');
$users->insert(['name' => 'Juan']);
$user = $users[0];

// de forma corta se puede hacer
$user = Record::add('users', ['name' => 'Juan']);
```

### Métodos DML, manipulando datos relacionados

En las relaciones estos métodos se comporta de una forma ligeramente diferente.

- Para relaciones `n1`

```php
$users = new RecordSet('users');

// Se inserta primero el registro en 'groups'
// luego se insertará el registro en 'users'
$users->insert([
  'name' => 'Juan',
  'group' => ['name' => 'Admins']
]);

$users[0]->group; // devuelve una instancia de Record
$users[0]->group->name; // Admins
```

- Para relaciones `1n` y `nm`

```php
$users = new RecordSet('users');

// se crean dos registros 'groups'
// y se relacionan al nuevo registro 'users'
$users->insert([
  'name' => 'Juan',
  'groups' => [
    ['name' => 'Admins'],
    ['name' => 'Public']
  ]
]);

$users[0]->groups; // devuelve una instancia de RecordSet
$users[0]->groups[1]->name; // Public
```

- Particularmente, al devolver instancias de `RecordSet` los métodos `select()` o `insert()` también están disponibles.

```php
// asumiendo que ya existe un usuario con id=1
$user = Record::find('users', 1);

// captura un registro de 'groups' con id=3
// y lo añade a la relación entre user(1) y sus groups
$user->groups->select(3);

// crea un nuevo registro en 'groups'
// y lo añade a la relación entre user(1) y sus groups
$user->groups->insert(['name' => 'Tech'])
```

> [!NOTE]
> Como se observa el manejo de modelos es bastante intuitivo, una vez dominado, aleja a tu código de escribir sentencias `SQL` permitiendo código más limpio, ordenado y estructurado.

<a name="extensibilidad"></a>

# 6. Extensibilidad

Hasta este punto ya eres capas de crear aplicaciones propias y el framework te ayuda a tener un código organizado, mantenible y extensible. Pero aún faltan algunas herramientas para mejorar el control total.

## 6.1. Interfaces (Component)

No toda la lógica de la aplicación puede estar en el controlador, so lo haces puedes terminar con `Controller` largos con demasiado código que probablemente no debe ir ahí.

Para eso existe una interfaz `Irbis\Interfaces\ComponentInterface` que se apoya en un trait `Irbis\Traits\Component`.

- Un `Componente` es una clase/objeto para organizar y separar lógica de negocio.
- Si sientes que hay métodos que no deben estar en el controlador, probablemente deben ir en un componente.

`Test/Authorization.php`
```php
<?php
namespace MyApp\Test;
use Irbis\Interfaces\ComponentInterface;
use Irbis\Traits\Component;

class Authorization implements ComponentInterface {
  use Component;

  public function auth ($credentials) {
    // la lógica va aqui
  }
}
```

- Si el controlador u otra clase requiere usar esta lógica.

`Test/Controller.php`
```php
<?php
<?php
namespace MyApps\Test;
use Irbis\Orm\RecordSet;
use Irbis\Orm\Record;
use Irbis\Exceptions\HttpException;
use Irbis\Controller as iController;


class Controller extends iController {
  public static $name = 'test';

  /**
   * @route /
   */
  final public function todoList ($request) {
    // se obtiene la instancia de Authorization
    $authorization = $this->component('Authorization');
    // se utiliza la logica implementada
    if (!$authorization->auth($request)) {
      throw new HttpException(401, 'Acceso denegado');
    }

    $db = Connector::getInstance();
    $stmt = $db->query("SELECT * FROM todo_list;");
    $list = $stmt->fetchAll();
    
    return ["@test/index.html", ['list' => $list]];
  }
}
```

- Todo componente debe declarar `use Component` e implementar `ComponentInterface`, para poder mantener una relación de dependencia y ser inyectado fácilmente por `Controller`.

## 6.2. Hooks

Clases `Hooks` es un componente especial que se utiliza para ejecutar lógica de instalación de aplicaciónes.

`Test/Hooks.php`
```php
<?php
namespace MyApp\Test;
use Irbis\Interfaces\ComponentInterface;
use Irbis\Interfaces\HooksInterface;
use Irbis\Traits\Component;

class Hooks implements ComponentInterface, HooksInterface {
  use Component;

  public function install () {
    // aqui puede ejecutar lógica de enlazado de modelos
    // crear registros de configuración, etc.
  }

  public function uninstall () {}
}
```

- Cuando se ejecuta el comando `install <app>` el sistema busca si en tu aplicación si existe una clase `Hooks`, si la encuentra lanza sus métodos durante el proceso.

## 6.3. Setup y Session

Clases `Setup` es un componente especial que se utiliza para realizar configuraciones y/o parametrizaciones del sistema, agregando lógica cada vez que se ejecuta una petición.

`Test/Setup.php`
```php
<?php
namespace MyApp\Test;
use Irbis\Interfaces\ComponentInterface;
use Irbis\Interfaces\SetupInterface;
use Irbis\Traits\Component;

class Setup implements ComponentInterface, SetupInterface {
  use Component;

  public function setup () {
    // aqui puedes colocar middlewares
    // u otras configuraciones que el sistema requiera
  }
}
```

- Útil para crear middlewares de acceso, autorización a recursos.
- Configurar entornos de renderizado.
- Control de errores, o respuesta a clientes.

Clases `Session` sirven para ser asignada como atributo a `Request` y este pueda saber como manejar la sesion de usuario activa.

- Por ejemplo se crea una clase `Session` que busca en base de datos un usuario cuyo `ID` sea igual al de la sesion guardada y devuelva la instancia `Record`.

`Test/Session.php`
```php
<?php
namespace MyApp\Test;
use Irbis\Interfaces\ComponentInterface;
use Irbis\Interfaces\SessionInterface;
use Irbis\Traits\Component;
use Irbis\Orm\Record;

class Session implements ComponentInterface, SessionInterface {
  use Component;

  public function getUser () {
    session_start();

    $id = $_SESSION['user'];
    $user = Record::find('users', $id);
    return $user;
  }
}
```

- Se configura un `Setup` para que asigne ese `Componente` a `Request`.
- Todo componente tiene acceso a un atributo `controller` que es su controlador correspondiente.

`Test/Setup.php`
```php
<?php
namespace MyApp\Test;
use Irbis\Interfaces\ComponentInterface;
use Irbis\Interfaces\SetupInterface;
use Irbis\Traits\Component;

class Setup implements ComponentInterface, SetupInterface {
  use Component;

  public function setup () {
    $request = Request::getInstance();
    $controller = $this->controller;
    $request->session = $controller->component('Session');
  }
}
```

- Cada que una petición llega al servidor, `Setup` es ejecutado.
- Pasando el componente `Session` a `Request`.
- `Request` tiene un atributo especial `session`.
- Esto se utiliza llamando a otro atributo de `Request` llamado `user`.

```php
$request = Request::getInstance();
$request->user; // Record, con el id de session
```

- Al invocar `user`, `Request` invoca al objeto `Session` preestablecido.