<?php namespace Bolmer\Model;

class BDocMetaList extends \Bolmer\Model
{
    public static $_table = 'site_content_metatags';

    public function meta() {
        return $this->belongs_to('\Bolmer\Model\BMeta', 'metatag_id', 'id');
    }

    public function doc() {
        return $this->belongs_to('\Bolmer\Model\BDoc', 'content_id', 'id');
    }
}