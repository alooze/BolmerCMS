<?php namespace Bolmer\Model;

class BDocGroupList extends \Bolmer\Model
{
    public static $_table = 'document_groups';

    public function docGroup() {
        return $this->belongs_to('\Bolmer\Model\BDocGroup', 'document_group', 'id');
    }
}