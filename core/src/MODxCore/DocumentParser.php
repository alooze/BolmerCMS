<?php namespace MODxCore;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 2:51
 */

class DocumentParser {
    var $db; // db object
    var $event, $Event; // event object
    var $pluginEvent;
    var $config= null;
    var $rs;
    var $result;
    var $sql;
    var $table_prefix;
    var $debug;
    var $documentIdentifier;
    var $documentMethod;
    var $documentGenerated;
    var $documentContent;
    var $tstart;
    var $mstart;
    var $minParserPasses;
    var $maxParserPasses;
    var $documentObject;
    var $templateObject;
    var $snippetObjects;
    var $stopOnNotice;
    var $executedQueries;
    var $queryTime;
    var $currentSnippet;
    var $documentName;
    var $aliases;
    var $visitor;
    var $entrypage;
    var $documentListing;
    var $dumpSnippets;
    var $snippetsCode;
    var $snippetsCount=array();
    var $snippetsTime=array();
    var $chunkCache;
    var $snippetCache;
    var $contentTypes;
    var $dumpSQL;
    var $queryCode;
    var $virtualDir;
    var $placeholders;
    var $sjscripts;
    var $jscripts;
    var $loadedjscripts;
    var $documentMap;
    var $forwards= 3;
    var $error_reporting;
    var $dumpPlugins;
    var $pluginsCode;
    var $pluginsTime=array();
    var $aliasListing;
    private $version=array();
    /**
     * @var \MODxCore\Pimple
     */
    protected $_pimple = null;
    /**
     * Document constructor
     *
     * @return DocumentParser
     */
    public function __construct() {
        $pimple = \MODxCore\Pimple::getInstance();
        $pimple['modx'] = $this;
        $pimple['debug'] = function($inj){
            return new \MODxCore\Debug($inj);
        };
        $pimple['db'] = function($inj){
            return $inj['modx']->db;
        };
        $pimple['config'] = function($inj){
            return $inj['modx']->config;
        };
        $pimple['response'] = function($inj){
            return new \MODxCore\Response($inj);
        };
        $pimple['HTML'] = function($inj){
            return new \MODxCore\HTML($inj);
        };
        $pimple['snippet'] = function($inj){
            return new \MODxCore\Parser\Snippet($inj);
        };
        $pimple['document'] = function($inj){
            return new \MODxCore\Document($inj);
        };
        $pimple['log'] = function($inj){
            return new \MODxCore\Log($inj);
        };
        $pimple['request'] = function($inj){
            return new \MODxCore\Request($inj);
        };
        $pimple['parser'] = function($inj){
            return new \MODxCore\Parser($inj);
        };
        $pimple['plugin'] = function($inj){
            return new \MODxCore\Parser\Plugin($inj);
        };
        $pimple['cache'] = function($inj){
            return new \MODxCore\Cache($inj);
        };

        if(substr(PHP_OS,0,3) === 'WIN' && $pimple['global_config']['database_server']==='localhost'){
            //Global config as Object
            $pimple['global_config']['database_server'] = '127.0.0.1';
        }
        $this->_pimple = $pimple;
        $this->loadExtension('DBAPI') or die('Could not load DBAPI class.'); // load DBAPI class
        $this->dbConfig = &$this->db->config; // alias for backward compatibility
        $this->jscripts = array ();
        $this->sjscripts = array ();
        $this->loadedjscripts = array ();
        // events
        $this->event= new \SystemEvent();
        $this->Event = &$this->event; //alias for backward compatibility
        $this->pluginEvent= array ();
        // set track_errors ini variable
        @ini_set("track_errors", "1"); // enable error tracking in $php_errormsg
        $this->error_reporting = 1;
    }

    function __call($name,$args) {
        include_once(MODX_MANAGER_PATH . 'includes/extenders/deprecated.functions.inc.php');
        if(method_exists($this->old,$name)) return call_user_func_array(array($this->old,$name),$args);
    }

    /**
     * Loads an extension from the extenders folder.
     * Currently of limited use - can only load the DBAPI and ManagerAPI.
     *
     * @global string $database_type
     * @param string $extnamegetAllChildren
     * @return boolean
     */
    function loadExtension($extname) {
        switch ($extname) {
            // Database API
            case 'DBAPI' :
                $this->db = new \MODxCore\Db\DBAPI;
                return true;
                break;
            // Manager API
            case 'ManagerAPI' :
                if (!include_once MODX_MANAGER_PATH . 'includes/extenders/manager.api.class.inc.php')
                    return false;
                $this->manager= new \ManagerAPI;
                return true;
                break;
            // PHPMailer
            case 'MODxMailer' :
                include_once(MODX_MANAGER_PATH . 'includes/extenders/modxmailer.class.inc.php');
                $this->mail= new \MODxMailer;
                if($this->mail) return true;
                else            return false;
                break;
            case 'EXPORT_SITE' :
                if(include_once(MODX_MANAGER_PATH . 'includes/extenders/export.class.inc.php'))
                {
                    $this->export= new \EXPORT_SITE;
                    return true;
                }
                else return false;
                break;
            default :
                return false;
        }
    }

    function getTimerStats($tstart) {
        $stats = array();

        $stats['totalTime'] = ($this->getMicroTime() - $tstart);
        $stats['queryTime'] = $this->queryTime;
        $stats['phpTime'] = $stats['totalTime'] - $stats['queryTime'];

        $stats['queryTime'] = sprintf("%2.4f s", $stats['queryTime']);
        $stats['totalTime'] = sprintf("%2.4f s", $stats['totalTime']);
        $stats['phpTime'] = sprintf("%2.4f s", $stats['phpTime']);
        $stats['source'] = $this->documentGenerated == 1 ? "database" : "cache";
        $stats['queries'] = isset ($this->executedQueries) ? $this->executedQueries : 0;
        $stats['phpMemory'] = (memory_get_peak_usage(true) / 1024 / 1024) . " mb";

        return $stats;
    }

    function sendmail($params=array(), $msg='')
    {
        if(isset($params) && is_string($params))
        {
            if(strpos($params,'=')===false)
            {
                if(strpos($params,'@')!==false) $p['to']      = $params;
                else                            $p['subject'] = $params;
            }
            else
            {
                $params_array = explode(',',$params);
                foreach($params_array as $k=>$v)
                {
                    $k = trim($k);
                    $v = trim($v);
                    $p[$k] = $v;
                }
            }
        }
        else
        {
            $p = $params;
            unset($params);
        }
        if(isset($p['sendto'])) $p['to'] = $p['sendto'];

        if(isset($p['to']) && preg_match('@^[0-9]+$@',$p['to']))
        {
            $userinfo = $this->getUserInfo($p['to']);
            $p['to'] = $userinfo['email'];
        }
        if(isset($p['from']) && preg_match('@^[0-9]+$@',$p['from']))
        {
            $userinfo = $this->getUserInfo($p['from']);
            $p['from']     = $userinfo['email'];
            $p['fromname'] = $userinfo['username'];
        }
        if($msg==='' && !isset($p['body']))
        {
            $p['body'] = $_SERVER['REQUEST_URI'] . "\n" . $_SERVER['HTTP_USER_AGENT'] . "\n" . $_SERVER['HTTP_REFERER'];
        }
        elseif(is_string($msg) && 0<strlen($msg)) $p['body'] = $msg;

        $this->loadExtension('MODxMailer');
        $sendto = (!isset($p['to']))   ? $this->getConfig('emailsender')  : $p['to'];
        $sendto = explode(',',$sendto);
        foreach($sendto as $address)
        {
            list($name, $address) = $this->mail->address_split($address);
            $this->mail->AddAddress($address,$name);
        }
        if(isset($p['cc']))
        {
            $p['cc'] = explode(',',$sendto);
            foreach($p['cc'] as $address)
            {
                list($name, $address) = $this->mail->address_split($address);
                $this->mail->AddCC($address,$name);
            }
        }
        if(isset($p['bcc']))
        {
            $p['bcc'] = explode(',',$sendto);
            foreach($p['bcc'] as $address)
            {
                list($name, $address) = $this->mail->address_split($address);
                $this->mail->AddBCC($address,$name);
            }
        }
        if(isset($p['from'])) list($p['fromname'],$p['from']) = $this->mail->address_split($p['from']);
        $this->mail->From     = (!isset($p['from']))  ? $this->getConfig('emailsender')  : $p['from'];
        $this->mail->FromName = (!isset($p['fromname'])) ? $this->getConfig('site_name') : $p['fromname'];
        $this->mail->Subject  = (!isset($p['subject']))  ? $this->getConfig('emailsubject') : $p['subject'];
        $this->mail->Body     = $p['body'];
        $rs = $this->mail->send();
        return $rs;
    }

    /**
     * Returns the MODX version information as version, branch, release date and full application name.
     *
     * @return array
     */
    function getVersionData($data=null) {
        $out=array();
        if(empty($this->version) || !is_array($this->version)){
            //include for compatibility modx version < 1.0.10
            include MODX_MANAGER_PATH . "includes/version.inc.php";
            $this->version=array();
            $this->version['version']= isset($modx_version) ? $modx_version : '';
            $this->version['branch']= isset($modx_branch) ? $modx_branch : '';
            $this->version['release_date']= isset($modx_release_date) ? $modx_release_date : '';
            $this->version['full_appname']= isset($modx_full_appname) ? $modx_full_appname : '';
            $this->version['new_version'] = $this->getConfig('newversiontext');
        }
        return (!is_null($data) && is_array($this->version) && isset($this->version[$data])) ? $this->version[$data] : $this->version;
    }

    /**
     * Returns an entry from the config
     *
     * Note: most code accesses the config array directly and we will continue to support this.
     *
     * @return boolean|string
     */
    function getConfig($name= '', $default = false) {
        return getkey($this->config, $name, $default);
    }

    /**
     * Get MODX settings including, but not limited to, the system_settings table
     */
    function getSettings() {
        $tbl_system_settings   = $this->getFullTableName('system_settings');
        $tbl_web_user_settings = $this->getFullTableName('web_user_settings');
        $tbl_user_settings     = $this->getFullTableName('user_settings');
        if (!is_array($this->config) || empty ($this->config)) {
            if ($included= file_exists(MODX_BASE_PATH . 'assets/cache/siteCache.idx.php')) {
                $included= include_once (MODX_BASE_PATH . 'assets/cache/siteCache.idx.php');
            }
            if (!$included || !is_array($this->config) || empty ($this->config)) {
                include_once(MODX_MANAGER_PATH . 'processors/cache_sync.class.processor.php');
                $cache = new \synccache();
                $cache->setCachepath(MODX_BASE_PATH . "assets/cache/");
                $cache->setReport(false);
                $rebuilt = $cache->buildCache($this);
                $included = false;
                if($rebuilt && $included= file_exists(MODX_BASE_PATH . 'assets/cache/siteCache.idx.php')) {
                    $included= include MODX_BASE_PATH . 'assets/cache/siteCache.idx.php';
                }
                if(!$included) {
                    $result= $this->db->select('setting_name, setting_value', $tbl_system_settings);
                    while ($row= $this->db->getRow($result, 'both')) {
                        $this->config[$row[0]]= $row[1];
                    }
                }
            }

            // added for backwards compatibility - garry FS#104
            $this->config['etomite_charset'] = & $this->config['modx_charset'];

            // store base_url and base_path inside config array
            $this->config['base_url'] = MODX_BASE_URL;
            $this->config['base_path'] = MODX_BASE_PATH;
            $this->config['site_url'] = MODX_SITE_URL;
            $this->config['valid_hostnames'] = MODX_SITE_HOSTNAMES;
            $this->config['site_manager_url'] = MODX_MANAGER_URL;
            $this->config['site_manager_path'] = MODX_MANAGER_PATH;

            // load user setting if user is logged in
            $usrSettings= array ();
            if ($id= $this->getLoginUserID()) {
                $usrType= $this->getLoginUserType();
                if (isset ($usrType) && $usrType == 'manager')
                    $usrType= 'mgr';

                if ($usrType == 'mgr' && $this->isBackend()) {
                    // invoke the OnBeforeManagerPageInit event, only if in backend
                    $this->invokeEvent("OnBeforeManagerPageInit");
                }

                if (isset ($_SESSION[$usrType . 'UsrConfigSet'])) {
                    $usrSettings= & $_SESSION[$usrType . 'UsrConfigSet'];
                } else {
                    if ($usrType == 'web')
                    {
                        $from = $tbl_web_user_settings;
                        $where = "webuser='{$id}'";
                    }
                    else
                    {
                        $from = $tbl_user_settings;
                        $where = "user='{$id}'";
                    }
                    $result= $this->db->select('setting_name, setting_value', $from, $where);
                    while ($row= $this->db->getRow($result, 'both'))
                        $usrSettings[$row[0]]= $row[1];
                    if (isset ($usrType))
                        $_SESSION[$usrType . 'UsrConfigSet']= $usrSettings; // store user settings in session
                }
            }
            if ($this->isFrontend() && $mgrid= $this->getLoginUserID('mgr')) {
                $musrSettings= array ();
                if (isset ($_SESSION['mgrUsrConfigSet'])) {
                    $musrSettings= & $_SESSION['mgrUsrConfigSet'];
                } else {
                    if ($result= $this->db->select('setting_name, setting_value', $tbl_user_settings, "user='{$mgrid}'")) {
                        while ($row= $this->db->getRow($result, 'both')) {
                            $usrSettings[$row[0]]= $row[1];
                        }
                        $_SESSION['mgrUsrConfigSet']= $musrSettings; // store user settings in session
                    }
                }
                if (!empty ($musrSettings)) {
                    $usrSettings= array_merge($musrSettings, $usrSettings);
                }
            }
            $this->error_reporting = $this->getConfig('error_reporting');
            $this->config['filemanager_path'] = str_replace('[(base_path)]',MODX_BASE_PATH, $this->getConfig('filemanager_path'));
            $this->config['rb_base_dir']      = str_replace('[(base_path)]',MODX_BASE_PATH, $this->getConfig('rb_base_dir'));
            $this->config= array_merge($this->config, $usrSettings);
        }
    }

    /**
     * Create an URL for the given document identifier. The url prefix and
     * postfix are used, when friendly_url is active.
     *
     * @param int $id The document identifier
     * @param string $alias The alias name for the document
     *                      Default: Empty string
     * @param string $args The paramaters to add to the URL
     *                     Default: Empty string
     * @param string $scheme With full as valus, the site url configuration is
     *                       used
     *                       Default: Empty string
     * @return string
     */
    function makeUrl($id, $alias= '', $args= '', $scheme= '') {
        $url= '';
        $virtualDir= '';
        $f_url_prefix = $this->getConfig('friendly_url_prefix');
        $f_url_suffix = $this->getConfig('friendly_url_suffix');
        if (!is_numeric($id)) {
            $this->messageQuit('`' . $id . '` is not numeric and may not be passed to makeUrl()');
        }
        if ($args != '' && $this->getConfig('friendly_urls') == 1) {
            // add ? to $args if missing
            $c= substr($args, 0, 1);
            if (strpos($f_url_prefix, '?') === false) {
                if ($c == '&')
                    $args= '?' . substr($args, 1);
                elseif ($c != '?') $args= '?' . $args;
            } else {
                if ($c == '?')
                    $args= '&' . substr($args, 1);
                elseif ($c != '&') $args= '&' . $args;
            }
        }
        elseif ($args != '') {
            // add & to $args if missing
            $c= substr($args, 0, 1);
            if ($c == '?')
                $args= '&' . substr($args, 1);
            elseif ($c != '&') $args= '&' . $args;
        }
        if ($this->getConfig('friendly_urls') == 1 && $alias != '') {
            $url= $f_url_prefix . $alias . $f_url_suffix . $args;
        }
        elseif ($this->getConfig('friendly_urls') == 1 && $alias == '') {
            $alias= $id;
            if ($this->getConfig('friendly_alias_urls') == 1) {
                $al= $this->aliasListing[$id];
                if($al['isfolder']===1 && $this->getConfig('make_folders')==='1')
                    $f_url_suffix = '/';
                $alPath= !empty ($al['path']) ? $al['path'] . '/' : '';
                if ($al && $al['alias'])
                    $alias= $al['alias'];
            }
            $alias= $alPath . $f_url_prefix . $alias . $f_url_suffix;
            $url= $alias . $args;
        } else {
            $url= 'index.php?id=' . $id . $args;
        }

        $host= $this->getConfig('base_url');
        // check if scheme argument has been set
        if ($scheme != '') {
            // for backward compatibility - check if the desired scheme is different than the current scheme
            if (is_numeric($scheme) && $scheme != $_SERVER['HTTPS']) {
                $scheme= ($_SERVER['HTTPS'] ? 'http' : 'https');
            }

            // to-do: check to make sure that $site_url incudes the url :port (e.g. :8080)
            $host= $scheme == 'full' ? $this->getConfig('site_url') : $scheme . '://' . $_SERVER['HTTP_HOST'] . $host;
        }

        //fix strictUrl by Bumkaka
        if ($this->getConfig('seostrict')=='1'){
            $url = $this->toAlias($url);
        }
        if ($this->getConfig('xhtml_urls')) {
            return preg_replace("/&(?!amp;)/","&amp;", $host . $virtualDir . $url);
        } else {
            return $host . $virtualDir . $url;
        }
    }

    /**
     * Returns the manager relative URL/path with respect to the site root.
     *
     * @global string $base_url
     * @return string The complete URL to the manager folder
     */
    function getManagerPath() {
        return MODX_MANAGER_URL;
    }

    /**
     * Create a 'clean' document identifier with path information, friendly URL suffix and prefix.
     *
     * @param string $qOrig
     * @return string
     */
    function cleanDocumentIdentifier($qOrig) {
        (!empty($qOrig)) or $qOrig = $this->getConfig('site_start');
        $q= $qOrig;
        /* First remove any / before or after */
        if ($q[strlen($q) - 1] == '/')
            $q= substr($q, 0, -1);
        if ($q[0] == '/')
            $q= substr($q, 1);
        /* Save path if any */
        /* FS#476 and FS#308: only return virtualDir if friendly paths are enabled */
        if ($this->getConfig('use_alias_path') == 1) {
            $this->virtualDir= dirname($q);
            $this->virtualDir= ($this->virtualDir == '.' ? '' : $this->virtualDir);
            $q= basename($q);
        } else {
            $this->virtualDir= '';
        }
        $q= str_replace($this->getConfig('friendly_url_prefix'), "", $q);
        $q= str_replace($this->getConfig('friendly_url_suffix'), "", $q);
        if (is_numeric($q) && !isset($this->documentListing[$q])) { /* we got an ID returned, check to make sure it's not an alias */
            /* FS#476 and FS#308: check that id is valid in terms of virtualDir structure */
            if ($this->getConfig('use_alias_path') == 1) {
                if ((($this->virtualDir != '' && !isset($this->documentListing[$this->virtualDir . '/' . $q])) || ($this->virtualDir == '' && !isset($this->documentListing[$q]))) && (($this->virtualDir != '' && isset($this->documentListing[$this->virtualDir]) && in_array($q, $this->getChildIds($this->documentListing[$this->virtualDir], 1))) || ($this->virtualDir == '' && in_array($q, $this->getChildIds(0, 1))))) {
                    $this->documentMethod= 'id';
                    return $q;
                } else { /* not a valid id in terms of virtualDir, treat as alias */
                    $this->documentMethod= 'alias';
                    return $q;
                }
            } else {
                $this->documentMethod= 'id';
                return $q;
            }
        } else { /* we didn't get an ID back, so instead we assume it's an alias */
            if ($this->getConfig('friendly_alias_urls') != 1) {
                $q= $qOrig;
            }
            $this->documentMethod= 'alias';
            return $q;
        }
    }

    /**
     * Starts the parsing operations.
     *
     * - connects to the db
     * - gets the settings (including system_settings)
     * - gets the document/resource identifier as in the query string
     * - finally calls prepareResponse()
     */
    function executeParser() {

        //error_reporting(0);
        set_error_handler(array (
            & $this,
            "phpError"
        ), E_ALL);

        //$this->db->connect();

        // get the settings
        if (empty ($this->config)) {
            $this->getSettings();
        }

        // IIS friendly url fix
        if ($this->getConfig('friendly_urls') == 1 && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false) {
            $url= $_SERVER['QUERY_STRING'];
            $err= substr($url, 0, 3);
            if ($err == '404' || $err == '405') {
                $k= array_keys($_GET);
                unset ($_GET[$k[0]]);
                unset ($_REQUEST[$k[0]]); // remove 404,405 entry
                $qp= parse_url(str_replace($this->getConfig('site_url'), '', substr($url, 4)));
                $_SERVER['QUERY_STRING'] = $qp['query'];
                if (!empty ($qp['query'])) {
                    parse_str($qp['query'], $qv);
                    foreach ($qv as $n => $v)
                        $_REQUEST[$n]= $_GET[$n]= $v;
                }
                $_SERVER['PHP_SELF']= $this->getConfig('base_url') . $qp['path'];
                $_REQUEST['q']= $_GET['q']= $qp['path'];
            }
        }

        // check site settings
        if (!$this->checkSiteStatus()) {
            header('HTTP/1.0 503 Service Unavailable');
            if (!$this->getConfig('site_unavailable_page')) {
                // display offline message
                $this->documentContent= $this->getConfig('site_unavailable_message');
                $this->outputContent();
                exit; // stop processing here, as the site's offline
            } else {
                // setup offline page document settings
                $this->documentMethod= "id";
                $this->documentIdentifier= $this->getConfig('site_unavailable_page');
            }
        } else {
            // make sure the cache doesn't need updating
            $this->checkPublishStatus();

            // find out which document we need to display
            $this->documentMethod= $this->getDocumentMethod();
            $this->documentIdentifier= $this->getDocumentIdentifier($this->documentMethod);
        }

        if ($this->documentMethod == "none") {
            $this->documentMethod= "id"; // now we know the site_start, change the none method to id
        }

        if ($this->documentMethod == "alias") {
            $this->documentIdentifier= $this->cleanDocumentIdentifier($this->documentIdentifier);

            // Check use_alias_path and check if $this->virtualDir is set to anything, then parse the path
            if ($this->getConfig('use_alias_path') == 1) {
                $alias= (strlen($this->virtualDir) > 0 ? $this->virtualDir . '/' : '') . $this->documentIdentifier;
                if (isset($this->documentListing[$alias])) {
                    $this->documentIdentifier= $this->documentListing[$alias];
                } else {
                    //@TODO: check new $alias;
                    $this->sendErrorPage();
                }
            } else {
                if (isset($this->documentListing[$this->documentIdentifier])) {
                    $this->documentIdentifier = $this->documentListing[$this->documentIdentifier];
                } else {
                    $this->documentIdentifier = (int) $this->documentIdentifier;
                }
            }
            $this->documentMethod= 'id';
        }

        //$this->_fixURI();
        // invoke OnWebPageInit event
        $this->invokeEvent("OnWebPageInit");
        // invoke OnLogPageView event
        if ($this->getConfig('track_visitors') == 1) {
            $this->invokeEvent("OnLogPageHit");
        }
        if($this->getConfig('seostrict')==='1') $this->sendStrictURI();
        $this->prepareResponse();
    }






    public function htmlspecialchars($text, $flags = ENT_COMPAT){
        return \MODxCore\Helper::htmlchars($text, $flags);
    }
    function checkCache($id) {
        return $this->_pimple['cache']->checkCache($id);
    }
    function clearCache($type='', $report=false) {
        return $this->_pimple['cache']->clearCache($type, $report);
    }
    function getCachePath() {
        return $this->_pimple['cache']->getCachePath();
    }
    function getFullTableName($tbl) {
        return \MODxCore\Helper::getFullTableName($tbl, $this->db->config);
    }
    function rewriteUrls($documentSource) {
        return $this->_pimple['parser']->rewriteUrls($documentSource);
    }
    function isBackend() {
        return $this->_pimple['response']->isBackend();
    }
    function isFrontend() {
        return $this->_pimple['response']->isFrontend();
    }
    function insideManager() {
        return $this->_pimple['response']->insideManager();
    }
    function sendStrictURI(){
        return $this->_pimple['response']->sendStrictURI();
    }
    function outputContent($noEvent= false) {
        return $this->_pimple['response']->outputContent($noEvent);
    }
    function checkSiteStatus() {
        return $this->_pimple['response']->checkSiteStatus();
    }
    function checkPublishStatus() {
        return $this->_pimple['response']->checkPublishStatus();
    }
    function phpError($nr, $text, $file, $line) {
        $this->_pimple['debug']->phpError($nr, $text, $file, $line);
    }
    function detectError($error) {
        $this->_pimple['debug']->detectError($error);
    }
    function messageQuit($msg= 'unspecified error', $query= '', $is_error= true, $nr= '', $file= '', $source= '', $text= '', $line= '', $output='') {
        $this->_pimple['debug']->messageQuit($msg, $query, $is_error, $nr, $file, $source, $text, $line, $output);
    }
    function get_backtrace($backtrace) {
        return $this->_pimple['debug']->get_backtrace($backtrace);
    }
    function postProcess() {
        $this->_pimple['response']->postProcess();
    }
    function parseDocumentSource($source) {
        return $this->_pimple['parser']->parseDocumentSource($source);
    }
    function prepareResponse() {
        $this->_pimple['response']->prepareResponse();
    }
    function evalPlugin($pluginCode, $params) {
        $this->_pimple['plugin']->evalPlugin($pluginCode, $params);
    }
    function addEventListener($evtName, $pluginName) {
        return $this->_pimple['plugin']->addEventListener($evtName, $pluginName);
    }
    function removeEventListener($evtName) {
        return $this->_pimple['plugin']->removeEventListener($evtName);
    }
    function removeAllEventListener() {
        $this->_pimple['plugin']->removeAllEventListener();
    }
    function invokeEvent($evtName, $extParams= array ()) {
        return $this->_pimple['plugin']->invokeEvent($evtName, $extParams);
    }
    function mergeDocumentContent($content) {
        return $this->_pimple['parser']->mergeDocumentContent($content);
    }
    function mergeSettingsContent($content) {
        return $this->_pimple['parser']->mergeSettingsContent($content);
    }
    function mergeChunkContent($content) {
        return $this->_pimple['parser']->mergeChunkContent($content);
    }
    function mergePlaceholderContent($content){
        return $this->_pimple['parser']->mergePlaceholderContent($content);
    }
    function getRegisteredClientStartupScripts() {
        return $this->_pimple['HTML']->getRegisteredClientStartupScripts();
    }
    function getDocumentIdentifier($method) {
        return  $this->_pimple['request']->getDocumentIdentifier($method);
    }
    function sendAlert($type, $to, $from, $subject, $msg, $private= 0) {
        return \MODxCore\User\Manager::sendAlert($type, $to, $from, $subject, $msg, $private);
    }
    function getIdFromAlias($alias){
        return $this->_pimple['document']->getIdFromAlias($alias);
    }
    function stripAlias($alias) {
        return $this->_pimple['document']->stripAlias($alias);
    }
    function getDocumentMethod() {
        return $this->_pimple['request']->getDocumentMethod();
    }
    function logEvent($evtid, $type, $msg, $source= 'Parser') {
        $this->_pimple['log']->logEvent($evtid, $type, $msg, $source);
    }
    function rotate_log($target='event_log',$limit=3000, $trim=100){
        $this->_pimple['log']->rotate_log($target, $limit, $trim);
    }
    function getRegisteredClientScripts() {
        return $this->_pimple['HTML']->getRegisteredClientScripts();
    }
    function getDocumentChildrenTVars($parentid= 0, $tvidnames= array (), $published= 1, $docsort= "menuindex", $docsortdir= "ASC", $tvfields= "*", $tvsort= "rank", $tvsortdir= "ASC") {
        return $this->_pimple['document']->getDocumentChildrenTVars($parentid, $tvidnames, $published, $docsort, $docsortdir, $tvfields, $tvsort, $tvsortdir);
    }
    function getDocumentChildrenTVarOutput($parentid= 0, $tvidnames= array (), $published= 1, $docsort= "menuindex", $docsortdir= "ASC") {
        return $this->_pimple['document']->getDocumentChildrenTVarOutput($parentid, $tvidnames, $published, $docsort, $docsortdir);
    }
    function getTemplateVar($idname= "", $fields= "*", $docid= "", $published= 1) {
        return $this->_pimple['document']->getTemplateVar($idname, $fields, $docid, $published);
    }
    function getTemplateVars($idnames= array (), $fields= "*", $docid= "", $published= 1, $sort= "rank", $dir= "ASC") {
        return $this->_pimple['document']->getTemplateVar($idnames, $fields, $docid, $published, $sort, $dir);
    }
    function getTemplateVarOutput($idnames= array (), $docid= "", $published= 1, $sep='') {
        return $this->_pimple['document']->getTemplateVarOutput($idnames, $docid, $published, $sep);
    }
    function getDocumentObject($method, $identifier, $isPrepareResponse=false) {
        return $this->_pimple['document']->getDocumentObject($method, $identifier, $isPrepareResponse);
    }
    function toDateFormat($timestamp = 0, $mode = '') {
        return \MODxCore\Helper::toDateFormat($timestamp, $mode);
    }
    function toTimeStamp($str) {
        return \MODxCore\Helper::toTimeStamp($str);
    }
    function regClientCSS($src, $media='') {
        return $this->_pimple['HTML']->regClientCSS($src, $media);
    }
    function regClientStartupScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false)) {
        return $this->_pimple['HTML']->regClientCSS($src, $options);
    }
    function regClientScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false), $startup= false) {
        return $this->_pimple['HTML']->regClientScript($src, $options, $startup);
    }
    function regClientStartupHTMLBlock($html) {
        return $this->_pimple['HTML']->regClientStartupHTMLBlock($html);
    }
    function regClientHTMLBlock($html) {
        return $this->_pimple['HTML']->regClientHTMLBlock($html);
    }
    function getAllChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle') {
        return $this->_pimple['document']->getAllChildren($id, $sort, $dir, $fields);
    }
    function getActiveChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle') {
        return $this->_pimple['document']->getActiveChildren($id, $sort, $dir, $fields);
    }
    function getDocumentChildren($parentid= 0, $published= 1, $deleted= 0, $fields= "*", $where= '', $sort= "menuindex", $dir= "ASC", $limit= "") {
        return $this->_pimple['document']->getDocumentChildren($parentid, $published, $deleted, $fields, $where, $sort, $dir, $limit);
    }
    function getDocuments($ids= array (), $published= 1, $deleted= 0, $fields= "*", $where= '', $sort= "menuindex", $dir= "ASC", $limit= "") {
        return $this->_pimple['document']->getDocuments($ids, $published, $deleted, $fields, $where, $sort, $dir, $limit);
    }
    function getDocument($id= 0, $fields= "*", $published= 1, $deleted= 0) {
        return $this->_pimple['document']->getDocument($id, $fields, $published, $deleted);
    }
    function getPageInfo($pageid= -1, $active= 1, $fields= 'id, pagetitle, description, alias') {
        return $this->_pimple['document']->getPageInfo($pageid, $active, $fields);
    }
    function getParent($pid= -1, $active= 1, $fields= 'id, pagetitle, description, alias, parent') {
        return $this->_pimple['document']->getParent($pid, $active, $fields);
    }
    function getSnippetId() {
        return $this->_pimple['snippet']->getSnippetId();
    }
    function getSnippetName() {
        return $this->_pimple['snippet']->getSnippetName();
    }
    function runSnippet($snippetName, $params= array ()) {
        return $this->_pimple['snippet']->runSnippet($snippetName, $params);
    }
    // deprecated
    function putChunk($chunkName) {
        return \MODxCore\Parser::getChunk($chunkName);
    }
    function getChunk($chunkName) {
        return \MODxCore\Parser::getChunk($chunkName);
    }
    function parseText($chunk, $chunkArr, $prefix = '[+', $suffix = '+]'){
        return \MODxCore\Parser::parseText($chunk, $chunkArr, $prefix, $suffix);
    }
    function parseChunk($chunkName, $chunkArr, $prefix = '{', $suffix = '}'){
        return \MODxCore\Parser::parseChunk($chunkName, $chunkArr, $prefix, $suffix);
    }
    function nicesize($size) {
        return \MODxCore\Helper::nicesize($size);
    }
    function getTagsFromContent($content,$left='[+',$right='+]') {
        return \MODxCore\Parser::getTagsFromContent($content,$left,$right);
    }
    function getPlaceholder($name) {
        return \MODxCore\Parser::getPlaceholder($name);
    }
    function setPlaceholder($name, $value) {
        return \MODxCore\Parser::setPlaceholder($name, $value);
    }
    function toPlaceholders($subject, $prefix= '') {
        \MODxCore\Parser::toPlaceholders($subject, $prefix);
    }
    function toPlaceholder($key, $value, $prefix= '') {
        \MODxCore\Parser::toPlaceholder($key, $value, $prefix);
    }
    function getLoginUserID($context= '') {
        return \MODxCore\User::getLoginUserID($context);
    }
    function getLoginUserName($context= '') {
        return \MODxCore\User::getLoginUserName($context);
    }
    function getLoginUserType() {
        return \MODxCore\User::getLoginUserType();
    }
    function getUserInfo($uid) {
        return \MODxCore\User::getUserInfo($uid);
    }
    function getWebUserInfo($uid) {
        return \MODxCore\User::getUserInfo($uid);
    }
    function getUserDocGroups($resolveIds= false) {
        return \MODxCore\User::getUserInfo($resolveIds);
    }
    function changeWebUserPassword($oldPwd, $newPwd) {
        return \MODxCore\User::changeWebUserPassword($oldPwd, $newPwd);
    }
    function isMemberOfWebGroup($groupNames= array ()) {
        return \MODxCore\User::isMemberOfWebGroup($groupNames);
    }
    function stripTags($html, $allowed= "") {
        return \MODxCore\Helper::stripTags($html, $allowed);
    }
    function jsonDecode($json, $assoc = false) {
        return \MODxCore\Lib\json::jsonDecode($json, array('assoc' => $assoc));
    }
    function parseProperties($propertyString) {
        return \MODxCore\Parser::parseProperties($propertyString);
    }
    function getParentIds($id, $height= 10) {
        return $this->_pimple['document']->getParentIds($id, $height);
    }
    function getChildIds($id, $depth= 10, $children= array ()) {
        return $this->_pimple['document']->getChildIds($id, $depth, $children);
    }
    function webAlert($msg, $url= "") {
        return $this->_pimple['HTML']->webAlert($msg, $url);
    }
    function hasPermission($pm) {
        return \MODxCore\User\Manager::hasPermission($pm);
    }
    function evalSnippet($snippet, $params) {
        return $this->_pimple['snippet']->evalSnippet($snippet, $params);
    }
    function evalSnippets($documentSource) {
        return $this->_pimple['snippet']->evalSnippets($documentSource);
    }
    function checkSession() {
        return \MODxCore\User\Manager::checkSession();
    }
    function checkPreview() {
        return \MODxCore\Helper::checkPreview();
    }
    function getMicroTime() {
        return \MODxCore\Helper::getMicroTime();
    }
    function sendRedirect($url, $count_attempts= 0, $type= '', $responseCode= '') {
        return $this->_pimple['response']->sendRedirect($url, $count_attempts, $type, $responseCode);
    }
    function sendForward($id, $responseCode= '') {
        return $this->_pimple['response']->sendForward($id, $responseCode);
    }
    function sendErrorPage() {
        return $this->_pimple['response']->sendErrorPage();
    }
    function sendUnauthorizedPage() {
        $this->_pimple['response']->sendUnauthorizedPage();
    }
}