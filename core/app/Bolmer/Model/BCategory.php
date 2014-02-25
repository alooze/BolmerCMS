<?php namespace Bolmer\Model;

class BCategory extends \Bolmer\Model
{
    public static $_table = 'categories';

    public function template(){
        return $this->belongs_to('\Bolmer\Model\BTemplate', 'id', 'category');
    }

    public function chunk(){
        return $this->belongs_to('\Bolmer\Model\BChunk', 'id', 'category');
    }

    public function module(){
        return $this->belongs_to('\Bolmer\Model\BModule', 'id', 'category');
    }

    public function plugin(){
        return $this->belongs_to('\Bolmer\Model\BPlugin', 'id', 'category');
    }

    public function snippet(){
        return $this->belongs_to('\Bolmer\Model\BSnippet', 'id', 'category');
    }

    public function tv(){
        return $this->belongs_to('\Bolmer\Model\BTv', 'id', 'category');
    }
}