<?php

/*
|--------------------------------------------------------------------------
| Autoloader
|--------------------------------------------------------------------------
|
*/
function irbis_loader (string $base = '') 
{
    spl_autoload_register(function ($k) use ($base) {
        $s = DIRECTORY_SEPARATOR;
        $base = str_replace(['\\','/'], $s, $base).$s;
        $path = implode($s, explode('\\', $k)).'.php';
        $file = BASE_PATH.$base.$path;
        if (file_exists($file)) require_once($file);
    });
}

/*
|--------------------------------------------------------------------------
| Herramientas
|--------------------------------------------------------------------------
*/
function redirect (string $url) 
{
    \Irbis\Server::instance()->state->save();

    // if (\Irbis\Orm\Connector::exists()) {
    //     \Irbis\Orm\Connector::instance()->commit();
    // }

    header('Location: '.$url);
    die('redirecting...');
}

/*
|--------------------------------------------------------------------------
| Herramientas; arreglos
|--------------------------------------------------------------------------
*/
function is_assoc($_array): bool 
{ 
    if ( !is_array($_array) || empty($_array) )
        return false;
    $keys = array_keys($_array);
    return array_keys($keys) !== $keys;
}

function array_pull(array &$array, string|int $key): mixed
{
    if (!array_key_exists($key, $array)) {
        return null;
    }

    $value = $array[$key];
    unset($array[$key]);

    return $value;
}

function array_get(array $array, string $path, $default = null, string $separator = '.')
{
    $keys = explode($separator, $path);

    foreach ($keys as $key) {
        if (!is_array($array) || !array_key_exists($key, $array)) {
            return $default;
        }
        $array = $array[$key];
    }

    return $array;
}

function array_set(array &$array, string $path, $value, string $separator = '.')
{
    $keys = explode($separator, $path);
    $current = &$array;

    foreach ($keys as $key) {
        if (!isset($current[$key]) || !is_array($current[$key])) {
            $current[$key] = [];
        }
        $current = &$current[$key];
    }

    $current = $value;
}

function array_unset(array &$array, string $path, string $separator = '.'): bool
{
    $keys = explode($separator, $path);
    $current = &$array;

    foreach ($keys as $i => $key) {
        if (!is_array($current) || !array_key_exists($key, $current)) {
            return false;
        }

        if ($i === count($keys) - 1) {
            unset($current[$key]);
            return true;
        }

        $current = &$current[$key];
    }

    return false;
}

if (!function_exists('array_any')) {
    function array_any(array $array, ?callable $callback = null): bool 
    {
        if ($callback === null) {
            foreach ($array as $value) {
                if ($value) return true;
            }
        } else {
            foreach ($array as $value) {
                if ($callback($value)) return true;
            }
        }
        return false;
    }
}

if (!function_exists('array_all')) {
    function array_all(array $array, ?callable $callback = null): bool 
    {
        if ($callback === null) {
            foreach ($array as $value) {
                if (!$value) {
                    return false;
                }
            }
        } else {
            foreach ($array as $value) {
                if (!$callback($value)) {
                    return false;
                }
            }
        }

        return true;
    }
}


/*
|--------------------------------------------------------------------------
| Herramientas; cadenas de texto
|--------------------------------------------------------------------------
*/
function str_unique_id(int $lenght = 8): string 
{
    // return substr( md5(microtime()), 1, $lenght);
    return substr(bin2hex(random_bytes(16)), 0, $length);
}

function str_encrypt(string $cadena): string 
{
    $iv_length = openssl_cipher_iv_length(CRYPT_METHOD);
    $iv = random_bytes($iv_length);

    $encrypted = openssl_encrypt(
        $cadena,
        CRYPT_METHOD,
        CRYPT_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );

    return base64_encode($iv . $encrypted);
    //return base64_encode(openssl_encrypt($cadena, CRYPT_METHOD, CRYPT_KEY, 0, ''));
}
 
function str_decrypt(string $cadena): string 
{
    $data = base64_decode($cadena);

    $iv_length = openssl_cipher_iv_length(CRYPT_METHOD);

    $iv = substr($data, 0, $iv_length);
    $ciphertext = substr($data, $iv_length);

    return openssl_decrypt(
        $ciphertext,
        CRYPT_METHOD,
        CRYPT_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );
    //return rtrim(openssl_decrypt(base64_decode($cadena), CRYPT_METHOD, CRYPT_KEY, 0, ''), "\0");
}

function str_camel_to_snake(string $string): string 
{
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
}

function str_snake_to_camel(string $string): string 
{
    $string = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    return lcfirst($string);
}

function str_token(int $length = 32): string
{
    return substr(bin2hex(random_bytes($length)), 0, $length);
}

if (!function_exists("str_starts_with")) {
    function str_starts_with (string $string, string $startString): bool
    {
        return $prefix === '' || strncmp($string, $prefix, strlen($prefix)) === 0;
    }
}

if (!function_exists("str_ends_with")) {
    function str_ends_with (string $string, string $endString): bool
    {
        if ($suffix === '') {
            return true;
        }

        return substr($string, -strlen($suffix)) === $suffix;
    }
}

if (!function_exists("str_contains")) {
    function str_contains(string $haystack, string $needle): bool 
    {
        return $needle === '' || mb_strpos($haystack, $needle) !== false;
    }
}

function str_slug(string $string): string
{
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9]+/i', '-', $string);
    return trim($string, '-');
}

function str_uuid(): string
{
    $data = random_bytes(16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/*
|--------------------------------------------------------------------------
| Herramientas; números
|--------------------------------------------------------------------------
*/
function num_is_int($value): bool
{
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

function num_is_float($value): bool
{
    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
}

function num_format(float $value, int $decimals = 2): string
{
    return number_format($value, $decimals, '.', '');
}

function num_percent(float $value, float $total): float
{
    if ($total == 0) {
        return 0;
    }

    return ($value / $total) * 100;
}