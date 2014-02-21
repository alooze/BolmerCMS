<?php namespace Bolmer\Operations;

class User
{
    /** @var \Bolmer\Pimple $_inj */
    private $_inj = null;

    /** @var \Bolmer\Core $_core */
    protected $_core = null;

    public function __construct(\Pimple $inj)
    {
        $this->_inj = $inj;
        $this->_core = $inj['core'];
    }

    /**
     * Returns true if the current web user is a member the specified groups
     *
     * @param array $groupNames
     * @return boolean
     */
    public function isMemberOfWebGroup($groupNames = array())
    {
        if (!is_array($groupNames))
            return false;
        // check cache
        $grpNames = isset ($_SESSION['webUserGroupNames']) ? $_SESSION['webUserGroupNames'] : false;
        if (!is_array($grpNames)) {
            $tbl = $this->_core->getTableName("BWebGroup");
            $tbl2 = $this->_core->getTableName("BWebUserGroupList");
            $sql = "SELECT wgn.name
                    FROM $tbl wgn
                    INNER JOIN $tbl2 wg ON wg.webgroup=wgn.id AND wg.webuser='" . $this->getLoginUserID() . "'";
            $grpNames = $this->_core->db->getColumn("name", $sql);
            // save to cache
            $_SESSION['webUserGroupNames'] = $grpNames;
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
    public function changeWebUserPassword($oldPwd, $newPwd)
    {
        $rt = false;
        if ($_SESSION["webValidated"] == 1) {
            $tbl = $this->_core->getTableName("BWebUser");
            $ds = $this->_core->db->query("SELECT `id`, `username`, `password` FROM $tbl WHERE `id`='" . $this->getLoginUserID() . "'");
            $limit = $this->_core->db->getRecordCount($ds);
            if ($limit == 1) {
                $row = $this->_core->db->getRow($ds);
                if ($row["password"] == md5($oldPwd)) {
                    if (strlen($newPwd) < 6) {
                        return "Password is too short!";
                    } elseif ($newPwd == "") {
                        return "You didn't specify a password for this user!";
                    } else {
                        $this->_core->db->query("UPDATE $tbl SET password = md5('" . $this->_core->db->escape($newPwd) . "') WHERE id='" . $this->getLoginUserID() . "'");
                        // invoke OnWebChangePassword event
                        $this->_core->invokeEvent("OnWebChangePassword", array(
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
     * @param string $context . Default is an empty string which indicates the method should automatically pick 'web (frontend) or 'mgr' (backend)
     * @return string
     */
    public function getLoginUserID($context = '')
    {
        if ($context && isset ($_SESSION[$context . 'Validated'])) {
            return $_SESSION[$context . 'InternalKey'];
        } elseif ($this->_core->isFrontend() && isset ($_SESSION['webValidated'])) {
            return $_SESSION['webInternalKey'];
        } elseif ($this->_core->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return $_SESSION['mgrInternalKey'];
        }
    }

    /**
     * Returns current user name
     *
     * @param string $context . Default is an empty string which indicates the method should automatically pick 'web (frontend) or 'mgr' (backend)
     * @return string
     */
    public function getLoginUserName($context = '')
    {
        if (!empty($context) && isset ($_SESSION[$context . 'Validated'])) {
            return $_SESSION[$context . 'Shortname'];
        } elseif ($this->_core->isFrontend() && isset ($_SESSION['webValidated'])) {
            return $_SESSION['webShortname'];
        } elseif ($this->_core->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return $_SESSION['mgrShortname'];
        }
    }

    /**
     * Returns current login user type - web or manager
     *
     * @return string
     */
    public function getLoginUserType()
    {
        if ($this->_core->isFrontend() && isset ($_SESSION['webValidated'])) {
            return 'web';
        } elseif ($this->_core->isBackend() && isset ($_SESSION['mgrValidated'])) {
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
    public function getUserInfo($uid)
    {
        $row = \Bolmer\Model\BManagerUser::filter('fullProfile', $uid);
        if (!empty($row)) {
            $row = $row->as_array();
            if (empty($row["usertype"])) {
                $row["usertype"] = "manager";
            }
        } else {
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
    public function getWebUserInfo($uid)
    {
        $sql = "
              SELECT wu.username, wu.password, wua.*
              FROM " . $this->_core->getTableName("BWebUser") . " wu
              INNER JOIN " . $this->_core->getTableName("BWebUserAttr") . " wua ON wua.internalkey=wu.id
              WHERE wu.id='$uid'
              ";
        $rs = $this->_core->db->query($sql);
        $limit = $this->_core->db->getRecordCount($rs);
        if ($limit == 1) {
            $row = $this->_core->db->getRow($rs);
            if (!$row["usertype"])
                $row["usertype"] = "web";
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
    public function getUserDocGroups($resolveIds = false)
    {
        if ($this->_core->isFrontend() && isset ($_SESSION['webDocgroups']) && isset ($_SESSION['webValidated'])) {
            $dg = $_SESSION['webDocgroups'];
            $dgn = isset ($_SESSION['webDocgrpNames']) ? $_SESSION['webDocgrpNames'] : false;
        } else
            if ($this->_core->isBackend() && isset ($_SESSION['mgrDocgroups']) && isset ($_SESSION['mgrValidated'])) {
                $dg = $_SESSION['mgrDocgroups'];
                $dgn = isset ($_SESSION['mgrDocgrpNames']) ? $_SESSION['mgrDocgrpNames'] : false;
            } else {
                $dg = '';
            }
        if (!$resolveIds)
            return $dg;
        else
            if (is_array($dgn))
                return $dgn;
            else
                if (is_array($dg)) {
                    // resolve ids to names
                    $dgn = array();
                    $tbl = $this->_core->getTableName("BDocGroup");
                    $ds = $this->_core->db->query("SELECT name FROM $tbl WHERE id IN (" . implode(",", $dg) . ")");
                    while ($row = $this->_core->db->getRow($ds))
                        $dgn[count($dgn)] = $row['name'];
                    // cache docgroup names to session
                    if ($this->_core->isFrontend())
                        $_SESSION['webDocgrpNames'] = $dgn;
                    else
                        $_SESSION['mgrDocgrpNames'] = $dgn;
                    return $dgn;
                }
    }

    /**
     * Returns user login information, as loggedIn (true or false), internal key, username and usertype (web or manager).
     *
     * @return boolean|array
     */
    function userLoggedIn()
    {
        $userdetails = array();
        if ($this->_core->isFrontend() && isset ($_SESSION['webValidated'])) {
            // web user
            $userdetails['loggedIn'] = true;
            $userdetails['id'] = $_SESSION['webInternalKey'];
            $userdetails['username'] = $_SESSION['webShortname'];
            $userdetails['usertype'] = 'web'; // added by Raymond
            return $userdetails;
        } else
            if ($this->_core->isBackend() && isset ($_SESSION['mgrValidated'])) {
                // manager user
                $userdetails['loggedIn'] = true;
                $userdetails['id'] = $_SESSION['mgrInternalKey'];
                $userdetails['username'] = $_SESSION['mgrShortname'];
                $userdetails['usertype'] = 'manager'; // added by Raymond
                return $userdetails;
            } else {
                return false;
            }
    }

    /**
     * Get data from phpSniff
     *
     * @category API-Function
     * @return array
     */
    function getUserData()
    {
        $client['ip'] = $_SERVER['REMOTE_ADDR'];
        $client['ua'] = $_SERVER['HTTP_USER_AGENT'];
        return $client;
    }
}