<?php namespace MODxCore;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 6:43
 */

class HTML{
    /** @var \MODxCore\Pimple $_inj */
    private $_inj = null;

    public function __construct(\Pimple $inj){
        $this->_inj= $inj;
    }

    /**
     * Displays a javascript alert message in the web browser
     *
     * @param string $msg Message to show
     * @param string $url URL to redirect to
     */
    public function webAlert($msg, $url= "") {
        $msg= addslashes($msg);
        if (substr(strtolower($url), 0, 11) == "javascript:") {
            $act= "__WebAlert();";
            $fnc= "function __WebAlert(){" . substr($url, 11) . "};";
        } else {
            $act= ($url ? "window.location.href='" . addslashes($url) . "';" : "");
        }
        $html= "<script>".$fnc." window.setTimeout(\"alert('".$msg."');".$act."\",100);</script>";
        if ($this->_inj['modx']->isFrontend())
            $this->_inj['modx']->regClientScript($html);
        else {
            echo $html;
        }
    }
}