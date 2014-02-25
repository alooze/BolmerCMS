<?php namespace Bolmer\Model;

class BPlugin extends \Bolmer\Model
{
    /** @var string $UKey поле с уникальным значением в таблице */
    public static $UKey = 'name';

    public static $_table = 'site_plugins';

    public function category(){
        return $this->has_one('\Bolmer\Model\BCategory', 'id', 'category');
    }
}