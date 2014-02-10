<?php namespace MODxCore;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 5:21
 */
class Pimple extends \Pimple{
    protected static $instance;

    final public static function getInstance(array $values = array()){
        $class = get_called_class();
        if(!static::$instance) static::$instance = new $class($values);
        return static::$instance;
    }
}