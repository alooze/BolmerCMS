<?php
header('X-Powered-By: MODX 2');

// Check PHP version
if (version_compare(phpversion(), '5.3.0', '<')) {
    printf("PHP 5.3.0 is required, you have %s\n", phpversion());
    exit();
}

if (!defined('BOLMER_BASE_PATH')) define('BOLMER_BASE_PATH', MODX_BASE_PATH);
if (!defined('BOLMER_BASE_URL')) define('BOLMER_BASE_URL', MODX_BASE_URL);
if (!defined('BOLMER_SITE_URL')) define('BOLMER_SITE_URL', MODX_SITE_URL);
if (!defined('BOLMER_MANAGER_PATH')) define('BOLMER_MANAGER_PATH', MODX_MANAGER_PATH);
if (!defined('BOLMER_MANAGER_URL')) define('BOLMER_MANAGER_URL', MODX_MANAGER_URL);

if (!defined("BOLMER_DEBUG")) {
    define("BOLMER_DEBUG", false);
}
if(BOLMER_DEBUG){
    error_reporting(E_ALL);
    ini_set('display_errors','On');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

require PATH_BOLMER_CORE . "/bootstrap/functions.php";

//trigger_error("File not found ".$f, E_USER_ERROR);
if(function_exists('errorHandler')){
    register_shutdown_function('errorHandler');
}
if(function_exists('errorDisplay')){
    register_shutdown_function('errorDisplay');
}

/**
 * Autoload files
 */
$files = require PATH_BOLMER_CORE . "/bootstrap/autoload.php";
$logStack = array();
foreach($files as $i => $f){
    try{
        switch(true){
            case !is_scalar($f):{
                throw new RuntimeException($i." position in the array with the files is not a string");
                break;
            }
            case !is_file($f):{ //http://www.php.net/manual/ru/wrappers.php
                throw new RuntimeException("File not found: ". (string)$f);
                break;
            }
            case !is_readable($f):{
                throw new RuntimeException("Can not access files: ". (string)$f);
                break;
            }
            default:{
                require_once $f;
            }
        }
    } catch (RuntimeException $e) {//RuntimeException
        trigger_error($e->getMessage(), E_USER_ERROR);
    }
}
require PATH_BOLMER_CORE . "/app/Pimple.class.php";
require PATH_BOLMER_CORE . "/app/SplClassLoader.class.php";

with(new SplClassLoader('Bolmer', PATH_BOLMER_CORE ."/app/"))->register();
with(new SplClassLoader('Granada', PATH_BOLMER_CORE ."/app/Granada/src/"))->register();
with(new SplClassLoader('Tcache', PATH_BOLMER_CORE ."/app/Tcache/src/"))->register();