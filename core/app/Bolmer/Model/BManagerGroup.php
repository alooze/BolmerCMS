<?php namespace Bolmer\Model;

/**
 * Class BManagerGroup
 * Группы менеджеров
 *
 * @package Bolmer\Model
 */
class BManagerGroup extends \Bolmer\Model
{
    public static $_table = 'membergroup_names';

    public function docGroup() {
        return $this->has_many('\Bolmer\Model\BManagerGroupDocGroup', 'membergroup', 'id');
    }
}