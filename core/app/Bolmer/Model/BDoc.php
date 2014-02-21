<?php namespace Bolmer\Model;

/**
 * \Bolmer\Model::factory('\Bolmer\Model\BDoc')->select_many_expr('id','pagetitle')->where_raw('id!=1')->order_by_desc('id')->limit(2)->find_array();
 * \Bolmer\Model\BDoc::select_many_expr('id','pagetitle')->where_raw('id!=1')->order_by_desc('id')->limit(2)->find_array();
 */
class BDoc extends \Bolmer\Model
{
    public static $_table = 'site_content';

    /**
     * \Bolmer\Model::factory('\Bolmer\Model\BDoc')->filter('childrens', 2)->find_array();
     * \Bolmer\Model\BDoc::filter('childrens', 2)->find_array();
     */
    public function childrens($orm, $id)
    {
        return $orm->where_equal('parent', $id);
    }
}