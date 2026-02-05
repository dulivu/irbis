# RecordSet & Record

Ambas clases trabajan en conjunto para poder acceder y manipular datos.

### RecordSet: Métodos de uso interno

Estos métodos también son accesibles desde Record.

- **toArray(): void** → Convierte el recordset en un arreglo
- **newRecordSet(string $name): RecordSet** → Crea un nuevo recordset con el puntero del original
- **newRecord([array $values]): Record** → Crea un nuevo record relacionado a recordset origen
- **execute(string $query, array $params): PDOStatement** → Ejecuta un query SQL, usando su conexion a db
- **exec(string $query): int** → Ejecuta un query SQL, devuelve la cantidad de afectaciones

### RecordSet: Métodos de utilidad

- **insert(...$values): self** → Inserta en bd los registro y agrega lo insertado al recordset
- **select(string $query, [mix $order], [mix $limit]): self** → Selecciona de la bd y agrega los registro al recordset
- **update(array $values): self** → Actualiza en bd los valores de los registros en el recordset
- **delete(): self** → Elimina en bd los registros del recordset
- **map(string $prop_name): array** → devuelve una lista del elemento de un recordset
- **flush([closure $fn]): self** → Limpia el recordset, si se pasa un closure quitá los elementos que calcen con ello
- **filter(Closure $fn): RecordSet** → Ejecuta una retrollamada para filtrar los elementos del recordset

```php
$users = new RecordSet('users');        // inicia un recordset vacio para el modelo 'users'
$users->select(1);                      // trae de bd un registro 'users' con id=1
$users->select('Juan');                 // trae de bd un registro 'users' con name='Juan'
$users->select(['active' => false]);    // trae de bd registros 'users' cuyo valor 'active' sea falso

$users->insert(['name' => 'Pedro']);    // inserta en bd un registro 'users' con nombre 'Pedro'

$users->update(['active' => true]);     // actualiza todos los registros capturados

$users->delete();                       // elimina todos los registros capturados
```

```php
// crea un recordset y captura todos los usuarios activos
$users = RecordSet::find('users', ['active'=>true]);
// quita todos los registros cuyo nombre sea 'Juan'
$users->flush(function ($record) { $record->name == 'Juan'; }); 
// crea un nuevo recordset con elementos cuyo nombre sea 'Ana'
$ana = $users->filter(function ($record) { $record->name == 'Ana'; });
// obtiene una lista de todas las fechas de creación de los elementos
$create_date_list = $users->map('create_date');
```

### Record: Métodos de uso interno

- **raw($key, $val): void** → Establece un valor del registro, directamente sin pasar por validaciones
- **raw($key): mix** → Obtiene un valor del registro, directamente sin pasar por validaciones
- **raw($values): void** → Establece valores del registro por medio de un arreglo asociativo
- **update($values): void** → Actualiza en la base de datos los valores del registro

### Record: Métodos de utilidad

- **delete(): void** → Elimina el registro de la base de datos
- **find(string $model, array $query, [mix $order]): Record|null** → obtiene la primera coincidencia
- **add(string $model, array $values): Record** → inserta un nuevo registro y devuelve el objeto creado

```php
$juan = Record::find('Juan'); // busca un registro cuyo nombre sea 'Juan'
$ana = Record::add(['name' => 'Ana']); // crear un nuevo registro de nombre 'Ana'

$juan->active = false; // cambia el valor del campo 'active' de Juan
$ana->delete(); // elimina el registro Ana
```

### Acceso a propiedades y tipos

- **propiedades que inician con @** → declaradas directamente en la definición
- **@delegate** → debe apuntar a un campo relacion n1
- **propiedades que inician con $** → privadas de uso de la clase
- **métodos que inician con @ (declarados)** → para métodos que se llaman en el recordse



