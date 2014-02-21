<?php namespace Bolmer;

class Service
{
    /**
     * @var \Bolmer\Service ссылка на экземпляр сервиса
     */
    protected static $_instance;

    /**
     * @var array|Pimple Коллекция сервисов
     */
    public $collection = array();

    /**
     * @var \Bolmer\Helper\xNop
     */
    protected $_nop = null;

    /**
     * Этот метод может быть вызван только при первом вызове севриса
     *
     * @param array $data параметры для объекта \Pimple
     */
    private function __construct($data)
    {
        $this->collection = new Pimple($data);

        $this->_nop = new \Bolmer\Helper\xNop();
    }
    /**
     * Этот метод может быть вызван только при первом вызове севриса
     */
    private function __clone()
    {

    }

    /**
     * Этот метод может быть вызван только при первом вызове севриса
     */
    private function __wakeup()
    {

    }

    /**
     * Загрузка коллекции сервисов
     *
     * @param array $data параметры для объекта \Pimple
     * @return \Bolmer\Service
     */
    final public static function getInstance(array $data = array())
    {
        $class = get_called_class();
        if (!static::$_instance) static::$_instance = new $class($data);
        return static::$_instance;
    }

    /**
     * Запрос сервиса из коллекции Pimple
     *
     * @param string $key Имя сервиса
     * @param bool $nop Если сервис не найден, то отдавать в ответ заглушку \Bolmer\Helper\xNop класс или null
     * @return null|Helper\xNop|array|Operations\User|Operations\User\Manager|Cache|Debug|Log|Parser|Presenter\Request|Operations\Document|Parser\Snippet|Parser\Plugin|Presenter\HTML|Presenter\Response    object of class $key
     */

    public function get($key, $nop = true)
    {
        if (isset($this->collection[$key])) {
            $out = $this->collection[$key];
        } else {
            $out = $nop ? $this->_nop : null;
        }
        return $out;
    }
}