# RecordSet

Por el momento sólo funciona con bases de datos MySQL o MariaDB.

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
```php
// puede lanzar un error en el método 'check_age' si la edad es menor que 18
// Ó si el método 'check_age' devuelve otro valor, este será el que se almacene en la base de datos al final
'check_age' => function ($val) { return $val*2; }
```
```php
$users = new RecordSet('users');
$users->insert(['username' => 'Jhon', 'age' => 5]); 

// en la base de datos verá
// --------------------
// |  username  | age |
// --------------------
// |   Jhon     |  10 |
// --------------------
```

## Miembros: Métodos
Un método del modelo se definirá por medio de una función anónima, la clave será el nombre del método, se puede utilizar el identificador '$this' para hacer referencia a un registro en especifico.
```php
'sayHello' => function () {
  var_dump('Hola mi nombre es '. $this->username);
}
```
Para ejecutar los métodos del modelo se realizan a travez de cada registro único, la clase 'RecordSet' como su nombre indica es un conjunto de registros al que se pueden acceder como si de un arreglo se tratasen.
```php
$users = new RecordSet('users');
$users->select();

foreach ($users as $user) {
  $user->sayHello(); // Hola mi nombre es ...
}
```
## Métodos DML
Un objeto RecordSet tiene cuatro métodos principales para manipular información de la base de datos.

**select**, permite capturar registros, puede recibir un entero (id), o un arreglo de enteros (ids), o un arreglo asociativo como filtros.
```php
$users->select(); //captura todos los registros de la tabla
$users->select(1); //captura el registro con id = 1
$users->select([1,2,3,5]); //captura los registros con id = 1,2,3,5
$users->select(['username:like' => '%jhon%', 'age:>=' => 18]) // captura los registros que cumplan => WHERE username like '%Jhon%' and age >= 18
```

**insert**, permite insertar registros nuevos, recibe varios arreglos asociativos donde cada uno será un nuevo registro. Si algún valor no es enviado se considerará el valor por defecto o null. Los registros insertados se agregan al conjunto de registros actual.
```php
$users->insert(['username' => 'Jhon', 'userpass' => '123', 'age' => 20], ['username' => 'Doe', 'userpass' => '456'], ...);
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
## Relaciones
El modelo intregra 3 tipos de campos especiales para relaciones entre modelos.  

**Muchos a uno**, el campo será de tipo 'n1' y deberá tener un atributo 'target' que apunte al modelo relacionado.
```php
// model: groups
return [
  'groupname' => ['varchar']
];
```

```php
// model: users
return [
  'username' => ['varchar'., 'required' => true],
  'group' => ['n1', 'target' => 'groups'] // previamente se deberá definir el modelo 'groups'
];
```

```php
$users = new RecordSet('users');
// creará un nuevo registro en 'groups' para el nuevo grupo
$users->insert(['username' => 'Jhon', 'group' => ['groupname' => 'admins']]);
$users[0]->group->groupname; // devolverá 'admins'

// capturará el registro con id 1 de 'groups' y lo asociará con el nuevo usuario
$users->insert(['username' => 'Juan', 'group' => 1]);
$users[1]->group->groupname; // devolverá 'admins'
```

**Uno a muchos**, el campo será de tipo '1n' y deberá tener un atributo 'target' que apunte al modelo relacionado y la columna relacionada. Este tipo de campo requiere que en el modelo relacionado exista un campo 'n1' contrario.
```php
// model: groups
return [
  'groupname' => ['varchar'],
  'users' => ['1n', 'target' => 'users(group)']
];
```

```php
$groups = new RecordSet('groups');
// creará un nuevo grupo y 2 nuevos usuarios relacionados a este grupo
$groups->insert(['groupname' => 'portalusers', 'users' => [['username' => 'Pedro'], ['username' => 'Pablo']]]);
$groups[0]->users; // devuelve otro conjunto de registros (RecordSet) con los usuarios relacionados a 'portalusers'

// creará un nuevo grupo y relacionará a los usuarios con id 1,2 a este grupo
$groups->insert(['groupname' => 'webusers', 'users' => [1,2]]);
$groups[1]->users; // devuelve otro conjunto de registros (RecordSet) con los usuarios relacionados a 'webusers'
```

**Muchos a muchos**, el campo será de tipo 'nm' y deberá tener un atributo 'target' que apunte al modelo relacionado y la columna relacionada. Este tipo de campo requiere que en el modelo relacionado exista otro campo 'nm' contrario.
```php
// model: groups
return [
  'groupname' => ['varchar'],
  'users' => ['nm', 'target' => 'users(groups)']
];
```
```php
// model: users
return [
  'username' => ['varchar'., 'required' => true],
  'group' => ['nm', 'target' => 'groups(users)']
];
```

```php
$users = new RecordSet('users');
$groups = new RecordSet('groups');

// el proceso de creación es similar a los campos 1n, pero en este caso desde cualquier de los dos modelos se puede acceder a sus relacionados
$users->insert(['username' => 'Jhon', 'groups' => [['groupname' => 'portalusers'], ['groupname' => 'webusers']]]);
$groups->insert(['groupname' => 'externalusers', 'users' => [['username' => 'Pedro'], ['username' => 'Pablo']]]);
```

## Modularidad
Tal como se explicó en la base del framework el objetivo modular también se cumple en los modelos, cada módulo puede tener un directorio 'models' y dentro tener la estructura de sus propios modelos. Pero, también es posible externder funcionalidad de un modelo desde otro módulo.

Ejemplo, teniendo dos módulos 'Test' y 'Test2' ambos pueden estructurar un solo modelo 'users' uno amplía funcionalidad sobre el otro.

*Test/models/users.php*
```php
return [
  'username' => ['varchar'],
  'userpass' => ['varchar'],
  
  'login' => function ($userpass) {
    return $userpass == $this->userpass;
  }
]
```

*Test2/models/users.php*
```php
return [
  'userpass' => ['store' => 'hash'],
  'active' => ['boolean', 'default' => 1]
  
  'hash' => function ($value) {
    return password_hash($value ?: uniqid(), PASSWORD_DEFAULT);
  },
  
  'login' => function ($userpass) {
    return password_verify($userpass, $this->userpass);
  }
]
```
Como se muestra en los ejemplos, nuestro primer módulo 'Test' implementa un modelo 'users' con nombre y contraseña pero sólo valida que las contraseñas ingresadas sean iguales lo que no es muy seguro. En el segundo módulo 'Test2' se extiende el modelo  'users', se agrega un campo 'active' y se modifica el campo 'userpass' para que haga un hash sobre la contraseña, por último se sobreescribe el método 'login' para que valide el hash.

Si en algún momento quitamos el módulo 'Test2' el modelo 'users' sólo tendrá la funcionalidad y campos que el módulo 'Test' implementa.
