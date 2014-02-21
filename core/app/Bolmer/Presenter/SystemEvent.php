<?php namespace Bolmer\Presenter;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 5:00
 */

class SystemEvent
{
    var $name;
    var $_propagate;
    var $_output;
    var $activated;
    var $activePlugin;

    /**
     * @param string $name Name of the event
     */
    function __construct($name = "")
    {
        $this->_resetEventObject();
        $this->name = $name;
    }

    /**
     * Display a message to the user
     *
     * @global array $SystemAlertMsgQueque
     * @param string $msg The message
     */
    public function alert($msg)
    {
        global $SystemAlertMsgQueque;
        if ($msg == "")
            return;
        if (is_array($SystemAlertMsgQueque)) {
            if ($this->name && $this->activePlugin)
                $title = "<div><b>" . $this->activePlugin . "</b> - <span style='color:maroon;'>" . $this->name . "</span></div>";
            $SystemAlertMsgQueque[] = "$title<div style='margin-left:10px;margin-top:3px;'>$msg</div>";
        }
    }

    /**
     * Output
     *
     * @param string $msg
     */
    public function output($msg)
    {
        $this->_output .= $msg;
    }

    /**
     * Stop event propogation
     */
    public function stopPropagation()
    {
        $this->_propagate = false;
    }

    public function _resetEventObject()
    {
        unset ($this->returnedValues);
        $this->name = "";
        $this->_output = "";
        $this->_propagate = true;
        $this->activated = false;
    }
}