<?php namespace Bolmer;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 5:21
 */
class Pimple extends \Pimple
{
    protected static $instance;

    public function __construct($data)
    {
        $this['debug'] = function ($inj) {
            return new \Bolmer\Debug($inj);
        };

        $this['log'] = function ($inj) {
            return new \Bolmer\Log($inj);
        };

        $this['config'] = function ($inj) {
            return $inj['modx']->config;
        };

        $this['db'] = function ($inj) {
            return $inj['modx']->db;
        };

        $this['request'] = function ($inj) {
            return new \Bolmer\Presenter\Request($inj);
        };

        $this['document'] = function ($inj) {
            return new \Bolmer\Operations\Document($inj);
        };

        $this['parser'] = function ($inj) {
            return new \Bolmer\Parser($inj);
        };

        $this['snippet'] = function ($inj) {
            return new \Bolmer\Parser\Snippet($inj);
        };

        $this['plugin'] = function ($inj) {
            return new \Bolmer\Parser\Plugin($inj);
        };

        $this['HTML'] = function ($inj) {
            return new \Bolmer\Presenter\HTML($inj);
        };

        $this['cache'] = function ($inj) {
            return new \Bolmer\Cache($inj);
        };

        $this['response'] = function ($inj) {
            return new \Bolmer\Presenter\Response($inj);
        };
    }

    final public static function getInstance(array $values = array())
    {
        $class = get_called_class();
        if (!static::$instance) static::$instance = new $class($values);
        return static::$instance;
    }
}