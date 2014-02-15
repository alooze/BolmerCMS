<?php namespace Bolmer\Operations\User;
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

    /**
     * Sends a message to a user's message box.
     *
     * @param string $type Type of the message
     * @param string $to The recipient of the message
     * @param string $from The sender of the message
     * @param string $subject The subject of the message
     * @param string $msg The message body
     * @param int $private Whether it is a private message, or not
     *                     Default : 0
     */
    public static function sendAlert($type, $to, $from, $subject, $msg, $private= 0) {
        $private= ($private) ? 1 : 0;
        $modx = modx();
        if (!is_numeric($to)) {
            // Query for the To ID
            $sql= "SELECT id FROM " . $modx->getFullTableName("manager_users") . " WHERE username='$to';";
            $rs= $modx->db->query($sql);
            if ($modx->db->getRecordCount($rs)) {
                $rs= $modx->db->getRow($rs);
                $to= $rs['id'];
            }
        }
        if (!is_numeric($from)) {
            // Query for the From ID
            $sql= "SELECT id FROM " . $modx->getFullTableName("manager_users") . " WHERE username='$from';";
            $rs= $modx->db->query($sql);
            if ($modx->db->getRecordCount($rs)) {
                $rs= $modx->db->getRow($rs);
                $from= $rs['id'];
            }
        }
        // insert a new message into user_messages
        $sql= "INSERT INTO " . $modx->getFullTableName("user_messages") . " ( id , type , subject , message , sender , recipient , private , postdate , messageread ) VALUES ( '', '$type', '$subject', '$msg', '$from', '$to', '$private', '" . time() . "', '0' );";
        $rs= $modx->db->query($sql);
        return true;
    }
}