<?php namespace Bolmer\Model;

class BTv extends \Bolmer\Model
{
    public static $_table = 'site_tmplvars';

    public function category(){
        return $this->has_one('\Bolmer\Model\BCategory', 'id', 'category');
    }
}