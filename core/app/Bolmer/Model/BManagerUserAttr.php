<?php namespace Bolmer\Model;

class BManagerUserAttr extends \Bolmer\Model
{
    public static $_table = 'user_attributes';

    public function user()
    {
        return $this->has_one('\Bolmer\Model\BManagerUser', 'id', 'internalKey');
    }


    /**
     * @TODO: Связь с ролью
     */
}