<?php namespace Bolmer;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 2:51
 */

class Core {
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
    var $pluginCache;
    var $contentTypes;
    var $dumpSQL;
    var $virtualDir;
    var $placeholders;
    var $sjscripts;
    var $jscripts;
    var $loadedjscripts;
    var $documentMap;
    var $forwards= 3;
    var $error_reporting;
    var $dbConfig;
    var $dumpPlugins;
    var $pluginsCode;
    var $pluginsTime=array();
    var $aliasListing;
    private $version=array();
    /**
     * @var \Bolmer\Service
     */
    public $_service = null;
    /**
     * Document constructor
     *
     * @return \Bolmer\Core
     */
    public function __construct() {
        $service = \Bolmer\Service::getInstance();
        $service->collection['core'] = $this;

        $config = $service->collection['global_config'];
        if(substr(PHP_OS,0,3) === 'WIN' && $config['database_server']==='localhost'){
            //Global config as Object
            $config['database_server'] = '127.0.0.1';
            $service->collection['global_config'] = $config;
        }
        $this->_service = &$service;
        $this->loadExtension('DBAPI') or die('Could not load DBAPI class.'); // load DBAPI class
        $this->dbConfig= & $this->db->config;

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
        include_once(BOLMER_MANAGER_PATH . 'includes/extenders/deprecated.functions.inc.php');
        if(method_exists($this->old,$name)) return call_user_func_array(array($this->old,$name),$args);
    }
    public function __get($name){
        $out = null;
        switch($name){
            case 'queryCode':{
                $out = $this->_service->get('debug')->showQuery();
                break;
            }
        }
        return $out;
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
                $this->db = new \Bolmer\DB;
                return true;
                break;
            // Manager API
            case 'ManagerAPI' :
                if (!include_once BOLMER_MANAGER_PATH . 'includes/extenders/manager.api.class.inc.php')
                    return false;
                $this->manager= new \ManagerAPI;
                return true;
                break;
            // PHPMailer
            case 'MODxMailer' :
                include_once(BOLMER_MANAGER_PATH . 'includes/extenders/modxmailer.class.inc.php');
                $this->mail= new \MODxMailer;
                if($this->mail) return true;
                else            return false;
                break;
            case 'EXPORT_SITE' :
                if(include_once(BOLMER_MANAGER_PATH . 'includes/extenders/export.class.inc.php'))
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
            include_once BOLMER_MANAGER_PATH . "includes/version.inc.php";
            $this->version=array();
            $this->version['version']= CMS_RELEASE_VERSION;
            $this->version['branch']= CMS_NAME;
            $this->version['release_date']= CMS_RELEASE_DATE;
            $this->version['full_appname']= CMS_FULL_APPNAME;
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
        if (!is_array($this->config) || empty ($this->config)) {
            if ($included= file_exists(BOLMER_BASE_PATH . 'assets/cache/siteCache.idx.php')) {
                $included= include_once (BOLMER_BASE_PATH . 'assets/cache/siteCache.idx.php');
            }
            if (!$included || !is_array($this->config) || empty ($this->config)) {
                include_once(BOLMER_MANAGER_PATH . 'processors/cache_sync.class.processor.php');
                $cache = new \synccache();
                $cache->setCachepath(BOLMER_BASE_PATH . "assets/cache/");
                $cache->setReport(false);
                $rebuilt = $cache->buildCache($this);
                $included = false;
                if($rebuilt && $included= file_exists(BOLMER_BASE_PATH . 'assets/cache/siteCache.idx.php')) {
                    $included= include BOLMER_BASE_PATH . 'assets/cache/siteCache.idx.php';
                }
                if(!$included) {
                    $result= $this->db->select('setting_name, setting_value', $this->getFullTableName('system_settings'));
                    while ($row= $this->db->getRow($result, 'both')) {
                        $this->config[$row[0]]= $row[1];
                    }
                }
            }

            // added for backwards compatibility - garry FS#104
            $this->config['etomite_charset'] = & $this->config['modx_charset'];

            // store base_url and base_path inside config array
            $this->config['base_url'] = BOLMER_BASE_URL;
            $this->config['base_path'] = BOLMER_BASE_PATH;
            $this->config['site_url'] = BOLMER_SITE_URL;
            $this->config['valid_hostnames'] = MODX_SITE_HOSTNAMES;
            $this->config['site_manager_url'] = BOLMER_MANAGER_URL;
            $this->config['site_manager_path'] = BOLMER_MANAGER_PATH;

            $this->getUserSettings();

            $this->error_reporting = $this->config['error_reporting'];
            $this->config['filemanager_path'] = str_replace('[(base_path)]',BOLMER_BASE_PATH,$this->config['filemanager_path']);
            $this->config['rb_base_dir']      = str_replace('[(base_path)]',BOLMER_BASE_PATH,$this->config['rb_base_dir']);
        }
    }

    /**
     * Load user settings if user is logged in
     */
    function getUserSettings() {
        $usrSettings= array ();
        if ($id= $this->_service->get('user')->getLoginUserID()) {
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
                    $query= $this->getFullTableName('web_user_settings') . ' WHERE webuser=\'' . $id . '\'';
                else
                    $query= $this->getFullTableName('user_settings') . ' WHERE user=\'' . $id . '\'';
                $result= $this->db->query('SELECT setting_name, setting_value FROM ' . $query);
                while ($row= $this->db->getRow($result, 'both'))
                    $usrSettings[$row[0]]= $row[1];
                if (isset ($usrType))
                    $_SESSION[$usrType . 'UsrConfigSet']= $usrSettings; // store user settings in session
            }
        }
        if ($this->isFrontend() && $mgrid= $this->_service->get('user')->getLoginUserID('mgr')) {
            $musrSettings= array ();
            if (isset ($_SESSION['mgrUsrConfigSet'])) {
                $musrSettings= & $_SESSION['mgrUsrConfigSet'];
            } else {
                $query= $this->getFullTableName('user_settings') . ' WHERE user=\'' . $mgrid . '\'';
                if ($result= $this->db->query('SELECT setting_name, setting_value FROM ' . $query)) {
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
        $this->config= array_merge($this->config, $usrSettings);
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
        return BOLMER_MANAGER_URL;
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
            $this->documentMethod= $this->_service->get('request')->getDocumentMethod();
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

    /**
     * Returns the full table name based on db settings
     *
     * @param string $tbl Table name
     * @return string Table name with prefix
     */
    function getFullTableName($tbl) {
        return getkey($this->db->config, 'dbase') . ".`" . getkey($this->db->config, 'table_prefix') . $tbl . "`";
    }
    function getTableName($className){
        return \Bolmer\Model::getFullTableName($className);
    }
    /**
     * Returns the timestamp in the date format defined in $this->config['datetime_format']
     *
     * @param int $timestamp Default: 0
     * @param string $mode Default: Empty string (adds the time as below). Can also be 'dateOnly' for no time or 'formatOnly' to get the datetime_format string.
     * @return string
     */
    function toDateFormat($timestamp = 0, $mode = '') {
        $timestamp = trim($timestamp);
        if($mode !== 'formatOnly' && empty($timestamp)) return '-';
        $timestamp = intval($timestamp);

        switch(getService('core')->getConfig('datetime_format')) {
            case 'YYYY/mm/dd':
                $dateFormat = '%Y/%m/%d';
                break;
            case 'dd-mm-YYYY':
                $dateFormat = '%d-%m-%Y';
                break;
            case 'mm/dd/YYYY':
                $dateFormat = '%m/%d/%Y';
                break;
            /*
            case 'dd-mmm-YYYY':
                $dateFormat = '%e-%b-%Y';
                break;
            */
        }

        if (empty($mode)) {
            $strTime = strftime($dateFormat . " %H:%M:%S", $timestamp);
        } elseif ($mode == 'dateOnly') {
            $strTime = strftime($dateFormat, $timestamp);
        } elseif ($mode == 'formatOnly') {
            $strTime = $dateFormat;
        }
        return $strTime;
    }
    /**
     * Make a timestamp from a string corresponding to the format in $this->config['datetime_format']
     *
     * @param string $str
     * @return string
     */
    function toTimeStamp($str) {
        $str = trim($str);
        if (empty($str)) {return '';}

        switch(getService('core')->getConfig('datetime_format')) {
            case 'YYYY/mm/dd':
                if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}[0-9 :]*$/', $str)) {return '';}
                list ($Y, $m, $d, $H, $M, $S) = sscanf($str, '%4d/%2d/%2d %2d:%2d:%2d');
                break;
            case 'dd-mm-YYYY':
                if (!preg_match('/^[0-9]{2}-[0-9]{2}-[0-9]{4}[0-9 :]*$/', $str)) {return '';}
                list ($d, $m, $Y, $H, $M, $S) = sscanf($str, '%2d-%2d-%4d %2d:%2d:%2d');
                break;
            case 'mm/dd/YYYY':
                if (!preg_match('/^[0-9]{2}\/[0-9]{2}\/[0-9]{4}[0-9 :]*$/', $str)) {return '';}
                list ($m, $d, $Y, $H, $M, $S) = sscanf($str, '%2d/%2d/%4d %2d:%2d:%2d');
                break;
            /*
            case 'dd-mmm-YYYY':
            	if (!preg_match('/^[0-9]{2}-[0-9a-z]+-[0-9]{4}[0-9 :]*$/i', $str)) {return '';}
            	list ($m, $d, $Y, $H, $M, $S) = sscanf($str, '%2d-%3s-%4d %2d:%2d:%2d');
                break;
            */
        }
        if (!$H && !$M && !$S) {$H = 0; $M = 0; $S = 0;}
        $timeStamp = mktime($H, $M, $S, $m, $d, $Y);
        $timeStamp = intval($timeStamp);
        return $timeStamp;
    }
    function nicesize($size) {
        $sizes = array('Tb'=>1099511627776, 'Gb'=>1073741824, 'Mb'=>1048576, 'Kb'=>1024, 'b'=>1);
        $precisions = count($sizes)-1;
        foreach ($sizes as $unit=>$bytes) {
            if ($size>=$bytes)
                return number_format($size/$bytes, $precisions).' '.$unit;
            $precisions--;
        }
        return '0 b';
    }
    /**
     * Remove unwanted html tags and snippet, settings and tags
     *
     * @param string $html
     * @param string $allowed Default: Empty string
     * @return string
     */
    function stripTags($html, $allowed= "") {
        $t= strip_tags($html, $allowed);
        $t= preg_replace('~\[\*(.*?)\*\]~', "", $t); //tv
        $t= preg_replace('~\[\[(.*?)\]\]~', "", $t); //snippet
        $t= preg_replace('~\[\!(.*?)\!\]~', "", $t); //snippet
        $t= preg_replace('~\[\((.*?)\)\]~', "", $t); //settings
        $t= preg_replace('~\[\+(.*?)\+\]~', "", $t); //placeholders
        $t= preg_replace('~{{(.*?)}}~', "", $t); //chunks
        $t= preg_replace('~&#x005B;\*(.*?)\*&#x005D;~', "", $t); //encoded tv
        $t= preg_replace('~&#x005B;&#x005B;(.*?)&#x005D;&#x005D;~', "", $t); //encoded snippet
        $t= preg_replace('~&#x005B;\!(.*?)\!&#x005D;~', "", $t); //encoded snippet
        $t= preg_replace('~&#x005B;\((.*?)\)&#x005D;~', "", $t); //encoded settings
        $t= preg_replace('~&#x005B;\+(.*?)\+&#x005D;~', "", $t); //encoded placeholders
        $t= preg_replace('~&#x007B;&#x007B;(.*?)&#x007D;&#x007D;~', "", $t); //encoded chunks
        return $t;
    }
    /**
     * Returns the current micro time
     *
     * @return float
     */
    function getMicroTime() {
        return microtime(true);
    }
    function jsonDecode($json, $assoc = false) {
        return \Bolmer\Helper\json::jsonDecode($json, array('assoc' => $assoc));
    }
    public function htmlspecialchars($text, $flags = ENT_COMPAT){
        $charset = getkey(getService('config'), 'modx_charset', 'UTF-8');
        if(!is_scalar($text)){
            $str = '';
        }
        $ent_str = htmlspecialchars($text, $flags, $charset);
        if(!empty($str) && empty($ent_str)){
            $detect_order = join(',', mb_detect_order());
            $ent_str = mb_convert_encoding($str,$charset,$detect_order);
        }
        return $ent_str;
    }
    function checkCache($id) {
        return $this->_service->get('cache')->checkCache($id);
    }
    function clearCache($type='', $report=false) {
        return $this->_service->get('cache')->clearCache($type, $report);
    }
    function getCachePath() {
        return $this->_service->get('cache')->getCachePath();
    }
    function rewriteUrls($documentSource) {
        return $this->_service->get('parser')->rewriteUrls($documentSource);
    }
    function isBackend() {
        return $this->_service->get('response')->isBackend();
    }
    function isFrontend() {
        return $this->_service->get('response')->isFrontend();
    }
    function insideManager() {
        return $this->_service->get('response')->insideManager();
    }
    function sendStrictURI(){
        return $this->_service->get('response')->sendStrictURI();
    }
    function outputContent($noEvent= false) {
        return $this->_service->get('response')->outputContent($noEvent);
    }
    function checkSiteStatus() {
        return $this->_service->get('response')->checkSiteStatus();
    }
    function checkPublishStatus() {
        return $this->_service->get('response')->checkPublishStatus();
    }
    function phpError($nr, $text, $file, $line) {
        $this->_service->get('debug')->phpError($nr, $text, $file, $line);
    }
    function detectError($error) {
        $this->_service->get('debug')->detectError($error);
    }
    function messageQuit($msg= 'unspecified error', $query= '', $is_error= true, $nr= '', $file= '', $source= '', $text= '', $line= '', $output='') {
        $this->_service->get('debug')->messageQuit($msg, $query, $is_error, $nr, $file, $source, $text, $line, $output);
    }
    function get_backtrace($backtrace) {
        return $this->_service->get('debug')->get_backtrace($backtrace);
    }
    function postProcess() {
        $this->_service->get('response')->postProcess();
    }
    function parseDocumentSource($source) {
        return $this->_service->get('parser')->parseDocumentSource($source, false);
    }
    function prepareResponse() {
        $this->_service->get('response')->prepareResponse();
    }
    function evalPlugin($pluginCode, $params) {
        $this->_service->get('plugin')->evalPlugin($pluginCode, $params);
    }
    function addEventListener($evtName, $pluginName) {
        return $this->_service->get('plugin')->addEventListener($evtName, $pluginName);
    }
    function removeEventListener($evtName) {
        return $this->_service->get('plugin')->removeEventListener($evtName);
    }
    function removeAllEventListener() {
        $this->_service->get('plugin')->removeAllEventListener();
    }
    function invokeEvent($evtName, $extParams= array ()) {
        return $this->_service->get('plugin')->invokeEvent($evtName, $extParams);
    }
    function mergeDocumentContent($content) {
        return $this->_service->get('parser')->mergeDocumentContent($content);
    }
    function mergeSettingsContent($content) {
        return $this->_service->get('parser')->mergeSettingsContent($content);
    }
    function mergeChunkContent($content) {
        return $this->_service->get('parser')->mergeChunkContent($content);
    }
    function mergePlaceholderContent($content){
        return $this->_service->get('parser')->mergePlaceholderContent($content);
    }
    function getRegisteredClientStartupScripts() {
        return $this->_service->get('HTML')->getRegisteredClientStartupScripts();
    }
    function getDocumentIdentifier($method) {
        return  $this->_service->get('request')->getDocumentIdentifier($method);
    }
    function sendAlert($type, $to, $from, $subject, $msg, $private= 0) {
        return $this->_service->get('manager')->sendAlert($type, $to, $from, $subject, $msg, $private);
    }
    function getIdFromAlias($alias){
        return $this->_service->get('document')->getIdFromAlias($alias);
    }
    function stripAlias($alias) {
        return $this->_service->get('document')->stripAlias($alias);
    }
    function getDocumentMethod() {
        return $this->_service->get('request')->getDocumentMethod();
    }
    function logEvent($evtid, $type, $msg, $source= 'Parser') {
        $this->_service->get('log')->logEvent($evtid, $type, $msg, $source);
    }
    function rotate_log($target='event_log',$limit=3000, $trim=100){
        $this->_service->get('log')->rotate_log($target, $limit, $trim);
    }
    function getRegisteredClientScripts() {
        return $this->_service->get('HTML')->getRegisteredClientScripts();
    }
    function getDocumentChildrenTVars($parentid= 0, $tvidnames= array (), $published= 1, $docsort= "menuindex", $docsortdir= "ASC", $tvfields= "*", $tvsort= "rank", $tvsortdir= "ASC") {
        return $this->_service->get('document')->getDocumentChildrenTVars($parentid, $tvidnames, $published, $docsort, $docsortdir, $tvfields, $tvsort, $tvsortdir);
    }
    function getDocumentChildrenTVarOutput($parentid= 0, $tvidnames= array (), $published= 1, $docsort= "menuindex", $docsortdir= "ASC") {
        return $this->_service->get('document')->getDocumentChildrenTVarOutput($parentid, $tvidnames, $published, $docsort, $docsortdir);
    }
    function getTemplateVar($idname= "", $fields= "*", $docid= "", $published= 1) {
        return $this->_service->get('document')->getTemplateVar($idname, $fields, $docid, $published);
    }
    function getTemplateVars($idnames= array (), $fields= "*", $docid= "", $published= 1, $sort= "rank", $dir= "ASC") {
        return $this->_service->get('document')->getTemplateVar($idnames, $fields, $docid, $published, $sort, $dir);
    }
    function getTemplateVarOutput($idnames= array (), $docid= "", $published= 1, $sep='') {
        return $this->_service->get('document')->getTemplateVarOutput($idnames, $docid, $published, $sep);
    }
    function getDocumentObject($method, $identifier, $isPrepareResponse=false) {
        return $this->_service->get('document')->getDocumentObject($method, $identifier, $isPrepareResponse);
    }
    function regClientCSS($src, $media='') {
        return $this->_service->get('HTML')->regClientCSS($src, $media);
    }
    function regClientStartupScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false)) {
        return $this->_service->get('HTML')->regClientCSS($src, $options);
    }
    function regClientScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false), $startup= false) {
        return $this->_service->get('HTML')->regClientScript($src, $options, $startup);
    }
    function regClientStartupHTMLBlock($html) {
        return $this->_service->get('HTML')->regClientStartupHTMLBlock($html);
    }
    function regClientHTMLBlock($html) {
        return $this->_service->get('HTML')->regClientHTMLBlock($html);
    }
    function getAllChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle') {
        return $this->_service->get('document')->getAllChildren($id, $sort, $dir, $fields);
    }
    function getActiveChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle') {
        return $this->_service->get('document')->getActiveChildren($id, $sort, $dir, $fields);
    }
    function getDocumentChildren($parentid= 0, $published= 1, $deleted= 0, $fields= "*", $where= '', $sort= "menuindex", $dir= "ASC", $limit= "") {
        return $this->_service->get('document')->getDocumentChildren($parentid, $published, $deleted, $fields, $where, $sort, $dir, $limit);
    }
    function getDocuments($ids= array (), $published= 1, $deleted= 0, $fields= "*", $where= '', $sort= "menuindex", $dir= "ASC", $limit= "") {
        return $this->_service->get('document')->getDocuments($ids, $published, $deleted, $fields, $where, $sort, $dir, $limit);
    }
    function getDocument($id= 0, $fields= "*", $published= 1, $deleted= 0) {
        return $this->_service->get('document')->getDocument($id, $fields, $published, $deleted);
    }
    function getPageInfo($pageid= -1, $active= 1, $fields= 'id, pagetitle, description, alias') {
        return $this->_service->get('document')->getPageInfo($pageid, $active, $fields);
    }
    function getParent($pid= -1, $active= 1, $fields= 'id, pagetitle, description, alias, parent') {
        return $this->_service->get('document')->getParent($pid, $active, $fields);
    }
    function getSnippetId() {
        return $this->_service->get('snippet')->getSnippetId();
    }
    function getSnippetName() {
        return $this->_service->get('snippet')->getSnippetName();
    }
    function runSnippet($snippetName, $params= array ()) {
        return $this->_service->get('snippet')->runSnippet($snippetName, $params);
    }
    // deprecated
    function putChunk($chunkName) {
        return $this->_service->get('parser')->getChunk($chunkName);
    }
    function getChunk($chunkName) {
        return $this->_service->get('parser')->getChunk($chunkName);
    }
    function parseText($chunk, $chunkArr, $prefix = '[+', $suffix = '+]'){
        return $this->_serivce->get('parser')->parseText($chunk, $chunkArr, $prefix, $suffix);
    }
    function parseChunk($chunkName, $chunkArr, $prefix = '{', $suffix = '}'){
        return $this->_service->get('parser')->parseChunk($chunkName, $chunkArr, $prefix, $suffix);
    }
    function getTagsFromContent($content,$left='[+',$right='+]') {
        return $this->_service->get('parser')->getTagsFromContent($content,$left,$right);
    }
    function getPlaceholder($name) {
        return $this->_service->get('parser')->getPlaceholder($name);
    }
    function setPlaceholder($name, $value) {
        return $this->_service->get('parser')->setPlaceholder($name, $value);
    }
    function toPlaceholders($subject, $prefix= '') {
        $this->_service->get('parser')->toPlaceholders($subject, $prefix);
    }
    function toPlaceholder($key, $value, $prefix= '') {
        $this->_service->get('parser')->toPlaceholder($key, $value, $prefix);
    }
    function getLoginUserID($context= '') {
        return $this->_service->get('user')->getLoginUserID($context);
    }
    function getLoginUserName($context= '') {
        return $this->_service->get('user')->getLoginUserName($context);
    }
    function getLoginUserType() {
        return $this->_service->get('user')->getLoginUserType();
    }
    function getUserInfo($uid) {
        return $this->_service->get('user')->getUserInfo($uid);
    }
    function getWebUserInfo($uid) {
        return $this->_service->get('user')->getWebUserInfo($uid);
    }
    function getUserDocGroups($resolveIds= false) {
        return $this->_service->get('user')->getUserDocGroups($resolveIds);
    }
    function changeWebUserPassword($oldPwd, $newPwd) {
        return $this->_service->get('user')->changeWebUserPassword($oldPwd, $newPwd);
    }
    function isMemberOfWebGroup($groupNames= array ()) {
        return $this->_service->get('user')->isMemberOfWebGroup($groupNames);
    }
    function parseProperties($propertyString) {
        return $this->_service->get('parser')->parseProperties($propertyString);
    }
    function getParentIds($id, $height= 10) {
        return $this->_service->get('document')->getParentIds($id, $height);
    }
    function getChildIds($id, $depth= 10, $children= array ()) {
        return $this->_service->get('document')->getChildIds($id, $depth, $children);
    }
    function webAlert($msg, $url= "") {
        return $this->_service->get('HTML')->webAlert($msg, $url);
    }
    function hasPermission($pm) {
        return $this->_service->get('manager')->hasPermission($pm);
    }
    function evalSnippet($snippet, $params) {
        return $this->_service->get('snippet')->evalSnippet($snippet, $params);
    }
    function evalSnippets($documentSource) {
        return $this->_service->get('snippet')->evalSnippets($documentSource);
    }
    function checkSession() {
        return $this->_service->get('manager')->checkSession();
    }
    function checkPreview() {
        return $this->_service->get('manager')->checkPreview();
    }
    function sendRedirect($url, $count_attempts= 0, $type= '', $responseCode= '') {
        return $this->_service->get('response')->sendRedirect($url, $count_attempts, $type, $responseCode);
    }
    function sendForward($id, $responseCode= '') {
        return $this->_service->get('response')->sendForward($id, $responseCode);
    }
    function sendErrorPage() {
        return $this->_service->get('response')->sendErrorPage();
    }
    function sendUnauthorizedPage() {
        $this->_service->get('response')->sendUnauthorizedPage();
    }
}