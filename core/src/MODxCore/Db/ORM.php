<?php namespace MODxCore\Db;

include_once(PATH_MODXCORE."/lib/idiorm.class.php");
class ORM extends \ORM{
    public static function for_table($table_name, $connection_name = self::DEFAULT_CONNECTION) {
        static::_setup_db($connection_name);
        return new static($table_name, array(), $connection_name);
    }
}