<?php namespace Bolmer\Operations\User;

class Manager
{
    /** @var \Bolmer\Pimple $_inj коллекция зависимостей */
    private $_inj = null;

    /** @var \Bolmer\Core $_core */
    protected $_core = null;

    /**
     * Конструктор класса \Bolmer\Operations\User\Manager
     *
     * @param \Pimple $inj коллекция зависимостей
     */
    public function __construct(\Pimple $inj)
    {
        $this->_inj = $inj;
        $this->_core = $inj['core'];
    }

    /**
     * Check for manager login session
     *
     * @return boolean
     */
    public function checkSession()
    {
        if (isset ($_SESSION['mgrValidated'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks, if a the result is a preview
     *
     * @return boolean
     */
    public function checkPreview()
    {
        if ($this->checkSession() == true) {
            if (isset ($_REQUEST['z']) && $_REQUEST['z'] == 'manprev') {
                return true;
            } else {
                return false;
            }
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
    public function hasPermission($pm)
    {
        $state = false;
        $pms = $_SESSION['mgrPermissions'];
        if ($pms)
            $state = ($pms[$pm] == 1);
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
     * @return bool эта функция всегда возвращает true
     */
    public function sendAlert($type, $to, $from, $subject, $msg, $private = 0)
    {
        $private = ($private) ? 1 : 0;
        if (!is_numeric($to)) {
            // Query for the To ID
            $sql = "SELECT id FROM " . $this->_core->getTableName("BManagerUser") . " WHERE username='$to';";
            $rs = $this->_core->db->query($sql);
            if ($this->_core->db->getRecordCount($rs)) {
                $rs = $this->_core->db->getRow($rs);
                $to = $rs['id'];
            }
        }
        if (!is_numeric($from)) {
            // Query for the From ID
            $sql = "SELECT id FROM " . $this->_core->getTableName("BManagerUser") . " WHERE username='$from';";
            $rs = $this->_core->db->query($sql);
            if ($this->_core->db->getRecordCount($rs)) {
                $rs = $this->_core->db->getRow($rs);
                $from = $rs['id'];
            }
        }
        // insert a new message into user_messages
        $sql = "INSERT INTO " . $this->_core->getTableName("BManagerUserMessage") . " ( id , type , subject , message , sender , recipient , private , postdate , messageread ) VALUES ( '', '$type', '$subject', '$msg', '$from', '$to', '$private', '" . time() . "', '0' );";
        $rs = $this->_core->db->query($sql);
        return true;
    }
}