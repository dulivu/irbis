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
if (!defined("DEBUG_MODE")) define("DEBUG_MODE", false);

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
if (!defined("CRYPT_KEY")) define("CRYPT_KEY", 'irbis');
if (!defined("CRYPT_METHOD")) define("CRYPT_METHOD", 'AES-128-ECB');


/*
|--------------------------------------------------------------------------
| REQUEST_EMULATION
|--------------------------------------------------------------------------
|
| si se activa la clase Request permitirá comprobar métodos http
| dentro del cuerpo de un método POST en una variable '_method'
|
*/
if (!defined("REQUEST_EMULATION")) define("REQUEST_EMULATION", false);

/*
|--------------------------------------------------------------------------
| DB_INI
|--------------------------------------------------------------------------
|
| define la ruta donde la clase DataBase buscará datos para realizar
| conexiones, debe ser un archivo .ini
|
*/
if (!defined("DB_INI")) define("DB_INI", 'database.ini');

/*
|--------------------------------------------------------------------------
| MOD_REWRITE
|--------------------------------------------------------------------------
|
| Se activa para las rutas amigables, previamente se debe configurar el 
| archivo htaccess correspondiente
|
*/
if (!defined("MOD_REWRITE")) define("MOD_REWRITE", false);
/*
|--------------------------------------------------------------------------
| REQUEST
|--------------------------------------------------------------------------
|
| Se definen los tipos de peticiones shortcuts para usar en la
| clase Request->is([METHOD_REQUEST])
|
*/
if (!defined("GET_REQUEST")) define("GET_REQUEST", 'GET');
if (!defined("POST_REQUEST")) define("POST_REQUEST", 'POST');
if (!defined("PUT_REQUEST")) define("PUT_REQUEST", 'PUT');
if (!defined("DELETE_REQUEST")) define("DELETE_REQUEST", 'DELETE');
if (!defined("JSON_REQUEST")) define("JSON_REQUEST", 'JSON');
if (!defined("FILE_REQUEST")) define("FILE_REQUEST", 'FILE');
/*
|--------------------------------------------------------------------------
| MOD_REWRITE
|--------------------------------------------------------------------------
|
| Una variable para el estado del controlador, en caso se requiera
| quitar un estado existente.
| $controller->state('key', REMOVE_STATE);
|
*/
if (!defined("REMOVE_STATE")) define("REMOVE_STATE", uniqid());