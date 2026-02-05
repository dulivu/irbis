<?php

/*
|--------------------------------------------------------------------------
| BASE_PATH
|--------------------------------------------------------------------------
|
| la ruta base para llamada a directorios,
| por defecto se llena con la ruta del script desde donde se llama
| a este archivo, normalmente sería tu index.php en la raíz de tu directorio
|
*/
if (!defined("BASE_PATH")) define("BASE_PATH", getcwd());

/*
|--------------------------------------------------------------------------
| DEBUG_MODE
|--------------------------------------------------------------------------
|
| Activa o no el modo de depuracion
|
*/
if (!defined("DEBUG_MODE")) define("DEBUG_MODE", ($_ENV['IRBIS_MODE'] ?? null) === 'development');

/*
|--------------------------------------------------------------------------
| DEFAULT_VIEW
|--------------------------------------------------------------------------
|
| ayudante: el valor por defecto que se usa cuando la ruta es dinámica y usa
| las variables /?view={view} ó /[0] por ejemplo, por defecto el valor será 'index'
|
*/
if (!defined("DEFAULT_VIEW")) define("DEFAULT_VIEW", 'index');

/*
|--------------------------------------------------------------------------
| CRYPT_KEY
|--------------------------------------------------------------------------
|
| llave que se usa por defecto en las funciones de encriptación
| y el método de encriptación por defecto a usar
|
*/
if (!defined("CRYPT_KEY")) define("CRYPT_KEY", 'irbis_framework_key_1234');
if (!defined("CRYPT_METHOD")) define("CRYPT_METHOD", 'AES-128-ECB');

/*
|--------------------------------------------------------------------------
| DB_INI
|--------------------------------------------------------------------------
|
| define la ruta donde la clase DataBase buscará datos para realizar
| conexiones, debe ser un archivo .ini
|
*/
if (!defined("STATE_FILE")) define("STATE_FILE", 'state.ini');