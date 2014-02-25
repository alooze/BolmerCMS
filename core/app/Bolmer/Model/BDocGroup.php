<?php namespace Bolmer\Model;

/**
 * Class BDocGroup
 * Группы ресурсов
 *
 * @package Bolmer\Model
 */
class BDocGroup extends \Bolmer\Model
{
    public static $_table = 'documentgroup_names';

    public function webUserGroup() {
        return $this->has_many('\Bolmer\Model\BWebUserGroupDocGroup', 'documentgroup', 'id');
    }
}