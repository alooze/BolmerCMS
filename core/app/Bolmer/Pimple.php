<?php namespace Bolmer;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 5:21
 */
class Pimple extends \Pimple
{
    const KEY_PREFIX = '_';
    /**
     * @var \Bolmer\Helper\xNop
     */
    protected $_nop = null;

    public function getFullName($name){
        return static::KEY_PREFIX.$name;
    }

    protected static $instance;

    public function __set($name, $value) {
        $this[$this->getFullName($name)] = $value;
    }

    public function __get($name) {
        return isset($this[$this->getFullName($name)]) ? $this[$this->getFullName($name)] : $this->_nop;
    }


    public function __construct($data)
    {
        $this->debugger = function ($inj) {
            return new \Bolmer\Debug($inj);
        };

        $this->log = function ($inj) {
            return new \Bolmer\Log($inj);
        };

        $this->request = function ($inj) {
            return new \Bolmer\Presenter\Request($inj);
        };

        $this->document = function ($inj) {
            return new \Bolmer\Operations\Document($inj);
        };

        $this->parser = function ($inj) {
            return new \Bolmer\Parser($inj);
        };

        $this->snippet = function ($inj) {
            return new \Bolmer\Parser\Snippet($inj);
        };

        $this->plugin = function ($inj) {
            return new \Bolmer\Parser\Plugin($inj);
        };

        $this->HTML = function ($inj) {
            return new \Bolmer\Presenter\HTML($inj);
        };

        $this->cache = function ($inj) {
            return new \Bolmer\Cache($inj);
        };

        $this->response = function ($inj) {
            return new \Bolmer\Presenter\Response($inj);
        };

        $this->_nop = new \Bolmer\Helper\xNop();
    }
}