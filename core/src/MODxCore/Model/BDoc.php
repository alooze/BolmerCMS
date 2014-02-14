<?php namespace MODxCore\Model;

/**
 * \MODxCore\Model::factory('\MODxCore\Model\BDoc')->select_many_expr('id','pagetitle')->where_raw('id!=1')->order_by_desc('id')->limit(2)->find_array();
 * \MODxCore\Model\BDoc::select_many_expr('id','pagetitle')->where_raw('id!=1')->order_by_desc('id')->limit(2)->find_array();
 */
class BDoc extends \MODxCore\Model{
    public static $_table = 'site_content';

    /**
     * \MODxCore\Model::factory('\MODxCore\Model\BDoc')->filter('childrens', 2)->find_array();
     * \MODxCore\Model\BDoc::filter('childrens', 2)->find_array();
     */
    public function childrens($orm, $id){
        return $orm->where_equal('parent', $id);
    }
}