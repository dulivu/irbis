# RecordSet
Conjunto de clases que permiten manipular registros de bases de datos como si fueran objetos, con propiedades y métodos. A continuación detallaremos el funcionamiento de estos por medio de un ejemplo, es necesario conocer la base del framework 'Irbis' y haber seguido los ejemplos ahí antes de continuar con este apartado.

## Creando la estructura de datos
En cada módulo añadiremos un directorio llamado 'models' y dentro de este directorio podremos declarar cada una de nuestras estructuras de datos, estas son archivos PHP con nombres en minusculas.

*directorio*
- Irbis (framework)
- Test (módulo)
  - models
    - users.php
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
