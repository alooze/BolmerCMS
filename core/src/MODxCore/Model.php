<?php namespace MODxCore;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 19.01.14
 * Time: 16:07
 */
require_once dirname(dirname(dirname(__FILE__))).'/lib/paris.class.php';
class Model extends \Model{
    public function save($CallEvents = false,$clearCache = false) {
        $q = $this->orm->save();
        if($clearCache){
            /**
             * @var $modx \MODxCore\DocumentParser
             */
            $modx = modx();
            $modx->clearCache('full', false);
        }
        return $q;
    }

    public static function factory($class_name, $connection_name = null) {
        $table_name = static::_get_table_name($class_name);
        if ($connection_name == null) {
            $connection_name = static::_get_static_property(
                $class_name,
                '_connection_name',
                \ORMWrapper::DEFAULT_CONNECTION
            );
        }
        $wrapper = \ORMWrapper::for_table(static::getTable($table_name, $connection_name), $connection_name);
        $wrapper->set_class_name($class_name);
        $wrapper->use_id_column(static::_get_id_column_name($class_name));
        return $wrapper;
    }

    public static function getFullTableName($className='', $connection_name = null){
        $class_name = $className ? (get_class() . '\\'. $className) : get_called_class();
        $table_name = static::_get_table_name($class_name);
        if ($connection_name == null) {
            $connection_name = static::_get_static_property(
                $class_name,
                '_connection_name',
                \ORMWrapper::DEFAULT_CONNECTION
            );
        }
        return static::getTable($table_name, $connection_name);
    }

    public static function getTable($name, $connection){
        return \ORMWrapper::get_config('prefix', $connection).$name;
    }

    public static function __callStatic($method, $parameters) {
        if(function_exists('get_called_class')) {
            $model = static::factory(get_called_class());
            return call_user_func_array(array($model, $method), $parameters);
        }
    }
}