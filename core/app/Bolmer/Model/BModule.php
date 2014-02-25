<?php namespace Bolmer\Model;

class BModule extends \Bolmer\Model
{
    public static $_table = 'site_modules';

    public function category(){
        return $this->has_one('\Bolmer\Model\BCategory', 'id', 'category');
    }
}