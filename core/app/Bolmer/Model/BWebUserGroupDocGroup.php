<?php namespace Bolmer\Model;

class BWebUserGroupDocGroup extends \Bolmer\Model
{
    public static $_table = 'webgroup_access';

    public function docGroup() {
        return $this->belongs_to('\Bolmer\Model\BDocGroup', 'documentgroup', 'id');
    }

    public function webUserGroup() {
        return $this->belongs_to('\Bolmer\Model\BWebGroup', 'webgroup', 'id');
    }
}