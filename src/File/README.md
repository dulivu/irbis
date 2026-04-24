# Irbis\File

**Nivel de acoplamiento:** uso de constantes definidas en 'Irbis\Tools'.

Todo lo concerniente al manejo de archivos y directorios en el servidor. Trabaja siempre con rutas relativas, cada clase se encargan de controlar el acceso y manejo de la ruta completa internamente.

## abstract class System

Clase base y abstracta para control de archivos y directorios.

> Estáticos
```php
// ruta raíz del sistema de archivos
// restringe el acceso a archivos fuera de esta ruta.
protected static string $root = BASE_PATH;

// establece y modifica la ruta raíz.
public static function setRoot(string $root): void;
```

> Especiales
```php
public function __construct(string $path);
```

> Protegidos
```php
// @setter, modifica la ruta del archivo o directorio.
protected function setPath(string $path): void;

// asegura que la ruta usada pertenesca a la ruta $root establecida.
protected function normalize(string $path): string;
```

> Públicos
```php
// @getter, devuelve la ruta del archivo o directorio
// @param $full=true, devuelve la ruta absoluta.
public function path(bool $full = false): string;

// indica si el archivo o directorio existe.
abstract public function exists(): bool;
```

## class File extends System

Gestión de archivos individuales.

> Públicos
```php
// lee el contenido completo del archivo.
public function read(): string;

// escribe contenido al archivo (modo append).
public function write(string $content): bool;

// vacía el contenido del archivo.
public function truncate(): bool;

// elimina el archivo del sistema.
public function delete(): bool;

// mueve el archivo a una nueva ruta.
public function move(string $newPath): void;

// copia el archivo a una nueva ruta y devuelve la nueva instancia.
public function copy(string $newPath): self;

// obtiene información del archivo (dirname, basename, extension, filename, mime, size).
// @param $key='', devuelve toda la información en array.
// @param $key='mime|size|dirname|basename|extension|filename', devuelve el valor específico.
public function info(string $key = ''): array|string;
```

> Privados
```php
// detecta el tipo MIME del archivo.
private function mimeType(): ?string;
```

> Ejemplos
```php
// crear un nuevo archivo
$file = new File('content/file.txt');
$file->write('Hola mundo!');
// crear una copia del archivo
$newfile = $file->copy('content/copy.txt');
```

## class Directory extends System

Gestión de directorios.

> Públicos
```php
// crea el directorio con permisos específicos.
// @param $permissions=0755, permisos del directorio.
// @param $recursive=true, crea directorios padre si no existen.
public function create(int $permissions = 0755, bool $recursive = true): bool;

// elimina el directorio.
// @param $recursive=false, si es true elimina todo el contenido recursivamente.
public function delete(bool $recursive = false): bool;

// mueve el directorio a una nueva ruta.
public function move(string $newPath): void;

// copia el directorio a una nueva ruta y devuelve la nueva instancia.
public function copy(string $newPath): self;

// devuelve array de instancias File con todos los archivos del directorio.
public function files(): array;

// devuelve array de instancias Directory con todos los subdirectorios.
public function directories(): array;

// devuelve array de rutas con todos los elementos (archivos y directorios).
public function all(): array;

// crea y devuelve una instancia File dentro del directorio.
public function file(string $path): File;
```

> Privados
```php
// elimina recursivamente el contenido de un directorio.
private function deleteRecursive(string $dir): bool;

// copia recursivamente el contenido de un directorio.
private function copyRecursive(string $source, string $destination): void;
```

## class Config extends File

Gestión de archivos de configuración (.ini). 

Al instanciar la clase intentará crear el archivo inmediatamente y/o cargar los datos del archivo de configuración.

> Estáticos
```php
// @exclusive Irbis\Server, 
// crea un instancia de Config con datos por defecto
public static function serverDefault(string $path): self 
```

> Públicos
```php
// obtiene un valor de configuración usando notación punto.
// ejemplo: get('server.debug') o get('database.dsn')
public function get(string $key);

// establece un valor de configuración usando notación punto.
// @param $value=null, elimina la clave si el valor es null.
public function set(string $key, $value): void;

// indica si el archivo de configuración está vacío.
public function empty(): bool;

// guarda los cambios en el archivo INI.
public function save(): void;
```

> Privados
```php
// formatea una línea del archivo INI con su valor apropiado.
private function formatLine(string $key, $value): string;
```

## class Logger extends File

Clase para registro de eventos en archivos de log

> Constantes
```php
const INFO = 'INFO';
const ERROR = 'ERROR';
const WARNING = 'WARNING';
```

> Públicos
```php
// registra un mensaje con nivel específico.
// @param $level=self::INFO, nivel del mensaje (INFO, ERROR, WARNING).
public function log(string $message, string $level = self::INFO): void;

// registra un mensaje de nivel INFO.
public function info(string $message): void;

// registra un mensaje de nivel ERROR.
public function error(string $message): void;

// registra un mensaje de nivel WARNING.
public function warning(string $message): void;
```

## class Upload

Gestión de archivos subidos mediante formularios HTTP.

Esta clase sólo se puede instanciar por medio de 'fromGlobals' y es para uso exclusivo de archivos cargados en $_FILE. 

> Estáticos
```php
// obtiene todos los archivos subidos desde $_FILES normalizados como instancias Upload.
public static function fromGlobals(): array;
```

> Públicos
```php
// guarda el archivo subido en el destino especificado.
// @param $destination, puede ser una ruta string o instancia Directory.
// @return File, instancia del archivo guardado.
public function save(string|Directory $destination): File;

// si $destination es string, se puede usar un comodin para mantener
// el nombre del archivo tal como se subió, ejemplo:
$uploads = Uploads::fromGlobals();
$file = $uploads['file']->save('uploads/*');

// indica si el archivo subido es válido y puede ser procesado.
// ya utilizado dentro de 'save', disponible para casos especiales
public function isValid(): bool;
```

> Privados
```php
// normaliza el array $_FILES al formato esperado.
private static function normalize(array $files): array;

// obtiene el error HTTP apropiado según el código de error de upload.
private function getError(): ?HttpException;
```

> Ejemplos
```php
// se puede usar un comodin para mantener el nombre
// original al momento de guardar el archivo
$uploads = Uploads::fromGlobals();
$file = $uploads['file'][0]->save('uploads/*');

// ó se puede pasar una instancia de Directory
$directory = new Directory('uploads');
$file = $uploads['file'][0]->save($directory);
```

