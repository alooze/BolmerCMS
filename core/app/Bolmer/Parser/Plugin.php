<?php namespace Bolmer\Parser;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 23:03
 */

class Plugin{
    /** @var \Bolmer\Pimple $_inj */
    private $_inj = null;

    public function __construct(\Pimple $inj){
        $this->_inj= $inj;
    }

    /**
     * Run a plugin
     *
     * @param string $pluginCode Code to run
     * @param array $params
     */
    function evalPlugin($pluginCode, $params) {
        if($pluginCode){
            $etomite = $modx = &$this->_inj['modx'];
            $this->_inj['modx']->event->params = & $params; // store params inside event object
            if (is_array($params)) {
                extract($params, EXTR_SKIP);
            }
            ob_start();
            eval($pluginCode);
            $msg = ob_get_contents();
            ob_end_clean();

            if ((0 < $this->_inj['modx']->getConfig('error_reporting')) && $msg && isset($php_errormsg)) {
                $error_info = error_get_last();
                if ($this->_inj['debug']->detectError($error_info['type'])) {
                    extract($error_info);
                    $msg = ($msg === false) ? 'ob_get_contents() error' : $msg;
                    $result = $this->_inj['modx']->messageQuit('PHP Parse Error', '', true, $type, $file, 'Plugin', $text, $line, $msg);
                    if ($this->_inj['modx']->isBackend()) {
                        $this->_inj['modx']->event->alert('An error occurred while loading. Please see the event log for more information.<p>' . $msg . '</p>');
                    }
                }
            } else {
                echo $msg;
            }
            unset($modx->event->params);
        }
    }

    /**
     * Add an event listner to a plugin - only for use within the current execution cycle
     *
     * @param string $evtName
     * @param string $pluginName
     * @return boolean|int
     */
    function addEventListener($evtName, $pluginName) {
        if (!$evtName || !$pluginName)
            return false;
        if (!array_key_exists($evtName,$this->_inj['modx']->pluginEvent))
            $this->_inj['modx']->pluginEvent[$evtName] = array();
        return array_push($this->_inj['modx']->pluginEvent[$evtName], $pluginName); // return array count
    }

    /**
     * Remove event listner - only for use within the current execution cycle
     *
     * @param string $evtName
     * @return boolean
     */
    function removeEventListener($evtName) {
        if (!$evtName)
            return false;
        unset ($this->_inj['modx']->pluginEvent[$evtName]);
    }
    /**
     * Remove all event listners - only for use within the current execution cycle
     */
    function removeAllEventListener() {
        unset ($this->_inj['modx']->pluginEvent);
        $this->_inj['modx']->pluginEvent= array ();
    }
    /**
     * Invoke an event.
     *
     * @param string $evtName
     * @param array $extParams Parameters available to plugins. Each array key will be the PHP variable name, and the array value will be the variable value.
     * @return boolean|array
     */
    function invokeEvent($evtName, $extParams= array ()) {
        if (!$evtName)
            return false;
        if (!isset ($this->_inj['modx']->pluginEvent[$evtName]))
            return false;
        $el= $this->_inj['modx']->pluginEvent[$evtName];
        $results= array ();
        $numEvents= count($el);
        if ($numEvents > 0)
            for ($i= 0; $i < $numEvents; $i++) { // start for loop
                if ($this->_inj['modx']->dumpPlugins == 1) $eventtime = $this->_inj['modx']->getMicroTime();
                $pluginName= $el[$i];
                $pluginName = stripslashes($pluginName);
                // reset event object
                $e= & $this->_inj['modx']->Event;
                $e->_resetEventObject();
                $e->name= $evtName;
                $e->activePlugin= $pluginName;

                // get plugin code
                if (isset ($this->_inj['modx']->pluginCache[$pluginName])) {
                    $pluginCode= $this->_inj['modx']->pluginCache[$pluginName];
                    $pluginProperties= isset($this->_inj['modx']->pluginCache[$pluginName . "Props"]) ? $this->_inj['modx']->pluginCache[$pluginName . "Props"] : '';
                } else {
                    $sql= "SELECT `name`, `plugincode`, `properties` FROM " . $this->_inj['modx']->getFullTableName("site_plugins") . " WHERE `name`='" . $pluginName . "' AND `disabled`=0;";
                    $result= $this->_inj['db']->query($sql);
                    if ($this->_inj['db']->getRecordCount($result) == 1) {
                        $row= $this->_inj['db']->getRow($result);
                        $pluginCode= $this->_inj['modx']->pluginCache[$row['name']]= $row['plugincode'];
                        $pluginProperties= $this->_inj['modx']->pluginCache[$row['name'] . "Props"]= $row['properties'];
                    } else {
                        $pluginCode= $this->_inj['modx']->pluginCache[$pluginName]= "return false;";
                        $pluginProperties= '';
                    }
                }

                // load default params/properties
                $parameter= \Bolmer\Parser::parseProperties($pluginProperties);
                if (!empty ($extParams))
                    $parameter= array_merge($parameter, $extParams);

                // eval plugin
                $this->evalPlugin($pluginCode, $parameter);
                if ($this->dumpPlugins == 1) {
                    $eventtime = $this->_inj['modx']->getMicroTime() - $eventtime;
                    $this->pluginsCode .= '<fieldset><legend><b>' . $evtName . ' / ' . $pluginName . '</b> ('.sprintf('%2.2f ms', $eventtime*1000).')</legend>';
                    foreach ($parameter as $k=>$v) $this->pluginsCode .= $k . ' => ' . print_r($v, true) . '<br>';
                    $this->pluginsCode .= '</fieldset><br />';
                    $this->pluginsTime["$evtName / $pluginName"] += $eventtime;
                }
                if ($e->_output != "")
                    $results[]= $e->_output;
                if ($e->_propagate != true)
                    break;
            }
        $e->activePlugin= "";
        return $results;
    }
}