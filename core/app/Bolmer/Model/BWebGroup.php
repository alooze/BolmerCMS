<?php namespace Bolmer\Model;

/**
 * Class BWebGroup
 * Группы веб-пользователей
 *
 * @package Bolmer\Model
 */
class BWebGroup extends \Bolmer\Model
{
    public static $_table = 'webgroup_names';

    public function docGroup() {
        return $this->has_many('\Bolmer\Model\BWebUserGroupDocGroup', 'webgroup', 'id');
    }
}