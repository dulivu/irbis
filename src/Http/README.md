# Irbis\Http

**Nivel de acoplamiento:** medio
- Irbis\File
- Irbis\Tools

Todo lo concerniente a la recepción y envio de peticiones Http, herramientas para capturar solicitudes de cliente, datos como; uri, método http, inputs y archivos de carga.

También permite realizar solicitudes a otros servicios por el protocolo Http, peticiones GET, POST y otros, también envío de archivos y solicitudes JSON o XML.

## enum Method: string

Enumeración de métodos HTTP disponibles.

> Casos
```php
case GET = 'GET';
case POST = 'POST';
case PUT = 'PUT';
case PATCH = 'PATCH';
case DELETE = 'DELETE';
case OPTIONS = 'OPTIONS';
case HEAD = 'HEAD';
```

> Estáticos
```php
// obtiene el método HTTP desde $_SERVER['REQUEST_METHOD'].
public static function fromGlobals(): self;
```

## enum Status: int

Enumeración de códigos de estado HTTP.

> Casos
```php
// Success
case OK = 200;
case CREATED = 201;
case ACCEPTED = 202;
case NON_AUTHORITATIVE_INFORMATION = 203;
case NO_CONTENT = 204;
case RESET_CONTENT = 205;
case PARTIAL_CONTENT = 206;

// Redirection
case MULTIPLE_CHOICES = 300;
case MOVED_PERMANENTLY = 301;
case FOUND = 302;
case SEE_OTHER = 303;
case NOT_MODIFIED = 304;
case USE_PROXY = 305;
case TEMPORARY_REDIRECT = 307;

// Client Error
case BAD_REQUEST = 400;
case UNAUTHORIZED = 401;
case PAYMENT_REQUIRED = 402;
case FORBIDDEN = 403;
case NOT_FOUND = 404;
case METHOD_NOT_ALLOWED = 405;
case NOT_ACCEPTABLE = 406;

// Server Error
case INTERNAL_SERVER_ERROR = 500;
case NOT_IMPLEMENTED = 501;
case BAD_GATEWAY = 502;
case SERVICE_UNAVAILABLE = 503;
case GATEWAY_TIMEOUT = 504;
```

> Públicos
```php
// indica si el estado es exitoso (200-299).
public function isSuccess(): bool;

// indica si el estado es error de cliente (400-499).
public function isClientError(): bool;

// indica si el estado es error de servidor (500-599).
public function isServerError(): bool;
```

## final class Headers

Gestión de encabezados HTTP con acceso tipo array.

Permite manejar el objeto como si de un array se tratara, validando las claves que se utilizan teniendo que ser siempre strings e internamente manejadas en minúsculas.

> Estáticos
```php
// obtiene los encabezados desde getallheaders().
public static function fromGlobals(): self;
```

> Especiales
```php
// constructor, inicializa con array de encabezados.
public function __construct(array $headers = []);
```

> Públicos
```php
// busca un contenido específico en el encabezado Content-Type.
public function content(string $search_content): ?string;

// verifica si el encabezado Accept incluye el contenido especificado.
public function accept(string $search_content): bool;

// devuelve el valor del token bearer si estuviera establecido
public function bearer(): ?string
```

> Ejemplos
```php
$headers = new Headers([
    'Authorization' => 'Bearer token123',
    'Content-Type' => 'application/json'
]);

$headers['X-Custom-Host'] = 'hello-world.com';
$headers['Content-Type']; // application/json
isset($headers['authorization']); // true
```

## class Uri

Representación de una URI HTTP con sus componentes.

> Propiedades
```php
string $scheme;
string $host;
int $port; // null si no existe
string $path;
array $query;
string $fragment; // null si no existe
```

> Estáticos
```php
// crea una instancia desde una cadena URL.
public static function fromString(string $url): static;

// crea una instancia desde $_SERVER con la URI actual.
public static function fromGlobals(): static;
```

> Públicos
```php
// devuelve el origen (scheme://host:port).
public function origin(): string;
```

> Ejemplos
```php
$uri = Uri::fromString('http://localhost:8080/path/to/resource?bar=foo#fragment');

$uri->path; // /path/to/resource
$uri->query['bar']; // foo
$uri->origin(); // http://localhost:8080

```

## class Request

Representa una solicitud HTTP.

Puede representar una solicitud realizada por un cliente al servidor.

También puede representar una solicitud que se realiza hacia otro servicio.

> Propiedades
```php
public Uri $uri;
public Method $method;
public Headers $headers;
```

> Especiales
```php
// constructor, inicializa la solicitud HTTP.
public function __construct(
    string|Uri $uri, 
    string|Method $method = 'GET', 
    array|Headers $headers = []
);
```

> Estáticos
```php
// crea una instancia desde las variables globales de PHP.
public static function fromGlobals(): static;
```

> Shorcuts
```php
public function isGet(): bool;
public function isPost(): bool;
public function isPut(): bool;
public function isPatch(): bool;
public function isDelete(): bool;
public function isOptions(): bool;
public function isHead(): bool;
public function isJson(): bool;
public function isXml(): bool;
```

> Públicos
```php
// obtiene o establece un parámetro de query string.
public function query(string $key, $value = null);

// obtiene o establece un valor de input.
public function input(string $key, $value = null);

// obtiene los archivos cargados desde el cliente
public function upload(string $key): ?Upload;

// establece el(los) archivo(s) que se quiere enviar
public function file(string|File $file, string $key = 'file'): void

// prepara datos de envío convirtiendo a formato específico
public function json(array $data): void
public function xml(array $data): void

// envía la solicitud HTTP y devuelve una respuesta.
public function send(): Response;
```

> Ejemplo: recibiendo petición (de cliente)
```php
$request = Request::fromGlobals();

// para una url: http://localhost:8000/user/1?debug=1
$request->query('debug'); // 1

// para un cuerpo de formulario: bar=foo
// ó para una solicitud JSON: { "bar": "foo" }
$request->input('bar') // foo

// para subida de archivos del cliente
$upload = $request->upload('file');
$upload->save('uploads/*'); // directory uploads

// caso el cliente envíe más de un archivo
while ($upload = $request->upload('file')) {
    $upload->save('uploads/*');
}
```

>[!TIP]
>Cada ves que se llama al método 'upload' se retira de la lista el primer archivo, ya que se espera que se haga algo con este archivo subido, cuando ya no haya más archivos el método devolverá 'null'.

> Ejemplo: enviando petición (json)
```php
$request = new Request('https://httpbin.org/post', 'POST');
$request->headers['Content-Type'] = 'application/json';
$request->query('bar', 'foo');
$request->input('bar', 'foo'); // al detectar la cabecera convertira automaticamente a JSON
$response = $request->send();

// alternativa corta, puedes usar el método json
$request = new Request('https://httpbin.org/post');
$request->json(['bar' => 'foo']);
$response = $request->send();
```

> Ejemplo: enviando petición (archivos)
```php
$request = new Request('https://httpbin.org/post');
// dos formas de agregar un archivo a la petición
$request->file('content/prueba1.txt');
$request->file(new File('content/prueba2.txt'));
// si el método no se declara, se infiere POST
$response = $request->send();
```

## class Response

Representa una respuesta HTTP.

Puede representar la respuesta que se planea enviar al cliente.

También representa la respuesta obtenida de una solicitud realizada a otro servicio, por medio de la clase 'Request::send()'.

> Propiedades
```php
public Status $status;
public Headers $headers;
```

> Especiales
```php
// constructor, inicializa la respuesta HTTP.
public function __construct (
    Status|int $status = 200, 
    array|Headers $headers = ['content-type' => 'text/html'],
    string $body = ''
);
```

> Shortcuts
```php
// convierta la respuesta en el tipo especificado
public function json(): array;
public function xml(): array;
```

> Públicos
```php
// configura la respuesta como template HTML.
public function template(string $path, array $data = []): void;

// configura la respuesta para enviar un archivo.
public function file(string $path): void;

// configura la respuesta para descargar un archivo.
public function download(string $path): void;

// configura la respuesta para streaming de archivo.
public function stream(string $path): void;

// configura la respuesta para otros tipos de datos
public function data($data = null);

// guarda el contenido de la respuesta en un archivo.
// útil para descargar archivos de solicitudes HTTP.
public function save(string|Directory $destination): File;
```

> Ejemplos, preparando respuesta hacia cliente
```php
$headers = ['X-Custom-Header' => 'cabecera_custom'];
$response = new Response(200, $headers);

// enviar texto plano
$response->data('Hola mundo');

// enviar datos en formato JSON, se infiere por defecto
$response->data(['message' => 'Hello World']);

// enviar renderizado de una plantilla
$response->template('views/home.html', ['title' => 'Home']);

// enviar descarga de archivo
$response->download('documents/file.pdf');

// mostrar directamente un archivo
$response->file('assest/style.css');

// enviar datos binarios para descarga
$response->binary($binary, 'filename.ext');

// finalemente se envia la respuesta al cliente
// esta parte naturalmente lo gestiona el framework
$response->send();
```

> Ejemplo, manejando respuesta de una solicitud
```php
// cuando se envia una solicitud, el método 'send()'
// devuelve un objeto 'Response'
$response = $request->send();

// tratará de inferir la conversión en función de las cabeceras de respuesta
// si content-type es 'application/json' o 'application/xml'
// para otros casos simplemente devuelve el texto plano obtenido
$data = $response->data();

// ó se puede forzar la conversión
// en caso el servicio no entregue explicitamente cabeceras
$json = $response->json();
$xml = $response->xml();

// casos especiales para webscraping
$html = $response->html();
$xpath = $response->xpath();

// si lo obtenido es un archivo directamente
$file = $response->save('downloads/*');
```
