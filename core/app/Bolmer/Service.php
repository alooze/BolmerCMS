<?php namespace Bolmer;

class Service{
    protected static $instance;

    protected $_service = array();

    private function __construct($data){
        $this->_service = new Pimple($data);
    }

    final public static function getInstance(array $data = array()){
        $class = get_called_class();
        if (!static::$instance) static::$instance = new $class($data);
        return static::$instance;
    }

    public function register($key, $data){
        $this->_service[$key] = $data;
    }

    public function get($key){
        return isset($this->_service[$key]) ? $this->_service[$key] : null;
    }
}