<?php namespace MODxCore;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 4:33
 */

class Config implements \ArrayAccess, \Countable, \IteratorAggregate{
    protected $_container = array();
    protected $_position = 0;
    protected static $instance;

    private function __construct(){}
    final public static function getInstance(){
        $class = get_called_class();
        if(!static::$instance) static::$instance = new $class();
        return static::$instance;
    }

    public function getIterator() {
        return new \ArrayIterator($this->_container);
    }
    public function rewind(){
        $this->_position = 0;
    }

    public function current(){
        return $this->_container[$this->_position];
    }

    public function key(){
        return $this->_position;
    }

    public function next(){
        ++$this->_position;
    }

    public function valid(){
        return isset($this->_container[$this->_position]);
    }
    public function count(){
        return count($this->_container);
    }
    public function get($key, $default = null){
        $out = $this->offsetGet($key);
        return is_null($out) ? $default : $out;
    }

    public function offsetExists($offset){
        return isset($this->_container[$offset]);
    }

    public function offsetGet($offset){
        return $this->offsetExists($offset) ? $this->_container[$offset] : null;
    }

    public function offsetSet($offset, $value){
        if (is_null($offset)) {
            $this->_container[] = $value;
        } else {
            $this->_container[$offset] = $value;
        }
    }

    public function offsetUnset($offset){
        unset($this->_container[$offset]);
    }
    /**
     * Метод возвращает первый элемент контейнера
     *
     * @return      mixed|null    Первый элемент контейнера. Если контейнер пуст - null
     */
    public function get_first()
    {
        foreach($this as $value)
        {
            return $value;
        }
    }

    /**
     * Метод возвращает последний элемент контейнера
     *
     * @return      mixed|null    Последний элемент контейнера. Если контейнер пуст - null
     */
    public function get_last()
    {
        if ($this->count() == 0)
        {
            return;
        }

        $iterator = $this->getIterator();

        // перейдем к последнему элементу
        $iterator->seek($this->count() - 1);

        return $iterator->current();
    }

    public function toArray(){
        return $this->_container;
    }
}