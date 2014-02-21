<?php namespace Bolmer;

class Pimple extends \Pimple
{

    /**
     * Создание коллекции Pimple
     *
     * @param array $data параметры для объекта \Pimple
     */
    public function __construct($data)
    {
        parent::__construct($data);

        $this['debug'] = function ($inj) {
            return new \Bolmer\Debug($inj);
        };

        $this['log'] = function ($inj) {
            return new \Bolmer\Log($inj);
        };

        $this['config'] = function ($inj) {
            return $inj['core']->config;
        };

        $this['db'] = function ($inj) {
            return $inj['core']->db;
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

        $this['user'] = function ($inj) {
            return new \Bolmer\Operations\User($inj);
        };

        $this['manager'] = function ($inj) {
            return new \Bolmer\Operations\User\Manager($inj);
        };
    }
}