<?php namespace MODxCore\Model;

class BManagerUserAttr extends \MODxCore\Model{
    public static $_table = 'user_attributes';

    public function user() {
        return $this->has_one('\MODxCore\Model\BManagerUser', 'id', 'internalKey');
    }
}