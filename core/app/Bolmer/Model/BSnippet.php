<?php namespace Bolmer\Model;

class BSnippet extends \Bolmer\Model
{
    /** @var string $UKey поле с уникальным значением в таблице */
    public static $UKey = 'name';

    public static $_table = 'site_snippets';
}