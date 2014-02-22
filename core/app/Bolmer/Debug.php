<?php namespace Bolmer;

class Debug
{
    /**
     * @var array список SQL запросов
     */
    protected $_queryCode = array();
    /**
     * @var array стек запуска элементов
     */
    protected $_evalStack = array();

    /** @var \Bolmer\Pimple $_inj коллекция зависимостей*/
    private $_inj = null;

    /** @var \Bolmer\Core $_core */
    protected $_core = null;

    /**
     * Конструктор класса \Bolmer\Debug
     *
     * @param \Pimple $inj коллекция зависимостей
     */
    public function __construct(\Pimple $inj)
    {
        $this->_inj = $inj;
        $this->_core = $inj['core'];
    }

    /**
     * Получение всего стека
     *
     * @return array
     */
    public function getEvalStack()
    {
        return $this->_evalStack;
    }

    /**
     * Поиск элемента в стеке
     *
     * @param string $hash уникальный идентификатор по кторому нужно найти элемент в стеке
     * @return null|array элемент из стека
     */
    public function findEvalStack($hash)
    {
        return (!empty($hash) && is_scalar($hash) && isset($this->_evalStack[$hash])) ? $this->_evalStack[$hash] : null;
    }

    /**
     * Добавление инфомрации к записи в стеке
     *
     * @param string $hash уникальный идентификатор записи в стеке
     * @param string $data ключ под которым нужно сохранить данные у записи
     * @param int|string $value значение которое нужно сохранить у записи
     * @return null|array элемент из стека найденный по уникальному идентификатору $hash
     */
    public function setDataEvalStack($hash, $data, $value = null)
    {
        $stack = $this->findEvalStack($hash);
        if (!empty($stack)) {
            $this->_evalStack[$hash][$data] = $stack[$data] = $value;
        }
        return $stack;
    }

    /**
     * Добавление элемента (сниппета/плагина) в стек
     *
     * @param string $type тип элемента (сниппет/плагин)
     * @param string $name имя элемента
     * @return null|string уникальный идентификатор записи в стеке
     */
    public function addToEvalStack($type, $name)
    {
        $hash = null;
        if (is_scalar($type) && (is_scalar($name) || is_null($name))) {
            $hash = md5(time() . $type . $name);
            $this->_evalStack[$hash] = compact('self', 'type', 'name');
        }
        return $hash;
    }

    /**
     * Построение древовидного стека запуска сниппетов/плагинов
     *
     * @return array
     */
    public function buildTreeEvalStack()
    {
        $tree = $this->getEvalStack();
        $tree = \Bolmer\Helper\Tree::build($tree, 'self', 'owner', 0, 'level', 'stack');

        return $tree;
    }

    /**
     * Добавление SQL запроса со временем выполнения в стек
     *
     * @param string $q SQL запрос
     * @param int $time время выполнения запроса
     */
    public function addQuery($q, $time = 0)
    {
        $this->_core->queryTime += $time;
        $this->_core->executedQueries += 1;

        $this->_queryCode[] = '[' . sprintf("%2.5f", $time) . '] ' . $q;
    }

    /**
     * Показать стек SQL запросов
     *
     * @return string строка со списоком SQL запросов для подстановки в HTML код страницы
     */
    public function showQuery()
    {
        return implode("<br />", $this->_queryCode);
    }

    /**
     * PHP error handler set by http://www.php.net/manual/en/function.set-error-handler.php
     *
     * Checks the PHP error and calls messageQuit() unless:
     *  - error_reporting() returns 0, or
     *  - the PHP error level is 0, or
     *  - the PHP error level is 8 (E_NOTICE) and stopOnNotice is false
     *
     * @param int $nr The PHP error level as per http://www.php.net/manual/en/errorfunc.constants.php
     * @param string $text Error message
     * @param string $file File where the error was detected
     * @param string $line Line number within $file
     * @return boolean
     */
    function phpError($nr, $text, $file, $line)
    {
        if (error_reporting() == 0 || $nr == 0) {
            return true;
        }
        if ($this->_core->stopOnNotice == false) {
            switch ($nr) {
                case E_NOTICE:
                    if ($this->_core->error_reporting <= 2) return true;
                    break;
                case E_STRICT:
                case E_DEPRECATED:
                    if ($this->_core->error_reporting <= 1) return true;
                    break;
                default:
                    if ($this->_core->error_reporting === 0) return true;
            }
        }
        if (is_readable($file)) {
            $source = file($file);
            $source = htmlspecialchars($source[$line - 1]);
        } else {
            $source = "";
        } //Error $nr in $file at $line: <div><code>$source</code></div>
        $this->messageQuit("PHP Parse Error", '', true, $nr, $file, $source, $text, $line);
    }

    /**
     * Detect PHP error according to MODX error level
     *
     * @param integer $error PHP error level
     * @return boolean Error detected
     */
    function detectError($error)
    {
        $detected = FALSE;
        if ($this->_core->getConfig('error_reporting') == 99 && $error)
            $detected = TRUE;
        elseif ($this->_core->getConfig('error_reporting') == 2 && ($error & ~E_NOTICE))
            $detected = TRUE;
        elseif ($this->_core->getConfig('error_reporting') == 1 && ($error & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT))
            $detected = TRUE;
        return $detected;
    }

    /**
     * @param string $msg текст ошибки
     * @param string $query текст SQL запроса
     * @param bool $is_error остановка парсера во время ошибки или по требованию
     * @param string $nr номер ошибки
     * @param string $file файл в котором произошла ошибка
     * @param string $source источник в котором произошла ошибка (Сниппет/плагин/файл)
     * @param string $text описание ошибки
     * @param string $line строка на которой произошла ошибка
     * @param string $output дополнительный текст для вывода в таблицу с ошибкой
     * @return bool
     */
    function messageQuit($msg = 'unspecified error', $query = '', $is_error = true, $nr = '', $file = '', $source = '', $text = '', $line = '', $output = '')
    {
        $version = $this->_core->getVersionData('version');
        $release_date = $this->_core->getVersionData('release_date');
        $request_uri = "http://" . $_SERVER['HTTP_HOST'] . ($_SERVER["SERVER_PORT"] == 80 ? "" : (":" . $_SERVER["SERVER_PORT"])) . $_SERVER['REQUEST_URI'];
        $request_uri = htmlspecialchars($request_uri, ENT_QUOTES, $this->_core->getConfig('modx_charset'));
        $ua = htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, $this->_core->getConfig('modx_charset'));
        $referer = htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES, $this->_core->getConfig('modx_charset'));
        if ($is_error) {
            $str = '<h3 style="color:red">&laquo; MODX Parse Error &raquo;</h3>
	                <table border="0" cellpadding="1" cellspacing="0">
	                <tr><td colspan="2">MODX encountered the following error while attempting to parse the requested resource:</td></tr>
	                <tr><td colspan="2"><b style="color:red;">&laquo; ' . $msg . ' &raquo;</b></td></tr>';
        } else {
            $str = '<h3 style="color:#003399">&laquo; MODX Debug/ stop message &raquo;</h3>
	                <table border="0" cellpadding="1" cellspacing="0">
	                <tr><td colspan="2">The MODX parser recieved the following debug/ stop message:</td></tr>
	                <tr><td colspan="2"><b style="color:#003399;">&laquo; ' . $msg . ' &raquo;</b></td></tr>';
        }

        if (!empty ($query)) {
            $str .= '<tr><td colspan="2"><div style="font-weight:bold;border:1px solid #ccc;padding:8px;color:#333;background-color:#ffffcd;">SQL &gt; <span id="sqlHolder">' . $query . '</span></div>
	                </td></tr>';
        }
        $errortype = array(
            E_ERROR => "ERROR",
            E_WARNING => "WARNING",
            E_PARSE => "PARSING ERROR",
            E_NOTICE => "NOTICE",
            E_CORE_ERROR => "CORE ERROR",
            E_CORE_WARNING => "CORE WARNING",
            E_COMPILE_ERROR => "COMPILE ERROR",
            E_COMPILE_WARNING => "COMPILE WARNING",
            E_USER_ERROR => "USER ERROR",
            E_USER_WARNING => "USER WARNING",
            E_USER_NOTICE => "USER NOTICE",
            E_STRICT => "STRICT NOTICE",
            E_RECOVERABLE_ERROR => "RECOVERABLE ERROR",
            E_DEPRECATED => "DEPRECATED",
            E_USER_DEPRECATED => "USER DEPRECATED"
        );

        if (!empty($nr) || !empty($file)) {
            $str .= '<tr><td colspan="2"><b>PHP error debug</b></td></tr>';
            if ($text != '') {
                $str .= '<tr><td colspan="2"><div style="font-weight:bold;border:1px solid #ccc;padding:8px;color:#333;background-color:#ffffcd;">Error : ' . $text . '</div></td></tr>';
            }
            if ($output != '') {
                $str .= '<tr><td colspan="2"><div style="font-weight:bold;border:1px solid #ccc;padding:8px;color:#333;background-color:#ffffcd;">' . $output . '</div></td></tr>';
            }
            $str .= '<tr><td valign="top">ErrorType[num] : </td>';
            $str .= '<td>' . $errortype [$nr] . "[{$nr}]</td>";
            $str .= '</tr>';
            $str .= "<tr><td>File : </td><td>{$file}</td></tr>";
            $str .= "<tr><td>Line : </td><td>{$line}</td></tr>";
        }

        if ($source != '') {
            $str .= "<tr><td>Source : </td><td>{$source}</td></tr>";
        }

        $str .= '<tr><td colspan="2"><b>Basic info</b></td></tr>';

        $str .= '<tr><td valign="top" style="white-space:nowrap;">REQUEST_URI : </td>';
        $str .= "<td>{$request_uri}</td>";
        $str .= '</tr>';

        if (isset($_GET['a'])) $action = $_GET['a'];
        elseif (isset($_POST['a'])) $action = $_POST['a'];
        if (isset($action) && !empty($action)) {
            include_once(BOLMER_MANAGER_PATH . 'includes/actionlist.inc.php');
            global $action_list;
            if (isset($action_list[$action])) $actionName = " - {$action_list[$action]}";
            else $actionName = '';
            $str .= '<tr><td valign="top">Manager action : </td>';
            $str .= "<td>{$action}{$actionName}</td>";
            $str .= '</tr>';
        }

        if (preg_match('@^[0-9]+@', $this->_core->documentIdentifier)) {
            $resource = $this->_core->getDocumentObject('id', $this->_core->documentIdentifier);
            $url = $this->_core->makeUrl($this->_core->documentIdentifier, '', '', 'full');
            $link = '<a href="' . $url . '" target="_blank">' . $resource['pagetitle'] . '</a>';
            $str .= '<tr><td valign="top">Resource : </td>';
            $str .= '<td>[' . $this->_core->documentIdentifier . ']' . $link . '</td></tr>';
        }
        if (!empty($this->_core->currentSnippet)) {
            $str .= "<tr><td>Current Snippet : </td>";
            $str .= '<td>' . $this->_core->currentSnippet . '</td></tr>';
        }

        if (!empty($this->_core->event->activePlugin)) {
            $str .= "<tr><td>Current Plugin : </td>";
            $str .= '<td>' . $this->_core->event->activePlugin . '(' . $this->_core->event->name . ')' . '</td></tr>';
        }

        $str .= "<tr><td>Referer : </td><td>{$referer}</td></tr>";
        $str .= "<tr><td>User Agent : </td><td>{$ua}</td></tr>";

        $str .= "<tr><td>IP : </td>";
        $str .= '<td>' . $_SERVER['REMOTE_ADDR'] . '</td>';
        $str .= '</tr>';

        $str .= '<tr><td colspan="2"><b>Benchmarks</b></td></tr>';

        $str .= "<tr><td>MySQL : </td>";
        $str .= '<td>[^qt^] ([^q^] Requests)</td>';
        $str .= '</tr>';

        $str .= "<tr><td>PHP : </td>";
        $str .= '<td>[^p^]</td>';
        $str .= '</tr>';

        $str .= "<tr><td>Total : </td>";
        $str .= '<td>[^t^]</td>';
        $str .= '</tr>';

        $str .= "<tr><td>Memory : </td>";
        $str .= '<td>[^m^]</td>';
        $str .= '</tr>';

        $str .= "</table>\n";

        $totalTime = ($this->_core->getMicroTime() - $this->_core->tstart);

        $mem = memory_get_peak_usage(true);
        $total_mem = $mem - $this->_core->mstart;
        $total_mem = ($total_mem / 1024 / 1024) . ' mb';

        $queryTime = $this->_core->queryTime;
        $phpTime = $totalTime - $queryTime;
        $queries = isset ($this->_core->executedQueries) ? $this->_core->executedQueries : 0;
        $queryTime = sprintf("%2.4f s", $queryTime);
        $totalTime = sprintf("%2.4f s", $totalTime);
        $phpTime = sprintf("%2.4f s", $phpTime);

        $str = str_replace('[^q^]', $queries, $str);
        $str = str_replace('[^qt^]', $queryTime, $str);
        $str = str_replace('[^p^]', $phpTime, $str);
        $str = str_replace('[^t^]', $totalTime, $str);
        $str = str_replace('[^m^]', $total_mem, $str);

        if (isset($php_errormsg) && !empty($php_errormsg)) $str = "<b>{$php_errormsg}</b><br />\n{$str}";
        $str .= '<br />' . $this->get_backtrace(debug_backtrace()) . "\n";

        // Log error
        if (!empty($this->_core->currentSnippet)) $source = 'Snippet - ' . $this->_core->currentSnippet;
        elseif (!empty($this->_core->event->activePlugin)) $source = 'Plugin - ' . $this->_core->event->activePlugin;
        elseif ($source !== '') $source = 'Parser - ' . $source;
        elseif ($query !== '') $source = 'SQL Query';
        else             $source = 'Parser';
        if (isset($actionName) && !empty($actionName)) $source .= $actionName;
        switch ($nr) {
            case E_DEPRECATED :
            case E_USER_DEPRECATED :
            case E_STRICT :
            case E_NOTICE :
            case E_USER_NOTICE :
                $error_level = 2;
                break;
            default:
                $error_level = 3;
        }

        $this->_core->logEvent(0, $error_level, $str, $source);

        if ($error_level === 2 && $this->_core->error_reporting !== '99') return true;
        if ($this->_core->error_reporting === '99' && !isset($_SESSION['mgrValidated'])) return true;

        // Set 500 response header
        if ($error_level !== 2) header('HTTP/1.1 500 Internal Server Error');

        // Display error
        if (isset($_SESSION['mgrValidated'])) {
            echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html><head><title>MODX Content Manager ' . $version . ' &raquo; ' . $release_date . '</title>
	             <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	             <link rel="stylesheet" type="text/css" href="' . $this->_core->getConfig('site_manager_url') . 'media/style/' . $this->_core->getConfig('manager_theme') . '/style.css" />
	             <style type="text/css">body { padding:10px; } td {font:inherit;}</style>
	             </head><body>
	             ' . $str . '</body></html>';

        } else  echo 'Error';
        ob_end_flush();
        exit;
    }

    /**
     * Подготовка таблицы со стеком вызова функций и методов
     *
     * @param array $backtrace стек вызова
     * @return string HTML таблица
     */
    function get_backtrace($backtrace)
    {

        $str = "<p><b>Backtrace</b></p>\n";
        $str .= '<table>';
        $backtrace = array_reverse($backtrace);
        foreach ($backtrace as $key => $val) {
            $key++;
            if (substr($val['function'], 0, 11) === 'messageQuit') break;
            elseif (substr($val['function'], 0, 8) === 'phpError') break;
            $path = str_replace('\\', '/', $val['file']);
            if (strpos($path, BOLMER_BASE_PATH) === 0) $path = substr($path, strlen(BOLMER_BASE_PATH));
            switch ($val['type']) {
                case '->':
                case '::':
                    $functionName = $val['function'] = $val['class'] . $val['type'] . $val['function'];
                    break;
                default:
                    $functionName = $val['function'];
            }
            $str .= "<tr><td valign=\"top\">{$key}</td>";
            $str .= "<td>{$functionName}()<br />{$path} on line {$val['line']}</td>";
        }
        $str .= '</table>';
        return $str;
    }
}