<?php namespace Bolmer\Parser;

class Plugin
{
    /** @var \Bolmer\Service $_inj */
    private $_inj = null;

    /** @var \Bolmer\Core $_core */
    protected $_core = null;

    /**
     * Конструктор класса \Bolmer\Parser\Plugin
     *
     * @param \Pimple $inj коллекция зависимостей
     */
    public function __construct(\Pimple $inj)
    {
        $this->_inj = $inj;
        $this->_core = $inj['core'];
    }

    /**
     * Run a plugin
     *
     * @param string $pluginCode Code to run
     * @param array $params
     */
    public function evalPlugin($pluginCode, $params)
    {
        if ($pluginCode) {
            $etomite = $modx = $core = & $this->_core;
            $this->_core->event->params = & $params; // store params inside event object
            if (is_array($params)) {
                extract($params, EXTR_SKIP);
            }
            ob_start();
            eval($pluginCode);
            $msg = ob_get_contents();
            ob_end_clean();

            if ((0 < $this->_core->getConfig('error_reporting')) && $msg && isset($php_errormsg)) {
                $error_info = error_get_last();
                if ($this->_inj['debug']->detectError($error_info['type'])) {
                    extract($error_info);
                    $msg = ($msg === false) ? 'ob_get_contents() error' : $msg;
                    $result = $this->_core->messageQuit('PHP Parse Error', '', true, $type, $file, 'Plugin', $text, $line, $msg);
                    if ($this->_core->isBackend()) {
                        $this->_core->event->alert('An error occurred while loading. Please see the event log for more information.<p>' . $msg . '</p>');
                    }
                }
            } else {
                echo $msg;
            }
            unset($core->event->params);
        }
    }

    /**
     * Add an event listner to a plugin - only for use within the current execution cycle
     *
     * @param string $evtName
     * @param string $pluginName
     * @return boolean|int
     */
    public function addEventListener($evtName, $pluginName)
    {
        if (!$evtName || !$pluginName)
            return false;
        if (!array_key_exists($evtName, $this->_core->pluginEvent))
            $this->_core->pluginEvent[$evtName] = array();
        return array_push($this->_core->pluginEvent[$evtName], $pluginName); // return array count
    }

    /**
     * Remove event listner - only for use within the current execution cycle
     *
     * @param string $evtName
     * @return boolean
     */
    public function removeEventListener($evtName)
    {
        if (!$evtName)
            return false;
        unset ($this->_core->pluginEvent[$evtName]);
    }

    /**
     * Remove all event listners - only for use within the current execution cycle
     */
    public function removeAllEventListener()
    {
        unset ($this->_core->pluginEvent);
        $this->_core->pluginEvent = array();
    }

    /**
     * Invoke an event.
     *
     * @param string $evtName
     * @param array $extParams Parameters available to plugins. Each array key will be the PHP variable name, and the array value will be the variable value.
     * @return boolean|array
     */
    public function invokeEvent($evtName, $extParams = array())
    {
        if (!$evtName)
            return false;
        if (!isset ($this->_core->pluginEvent[$evtName]))
            return false;
        $el = $this->_core->pluginEvent[$evtName];
        $results = array();
        $numEvents = count($el);
        if ($numEvents > 0)
            for ($i = 0; $i < $numEvents; $i++) { // start for loop
                if ($this->_core->dumpPlugins == 1) $eventtime = $this->_core->getMicroTime();
                $pluginName = $el[$i];
                $pluginName = stripslashes($pluginName);
                // reset event object
                $e = & $this->_core->Event;
                $e->_resetEventObject();
                $e->name = $evtName;
                $e->activePlugin = $pluginName;

                // get plugin code
                if (isset ($this->_core->pluginCache[$pluginName])) {
                    $pluginCode = $this->_core->pluginCache[$pluginName];
                    $pluginProperties = isset($this->_core->pluginCache[$pluginName . "Props"]) ? $this->_core->pluginCache[$pluginName . "Props"] : '';
                } else {
                    $row = \Bolmer\Model\BPlugin::where('disabled', 0)->filter('getItem', $pluginName, true);
                    $pluginCode = $this->_core->pluginCache[$row['name']] = getkey($row, 'plugincode', 'return false;');
                    $pluginProperties = $this->_core->pluginCache[$row['name'] . "Props"] = getkey($row, 'properties');
                }

                // load default params/properties
                $parameter = $this->_inj['parser']->parseProperties($pluginProperties);
                if (!empty ($extParams))
                    $parameter = array_merge($parameter, $extParams);

                // eval plugin
                $this->evalPlugin($pluginCode, $parameter);
                if ($this->_core->dumpPlugins == 1) {
                    $eventtime = $this->_core->getMicroTime() - $eventtime;
                    $this->_core->pluginsCode .= '<fieldset><legend><b>' . $evtName . ' / ' . $pluginName . '</b> (' . sprintf('%2.2f ms', $eventtime * 1000) . ')</legend>';
                    foreach ($parameter as $k => $v) $this->_core->pluginsCode .= $k . ' => ' . print_r($v, true) . '<br>';
                    $this->_core->pluginsCode .= '</fieldset><br />';
                    $this->_core->pluginsTime["$evtName / $pluginName"] += $eventtime;
                }
                if ($e->_output != "")
                    $results[] = $e->_output;
                if ($e->_propagate != true)
                    break;
            }
        $e->activePlugin = "";
        return $results;
    }
}