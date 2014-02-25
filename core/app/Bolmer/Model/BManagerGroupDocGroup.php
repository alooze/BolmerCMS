<?php namespace Bolmer\Model;

class BManagerGroupDocGroup extends \Bolmer\Model
{
    public static $_table = 'membergroup_access';

    public function docGroup() {
        return $this->belongs_to('\Bolmer\Model\BDocGroup', 'documentgroup', 'id');
    }

    public function mgrUserGroup() {
        return $this->belongs_to('\Bolmer\Model\BManagerGroup', 'membergroup', 'id');
    }
}