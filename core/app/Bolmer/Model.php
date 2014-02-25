<?php namespace Bolmer;

use Granada\Orm\Wrapper as ORMWrapper;

class Model extends \Granada\Model
{
    /** @var string $UKey поле с уникальным значением в таблице */
    public static $UKey = 'id';

    /**
     * Прослойка для сохранения объекта
     *
     * @param bool $CallEvents вызывать ли события заложенные в модели при сохранении объекта
     * @param bool $clearCache производить ли полную очистку кеша после сохранения объекта
     * @param bool $ignore Игнорировать существующую запись и на базе нее создать новую
     * @return mixed статус сохранения объекта
     */
    public function save($CallEvents = false, $clearCache = false, $ignore = false)
    {
        $q = parent::save($ignore);
        if ($clearCache) {
            getService('core')->clearCache('full', false);
        }
        return $q;
    }

    public function set($property, $value = null) {
        return parent::set($property, $value);
    }

    public static function factory($class_name, $connection_name = null)
    {
        $table_name = static::_get_table_name($class_name);
        if ($connection_name == null) {
            $connection_name = static::_get_static_property(
                $class_name,
                '_connection_name',
                \Granada\Orm\Wrapper::DEFAULT_CONNECTION
            );
        }
        $wrapper = \Granada\Orm\Wrapper::for_table(static::getTable($table_name, $connection_name), $connection_name);
        $wrapper->set_class_name($class_name);
        $wrapper->use_id_column(static::_get_id_column_name($class_name));
        return $wrapper;
    }

    protected static function _get_table_name($class_name)
    {
        $specified_table_name = static::_get_static_property($class_name, '_table');
        $specified_table_name = \Granada\Orm\Wrapper::get_config('prefix') . $specified_table_name;
        if (is_null($specified_table_name)) {
            return self::_class_name_to_table_name($class_name);
        }
        return $specified_table_name;
    }

    public static function getFullTableName($className = '', $connection_name = null)
    {
        $class_name = $className ? (get_class() . '\\' . $className) : get_called_class();
        $table_name = static::_get_table_name($class_name);
        if ($connection_name == null) {
            $connection_name = static::_get_static_property(
                $class_name,
                '_connection_name',
                \Granada\Orm\Wrapper::DEFAULT_CONNECTION
            );
        }
        return static::getTable($table_name, $connection_name);
    }

    public static function getTable($name, $connection)
    {
        $prefix = \Granada\Orm\Wrapper::get_config('prefix', $connection);
        if ($prefix && substr($name, 0, strlen($prefix)) != $prefix) {
            $name = $prefix . $name;
        }
        return $name;
    }

    public static function __callStatic($method, $parameters)
    {
        if (function_exists('get_called_class')) {
            $model = static::factory(get_called_class());
            return call_user_func_array(array($model, $method), $parameters);
        }
    }

    /***********************************************************************/
    /*                                                                     */
    /*                       Заготовки фильтров                            */
    /*                                                                     */
    /***********************************************************************/
    /**
     * Получение информации о об элементе по его имени
     * Пример выполнения запроса:
     *      $row = \Bolmer\Model\BModelName::filter('getItem', $name, false);
     *      $out = getkey($row, 'field_name', '');
     *
     *      $row = \Bolmer\Model\BModelName::filter('getItem', $name);
     *      $row->field_name;
     *
     * @param ORMWrapper $orm
     * @param string $name Имя чанка
     * @param bool $asArray В каком виде получить данны (объект или массив)
     * @return array
     */
    public function getItem(ORMWrapper $orm, $name, $asArray = false)
    {
        $row = is_scalar($name) ? $orm->where(static::$UKey, $name)->filter('cached', 'find_one') : null;
        if (!empty($row)) {
            $out = $asArray ? $row->as_array() : $row;
        } else {
            $out = $asArray ? array() : new \Bolmer\Helper\xNop;
        }
        return $out;
    }

    /**
     * Фильтр для выборочного кеширования запросов
     *
     * @param ORMWrapper $orm
     * @param string $method метод которым стоит получить данные
     * @return \Granada\Orm
     */
    public function cached(ORMWrapper $orm, $method = 'find_one')
    {
        $isCached = $orm->get_config('caching');
        if (!$isCached) {
            $orm->configure('caching', true);
        }
        $out = $orm->$method();
        if (!$isCached) {
            $orm->configure('caching', $isCached);
        }
        return $out;
    }

    /**
     * Формирование массива с данными из одной колонки
     *
     * @param ORMWrapper $orm
     * @param string $field имя поля по которому нужно сформировать результатирующий массив
     * @return array
     */
    public function makeArray(ORMWrapper $orm, $field)
    {
        $out = array();
        if (is_scalar($field)) {
            $q = $orm->find_many();
            foreach ($q as $item) {
                $out[] = $item->get($field);
            }
        }
        return $out;
    }
}