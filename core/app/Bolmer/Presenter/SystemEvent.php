<?php namespace Bolmer\Presenter;

class SystemEvent
{
    var $name;
    var $_propagate;
    var $_output;
    var $activated;
    var $activePlugin;

    protected $_eventService = array(
        1 => "Parser Service Events",
        2 => "Manager Access Events",
        3 => "Web Access Service Events",
        4 => "Cache Service Events",
        5 => "Template Service Events",
        6 => "User Defined Events"
    );

    /**
     * @param string $name Name of the event
     */
    function __construct($name = "")
    {
        $this->_resetEventObject();
        $this->name = $name;
    }

    /**
     * Получение имени группы событий плагинов
     * @TODO: Переписать метод для работы с новой таблицей
     *
     * @param int $id ID События
     * @param $default Имя группы по умолчанию
     * @return string
     */
    public function getEventService($id, $default){
        /**
         * $out = $default;
         * $service = \Bolmer\Model\BEventService::find_one($id);
         * if(!empty($service)){
         *      $out = $service->name;
         * }
         * return $out;
         */
        return getkey($this->_eventService, $id, $default);
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
            if ($this->name && $this->activePlugin) {
                $title = "<div><b>" . $this->activePlugin . "</b> - <span style='color:maroon;'>" . $this->name . "</span></div>";
            } else {
                $title = '';
            }
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