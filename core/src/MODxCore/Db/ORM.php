<?php namespace MODxCore\Db;

include_once(PATH_MODXCORE."/lib/idiorm.class.php");
class ORM extends \ORM{
    public function build_statement(){
        $query = $this->_build_select();
        static::_execute($query, $this->_values, $this->_connection_name);
        return static::get_last_statement();
    }

    public static function for_table($table_name, $connection_name = self::DEFAULT_CONNECTION) {
        static::_setup_db($connection_name);
        return new static($table_name, array(), $connection_name);
    }
}