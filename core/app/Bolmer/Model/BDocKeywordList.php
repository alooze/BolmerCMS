<?php namespace Bolmer\Model;

class BDocKeywordList extends \Bolmer\Model
{
    public static $_table = 'keyword_xref';

    public function key() {
        return $this->belongs_to('\Bolmer\Model\BKeyword', 'keyword_id', 'id');
    }

    public function doc() {
        return $this->belongs_to('\Bolmer\Model\BDoc', 'content_id', 'id');
    }
}