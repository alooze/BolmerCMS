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

    /** @var \Bolmer\Core $_modx */
    protected $_modx = null;

    public function __construct(\Pimple $inj){
        $this->_inj= $inj;
        $this->_modx = $inj['modx'];
    }

    /**
     * Run a plugin
     *
     * @param string $pluginCode Code to run
     * @param array $params
     */
    function evalPlugin($pluginCode, $params) {
        if($pluginCode){
            $etomite = $modx = &$this->_modx;
            $this->_modx->event->params = & $params; // store params inside event object
            if (is_array($params)) {
                extract($params, EXTR_SKIP);
            }
            ob_start();
            eval($pluginCode);
            $msg = ob_get_contents();
            ob_end_clean();

            if ((0 < $this->_modx->getConfig('error_reporting')) && $msg && isset($php_errormsg)) {
                $error_info = error_get_last();
                if ($this->_inj['debug']->detectError($error_info['type'])) {
                    extract($error_info);
                    $msg = ($msg === false) ? 'ob_get_contents() error' : $msg;
                    $result = $this->_modx->messageQuit('PHP Parse Error', '', true, $type, $file, 'Plugin', $text, $line, $msg);
                    if ($this->_modx->isBackend()) {
                        $this->_modx->event->alert('An error occurred while loading. Please see the event log for more information.<p>' . $msg . '</p>');
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
        if (!array_key_exists($evtName,$this->_modx->pluginEvent))
            $this->_modx->pluginEvent[$evtName] = array();
        return array_push($this->_modx->pluginEvent[$evtName], $pluginName); // return array count
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
        unset ($this->_modx->pluginEvent[$evtName]);
    }
    /**
     * Remove all event listners - only for use within the current execution cycle
     */
    function removeAllEventListener() {
        unset ($this->_modx->pluginEvent);
        $this->_modx->pluginEvent= array ();
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
        if (!isset ($this->_modx->pluginEvent[$evtName]))
            return false;
        $el= $this->_modx->pluginEvent[$evtName];
        $results= array ();
        $numEvents= count($el);
        if ($numEvents > 0)
            for ($i= 0; $i < $numEvents; $i++) { // start for loop
                if ($this->_modx->dumpPlugins == 1) $eventtime = $this->_modx->getMicroTime();
                $pluginName= $el[$i];
                $pluginName = stripslashes($pluginName);
                // reset event object
                $e= & $this->_modx->Event;
                $e->_resetEventObject();
                $e->name= $evtName;
                $e->activePlugin= $pluginName;

                // get plugin code
                if (isset ($this->_modx->pluginCache[$pluginName])) {
                    $pluginCode= $this->_modx->pluginCache[$pluginName];
                    $pluginProperties= isset($this->_modx->pluginCache[$pluginName . "Props"]) ? $this->_modx->pluginCache[$pluginName . "Props"] : '';
                } else {
                    $sql= "SELECT `name`, `plugincode`, `properties` FROM " . $this->_modx->getFullTableName("site_plugins") . " WHERE `name`='" . $pluginName . "' AND `disabled`=0;";
                    $result= $this->_modx->db->query($sql);
                    if ($this->_modx->db->getRecordCount($result) == 1) {
                        $row= $this->_modx->db->getRow($result);
                        $pluginCode= $this->_modx->pluginCache[$row['name']]= $row['plugincode'];
                        $pluginProperties= $this->_modx->pluginCache[$row['name'] . "Props"]= $row['properties'];
                    } else {
                        $pluginCode= $this->_modx->pluginCache[$pluginName]= "return false;";
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
                    $eventtime = $this->_modx->getMicroTime() - $eventtime;
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