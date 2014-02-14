<?php namespace MODxCore\Model;

/**
 * \MODxCore\Db\ORM::for_table('modx_site_content')->select_many_expr('id','pagetitle')->where_raw('id!=1')->order_by_desc('id')->limit(2)->find_array();
 * \MODxCore\Model::factory('\MODxCore\Model\SiteContent')->select_many_expr('id','pagetitle')->where_raw('id!=1')->order_by_desc('id')->limit(2)->find_array();
 * \MODxCore\Model\SiteContent::select_many_expr('id','pagetitle')->where_raw('id!=1')->order_by_desc('id')->limit(2)->find_array();
 */
class SiteContent extends \MODxCore\Model{
    public static $_table = 'site_content';

    /**
     * \MODxCore\Model::factory('\MODxCore\Model\SiteContent')->filter('childrens', 2)->find_array();
     * \MODxCore\Model\SiteContent::filter('childrens', 2)->find_array();
     */
    public function childrens($orm, $id){
        return $orm->where_equal('parent', $id);
    }
}