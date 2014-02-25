<?php namespace Bolmer\Model;

class BDocGroupList extends \Bolmer\Model
{
    public static $_table = 'document_groups';

    public function group() {
        return $this->belongs_to('\Bolmer\Model\BDocGroup', 'document_group', 'id');
    }

    public function doc() {
        return $this->belongs_to('\Bolmer\Model\BDoc', 'document', 'id');
    }

    public function webUserGroup() {
        return $this->has_many('\Bolmer\Model\BWebUserGroupDocGroup', 'documentgroup', 'document_group');
    }

    public function mgrUserGroup() {
        return $this->has_many('\Bolmer\Model\BManagerGroupDocGroup', 'documentgroup', 'document_group');
    }
}