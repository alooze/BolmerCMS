<?php namespace Bolmer\Model;

class BChunk extends \Bolmer\Model
{
    /** @var string $UKey поле с уникальным значением в таблице */
    public static $UKey = 'name';

    public static $_table = 'site_htmlsnippets';

    public function category(){
        return $this->has_one('\Bolmer\Model\BCategory', 'id', 'category');
    }
}