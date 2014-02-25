<?php namespace Bolmer\Model;

class BActiveManager extends \Bolmer\Model
{
    public static $_table = 'active_users';
    protected static $_id_column = 'internalKey';

    public function user(){
        return $this->has_one('\Bolmer\Model\BManagerUser', 'id', 'internalKey');
    }

    public function profile(){
        return $this->has_one('\Bolmer\Model\BManagerUserAttr', 'internalKey', 'internalKey');
    }
}