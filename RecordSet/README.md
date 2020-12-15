# RecordSet
Conjunto de clases que permiten manipular registros de bases de datos como si fueran objetos, con propiedades y métodos. A continuación detallaremos el funcionamiento de estos por medio de un ejemplo, es necesario conocer la base del framework 'Irbis' y haber seguido los ejemplos ahí antes de continuar con este apartado.

## Creando la estructura de datos
En cada módulo añadiremos un directorio llamado 'models' y dentro de este directorio podremos declarar cada una de nuestras estructuras de datos, estas son archivos PHP con nombres en minusculas.

*directorio*
- Irbis (framework)
- Test (módulo)
  - models
    - users.php (model)
  - views
    - index.html
    - users.html
  - Controller.php
- Test2 (módulo)
- index.php

Nuestra estructura de datos es un arreglo asociativo, donde cada par clave/valor corresponde a miembro/definición, a continuación declararemos un modelo 'users' básico para poder realizar un login.

*Test/models/users.php*
```php
<?php
// Model: users
return [
  'username' => ['varchar', 'required' => true],
  'userpass' => ['varchar', 'required' => true, 'store' => 'hash'],
  
  'hash' => function ($value) {
    return password_hash($value ?: uniqid(), PASSWORD_DEFAULT);
  },
  
  'login' => function ($userpass) {
    return password_verify($userpass, $this->userpass);
  }
];
```

Hay dos tipos de miembros dentro la estructura. Propiedad, representa una columna dentro de una tabla de base de datos con su tipo de datos respectivo entre otras carácteristicas. Método, representa las acciones que el modelo puede realizar, tiene acceso a sus propiedades y otros métodos como si de un objeto se tratasen. Finalmente este arreglo asociativo se devuelve por medio de la palabra reservada 'return'.

## La clase 'RecordSet' y el método 'bind'
Realizada la estructura del modelo, este modelo se puede utilizar por medio de la clase '\Irbis\RecordSet\RecordSet'. Siguiendo el ejemplo modificaremos el módulo 'Test2' método 'persons' que al momento de crear una persona se cree en conjunto un usuario.

```php
public function persons ($request, $response) {
  $db = DB::getInstance('main');
  
  if ($request->isMethod('POST')) {
    $stmt = $db->prepare("INSERT INTO `persons` VALUES (?, ?, ?)");
    $stmt->execute($request->input(['nombre', 'apellido', 'telefono']));
    
    // aqui añadimos nuestra lógica
    $users = new \Irbis\RecordSet\RecordSet('users');
    $users->insert([ 'username' => $request->input('nombre'), 'userpass' => '123' ]);
  }
  
  $response = $this->getServer()->respond();
  $response->view = 'Test2/views/persons.html';
}
```

OJO: consideremos que la tabla 'users' no ha sido creada previamente, pero podemos lograr que el modelo construya la estructura en la base de datos por medio del método 'bind', para esto crearemos en nuestro controlador otro método enrutado a la dirección 'install' para construir todos nuestros modelos.

```php
/**
 * @route /install
 */
public function install ($request, $response) {
  // el parámetro 'main' es el nombre de la conexión de base de datos
  $users = new RecordSet('users', 'main');
  $users->bind('main');
  return 'Modelos construidos';
}
```

Realizado y entrando a la ruta 'http://localhost/index.php/install' la tabla 'users' ya debería existir en nuestra base de datos. Y en la ruta '/persons' al agregar una nueva persona veremos que también se ingresa un usuario automáticamente.

## Miembros: Propiedades
Como vimos en el ejemplo podemos definir propiedades por medio de arreglos asociativos.
```php
'username' => ['varchar', 'required' => true, 'length' => 25, 'default' => 'Jhon'],
'age' => ['int', 'store' => 'check_age']
```
El primer valor (elemento 0) será el tipo de dato, los demás elementos con sus respectivas claves definirán otras carácteristicas de la propiedad.
La clave 'store' permite ejecutar lógica con el valor entregado antes de almacenarlo, su valor será el nombre del método.
La clave 'retrieve' permite ejecutar lógica con el valor almacenado antes de mostrarlo, su valor será el nombre del método.

## Miembros: Métodos
Un método del modelo se definirá por medio de una función anónima, la clave será el nombre del método, se puede utilizar el identificar '$this' para hacer referencia a un registro en especifico.
```php
'sayHello' => function () {
  var_dump('Hola mi nombre es '. $this->username);
}
```
Para ejecutar los médotos del modelo se realizan a travez de cada registro único, la clase 'RecordSet' como su nombre indica es un conjunto de registros al que se pueden acceder como si de un arreglo se tratasen.
```php
$users = new RecordSet('users');
$users->select();

foreach ($users as $user) {
  $user->sayHello(); // Hola mi nombre es ...
}
```
## Métodos DML
Un objeto record set tiene cuatro métodos principales para manipular información de la base de datos.

**select**, permite capturar registros, puede recibir un entero (id) y arreglo de enteros (ids), ó un arreglo asociativo como filtros.
```php
$users->select(1); //id
$users->select([1,2,3,5]); //ids
$users->select(['username:like' => '%jhon%', 'age:>=' => 18]) // WHERE username like '%Jhon%' and age >= 18
```

**insert**, permite insertar registros nuevos, recibe varios arreglos asociativos donde cada uno será un nuevo registro. Si algún valor no es enviado se considerará el valor por defecto o null.
```php
$users->insert(['username' => 'Jhon', 'userpass' => '123', 'age' => 20], ['username' => 'Doe', 'userpass' => '456']);
```

**update**, permite modificar campos de los registros capturados, si se llama sobre un conjunto de registros (RecordSet) la modificación se hará para todos los registros capturados.
```php
$users->select([1,2,3]) // captura los registros con id = 1,2,3
$users->update(['username' => 'Pedro']); //modifica el campo 'username' para todos los registros capturados (1,2,3)
$users[0]->update(['username' => 'Juan']); //modifica el campo 'username' sólo para el registro de id = 1
$users[0]->username = 'Juan' //funciona igual que la linea anterior, pero para un campo a la vez.
```

**delete**, elimina los registros capturados en la base de datos.
```php
$users->select([1,2,3]); // captura los registros con id = 1,2,3
$users[0]->delete(); // elimina sólo el registro con id = 1
$users->delete(); // elimina todos los registros capturados (1,2,3)
```
