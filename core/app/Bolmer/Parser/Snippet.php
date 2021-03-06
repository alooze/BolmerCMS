<?php namespace Bolmer\Parser;

class Snippet
{
    /** @var \Bolmer\Pimple $_inj */
    private $_inj = null;

    /** @var \Bolmer\Core $_core */
    protected $_core = null;

    /**
     * Конструктор класса \Bolmer\Parser\Snippet
     *
     * @param \Pimple $inj коллекция зависимостей
     */
    public function __construct(\Pimple $inj)
    {
        $this->_inj = $inj;
        $this->_core = $inj['core'];
    }

    /**
     * Returns the id of the current snippet.
     *
     * @return int
     */
    public function getSnippetId()
    {
        $id = 0;
        if ($this->getSnippetName()) {
            $id = (int)\Bolmer\Model\BSnippet::filter('getItem', $this->getSnippetName())->id;
        }
        return $id;
    }

    /**
     * Returns the name of the current snippet.
     *
     * @return string
     */
    public function getSnippetName()
    {
        return $this->_core->currentSnippet;
    }

    /**
     * Executes a snippet.
     *
     * @param string $snippetName
     * @param array $params Default: Empty array
     * @return string
     */
    public function runSnippet($snippetName, $params = array())
    {
        if (isset ($this->_core->snippetCache[$snippetName])) {
            $snippet = $this->_core->snippetCache[$snippetName];
            $properties = $this->_core->snippetCache[$snippetName . "Props"];
        } else { // not in cache so let's check the db
            $row = \Bolmer\Model\BSnippet::where('disabled', 0)->filter('getItem', $snippetName, true);

            $snippet = $this->_core->snippetCache[$row['name']] = getkey($row, 'snippet', 'return false;');
            $properties = $this->_core->snippetCache[$row['name'] . "Props"] = getkey($row, 'properties');
        }
        // load default params/properties
        $parameters = $this->_inj['parser']->parseProperties($properties);
        $parameters = array_merge($parameters, $params);
        // run snippet
        return $this->evalSnippet($snippet, $parameters, $snippetName);
    }

    /**
     * Run a snippet
     *
     * @param string $___code Код который необходимо выполнить
     * @param array $___params параметры сниппета
     * @param string $___name имя сниппета
     * @return string
     */
    public function evalSnippet($___code, $___params, $___name = null)
    {
        if ($___code) {
            $etomite = $modx = $core = & $this->_core;
            $this->_core->event->params = & $___params; // store params inside event object
            if (is_array($___params)) {
                extract($___params, EXTR_SKIP);
            }
            $___hash = $this->_inj['parser']->registerEvalInfo('snippet', $___name);
            $this->_inj['debug']->setDataEvalStack($___hash, 'params', $___params);
            $time = $this->_core->getMicroTime();
            ob_start();
            $___snip = eval($___code);
            $___msg = ob_get_contents();
            ob_end_clean();

            $this->_inj['parser']->unregisterEvalInfo(sprintf("%2.5f", ($this->_core->getMicroTime() - $time)));

            if (0 < $this->_core->getConfig('error_reporting')) {
                $error_info = error_get_last();
                if (!empty($error_info) && $this->_inj['debug']->detectError($error_info['type'])) {
                    extract($error_info);
                    $___msg = ($___msg === false) ? 'ob_get_contents() error' : $___msg;
                    $result = $this->_inj['debug']->messageQuit('PHP Parse Error', '', true, $error_info['type'], $error_info['file'], 'Snippet', $error_info['message'], $error_info['line'], $___msg);
                    if ($this->_core->isBackend()) {
                        $this->_core->event->alert('An error occurred while loading. Please see the event log for more information<p>' . $___msg . $___snip . '</p>');
                    }
                }
            }
            unset($core->event->params);
            $this->_core->currentSnippet = '';
            if (is_array($___snip) || is_object($___snip)) {
                return $___snip;
            } else {
                return $___msg . $___snip;
            }
        }
    }

    /**
     * Run snippets as per the tags in $documentSource and replace the tags with the returned values.
     *
     * @param string $documentSource
     * @return string
     */
    public function evalSnippets($documentSource)
    {
        if (strpos($documentSource, '[[') === false) return $documentSource;
        $etomite = & $this;

        $stack = $documentSource;
        unset($documentSource);


        $passes = $this->_core->minParserPasses;

        for ($i = 0; $i < $passes; $i++) {
            $stack = $this->_core->mergeSettingsContent($stack);
            if ($i == ($passes - 1)) $bt = md5($stack);
            $pieces = array();
            $pieces = explode('[[', $stack);
            $stack = '';
            $loop_count = 0;

            foreach ($pieces as $piece) {
                if ($loop_count < 1) $result = $piece;
                elseif (strpos($piece, ']]') === false) $result = '[[' . $piece;
                else                                $result = $this->_get_snip_result($piece);

                $stack .= $result;
                $loop_count++; // End of foreach loop
            }
            if ($i == ($passes - 1) && $i < ($this->_core->maxParserPasses - 1)) {
                if ($bt != md5($stack)) $passes++;
            }
        }
        return $stack;
    }

    /**
     * Выполнение сниппета с вызовом из шаблона
     *
     * @param $piece строка с вызовом сниппета
     * @return string результат выполнения сниппета
     */
    private function _get_snip_result($piece)
    {
        if ($this->_core->dumpSnippets == 1) $sniptime = $this->_core->getMicroTime();
        $snip_call = $this->_split_snip_call($piece);
        $snip_name = $snip_call['name'];
        $except_snip_call = $snip_call['except_snip_call'];

        $key = $snip_call['name'];

        $snippetObject = $this->_get_snip_properties($snip_call);
        $this->_core->currentSnippet = $snippetObject['name'];

        if (isset($snippetObject['properties'])) $params = $this->_core->parseProperties($snippetObject['properties']);
        else                                    $params = '';
        // current params
        if (!empty($snip_call['params'])) {
            $snip_call['params'] = ltrim($snip_call['params'], '?');

            $i = 0;
            $limit = 50;
            $params_stack = $snip_call['params'];
            while (!empty($params_stack) && $i < $limit) {
                if (strpos($params_stack, '=') !== false) list($pname, $params_stack) = explode('=', $params_stack, 2);
                else {
                    $pname = $params_stack;
                    $params_stack = '';
                }
                $params_stack = trim($params_stack);
                $delim = substr($params_stack, 0, 1);
                $temp_params = array();
                switch ($delim) {
                    case '`':
                    case '"':
                    case "'":
                        $params_stack = substr($params_stack, 1);
                        list($pvalue, $params_stack) = explode($delim, $params_stack, 2);
                        $params_stack = trim($params_stack);
                        if (substr($params_stack, 0, 2) === '//') {
                            $params_stack = strstr($params_stack, "\n");
                        }
                        break;
                    default:
                        if (strpos($params_stack, '&') !== false) {
                            list($pvalue, $params_stack) = explode('&', $params_stack, 2);
                        } else $pvalue = $params_stack;
                        $pvalue = trim($pvalue);
                        $delim = '';
                }
                if ($delim !== "'") {
                    $pvalue = (strpos($pvalue, '[*') !== false) ? $this->_core->mergeDocumentContent($pvalue) : $pvalue;
                    $pvalue = (strpos($pvalue, '[(') !== false) ? $this->_core->mergeSettingsContent($pvalue) : $pvalue;
                    $pvalue = (strpos($pvalue, '{{') !== false) ? $this->_core->mergeChunkContent($pvalue) : $pvalue;
                    $pvalue = (strpos($pvalue, '[+') !== false) ? $this->_core->mergePlaceholderContent($pvalue) : $pvalue;
                }

                $pname = str_replace('&amp;', '', $pname);
                $pname = trim($pname);
                $pname = trim($pname, '&');
                $params[$pname] = $pvalue;
                $params_stack = trim($params_stack);
                if ($params_stack !== '') $params_stack = '&' . ltrim($params_stack, '&');
                $i++;
            }
            unset($temp_params);
        } else {
            $params = array();
        }
        $value = $this->evalSnippet($snippetObject['content'], $params, $snip_name);

        if ($this->_core->dumpSnippets == 1) {
            $sniptime = $this->_core->getMicroTime() - $sniptime;
            $this->_core->snippetsCode .= '<fieldset><legend><b>' . $snippetObject['name'] . '</b> (' . sprintf('%2.2f ms', $sniptime * 1000) . ')</legend>';
            if ($this->_core->event->name) $this->_core->snippetsCode .= 'Current Event  => ' . $this->_core->event->name . '<br>';
            if ($this->_core->event->activePlugin) $this->_core->snippetsCode .= 'Current Plugin => ' . $this->_core->event->activePlugin . '<br>';
            foreach ($params as $k => $v) $this->_core->snippetsCode .= $k . ' => ' . print_r($v, true) . '<br>';
            $this->_core->snippetsCode .= '<textarea style="width:60%;height:200px">' . htmlentities($value, ENT_NOQUOTES, $this->_core->getConfig('modx_charset')) . '</textarea>';
            $this->_core->snippetsCode .= '</fieldset><br />';
            $this->_core->snippetsCount[$snippetObject['name']]++;
            $this->_core->snippetsTime[$snippetObject['name']] += $sniptime;
        }
        return $value . $except_snip_call;
    }

    /**
     * Разбор строки с вызовом сниппета
     *
     * @param string $src строка с вызовом сниппета
     * @return array массив с именем сниппета и его параметрами
     */
    private function _split_snip_call($src)
    {
        $snip = array();
        list($call, $snip['except_snip_call']) = explode(']]', $src, 2);
        if (strpos($call, '?') !== false && strpos($call, "\n") !== false && strpos($call, '?') < strpos($call, "\n")) {
            list($name, $params) = explode('?', $call, 2);
        } elseif (strpos($call, '?') !== false && strpos($call, "\n") !== false && strpos($call, "\n") < strpos($call, '?')) {
            list($name, $params) = explode("\n", $call, 2);
        } elseif (strpos($call, '?') !== false) {
            list($name, $params) = explode('?', $call, 2);
        } elseif ((strpos($call, '&') !== false) && (strpos($call, '=') !== false) && (strpos($call, '?') === false)) {
            list($name, $params) = explode('&', $call, 2);
            $params = "&{$params}";
        } elseif (strpos($call, "\n") !== false) {
            list($name, $params) = explode("\n", $call, 2);
        } else {
            $name = $call;
            $params = '';
        }
        return array_merge($snip, array('name' => trim($name), 'params' => $params));
    }

    /**
     * Получение информации о сниппете который необходимо выполнить
     *
     * @param array $snip_call
     * @return array
     */
    private function _get_snip_properties($snip_call)
    {
        $snip_name = $snip_call['name'];
        $snippetObject = array(
            'name' => '',
            'content' => '',
            'properties' => ''
        );
        $this->_core->snippetCache[$snip_name . 'Props'] = '';

        if (isset($this->_core->snippetCache[$snip_name])) {
            $snippetObject['name'] = $snip_name;
            $snippetObject['content'] = $this->_core->snippetCache[$snip_name];
            if (isset($this->_core->snippetCache[$snip_name . 'Props'])) {
                $snippetObject['properties'] = $this->_core->snippetCache[$snip_name . 'Props'];
            }
        } else {
            // get from db and store a copy inside cache
            $row = \Bolmer\Model\BSnippet::filter('getItem', $snip_name, true);

            $snippetObject['name'] = getkey($row, 'name', $snip_name);
            $snippetObject['content'] = $this->_core->snippetCache[$snip_name] = getkey($row, 'snippet', 'return false;');
            $snippetObject['properties'] = $this->_core->snippetCache[$snip_name . 'Props'] = getkey($row, 'properties');

        }
        return $snippetObject;
    }
}