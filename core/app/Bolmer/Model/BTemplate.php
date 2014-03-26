<?php namespace Bolmer\Model;

class BTemplate extends \Bolmer\Model
{
    public static $_table = 'site_templates';

    public function doc(){
        return $this->belongs_to('\Bolmer\Model\BDoc', 'id', 'template');
    }

    public function category(){
        return $this->has_one('\Bolmer\Model\BCategory', 'id', 'category');
    }

    /**
     * @TODO: Непонятный тип шаблона
     */

    /**
     * @TODO: Неиспользуемая иконка шаблона
     */
}