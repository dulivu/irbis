# Irbis\Tools

**Nivel de acoplamiento:** nulo.

Herramientas diversas de utilidad y reutilizables.

## Constants

- **BASE_PATH** > ruta base de la aplicación.
- **DEFAULT_VIEW** > vista por defecto 'index'.
- **CRYPT_KEY** > clave para encriptación de texto.
- **CRYPT_METHOD** > método de encriptación utilizado por defecto.
- **STATE_FILE** > nombre por defecto del archivo de estado del servidor.

## Functions

> Generales
- **irbis_loader (string $base = '')** > autocargador de irbis.
- **redirect (string $url)** > genera una redirección y termina la ejecución.

> Arreglos
- **is_assoc ($_array)** > determina si un arreglo es asociativo.
- **array_pull(array &$array, string|int $key)** > elimina un elemento de un arreglo y lo devuelve.
- **array_get(array $array, string $path, string $separator = '.')** > obtiene un valor por notación de puntos.
- **array_set(array &$array, string $path, $value, string $separator = '.')** > establece un valor por notación de puntos.
- **array_unset(array &$array, string $path, string $separator = '.')** > elimina un valor por notación de puntos.
- **array_any(array $array, ?callable $callback = null)** > indica si algún elemento es 'true' o cumple con el criterio del 'callback'.
- **array_all(array $array, ?callable $callback = null)** > indica si todos los elementos son 'true' o cumplen con el criterio del 'callback'.

> Texto
- **str_unique_id(int $lenght = 8)** > genera un string id único.
- **str_encrypt(string $cadena)** > encripta un texto usando las configuraciones por defecto.
- **str_decrypt(string $cadena)** > desencripta un texto usando las configuraciones por defecto.
- **str_camel_to_snake(string $string)** > convierte un texto en formato camelCase a snake.
- **str_snake_to_camel(string $string)** > convierte un texto en formato snake a camelCase.
- **str_token(int $length = 32)** > genera un token aleatorio.
- **str_starts_with (string $string, string $startString)** > indica si una cadena inicia con.
- **str_ends_with (string $string, string $endString)** > indica si una cadena termina con.
- **str_contains(string $haystack, string $needle)** > indica si una cadena esta contenida.
- **str_slug(string $string)** > convierte un texto normal a texto-normal
- **str_uuid()** > devuelve un texto en formato uuid

> Números
- **num_is_int($value)** > indica si un valor es un entero.
- **num_is_float($value)** > indica si un valor es un flotante.
- **num_format(float $value, int $decimals = 2)** > wrapper de number_format
- **num_percent(float $value, float $total)** > obtiene el porcentaje en decimales

## class Json

métodos para codificar y decodificar formatos JSON.

```php
Json::encode($array);

Json::decode($json_text);
```

## class Xml

métodos para codificar y decodificar formatos XML.

```php
Xml::encode($array);

Xml::decode($xml_text);
```