<?php namespace MODxCore;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 6:21
 */

class User{
    /**
     * Returns true if the current web user is a member the specified groups
     *
     * @param array $groupNames
     * @return boolean
     */
    public static function isMemberOfWebGroup($groupNames= array ()) {
        $modx = modx();
        if (!is_array($groupNames))
            return false;
        // check cache
        $grpNames= isset ($_SESSION['webUserGroupNames']) ? $_SESSION['webUserGroupNames'] : false;
        if (!is_array($grpNames)) {
            $tbl= $modx->getFullTableName("webgroup_names");
            $tbl2= $modx->getFullTableName("web_groups");
            $sql= "SELECT wgn.name
                    FROM $tbl wgn
                    INNER JOIN $tbl2 wg ON wg.webgroup=wgn.id AND wg.webuser='" . self::getLoginUserID() . "'";
            $grpNames= $modx->db->getColumn("name", $sql);
            // save to cache
            $_SESSION['webUserGroupNames']= $grpNames;
        }
        foreach ($groupNames as $k => $v)
            if (in_array(trim($v), $grpNames))
                return true;
        return false;
    }

    /**
     * Change current web user's password
     *
     * @todo Make password length configurable, allow rules for passwords and translation of messages
     * @param string $oldPwd
     * @param string $newPwd
     * @return string|boolean Returns true if successful, oterhwise return error
     *                        message
     */
    public static function changeWebUserPassword($oldPwd, $newPwd) {
        $modx = modx();
        $rt= false;
        if ($_SESSION["webValidated"] == 1) {
            $tbl= $modx->getFullTableName("web_users");
            $ds= $modx->db->query("SELECT `id`, `username`, `password` FROM $tbl WHERE `id`='" . self::getLoginUserID() . "'");
            $limit= $modx->db->getRecordCount($ds);
            if ($limit == 1) {
                $row= $modx->db->getRow($ds);
                if ($row["password"] == md5($oldPwd)) {
                    if (strlen($newPwd) < 6) {
                        return "Password is too short!";
                    }
                    elseif ($newPwd == "") {
                        return "You didn't specify a password for this user!";
                    } else {
                        $modx->db->query("UPDATE $tbl SET password = md5('" . $modx->db->escape($newPwd) . "') WHERE id='" . self::getLoginUserID() . "'");
                        // invoke OnWebChangePassword event
                        $modx->invokeEvent("OnWebChangePassword", array (
                            "userid" => $row["id"],
                            "username" => $row["username"],
                            "userpassword" => $newPwd
                        ));
                        return true;
                    }
                } else {
                    return "Incorrect password.";
                }
            }
        }
    }

    /**
     * Returns current user id.
     *
     * @param string $context. Default is an empty string which indicates the method should automatically pick 'web (frontend) or 'mgr' (backend)
     * @return string
     */
    public static function getLoginUserID($context= '') {
        $modx = modx();
        if ($context && isset ($_SESSION[$context . 'Validated'])) {
            return $_SESSION[$context . 'InternalKey'];
        }
        elseif ($modx->isFrontend() && isset ($_SESSION['webValidated'])) {
            return $_SESSION['webInternalKey'];
        }
        elseif ($modx->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return $_SESSION['mgrInternalKey'];
        }
    }

    /**
     * Returns current user name
     *
     * @param string $context. Default is an empty string which indicates the method should automatically pick 'web (frontend) or 'mgr' (backend)
     * @return string
     */
    public static function getLoginUserName($context= '') {
        $modx = modx();
        if (!empty($context) && isset ($_SESSION[$context . 'Validated'])) {
            return $_SESSION[$context . 'Shortname'];
        }
        elseif ($modx->isFrontend() && isset ($_SESSION['webValidated'])) {
            return $_SESSION['webShortname'];
        }
        elseif ($modx->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return $_SESSION['mgrShortname'];
        }
    }

    /**
     * Returns current login user type - web or manager
     *
     * @return string
     */
    public static function getLoginUserType() {
        $modx = modx();
        if ($modx->isFrontend() && isset ($_SESSION['webValidated'])) {
            return 'web';
        }
        elseif ($modx->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return 'manager';
        } else {
            return '';
        }
    }

    /**
     * Returns a user info record for the given manager user
     *
     * @param int $uid
     * @return boolean|string
     */
    public static function getUserInfo($uid) {
        $row = \MODxCore\Model\BManagerUser::filter('fullProfile', $uid);
        if(!empty($row)){
            $row = $row->as_array();
            if(empty($row["usertype"])){
                $row["usertype"]= "manager";
            }
        }else{
            $row = array();
        }
        return $row;
    }

    /**
     * Returns a record for the web user
     *
     * @param int $uid
     * @return boolean|string
     */
    public static function getWebUserInfo($uid) {
        $modx = modx();
        $sql= "
              SELECT wu.username, wu.password, wua.*
              FROM " . $modx->getFullTableName("web_users") . " wu
              INNER JOIN " . $modx->getFullTableName("web_user_attributes") . " wua ON wua.internalkey=wu.id
              WHERE wu.id='$uid'
              ";
        $rs= $modx->db->query($sql);
        $limit= $modx->db->getRecordCount($rs);
        if ($limit == 1) {
            $row= $modx->db->getRow($rs);
            if (!$row["usertype"])
                $row["usertype"]= "web";
            return $row;
        }
    }

    /**
     * Returns an array of document groups that current user is assigned to.
     * This function will first return the web user doc groups when running from
     * frontend otherwise it will return manager user's docgroup.
     *
     * @param boolean $resolveIds Set to true to return the document group names
     *                            Default: false
     * @return string|array
     */
    public static function getUserDocGroups($resolveIds= false) {
        $modx = modx();
        if ($modx->isFrontend() && isset ($_SESSION['webDocgroups']) && isset ($_SESSION['webValidated'])) {
            $dg= $_SESSION['webDocgroups'];
            $dgn= isset ($_SESSION['webDocgrpNames']) ? $_SESSION['webDocgrpNames'] : false;
        } else
            if ($modx->isBackend() && isset ($_SESSION['mgrDocgroups']) && isset ($_SESSION['mgrValidated'])) {
                $dg= $_SESSION['mgrDocgroups'];
                $dgn= isset ($_SESSION['mgrDocgrpNames']) ? $_SESSION['mgrDocgrpNames'] : false;
            } else {
                $dg= '';
            }
        if (!$resolveIds)
            return $dg;
        else
            if (is_array($dgn))
                return $dgn;
            else
                if (is_array($dg)) {
                    // resolve ids to names
                    $dgn= array ();
                    $tbl= $modx->getFullTableName("documentgroup_names");
                    $ds= $modx->db->query("SELECT name FROM $tbl WHERE id IN (" . implode(",", $dg) . ")");
                    while ($row= $modx->db->getRow($ds))
                        $dgn[count($dgn)]= $row['name'];
                    // cache docgroup names to session
                    if ($modx->isFrontend())
                        $_SESSION['webDocgrpNames']= $dgn;
                    else
                        $_SESSION['mgrDocgrpNames']= $dgn;
                    return $dgn;
                }
    }
}