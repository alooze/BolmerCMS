<?php namespace Bolmer;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 23:35
 */

class Debug{
    /** @var \Bolmer\Pimple $_inj */
    private static $_inj = null;
    protected static $_queryCode = array();
    protected $_evalStack = array();

    public function __construct(\Pimple $inj){
        static::$_inj = $inj;
    }

    public function getEvalStack(){
        return $this->_evalStack;
    }
    public function findEvalStack($hash){
        return (!empty($hash) && is_scalar($hash) && isset($this->_evalStack[$hash])) ? $this->_evalStack[$hash] : null;
    }

    public function setDataEvalStack($hash, $data, $value = 0){
        $stack = static::findEvalStack($hash);
        if(!empty($stack)){
            $this->_evalStack[$hash][$data] = $stack[$data] = $value;
        }
        return $stack;
    }

    public function addToEvalStack($type, $name){
        $self = null;
        if(is_scalar($type) && (is_scalar($name) || is_null($name))){
            $self = md5(time().$type.$name);
            $this->_evalStack[$self] = compact('self', 'type', 'name');
        }
        return $self;
    }

    public function buildTreeEvalStack(){
        $tree = static::getEvalStack();
        $tree = \Bolmer\Helper\Tree::build($tree, 'self', 'owner', 0, 'level', 'stack');

        return $tree;
    }

    public static function addQuery($q, $time=0){

        static::$_inj['modx']->queryTime += $time;
        static::$_inj['modx']->executedQueries += 1;

        static::$_queryCode[] = '['.sprintf("%2.5f", $time).'] '.$q;
    }

    public static function showQuery(){
        return implode("<br />", static::$_queryCode);
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
    function phpError($nr, $text, $file, $line) {
        if (error_reporting() == 0 || $nr == 0) {
            return true;
        }
        if(static::$_inj['modx']->stopOnNotice == false)
        {
            switch($nr)
            {
                case E_NOTICE:
                    if(static::$_inj['modx']->error_reporting <= 2) return true;
                    break;
                case E_STRICT:
                case E_DEPRECATED:
                    if(static::$_inj['modx']->error_reporting <= 1) return true;
                    break;
                default:
                    if(static::$_inj['modx']->error_reporting === 0) return true;
            }
        }
        if (is_readable($file)) {
            $source= file($file);
            $source= htmlspecialchars($source[$line -1]);
        } else {
            $source= "";
        } //Error $nr in $file at $line: <div><code>$source</code></div>
        $this->messageQuit("PHP Parse Error", '', true, $nr, $file, $source, $text, $line);
    }
    /**
     * Detect PHP error according to MODX error level
     *
     * @param integer $error PHP error level
     * @return boolean Error detected
     */
    function detectError($error) {
        $detected = FALSE;
        if (static::$_inj['modx']->getConfig('error_reporting') == 99 && $error)
            $detected = TRUE;
        elseif (static::$_inj['modx']->getConfig('error_reporting') == 2 && ($error & ~E_NOTICE))
            $detected = TRUE;
        elseif (static::$_inj['modx']->getConfig('error_reporting') == 1 && ($error & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT))
            $detected = TRUE;
        return $detected;
    }
    function messageQuit($msg= 'unspecified error', $query= '', $is_error= true, $nr= '', $file= '', $source= '', $text= '', $line= '', $output='') {
        $version = static::$_inj['modx']->getVersionData('version');
        $release_date= static::$_inj['modx']->getVersionData('release_date');
        $request_uri = "http://".$_SERVER['HTTP_HOST'].($_SERVER["SERVER_PORT"]==80?"":(":".$_SERVER["SERVER_PORT"])).$_SERVER['REQUEST_URI'];
        $request_uri = htmlspecialchars($request_uri, ENT_QUOTES, static::$_inj['modx']->getConfig('modx_charset'));
        $ua          = htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, static::$_inj['modx']->getConfig('modx_charset'));
        $referer     = htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES, static::$_inj['modx']->getConfig('modx_charset'));
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
        $errortype= array (
            E_ERROR             => "ERROR",
            E_WARNING           => "WARNING",
            E_PARSE             => "PARSING ERROR",
            E_NOTICE            => "NOTICE",
            E_CORE_ERROR        => "CORE ERROR",
            E_CORE_WARNING      => "CORE WARNING",
            E_COMPILE_ERROR     => "COMPILE ERROR",
            E_COMPILE_WARNING   => "COMPILE WARNING",
            E_USER_ERROR        => "USER ERROR",
            E_USER_WARNING      => "USER WARNING",
            E_USER_NOTICE       => "USER NOTICE",
            E_STRICT            => "STRICT NOTICE",
            E_RECOVERABLE_ERROR => "RECOVERABLE ERROR",
            E_DEPRECATED        => "DEPRECATED",
            E_USER_DEPRECATED   => "USER DEPRECATED"
        );

        if(!empty($nr) || !empty($file))
        {
            $str .= '<tr><td colspan="2"><b>PHP error debug</b></td></tr>';
            if ($text != '')
            {
                $str .= '<tr><td colspan="2"><div style="font-weight:bold;border:1px solid #ccc;padding:8px;color:#333;background-color:#ffffcd;">Error : ' . $text . '</div></td></tr>';
            }
            if($output!='')
            {
                $str .= '<tr><td colspan="2"><div style="font-weight:bold;border:1px solid #ccc;padding:8px;color:#333;background-color:#ffffcd;">' . $output . '</div></td></tr>';
            }
            $str .= '<tr><td valign="top">ErrorType[num] : </td>';
            $str .= '<td>' . $errortype [$nr] . "[{$nr}]</td>";
            $str .= '</tr>';
            $str .= "<tr><td>File : </td><td>{$file}</td></tr>";
            $str .= "<tr><td>Line : </td><td>{$line}</td></tr>";
        }

        if ($source != '')
        {
            $str .= "<tr><td>Source : </td><td>{$source}</td></tr>";
        }

        $str .= '<tr><td colspan="2"><b>Basic info</b></td></tr>';

        $str .= '<tr><td valign="top" style="white-space:nowrap;">REQUEST_URI : </td>';
        $str .= "<td>{$request_uri}</td>";
        $str .= '</tr>';

        if(isset($_GET['a']))      $action = $_GET['a'];
        elseif(isset($_POST['a'])) $action = $_POST['a'];
        if(isset($action) && !empty($action))
        {
            include_once(MODX_MANAGER_PATH . 'includes/actionlist.inc.php');
            global $action_list;
            if(isset($action_list[$action])) $actionName = " - {$action_list[$action]}";
            else $actionName = '';
            $str .= '<tr><td valign="top">Manager action : </td>';
            $str .= "<td>{$action}{$actionName}</td>";
            $str .= '</tr>';
        }

        if(preg_match('@^[0-9]+@',static::$_inj['modx']->documentIdentifier))
        {
            $resource  = static::$_inj['modx']->getDocumentObject('id',static::$_inj['modx']->documentIdentifier);
            $url = static::$_inj['modx']->makeUrl(static::$_inj['modx']->documentIdentifier,'','','full');
            $link = '<a href="' . $url . '" target="_blank">' . $resource['pagetitle'] . '</a>';
            $str .= '<tr><td valign="top">Resource : </td>';
            $str .= '<td>[' . static::$_inj['modx']->documentIdentifier . ']' . $link . '</td></tr>';
        }
        if(!empty(static::$_inj['modx']->currentSnippet))
        {
            $str .= "<tr><td>Current Snippet : </td>";
            $str .= '<td>' . static::$_inj['modx']->currentSnippet . '</td></tr>';
        }

        if(!empty(static::$_inj['modx']->event->activePlugin))
        {
            $str .= "<tr><td>Current Plugin : </td>";
            $str .= '<td>' . static::$_inj['modx']->event->activePlugin . '(' . static::$_inj['modx']->event->name . ')' . '</td></tr>';
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

        $totalTime= (static::$_inj['modx']->getMicroTime() - static::$_inj['modx']->tstart);

        $mem = memory_get_peak_usage(true);
        $total_mem = $mem - static::$_inj['modx']->mstart;
        $total_mem = ($total_mem / 1024 / 1024) . ' mb';

        $queryTime= static::$_inj['modx']->queryTime;
        $phpTime= $totalTime - $queryTime;
        $queries= isset (static::$_inj['modx']->executedQueries) ? static::$_inj['modx']->executedQueries : 0;
        $queryTime= sprintf("%2.4f s", $queryTime);
        $totalTime= sprintf("%2.4f s", $totalTime);
        $phpTime= sprintf("%2.4f s", $phpTime);

        $str= str_replace('[^q^]', $queries, $str);
        $str= str_replace('[^qt^]',$queryTime, $str);
        $str= str_replace('[^p^]', $phpTime, $str);
        $str= str_replace('[^t^]', $totalTime, $str);
        $str= str_replace('[^m^]', $total_mem, $str);

        if(isset($php_errormsg) && !empty($php_errormsg)) $str = "<b>{$php_errormsg}</b><br />\n{$str}";
        $str .= '<br />' . $this->get_backtrace(debug_backtrace()) . "\n";

        // Log error
        if(!empty(static::$_inj['modx']->currentSnippet)) $source = 'Snippet - ' . static::$_inj['modx']->currentSnippet;
        elseif(!empty(static::$_inj['modx']->event->activePlugin)) $source = 'Plugin - ' . static::$_inj['modx']->event->activePlugin;
        elseif($source!=='') $source = 'Parser - ' . $source;
        elseif($query!=='')  $source = 'SQL Query';
        else             $source = 'Parser';
        if(isset($actionName) && !empty($actionName)) $source .= $actionName;
        switch($nr)
        {
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

        static::$_inj['modx']->logEvent(0, $error_level, $str,$source);

        if($error_level === 2 && static::$_inj['modx']->error_reporting!=='99') return true;
        if(static::$_inj['modx']->error_reporting==='99' && !isset($_SESSION['mgrValidated'])) return true;

        // Set 500 response header
        if($error_level !== 2) header('HTTP/1.1 500 Internal Server Error');

        // Display error
        if (isset($_SESSION['mgrValidated']))
        {
            echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html><head><title>MODX Content Manager ' . $version . ' &raquo; ' . $release_date . '</title>
	             <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	             <link rel="stylesheet" type="text/css" href="' . static::$_inj['modx']->getConfig('site_manager_url') . 'media/style/' . static::$_inj['modx']->getConfig('manager_theme') . '/style.css" />
	             <style type="text/css">body { padding:10px; } td {font:inherit;}</style>
	             </head><body>
	             ' . $str . '</body></html>';

        }

        else  echo 'Error';
        ob_end_flush();
        exit;
    }
    function get_backtrace($backtrace) {

        $str = "<p><b>Backtrace</b></p>\n";
        $str  .= '<table>';
        $backtrace = array_reverse($backtrace);
        foreach ($backtrace as $key => $val)
        {
            $key++;
            if(substr($val['function'],0,11)==='messageQuit') break;
            elseif(substr($val['function'],0,8)==='phpError') break;
            $path = str_replace('\\','/',$val['file']);
            if(strpos($path,MODX_BASE_PATH)===0) $path = substr($path,strlen(MODX_BASE_PATH));
            switch($val['type'])
            {
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