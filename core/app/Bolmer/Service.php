<?php namespace Bolmer;

class Service{
    protected static $instance;
    protected $_service = array();

    private function __construct($data){
        $this->_service = new Pimple($data);
    }

    function __call($name,$args) {
        if(method_exists($this->_service,$name)) return call_user_func_array(array($this->_service,$name),$args);
    }

    final public static function getInstance(array $data = array()){
        $class = get_called_class();
        if (!static::$instance) static::$instance = new $class($data);
        return static::$instance;
    }

    public function register($key, $data){
        $flag = false;
        if(is_scalar($key)){
            $key = $this->_service->getFullName($key);
            //if(isset($this->_service[$key])){
               // $this->_service[$key] = $this->_service->extend($key, $data);
            //}else{
                $this->_service[$key] = $data;
            //}
            $flag = true;
        }
        return $flag;
    }

    public function full(){
        return $this->_service;
    }

    public function get($key){
        return $this->_service->$key;
    }
}