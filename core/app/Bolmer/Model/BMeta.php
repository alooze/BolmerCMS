<?php namespace Bolmer\Model;

class BMeta extends \Bolmer\Model
{
    public static $_table = 'site_metatags';

    public function docList() {
        return $this->has_many('\Bolmer\Model\BDocMetaList', 'metatag_id', 'id');
    }
}