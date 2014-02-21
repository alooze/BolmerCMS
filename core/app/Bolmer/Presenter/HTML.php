<?php namespace Bolmer\Presenter;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 6:43
 */

class HTML{
    /** @var \Bolmer\Pimple $_inj */
    private $_inj = null;

    /** @var \Bolmer\Core $_core */
    protected $_core = null;

    public function __construct(\Pimple $inj){
        $this->_inj= $inj;
        $this->_core = $inj['core'];
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
        if ($this->_core->isFrontend())
            $this->_core->regClientScript($html);
        else {
            echo $html;
        }
    }

    /**
     * Registers Client-side CSS scripts - these scripts are loaded at inside
     * the <head> tag
     *
     * @param string $src
     * @param string $media Default: Empty string
     */
    public function regClientCSS($src, $media='') {
        if (empty($src) || isset ($this->_core->loadedjscripts[$src]))
            return '';
        $nextpos= max(array_merge(array(0),array_keys($this->_core->sjscripts)))+1;
        $this->_core->loadedjscripts[$src]['startup']= true;
        $this->_core->loadedjscripts[$src]['version']= '0';
        $this->_core->loadedjscripts[$src]['pos']= $nextpos;
        if (strpos(strtolower($src), "<style") !== false || strpos(strtolower($src), "<link") !== false) {
            $this->_core->sjscripts[$nextpos]= $src;
        } else {
            $this->_core->sjscripts[$nextpos]= "\t" . '<link rel="stylesheet" type="text/css" href="'.$src.'" '.($media ? 'media="'.$media.'" ' : '').'/>';
        }
    }

    /**
     * Registers Startup Client-side JavaScript - these scripts are loaded at inside the <head> tag
     *
     * @param string $src
     * @param array $options Default: 'name'=>'', 'version'=>'0', 'plaintext'=>false
     */
    public function regClientStartupScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false)) {
        $this->_core->regClientScript($src, $options, true);
    }

    /**
     * Registers Client-side JavaScript these scripts are loaded at the end of the page unless $startup is true
     *
     * @param string $src
     * @param array $options Default: 'name'=>'', 'version'=>'0', 'plaintext'=>false
     * @param boolean $startup Default: false
     * @return string
     */
    public function regClientScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false), $startup= false) {
        if (empty($src))
            return ''; // nothing to register
        if (!is_array($options)) {
            if (is_bool($options))  // backward compatibility with old plaintext parameter
                $options=array('plaintext'=>$options);
            elseif (is_string($options)) // Also allow script name as 2nd param
                $options=array('name'=>$options);
            else
                $options=array();
        }
        $name= isset($options['name']) ? strtolower($options['name']) : '';
        $version= isset($options['version']) ? $options['version'] : '0';
        $plaintext= isset($options['plaintext']) ? $options['plaintext'] : false;
        $key= !empty($name) ? $name : $src;
        unset($overwritepos); // probably unnecessary--just making sure

        $useThisVer= true;
        if (isset($this->_core->loadedjscripts[$key])) { // a matching script was found
            // if existing script is a startup script, make sure the candidate is also a startup script
            if ($this->_core->loadedjscripts[$key]['startup'])
                $startup= true;

            if (empty($name)) {
                $useThisVer= false; // if the match was based on identical source code, no need to replace the old one
            } else {
                $useThisVer = version_compare($this->_core->loadedjscripts[$key]['version'], $version, '<');
            }

            if ($useThisVer) {
                if ($startup==true && $this->_core->loadedjscripts[$key]['startup']==false) {
                    // remove old script from the bottom of the page (new one will be at the top)
                    unset($this->_core->jscripts[$this->_core->loadedjscripts[$key]['pos']]);
                } else {
                    // overwrite the old script (the position may be important for dependent scripts)
                    $overwritepos= $this->_core->loadedjscripts[$key]['pos'];
                }
            } else { // Use the original version
                if ($startup==true && $this->_core->loadedjscripts[$key]['startup']==false) {
                    // need to move the exisiting script to the head
                    $version= $this->_core->loadedjscripts[$key][$version];
                    $src= $this->_core->jscripts[$this->_core->loadedjscripts[$key]['pos']];
                    unset($this->_core->jscripts[$this->_core->loadedjscripts[$key]['pos']]);
                } else {
                    return ''; // the script is already in the right place
                }
            }
        }

        if ($useThisVer && $plaintext!=true && (strpos(strtolower($src), "<script") === false))
            $src= "\t" . '<script type="text/javascript" src="' . $src . '"></script>';
        if ($startup) {
            $pos= isset($overwritepos) ? $overwritepos : max(array_merge(array(0),array_keys($this->_core->sjscripts)))+1;
            $this->_core->sjscripts[$pos]= $src;
        } else {
            $pos= isset($overwritepos) ? $overwritepos : max(array_merge(array(0),array_keys($this->_core->jscripts)))+1;
            $this->_core->jscripts[$pos]= $src;
        }
        $this->_core->loadedjscripts[$key]['version']= $version;
        $this->_core->loadedjscripts[$key]['startup']= $startup;
        $this->_core->loadedjscripts[$key]['pos']= $pos;
    }

    /**
     * Returns all registered JavaScripts
     *
     * @return string
     */
    public function regClientStartupHTMLBlock($html) {
        $this->regClientScript($html, true, true);
    }

    /**
     * Returns all registered startup scripts
     *
     * @return string
     */
    public function regClientHTMLBlock($html) {
        $this->regClientScript($html, true);
    }

    public function getRegisteredClientScripts() {
        return implode("\n", $this->_core->jscripts);
    }
    public function getRegisteredClientStartupScripts() {
        return implode("\n", $this->_core->sjscripts);
    }
}