<?php

if (DEBUG_MODE) {
	error_reporting( E_ALL );
	ini_set('display_errors', 1);
	# ini_set('display_startup_errors', 1);
}

set_error_handler(function (int $errNo, string $errMsg, string $file, int $line) { 
	throw new \Error("$errMsg", $errNo);
});

/*
|--------------------------------------------------------------------------
| Autoloader
|--------------------------------------------------------------------------
|
| permite realizar autocarga de clases en función del namespace y el nombre de la clase
| registra un directorio base para la busqueda de los archivos PHP, al directorio enviado le
| agrega el directorio base establecido en BASE_PATH
|
*/
function irbis_loader (string $base = '') {
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
|
| redirect(string $url): reenvía a la ruta solicitada y termina el script
| myme_type(string $file): obtiene el tipo mime del archivo
| safe_file_write(string $file, mix $data): guardar datos en un archivo
| write_ini_file(string $fileName, array $data): guarda un array como un archivo de configuracion
|
*/
function redirect ($url) {
	header('Location: '.$url);
	die('redirecting...');
}

function mime_type ($file) {
	if (function_exists('finfo_open')) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $file);
		finfo_close($finfo);

		$extension = pathinfo($file, PATHINFO_EXTENSION);
		switch ($extension) {
			case 'css': $mime = 'text/css'; break;
			case 'js': $mime = 'application/javascript'; break;
			default: break;
		}
	} else {
		$mime = mime_content_type($file);
	}
	if (empty($mime)) $mime = 'application/octet-stream';
	return $mime;
}

function safe_file_write (string $file, $data) {
	if ($fp = fopen($file, 'w')) {
		$time = microtime(TRUE);
		do {
			$writeable = flock($fp, LOCK_EX);
			if(!$writeable) usleep(round(rand(0, 100)*1000));
		} while ((!$writeable) && ((microtime(TRUE)-$time) < 5));

		if ($writeable) {
			fwrite($fp, $data);
			flock($fp, LOCK_UN);
		}

		fclose($fp);
	} else trigger_error('Error, al modificar el archivo de configuración: '.$file);
}

function write_ini_file (string $file, array $data) {
	$res = array();
	foreach($data as $key => $val) {
		if (is_array($val)) {
			$res[] = "[$key]";
			foreach($val as $k => $v) 
				$res[] = "$k = ".(is_numeric($v) ? $v : '"'.$v.'"');
		}
		else $res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
	}
	safe_file_write($file, implode("\r\n", $res));
}

/*
|--------------------------------------------------------------------------
| Herramientas; arreglos
|--------------------------------------------------------------------------
| is_assoc:
|		devuelve true o false en caso un array sea o no asociativo.
|
| delete:
|		elimina un elemento de un arreglo dado, y lo devuelve.
|
| array_get, array_set, array_unset:
| 		entregado un arreglo asociativo, permite obtener, establecer o eliminar
|		un valor por medio de una ruta (devuelve el valor eliminado).
| 		$arr = ['uno'=> ['dos'=> ['tres' => 'valor']]]
| 		$val = array_get($arr, 'uno.dos.tres') // 'valor'
|
*/
function is_assoc($_array) { 
	if ( !is_array($_array) || empty($_array) )
		return false;
	$keys = array_keys($_array);
	return array_keys($keys) !== $keys;
}

function delete (&$arr, $key) {
	$tmp = $arr[$key];
	unset($arr[$key]);
	return $tmp;
}

function array_get(array $array, string $path, string $separator = '.') {
	$keys = explode($separator, $path);
	$current = $array;
	foreach ($keys as $key) {
		if (!isset($current[$key])) return;
		$current = $current[$key];
	}
	return $current;
}

function array_set(array &$array, string $path, $value, string $separator = '.') {
	$keys = explode($separator, $path);
	$current = &$array;
	foreach ($keys as $key) {
		$current = &$current[$key];
	}
	$current = $value;
}

function array_unset(array &$array, string $path, string $separator = '.') {
	$keys = explode($separator, $path);
	$current = &$array;
	$parent = &$array;
	foreach ($keys as $i => $key) {
		if (!array_key_exists($key, $current)) return;
		if ($i) $parent = &$current;
		$current = &$current[$key];
	}
	$temp = $parent[$key];
	unset($parent[$key]);
	return $temp;
}

/*
|--------------------------------------------------------------------------
| Herramientas; cadenas de texto
|--------------------------------------------------------------------------
|
| strUniqueID: 	genera una cadena única de 8 carácteres
| strEncrypt: 		encripta una cadena
| strDecrypt: 		desencripta una cadena
| strToken:		genera una cadena única de 20 carácteres
| strStartsWith:	cadena empieza con
| strEndsWith:		cadena termina con
|
| decamelize, camelize:
|		convierten cadenas de texto en formato CamelCase y viceversa
| 		HolaMundoGenial => hola_mundo_genial
|
*/
function str_unique_id ($lenght=8) {
	return substr( md5(microtime()), 1, $lenght);
}

function str_encrypt($cadena){
	return base64_encode(openssl_encrypt($cadena, CRYPT_METHOD, CRYPT_KEY, 0, ''));
}
 
function str_decrypt($cadena){
	return rtrim(openssl_decrypt(base64_decode($cadena), CRYPT_METHOD, CRYPT_KEY, 0, ''), "\0");
}

function decamelize ($string) {
	return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
}

function camelize ($string) {
	return $word = preg_replace_callback("/(^|_)([a-z])/", function($m) { 
		return strtoupper("$m[2]"); 
	}, $string);
}

function _crypto_rand_secure ($min, $max) {
    $range = $max - $min;
    if ($range < 1) return $min; // not so random...
    $log = ceil(log($range, 2));
    $bytes = (int) ($log / 8) + 1; // length in bytes
    $bits = (int) $log + 1; // length in bits
    $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
    do {
        $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
        $rnd = $rnd & $filter; // discard irrelevant bits
    } while ($rnd > $range);
    return $min + $rnd;
}

function str_token ($length=20) {
    $token = "";
    $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
    $codeAlphabet.= "0123456789";
    $max = strlen($codeAlphabet); // edited

    for ($i=0; $i < $length; $i++) {
        $token .= $codeAlphabet[_crypto_rand_secure(0, $max-1)];
    }

    return $token;
}

function str_starts_with ($string, $startString) {
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}

function str_ends_with ($string, $endString) {
    $len = strlen($endString);
    if ($len == 0)
        return false;
    return (substr($string, -$len) === $endString);
}

function pathcheck($path) {
	$path = str_replace(['/','\\'], DIRECTORY_SEPARATOR, $path);
	if (str_starts_with($path, DIRECTORY_SEPARATOR))
		$path = substr($path, 1);
	return $path;
}

function path_to_namespace ($path) {
	$path = str_replace(['/', '.php'], ['\\', ''], $path);
	if (str_starts_with($path, '\\'))
		$path = substr($path, 1);
	return $path;
}

function snake_to_text ($string) {
	return ucfirst(str_replace('_', ' ', $string));
}