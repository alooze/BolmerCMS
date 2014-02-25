<?php namespace Bolmer\Model;

class BKeyword extends \Bolmer\Model
{
    public static $_table = 'site_keywords';

    public function docList() {
        return $this->has_many('\Bolmer\Model\BDocKeywordList', 'keyword_id', 'id');
    }
}