<?php
header('X-Powered-By: MODX 2');

// Check PHP version
if (version_compare(phpversion(), '5.3.0', '<')) {
    printf("PHP 5.3.0 is required, you have %s\n", phpversion());
    exit();
}

if (!defined("MODX_DEBUG")) {
    define("MODX_DEBUG", false);
}
if(MODX_DEBUG){
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors','On');
}

require PATH_MODXCORE . "/bootstrap/functions.php";

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
$files = require PATH_MODXCORE . "/bootstrap/autoload.php";
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

require PATH_MODXCORE . "/lib/Pimple.class.php";
require PATH_MODXCORE . "/lib/SplClassLoader.class.php";

with(new SplClassLoader('MODxCore', PATH_MODXCORE ."/src/"))->register();
with(new SplClassLoader('Granada', PATH_MODXCORE ."/lib/Granada/src/"))->register();