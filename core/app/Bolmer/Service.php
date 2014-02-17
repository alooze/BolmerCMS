<?php namespace Bolmer;

class Service{
    protected static $instance;
    public $collection = array();

    /**
     * @var \Bolmer\Helper\xNop
     */
    protected $_nop = null;

    private function __construct($data){
        $this->collection = new Pimple($data);
        $this->_nop = new \Bolmer\Helper\xNop();
    }

    final public static function getInstance(array $data = array()){
        $class = get_called_class();
        if (!static::$instance) static::$instance = new $class($data);
        return static::$instance;
    }

    public function get($key, $nop = true){
        if(isset($this->collection[$key])){
            $out = $this->collection[$key];
        }else{
            $out = $nop ? $this->_nop : null;
        }
        return $out;
    }
}