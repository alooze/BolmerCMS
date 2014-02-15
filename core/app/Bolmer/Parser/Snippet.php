<?php namespace Bolmer\Parser;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 6:14
 */

class Snippet{
    /** @var \Bolmer\Pimple $_inj */
    private $_inj = null;

    public function __construct(\Pimple $inj){
        $this->_inj= $inj;
    }

    /**
     * Returns the id of the current snippet.
     *
     * @return int
     */
    public function getSnippetId() {
        if ($this->_inj['modx']->currentSnippet) {
            $tbl= $this->_inj['modx']->getFullTableName("site_snippets");
            $rs= $this->_inj['db']->query("SELECT id FROM $tbl WHERE name='" . $this->_inj['db']->escape($this->_inj['modx']->currentSnippet) . "' LIMIT 1");
            $row= @ $this->_inj['db']->getRow($rs);
            if ($row['id'])
                return $row['id'];
        }
        return 0;
    }

    /**
     * Returns the name of the current snippet.
     *
     * @return string
     */
    function getSnippetName() {
        return $this->_inj['modx']->currentSnippet;
    }

    /**
     * Executes a snippet.
     *
     * @param string $snippetName
     * @param array $params Default: Empty array
     * @return string
     */
    public function runSnippet($snippetName, $params= array ()) {
        if (isset ($this->_inj['modx']->snippetCache[$snippetName])) {
            $snippet= $this->_inj['modx']->snippetCache[$snippetName];
            $properties= $this->_inj['modx']->snippetCache[$snippetName . "Props"];
        } else { // not in cache so let's check the db
            $sql= "SELECT `name`, `snippet`, `properties` FROM " . $this->_inj['modx']->getFullTableName("site_snippets") . " WHERE " . $this->_inj['modx']->getFullTableName("site_snippets") . ".`name`='" . $this->_inj['db']->escape($snippetName) . "';";
            $result= $this->_inj['db']->query($sql);
            if ($this->_inj['db']->getRecordCount($result) == 1) {
                $row= $this->_inj['db']->getRow($result);
                $snippet= $this->_inj['modx']->snippetCache[$row['name']]= $row['snippet'];
                $properties= $this->_inj['modx']->snippetCache[$row['name'] . "Props"]= $row['properties'];
            } else {
                $snippet= $this->_inj['modx']->snippetCache[$snippetName]= "return false;";
                $properties= '';
            }
        }
        // load default params/properties
        $parameters= $this->_inj['modx']->parseProperties($properties);
        $parameters= array_merge($parameters, $params);
        // run snippet
        return $this->evalSnippet($snippet, $parameters);
    }

    /**
     * Run a snippet
     *
     * @param string $snippet Code to run
     * @param array $params
     * @return string
     */
    public function evalSnippet($snippet, $params) {
        if($snippet){
            $etomite = $modx = & $this->_inj['modx'];
            $this->_inj['modx']->event->params = & $params; // store params inside event object
            if (is_array($params)) {
                extract($params, EXTR_SKIP);
            }
            ob_start();
            $snip = eval($snippet);
            $msg = ob_get_contents();
            ob_end_clean();

            if (0 < $this->_inj['modx']->getConfig('error_reporting')) {
                $error_info = error_get_last();
                if (!empty($error_info) && $this->_inj['debug']->detectError($error_info['type'])) {
                    extract($error_info);
                    $msg = ($msg === false) ? 'ob_get_contents() error' : $msg;
                    $result = $this->_inj['debug']->messageQuit('PHP Parse Error', '', true, $error_info['type'], $error_info['file'], 'Snippet', $error_info['message'], $error_info['line'], $msg);
                    if ($this->_inj['modx']->isBackend()) {
                        $this->_inj['modx']->event->alert('An error occurred while loading. Please see the event log for more information<p>' . $msg . $snip . '</p>');
                    }
                }
            }
            unset($modx->event->params);
            $this->_inj['modx']->currentSnippet = '';
            if (is_array($snip) || is_object($snip)) {
                return $snip;
            } else {
                return $msg . $snip;
            }
        }
    }

    /**
     * Run snippets as per the tags in $documentSource and replace the tags with the returned values.
     *
     * @param string $documentSource
     * @return string
     */
    function evalSnippets($documentSource) {
        if(strpos($documentSource,'[[')===false) return $documentSource;
        $etomite= & $this;

        $stack = $documentSource;
        unset($documentSource);


        $passes = $this->_inj['modx']->minParserPasses;

        for($i= 0; $i < $passes; $i++)
        {
            $stack=$this->_inj['modx']->mergeSettingsContent($stack);
            if($i == ($passes -1)) $bt = md5($stack);
            $pieces = array();
            $pieces = explode('[[', $stack);
            $stack = '';
            $loop_count = 0;

            foreach($pieces as $piece)
            {
                if($loop_count < 1)                 $result = $piece;
                elseif(strpos($piece,']]')===false) $result = '[[' . $piece;
                else                                $result = $this->_get_snip_result($piece);

                $stack .= $result;
                $loop_count++; // End of foreach loop
            }
            if($i == ($passes -1) && $i < ($this->_inj['modx']->maxParserPasses - 1))
            {
                if($bt != md5($stack)) $passes++;
            }
        }
        return $stack;
    }

    private function _get_snip_result($piece)
    {
        if ($this->_inj['modx']->dumpSnippets == 1) $sniptime = $this->_inj['modx']->getMicroTime();
        $snip_call        = $this->_split_snip_call($piece);
        $snip_name        = $snip_call['name'];
        $except_snip_call = $snip_call['except_snip_call'];

        $key = $snip_call['name'];

        $snippetObject = $this->_get_snip_properties($snip_call);
        $params   = array ();
        $this->_inj['modx']->currentSnippet = $snippetObject['name'];

        if(isset($snippetObject['properties'])) $params = $this->_inj['modx']->parseProperties($snippetObject['properties']);
        else                                    $params = '';
        // current params
        if(!empty($snip_call['params']))
        {
            $snip_call['params'] = ltrim($snip_call['params'], '?');

            $i = 0;
            $limit = 50;
            $params_stack = $snip_call['params'];
            while(!empty($params_stack) && $i < $limit)
            {
                if(strpos($params_stack,'=')!==false) list($pname,$params_stack) = explode('=',$params_stack,2);
                else {
                    $pname=$params_stack;
                    $params_stack = '';
                }
                $params_stack = trim($params_stack);
                $delim = substr($params_stack, 0, 1);
                $temp_params = array();
                switch($delim)
                {
                    case '`':
                    case '"':
                    case "'":
                        $params_stack = substr($params_stack,1);
                        list($pvalue,$params_stack) = explode($delim,$params_stack,2);
                        $params_stack = trim($params_stack);
                        if(substr($params_stack, 0, 2)==='//')
                        {
                            $params_stack = strstr($params_stack, "\n");
                        }
                        break;
                    default:
                        if(strpos($params_stack, '&')!==false)
                        {
                            list($pvalue,$params_stack) = explode('&',$params_stack,2);
                        }
                        else $pvalue = $params_stack;
                        $pvalue = trim($pvalue);
                        $delim = '';
                }
                if($delim !== "'")
                {
                    $pvalue = (strpos($pvalue,'[*')!==false) ? $this->_inj['modx']->mergeDocumentContent($pvalue) : $pvalue;
                    $pvalue = (strpos($pvalue,'[(')!==false) ? $this->_inj['modx']->mergeSettingsContent($pvalue) : $pvalue;
                    $pvalue = (strpos($pvalue,'{{')!==false) ? $this->_inj['modx']->mergeChunkContent($pvalue)    : $pvalue;
                    $pvalue = (strpos($pvalue,'[+')!==false) ? $this->_inj['modx']->mergePlaceholderContent($pvalue) : $pvalue;
                }

                $pname  = str_replace('&amp;', '', $pname);
                $pname  = trim($pname);
                $pname  = trim($pname,'&');
                $params[$pname] = $pvalue;
                $params_stack = trim($params_stack);
                if($params_stack!=='') $params_stack = '&' . ltrim($params_stack,'&');
                $i++;
            }
            unset($temp_params);
        }
        $value = $this->evalSnippet($snippetObject['content'], $params);

        if($this->_inj['modx']->dumpSnippets == 1)
        {
            $sniptime = $this->_inj['modx']->getMicroTime() - $sniptime;
            $this->_inj['modx']->snippetsCode .= '<fieldset><legend><b>' . $snippetObject['name'] . '</b> (' . sprintf('%2.2f ms', $sniptime*1000) . ')</legend>';
            if ($this->_inj['modx']->event->name) $this->_inj['modx']->snippetsCode .= 'Current Event  => ' . $this->_inj['modx']->event->name . '<br>';
            if ($this->_inj['modx']->event->activePlugin) $this->_inj['modx']->snippetsCode .= 'Current Plugin => ' . $this->_inj['modx']->event->activePlugin . '<br>';
            foreach ($params as $k=>$v) $this->_inj['modx']->snippetsCode .=  $k . ' => ' . print_r($v, true) . '<br>';
            $this->_inj['modx']->snippetsCode .= '<textarea style="width:60%;height:200px">' . htmlentities($value,ENT_NOQUOTES,$this->_inj['modx']->getConfig('modx_charset')) . '</textarea>';
            $this->_inj['modx']->snippetsCode .= '</fieldset><br />';
            $this->_inj['modx']->snippetsCount[$snippetObject['name']]++;
            $this->_inj['modx']->snippetsTime[$snippetObject['name']] += $sniptime;
        }
        return $value . $except_snip_call;
    }

    private function _split_snip_call($src)
    {
        list($call,$snip['except_snip_call']) = explode(']]', $src, 2);
        if(strpos($call, '?') !== false && strpos($call, "\n")!==false && strpos($call, '?') < strpos($call, "\n"))
        {
            list($name,$params) = explode('?',$call,2);
        }
        elseif(strpos($call, '?') !== false && strpos($call, "\n")!==false && strpos($call, "\n") < strpos($call, '?'))
        {
            list($name,$params) = explode("\n",$call,2);
        }
        elseif(strpos($call, '?') !== false)
        {
            list($name,$params) = explode('?',$call,2);
        }
        elseif((strpos($call, '&') !== false) && (strpos($call, '=') !== false) && (strpos($call, '?') === false))
        {
            list($name,$params) = explode('&',$call,2);
            $params = "&{$params}";
        }
        elseif(strpos($call, "\n") !== false)
        {
            list($name,$params) = explode("\n",$call,2);
        }
        else
        {
            $name   = $call;
            $params = '';
        }
        $snip['name']   = trim($name);
        $snip['params'] = $params;
        return $snip;
    }

    private function _get_snip_properties($snip_call)
    {
        $snip_name  = $snip_call['name'];

        if(isset($this->_inj['modx']->snippetCache[$snip_name]))
        {
            $snippetObject['name']    = $snip_name;
            $snippetObject['content'] = $this->_inj['modx']->snippetCache[$snip_name];
            if(isset($this->_inj['modx']->snippetCache[$snip_name . 'Props']))
            {
                $snippetObject['properties'] = $this->_inj['modx']->snippetCache[$snip_name . 'Props'];
            }
        }
        else
        {
            $tbl_snippets  = $this->_inj['modx']->getFullTableName('site_snippets');
            $esc_snip_name = $this->_inj['db']->escape($snip_name);
            // get from db and store a copy inside cache
            $result= $this->_inj['db']->select('name,snippet,properties',$tbl_snippets,"name='{$esc_snip_name}'");
            $added = false;
            if($this->_inj['db']->getRecordCount($result) == 1)
            {
                $row = $this->_inj['db']->getRow($result);
                if($row['name'] == $snip_name)
                {
                    $snippetObject['name']       = $row['name'];
                    $snippetObject['content']    = $this->_inj['modx']->snippetCache[$snip_name]           = $row['snippet'];
                    $snippetObject['properties'] = $this->_inj['modx']->snippetCache[$snip_name . 'Props'] = $row['properties'];
                    $added = true;
                }
            }
            if($added === false)
            {
                $snippetObject['name']       = $snip_name;
                $snippetObject['content']    = $this->_inj['modx']->snippetCache[$snip_name] = 'return false;';
                $snippetObject['properties'] = '';
            }
        }
        return $snippetObject;
    }
}