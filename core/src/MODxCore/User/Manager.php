<?php namespace MODxCore\User;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 6:36
 */
class Manager{
    /**
     * Check for manager login session
     *
     * @return boolean
     */
    public static function checkSession() {
        if (isset ($_SESSION['mgrValidated'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns true if user has the currect permission
     *
     * @param string $pm Permission name
     * @return int
     */
    public static function hasPermission($pm) {
        $state= false;
        $pms= $_SESSION['mgrPermissions'];
        if ($pms)
            $state= ($pms[$pm] == 1);
        return $state;
    }
}