<?php namespace MODxCore\Model;

class BManagerUser extends \MODxCore\Model{
    public static $_table = 'manager_users';

    public function attr() {
        return $this->belongs_to('\MODxCore\Model\BManagerUserAttr', 'id', 'internalkey');
    }

    /**
     * SELECT mu.username, mu.password, mua.* FROM `manager_users` `mu` INNER JOIN `user_attributes` `mua` ON `mua`.`internalkey` = `mu`.`id` WHERE `mu`.`id` = '?' LIMIT 1
     */
    public static function fullProfile(\ORMWrapper $orm, $uid, $noping = false){
        $out = $orm->tableAlias('mu')
            ->selectManyExpr('mu.username', 'mu.password', 'mua.*')
            ->innerJoin(
                \MODxCore\Model::getFullTableName('BManagerUserAttr'),
                array('mua.internalkey', '=', 'mu.id'),
                'mua'
            );

        switch(true){
            case is_array($uid):{
                $out = $out->whereIn('mu.id', $uid)
                    ->find_many();
                break;
            }
            case is_scalar($uid):{
                $out = $out->where('mu.id', $uid)
                    ->find_one();
                break;
            }
            default:{
                $out = null;
            }
        }

        if(empty($out) && $noping){
            $out = new \MODxCore\Lib\xNop();
        }

        return $out;
    }

    /**
     * SELECT * FROM `manager_users` WHERE `id` = '?' LIMIT 1
     * SELECT * FROM `user_attributes` WHERE `internalkey` = '?' LIMIT 1
     */
    public static function profile(\ORMWrapper $orm, $uid, $noping = false){
        switch(true){
            case is_array($uid):{
                $user = $orm->where_in('id', $uid)
                            ->find_many();
                if(!empty($user)){
                    foreach($user as $u){
                        $out[] = $u->attr()
                                    ->find_one();
                    }
                }
                break;
            }
            case is_scalar($uid):{
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
            $out = new \MODxCore\Lib\xNop();
        }

        return $out;
    }
}