<?php namespace Bolmer\Model;

use Granada\Orm\Wrapper as ORMWrapper;

class BManagerUser extends \Bolmer\Model{
    public static $_table = 'manager_users';

    public function attr() {
        return $this->belongs_to('\Bolmer\Model\BManagerUserAttr', 'id', 'internalkey');
    }

    /**
     * SELECT mu.username, mu.password, mua.* FROM `manager_users` `mu` INNER JOIN `user_attributes` `mua` ON `mua`.`internalkey` = `mu`.`id` WHERE `mu`.`id` = '?' LIMIT 1
     */
    public static function fullProfile(ORMWrapper $orm, $uid, $noping = false){
        $out = $orm->table_alias('mu')
            ->select_many_expr('mu.username', 'mu.password', 'mua.*')
            ->inner_join(
                \Bolmer\Model::getFullTableName('BManagerUserAttr'),
                array('mua.internalkey', '=', 'mu.id'),
                'mua'
            );

        switch(true){
            case (!empty($uid) && is_array($uid)):{
                $out = $out->where_in('mu.id', array_values($uid))
                    ->find_many();
                break;
            }
            case (!empty($uid) && is_scalar($uid)):{
                $out = $out->where('mu.id', $uid)
                    ->find_one();
                break;
            }
            default:{
                $out = null;
            }
        }

        if(empty($out) && $noping){
            $out = new \Bolmer\Helper\xNop();
        }

        return $out;
    }

    /**
     * SELECT * FROM `manager_users` WHERE `id` = '?' LIMIT 1
     * SELECT * FROM `user_attributes` WHERE `internalkey` = '?' LIMIT 1
     */
    public static function profile(ORMWrapper $orm, $uid, $noping = false){
        switch(true){
            case (!empty($uid) && is_array($uid)):{
                $user = $orm->where_in('id', array_values($uid))
                            ->find_many();
                if(!empty($user)){
                    foreach($user as $u){
                        $out[] = $u->attr()
                                    ->find_one();
                    }
                }
                break;
            }
            case (!empty($uid) && is_scalar($uid)):{
                $user = $orm->find_one($uid);
                if(!empty($user)){
                    $out = $user->attr()
                                ->find_one();
                }
                break;
            }
            default:{
                $out = null;
            }
        }

        if(empty($out) && $noping){
            $out = new \Bolmer\Helper\xNop();
        }

        return $out;
    }
}