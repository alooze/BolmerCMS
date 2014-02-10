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
        $pimple['db'] = $pimple->share(function($inj){
            return $inj['modx']->db;
        });
        $pimple['config'] = $pimple->share(function($inj){
            return $inj['modx']->config;
        });
        $pimple['Response'] = $pimple->share(function($inj){
            return new \MODxCore\Response($inj);
        });
        $pimple['HTML'] = $pimple->share(function($inj){
            return new \MODxCore\HTML($inj);
        });
        $pimple['snippet'] = $pimple->share(function($inj){
            return new \MODxCore\Parser\Snippet($inj);
        });
        $pimple['document'] = $pimple->share(function($inj){
            return new \MODxCore\Document($inj);
        });

        if(substr(PHP_OS,0,3) === 'WIN' && $pimple['global_config']['database_server']==='localhost'){
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
        global $database_type;

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
            case 'PHPCOMPAT' :
                if(is_object($this->phpcompat)) return;
                include_once(MODX_MANAGER_PATH . 'includes/extenders/phpcompat.class.inc.php');
                $this->phpcompat = new \PHPCOMPAT;
                break;

            default :
                return false;
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
     * Returns the cache relative URL/path with respect to the site root.
     *
     * @global string $base_url
     * @return string The complete URL to the cache folder
     */
    function getCachePath() {
        return MODX_BASE_URL . 'assets/cache/';
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
     * Get the method by which the current document/resource was requested
     *
     * @return string 'alias' (friendly url alias) or 'id'
     */
    function getDocumentMethod() {
        // function to test the query and find the retrieval method
        if (!empty ($_REQUEST['q'])) { //LANG
            return "alias";
        }
        elseif (isset ($_REQUEST['id'])) {
            return "id";
        } else {
            return "none";
        }
    }

    /**
     * Returns the document identifier of the current request
     *
     * @param string $method id and alias are allowed
     * @return int
     */
    function getDocumentIdentifier($method) {
        // function to test the query and find the retrieval method
        $docIdentifier= $this->getConfig('site_start');
        switch ($method) {
            case 'alias' :
                $docIdentifier= $this->db->escape($_REQUEST['q']);
                break;
            case 'id' :
                if (!is_numeric($_REQUEST['id'])) {
                    $this->sendErrorPage();
                } else {
                    $docIdentifier= intval($_REQUEST['id']);
                }
                break;
        }
        return $docIdentifier;
    }

    /**
     * check if site is offline
     *
     * @return boolean
     */
    function checkSiteStatus() {
        $siteStatus= $this->getConfig('site_status');
        if ($siteStatus == 1) {
            // site online
            return true;
        }
        elseif ($siteStatus == 0 && $this->checkSession()) {
            // site offline but launched via the manager
            return true;
        } else {
            // site is offline
            return false;
        }
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
     * Check the cache for a specific document/resource
     *
     * @param int $id
     * @return string
     */
    function checkCache($id) {
        $tbl_document_groups= $this->getFullTableName("document_groups");
        if ($this->getConfig('cache_type') == 2) {
            $md5_hash = '';
            if(!empty($_GET)) $md5_hash = '_' . md5(http_build_query($_GET));
            $cacheFile= "assets/cache/docid_" . $id .$md5_hash. ".pageCache.php";
        }else{
            $cacheFile= "assets/cache/docid_" . $id . ".pageCache.php";
        }
        if (file_exists($cacheFile)) {
            $this->documentGenerated= 0;
            $flContent = file_get_contents($cacheFile, false);
            $flContent= substr($flContent, 37); // remove php header
            $a= explode("<!--__MODxCacheSpliter__-->", $flContent, 2);
            if (count($a) == 1)
                return $a[0]; // return only document content
            else {
                $docObj= unserialize($a[0]); // rebuild document object
                // check page security
                if ($docObj['privateweb'] && isset ($docObj['__MODxDocGroups__'])) {
                    $pass= false;
                    $usrGrps= $this->getUserDocGroups();
                    $docGrps= explode(",", $docObj['__MODxDocGroups__']);
                    // check is user has access to doc groups
                    if (is_array($usrGrps)) {
                        foreach ($usrGrps as $k => $v)
                            if (in_array($v, $docGrps)) {
                                $pass= true;
                                break;
                            }
                    }
                    // diplay error pages if user has no access to cached doc
                    if (!$pass) {
                        if ($this->getConfig('unauthorized_page')) {
                            // check if file is not public
                            $secrs= $this->db->select('id', $tbl_document_groups, "document='{$id}'", '', '1');
                            if ($secrs)
                                $seclimit= $this->db->getRecordCount($secrs);
                        }
                        if ($seclimit > 0) {
                            // match found but not publicly accessible, send the visitor to the unauthorized_page
                            $this->sendUnauthorizedPage();
                            exit; // stop here
                        } else {
                            // no match found, send the visitor to the error_page
                            $this->sendErrorPage();
                            exit; // stop here
                        }
                    }
                }
                // Grab the Scripts
                if (isset($docObj['__MODxSJScripts__'])) $this->sjscripts = $docObj['__MODxSJScripts__'];
                if (isset($docObj['__MODxJScripts__']))  $this->jscripts = $docObj['__MODxJScripts__'];

                // Remove intermediate variables
                unset($docObj['__MODxDocGroups__'], $docObj['__MODxSJScripts__'], $docObj['__MODxJScripts__']);

                $this->documentObject= $docObj;
                return $a[1]; // return document content
            }
        } else {
            $this->documentGenerated= 1;
            return "";
        }
    }

    /**
     * Final processing and output of the document/resource.
     *
     * - runs uncached snippets
     * - add javascript to <head>
     * - removes unused placeholders
     * - converts URL tags [~...~] to URLs
     *
     * @param boolean $noEvent Default: false
     */
    function outputContent($noEvent= false) {
        $this->documentOutput= $this->documentContent;

        if ($this->documentGenerated == 1 && $this->documentObject['cacheable'] == 1 && $this->documentObject['type'] == 'document' && $this->documentObject['published'] == 1) {
            if (!empty($this->sjscripts)) $this->documentObject['__MODxSJScripts__'] = $this->sjscripts;
            if (!empty($this->jscripts)) $this->documentObject['__MODxJScripts__'] = $this->jscripts;
        }

        // check for non-cached snippet output
        if (strpos($this->documentOutput, '[!') > -1) {
            $this->documentOutput= str_replace('[!', '[[', $this->documentOutput);
            $this->documentOutput= str_replace('!]', ']]', $this->documentOutput);

            // Parse document source
            $this->documentOutput= $this->parseDocumentSource($this->documentOutput);
        }

        // Moved from prepareResponse() by sirlancelot
        // Insert Startup jscripts & CSS scripts into template - template must have a <head> tag
        if ($js= $this->getRegisteredClientStartupScripts()) {
            // change to just before closing </head>
            // $this->documentContent = preg_replace("/(<head[^>]*>)/i", "\\1\n".$js, $this->documentContent);
            $this->documentOutput= preg_replace("/(<\/head>)/i", $js . "\n\\1", $this->documentOutput);
        }

        // Insert jscripts & html block into template - template must have a </body> tag
        if ($js= $this->getRegisteredClientScripts()) {
            $this->documentOutput= preg_replace("/(<\/body>)/i", $js . "\n\\1", $this->documentOutput);
        }
        // End fix by sirlancelot

        // remove all unused placeholders
        if (strpos($this->documentOutput, '[+') > -1) {
            $matches= array ();
            preg_match_all('~\[\+(.*?)\+\]~s', $this->documentOutput, $matches);
            if ($matches[0])
                $this->documentOutput= str_replace($matches[0], '', $this->documentOutput);
        }

        $this->documentOutput= $this->rewriteUrls($this->documentOutput);

        // send out content-type and content-disposition headers
        if (IN_PARSER_MODE == "true") {
            $type= !empty ($this->contentTypes[$this->documentIdentifier]) ? $this->contentTypes[$this->documentIdentifier] : "text/html";
            header('Content-Type: ' . $type . '; charset=' . $this->getConfig('modx_charset'));
//            if (($this->documentIdentifier == $this->config['error_page']) || $redirect_error)
//                header('HTTP/1.0 404 Not Found');
            if (!$this->checkPreview() && $this->documentObject['content_dispo'] == 1) {
                if ($this->documentObject['alias'])
                    $name= $this->documentObject['alias'];
                else {
                    // strip title of special characters
                    $name= $this->documentObject['pagetitle'];
                    $name= strip_tags($name);
                    $name= strtolower($name);
                    $name= preg_replace('/&.+?;/', '', $name); // kill entities
                    $name= preg_replace('/[^\.%a-z0-9 _-]/', '', $name);
                    $name= preg_replace('/\s+/', '-', $name);
                    $name= preg_replace('|-+|', '-', $name);
                    $name= trim($name, '-');
                }
                $header= 'Content-Disposition: attachment; filename=' . $name;
                header($header);
            }
        }

        $stats = $this->getTimerStats($this->tstart);

        $out =& $this->documentOutput;
        $out= str_replace("[^q^]", $stats['queries'] , $out);
        $out= str_replace("[^qt^]", $stats['queryTime'] , $out);
        $out= str_replace("[^p^]", $stats['phpTime'] , $out);
        $out= str_replace("[^t^]", $stats['totalTime'] , $out);
        $out= str_replace("[^s^]", $stats['source'] , $out);
        $out= str_replace("[^m^]", $stats['phpMemory'], $out);
        //$this->documentOutput= $out;

        // invoke OnWebPagePrerender event
        if (!$noEvent) {
            $this->invokeEvent('OnWebPagePrerender');
        }
        global $sanitize_seed;
        if(strpos($this->documentOutput, $sanitize_seed)!==false) {
            $this->documentOutput = str_replace($sanitize_seed, '', $this->documentOutput);
        }

        echo $this->documentOutput;

        if ($this->dumpSQL) echo $this->queryCode;
        if ($this->dumpSnippets) {
            $sc = "";
            $tt = 0;
            foreach ($this->snippetsTime as $s=>$t) {
                $sc .= "$s: ".$this->snippetsCount[$s]." (".sprintf("%2.2f ms", $t*1000).")<br>";
                $tt += $t;
            }
            echo "<fieldset><legend><b>Snippets</b> (".count($this->snippetsTime)." / ".sprintf("%2.2f ms", $tt*1000).")</legend>{$sc}</fieldset><br />";
            echo $this->snippetsCode;
        }
        if ($this->dumpPlugins) {
            $ps = "";
            $tc = 0;
            foreach ($this->pluginsTime as $s=>$t) {
                $ps .= "$s (".sprintf("%2.2f ms", $t*1000).")<br>";
                $tt += $t;
            }
            echo "<fieldset><legend><b>Plugins</b> (".count($this->pluginsTime)." / ".sprintf("%2.2f ms", $tt*1000).")</legend>{$ps}</fieldset><br />";
            echo $this->pluginsCode;
        }

        ob_end_flush();
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

    /**
     * Checks the publish state of page
     */
    function checkPublishStatus() {
        $cacheRefreshTime= 0;
        @include $this->config["base_path"] . "assets/cache/sitePublishing.idx.php";
        $timeNow= time() + $this->getConfig('server_offset_time');
        if ($cacheRefreshTime <= $timeNow && $cacheRefreshTime != 0) {
            // now, check for documents that need publishing
            $sql = "UPDATE ".$this->getFullTableName("site_content")." SET published=1, publishedon=".time()." WHERE ".$this->getFullTableName("site_content").".pub_date <= $timeNow AND ".$this->getFullTableName("site_content").".pub_date!=0 AND published=0";
            if (@ !$result= $this->db->query($sql)) {
                $this->messageQuit("Execution of a query to the database failed", $sql);
            }

            // now, check for documents that need un-publishing
            $sql= "UPDATE " . $this->getFullTableName("site_content") . " SET published=0, publishedon=0 WHERE " . $this->getFullTableName("site_content") . ".unpub_date <= $timeNow AND " . $this->getFullTableName("site_content") . ".unpub_date!=0 AND published=1";
            if (@ !$result= $this->db->query($sql)) {
                $this->messageQuit("Execution of a query to the database failed", $sql);
            }

            // clear the cache
            $this->clearCache();

            // update publish time file
            $timesArr= array ();
            $sql= "SELECT MIN(pub_date) AS minpub FROM " . $this->getFullTableName("site_content") . " WHERE pub_date>$timeNow";
            if (@ !$result= $this->db->query($sql)) {
                $this->messageQuit("Failed to find publishing timestamps", $sql);
            }
            $tmpRow= $this->db->getRow($result);
            $minpub= $tmpRow['minpub'];
            if ($minpub != NULL) {
                $timesArr[]= $minpub;
            }

            $sql= "SELECT MIN(unpub_date) AS minunpub FROM " . $this->getFullTableName("site_content") . " WHERE unpub_date>$timeNow";
            if (@ !$result= $this->db->query($sql)) {
                $this->messageQuit("Failed to find publishing timestamps", $sql);
            }
            $tmpRow= $this->db->getRow($result);
            $minunpub= $tmpRow['minunpub'];
            if ($minunpub != NULL) {
                $timesArr[]= $minunpub;
            }

            if (count($timesArr) > 0) {
                $nextevent= min($timesArr);
            } else {
                $nextevent= 0;
            }

            $basepath= $this->config["base_path"] . "assets/cache";
            $fp= @ fopen($basepath . "/sitePublishing.idx.php", "wb");
            if ($fp) {
                @ flock($fp, LOCK_EX);
                @ fwrite($fp, "<?php \$cacheRefreshTime=$nextevent; ?>");
                @ flock($fp, LOCK_UN);
                @ fclose($fp);
            }
        }
    }

    /**
     * Final jobs.
     *
     * - cache page
     */
    function postProcess() {
        // if the current document was generated, cache it!
        if ($this->documentGenerated == 1 && $this->documentObject['cacheable'] == 1 && $this->documentObject['type'] == 'document' && $this->documentObject['published'] == 1) {
            $basepath= $this->config["base_path"] . "assets/cache";
            // invoke OnBeforeSaveWebPageCache event
            $this->invokeEvent("OnBeforeSaveWebPageCache");
            if ($this->getConfig('cache_type') == 2) {
                $md5_hash = '';
                if(!empty($_GET)) $md5_hash = '_' . md5(http_build_query($_GET));
                $pageCache = $md5_hash .".pageCache.php";
            }else{
                $pageCache = ".pageCache.php";
            }

            if ($fp= @ fopen($basepath . "/docid_" . $this->documentIdentifier . $pageCache, "w")) {
                // get and store document groups inside document object. Document groups will be used to check security on cache pages
                $sql= "SELECT document_group FROM " . $this->getFullTableName("document_groups") . " WHERE document='" . $this->documentIdentifier . "'";
                $docGroups= $this->db->getColumn("document_group", $sql);

                // Attach Document Groups and Scripts
                if (is_array($docGroups)) $this->documentObject['__MODxDocGroups__'] = implode(",", $docGroups);

                $docObjSerial= serialize($this->documentObject);
                $cacheContent= $docObjSerial . "<!--__MODxCacheSpliter__-->" . $this->documentContent;
                fputs($fp, "<?php die('Unauthorized access.'); ?>$cacheContent");
                fclose($fp);
            }
        }

        // Useful for example to external page counters/stats packages
        $this->invokeEvent('OnWebPageComplete');

        // end post processing
    }

    /**
     * Merge content fields and TVs
     *
     * @param string $template
     * @return string
     */
    function mergeDocumentContent($content) {
        if (strpos($content, '[*') === false)
            return $content;
        $replace = array();
        $matches = \MODxCore\Parser::getTagsFromContent($content, '[*', '*]');
        if ($matches) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                if ($matches[1][$i]) {
                    $key = $matches[1][$i];
                    $key = substr($key, 0, 1) == '#' ? substr($key, 1) : $key; // remove # for QuickEdit format
                    $value = $this->documentObject[$key];
                    if (is_array($value)) {
                        include_once MODX_MANAGER_PATH . 'includes/tmplvars.format.inc.php';
                        include_once MODX_MANAGER_PATH . 'includes/tmplvars.commands.inc.php';
                        $value = getTVDisplayFormat($value[0], $value[1], $value[2], $value[3], $value[4]);
                    }
                    $replace[$i] = $value;
                }
            }
            $content = str_replace($matches[0], $replace, $content);
        }
        return $content;
    }

    /**
     * Merge system settings
     *
     * @param string $template
     * @return string
     */
    function mergeSettingsContent($content) {
        if (strpos($content, '[(') === false)
            return $content;
        $replace = array();
        $matches = \MODxCore\Parser::getTagsFromContent($content, '[(', ')]');
        if ($matches) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                if ($matches[1][$i] && array_key_exists($matches[1][$i], $this->config))
                    $replace[$i] = $this->config[$matches[1][$i]];
            }

            $content = str_replace($matches[0], $replace, $content);
        }
        return $content;
    }

    /**
     * Merge chunks
     *
     * @param string $content
     * @return string
     */
    function mergeChunkContent($content) {
        if (strpos($content, '{{') === false)
            return $content;
        $replace = array();
        $matches = $this->getTagsFromContent($content, '{{', '}}');
        if ($matches) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                if ($matches[1][$i]) {
                    if (isset($this->chunkCache[$matches[1][$i]])) {
                        $replace[$i] = $this->chunkCache[$matches[1][$i]];
                    } else {
                        $sql = 'SELECT `snippet` FROM ' . $this->getFullTableName('site_htmlsnippets') . ' WHERE ' . $this->getFullTableName('site_htmlsnippets') . '.`name`="' . $this->db->escape($matches[1][$i]) . '";';
                        $result = $this->db->query($sql);
                        $limit = $this->db->getRecordCount($result);
                        if ($limit < 1) {
                            $this->chunkCache[$matches[1][$i]] = '';
                            $replace[$i] = '';
                        } else {
                            $row = $this->db->getRow($result);
                            $this->chunkCache[$matches[1][$i]] = $row['snippet'];
                            $replace[$i] = $row['snippet'];
                        }
                    }
                }
            }
            $content = str_replace($matches[0], $replace, $content);
            $content = $this->mergeSettingsContent($content);
        }
        return $content;
    }

    /**
     * Merge placeholder values
     *
     * @param string $content
     * @return string
     */
    function mergePlaceholderContent($content) {
        if (strpos($content, '[+') === false)
            return $content;
        $replace = array();
        $content = $this->mergeSettingsContent($content);
        $matches = $this->getTagsFromContent($content, '[+', '+]');
        if ($matches) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $v = '';
                $key = $matches[1][$i];
                if ($key && is_array($this->placeholders) && array_key_exists($key, $this->placeholders))
                    $v = $this->placeholders[$key];
                if ($v === '')
                    unset($matches[0][$i]); // here we'll leave empty placeholders for last.
                else
                    $replace[$i] = $v;
            }
            $content = str_replace($matches[0], $replace, $content);
        }
        return $content;
    }

    /**
     * Detect PHP error according to MODX error level
     *
     * @param integer $error PHP error level
     * @return boolean Error detected
     */

    function detectError($error) {
        $detected = FALSE;
        if ($this->getConfig('error_reporting') == 99 && $error)
            $detected = TRUE;
        elseif ($this->getConfig('error_reporting') == 2 && ($error & ~E_NOTICE))
            $detected = TRUE;
        elseif ($this->getConfig('error_reporting') == 1 && ($error & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT))
            $detected = TRUE;
        return $detected;
    }

    /**
     * Run a plugin
     *
     * @param string $pluginCode Code to run
     * @param array $params
     */
    function evalPlugin($pluginCode, $params) {
        $etomite = $modx = & $this;
        $modx->event->params = & $params; // store params inside event object
        if (is_array($params)) {
            extract($params, EXTR_SKIP);
        }
        ob_start();
        eval($pluginCode);
        $msg = ob_get_contents();
        ob_end_clean();
        if ((0 < $this->getConfig('error_reporting')) && $msg && isset($php_errormsg)) {
            $error_info = error_get_last();
            if ($this->detectError($error_info['type'])) {
                extract($error_info);
                $msg = ($msg === false) ? 'ob_get_contents() error' : $msg;
                $result = $this->messageQuit('PHP Parse Error', '', true, $type, $file, 'Plugin', $text, $line, $msg);
                if ($this->isBackend()) {
                    $this->event->alert('An error occurred while loading. Please see the event log for more information.<p>' . $msg . '</p>');
                }
            }
        } else {
            echo $msg;
        }
        unset($modx->event->params);
    }

    function toAlias($text) {
        $suff= $this->getConfig('friendly_url_suffix');
        return str_replace(array('.xml'.$suff,'.rss'.$suff,'.js'.$suff,'.css'.$suff),array('.xml','.rss','.js','.css'),$text);
    }

    /**
     * Convert URL tags [~...~] to URLs
     *
     * @param string $documentSource
     * @return string
     */
    function rewriteUrls($documentSource) {
        // rewrite the urls
        if ($this->getConfig('friendly_urls') == 1) {
            $aliases= array ();
            /* foreach ($this->aliasListing as $item) {
                $aliases[$item['id']]= (strlen($item['path']) > 0 ? $item['path'] . '/' : '') . $item['alias'];
                $isfolder[$item['id']]= $item['isfolder'];
            } */
            foreach($this->documentListing as $key=>$val){
                $aliases[$val] = $key;
                $isfolder[$val] = $this->aliasListing[$val]['isfolder'];
            }
            $in= '!\[\~([0-9]+)\~\]!ise'; // Use preg_replace with /e to make it evaluate PHP
            $isfriendly= ($this->getConfig('friendly_alias_urls') == 1 ? 1 : 0);
            $pref= $this->getConfig('friendly_url_prefix');
            $suff= $this->getConfig('friendly_url_suffix');
            $thealias= '$aliases[\\1]';
            $thefolder= '$isfolder[\\1]';
            if ($this->getConfig('seostrict')=='1'){

                $found_friendlyurl= "\$this->toAlias(\$this->makeFriendlyURL('$pref','$suff',$thealias,$thefolder,'\\1'))";
            }else{
                $found_friendlyurl= "\$this->makeFriendlyURL('$pref','$suff',$thealias,$thefolder,'\\1')";
            }
            $not_found_friendlyurl= "\$this->makeFriendlyURL('$pref','$suff','" . '\\1' . "')";
            $out= "({$isfriendly} && isset({$thealias}) ? {$found_friendlyurl} : {$not_found_friendlyurl})";
            $documentSource= preg_replace($in, $out, $documentSource);

        } else {
            $in= '!\[\~([0-9]+)\~\]!is';
            $out= "index.php?id=" . '\1';
            $documentSource= preg_replace($in, $out, $documentSource);
        }

        return $documentSource;
    }

    function sendStrictURI(){
        // FIX URLs
        if (empty($this->documentIdentifier) || $this->getConfig('seostrict')=='0' || $this->getConfig('friendly_urls')=='0')
            return;
        if ($this->getConfig('site_status') == 0) return;

        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
        $len_base_url = strlen($this->getConfig('base_url'));
        if(strpos($_SERVER['REQUEST_URI'],'?'))
            list($url_path,$url_query_string) = explode('?', $_SERVER['REQUEST_URI'],2);
        else $url_path = $_SERVER['REQUEST_URI'];
        $url_path = $_GET['q'];//LANG


        if(substr($url_path,0,$len_base_url)===$this->getConfig('base_url'))
            $url_path = substr($url_path,$len_base_url);

        $strictURL =  $this->toAlias($this->makeUrl($this->documentIdentifier));

        if(substr($strictURL,0,$len_base_url)===$this->getConfig('base_url'))
            $strictURL = substr($strictURL,$len_base_url);
        $http_host = $_SERVER['HTTP_HOST'];
        $requestedURL = "{$scheme}://{$http_host}" . '/'.$_GET['q']; //LANG

        $site_url = $this->getConfig('site_url');

        if ($this->documentIdentifier == $this->getConfig('site_start')){
            if ($requestedURL != $this->getConfig('site_url')){
                // Force redirect of site start
                // $this->sendErrorPage();
                $qstring = isset($url_query_string) ? preg_replace("#(^|&)(q|id)=[^&]+#", '', $url_query_string) : ''; // Strip conflicting id/q from query string
                if ($qstring) $url = "{$site_url}?{$qstring}";
                else          $url = $site_url;
                if ($this->getConfig('base_url') != $_SERVER['REQUEST_URI']){
                    if (empty($_POST)){
                        if (('/?'.$qstring) != $_SERVER['REQUEST_URI']) {
                            $this->sendRedirect($url,0,'REDIRECT_HEADER', 'HTTP/1.0 301 Moved Permanently');
                            exit(0);
                        }
                    }
                }
            }
        }elseif ($url_path != $strictURL && $this->documentIdentifier != $this->getConfig('error_page')){
            // Force page redirect
            //$strictURL = ltrim($strictURL,'/');

            if(!empty($url_query_string))
                $qstring = preg_replace("#(^|&)(q|id)=[^&]+#", '', $url_query_string);  // Strip conflicting id/q from query string
            if ($qstring) $url = "{$site_url}{$strictURL}?{$qstring}";
            else          $url = "{$site_url}{$strictURL}";
            $this->sendRedirect($url,0,'REDIRECT_HEADER', 'HTTP/1.0 301 Moved Permanently');
            exit(0);
        }
        return;
    }

    /**
     * Parse a source string.
     *
     * Handles most MODX tags. Exceptions include:
     *   - uncached snippet tags [!...!]
     *   - URL tags [~...~]
     *
     * @param string $source
     * @return string
     */
    function parseDocumentSource($source) {
        // set the number of times we are to parse the document source
        $this->minParserPasses= empty ($this->minParserPasses) ? 2 : $this->minParserPasses;
        $this->maxParserPasses= empty ($this->maxParserPasses) ? 10 : $this->maxParserPasses;
        $passes= $this->minParserPasses;
        for ($i= 0; $i < $passes; $i++) {
            // get source length if this is the final pass
            if ($i == ($passes -1))
                $st= strlen($source);
            if ($this->dumpSnippets == 1) {
                $this->snippetsCode .= "<fieldset><legend><b style='color: #821517;'>PARSE PASS " . ($i +1) . "</b></legend><p>The following snippets (if any) were parsed during this pass.</p>";
            }

            // invoke OnParseDocument event
            $this->documentOutput= $source; // store source code so plugins can
            $this->invokeEvent("OnParseDocument"); // work on it via $modx->documentOutput
            $source= $this->documentOutput;

            $source = $this->mergeSettingsContent($source);

            // combine template and document variables
            $source= $this->mergeDocumentContent($source);
            // replace settings referenced in document
            $source= $this->mergeSettingsContent($source);
            // replace HTMLSnippets in document
            $source= $this->mergeChunkContent($source);
            // insert META tags & keywords
            if($this->getConfig('show_meta')==1) {
                $source= $this->mergeDocumentMETATags($source);
            }
            // find and merge snippets
            $source= $this->evalSnippets($source);
            // find and replace Placeholders (must be parsed last) - Added by Raymond
            $source= $this->mergePlaceholderContent($source);

            $source = $this->mergeSettingsContent($source);

            if ($this->dumpSnippets == 1) {
                $this->snippetsCode .= "</fieldset><br />";
            }
            if ($i == ($passes -1) && $i < ($this->maxParserPasses - 1)) {
                // check if source length was changed
                $et= strlen($source);
                if ($st != $et)
                    $passes++; // if content change then increase passes because
            } // we have not yet reached maxParserPasses
        }
        return $source;
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

        $this->db->connect();

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
                $_SERVER['QUERY_STRING']= $qp['query'];
                $qp= parse_url(str_replace($this->getConfig('site_url'), '', substr($url, 4)));
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

    /**
     * The next step called at the end of executeParser()
     *
     * - checks cache
     * - checks if document/resource is deleted/unpublished
     * - checks if resource is a weblink and redirects if so
     * - gets template and parses it
     * - ensures that postProcess is called when PHP is finished
     */
    function prepareResponse() {
        // we now know the method and identifier, let's check the cache
        $this->documentContent= $this->checkCache($this->documentIdentifier);
        if ($this->documentContent != "") {
            // invoke OnLoadWebPageCache  event
            $this->invokeEvent("OnLoadWebPageCache");
        } else {

            // get document object
            $this->documentObject= $this->getDocumentObject($this->documentMethod, $this->documentIdentifier, 'prepareResponse');

            // write the documentName to the object
            $this->documentName= $this->documentObject['pagetitle'];

            // validation routines
            if ($this->documentObject['deleted'] == 1) {
                $this->sendErrorPage();
            }
            //  && !$this->checkPreview()
            if ($this->documentObject['published'] == 0) {

                // Can't view unpublished pages
                if (!$this->hasPermission('view_unpublished')) {
                    $this->sendErrorPage();
                } else {
                    // Inculde the necessary files to check document permissions
                    include_once (MODX_MANAGER_PATH . 'processors/user_documents_permissions.class.php');
                    $udperms= new \udperms();
                    $udperms->user= $this->getLoginUserID();
                    $udperms->document= $this->documentIdentifier;
                    $udperms->role= $_SESSION['mgrRole'];
                    // Doesn't have access to this document
                    if (!$udperms->checkPermissions()) {
                        $this->sendErrorPage();
                    }

                }

            }

            // check whether it's a reference
            if ($this->documentObject['type'] == "reference") {
                if (is_numeric($this->documentObject['content'])) {
                    // if it's a bare document id
                    $this->documentObject['content']= $this->makeUrl($this->documentObject['content']);
                }
                elseif (strpos($this->documentObject['content'], '[~') !== false) {
                    // if it's an internal docid tag, process it
                    $this->documentObject['content']= $this->rewriteUrls($this->documentObject['content']);
                }
                $this->sendRedirect($this->documentObject['content'], 0, '', 'HTTP/1.0 301 Moved Permanently');
            }

            // check if we should not hit this document
            if ($this->documentObject['donthit'] == 1) {
                $this->config['track_visitors'] = 0;
            }

            // get the template and start parsing!
            if (!$this->documentObject['template'])
                $this->documentContent= "[*content*]"; // use blank template
            else {
                $sql= "SELECT `content` FROM " . $this->getFullTableName("site_templates") . " WHERE " . $this->getFullTableName("site_templates") . ".`id` = '" . $this->documentObject['template'] . "';";
                $result= $this->db->query($sql);
                $rowCount= $this->db->getRecordCount($result);
                if ($rowCount > 1) {
                    $this->messageQuit("Incorrect number of templates returned from database", $sql);
                }
                elseif ($rowCount == 1) {
                    $row= $this->db->getRow($result);
                    $this->documentContent= $row['content'];
                }
            }

            // invoke OnLoadWebDocument event
            $this->invokeEvent("OnLoadWebDocument");

            // Parse document source
            $this->documentContent= $this->parseDocumentSource($this->documentContent);

            // setup <base> tag for friendly urls
            //			if($this->config['friendly_urls']==1 && $this->config['use_alias_path']==1) {
            //				$this->regClientStartupHTMLBlock('<base href="'.$this->config['site_url'].'" />');
            //			}
        }

        if($this->documentIdentifier==$this->getConfig('error_page') &&  $this->getConfig('error_page')!=$this->getConfig('site_start')){
            header('HTTP/1.0 404 Not Found');
        }
        register_shutdown_function(array (
            & $this,
            "postProcess"
        )); // tell PHP to call postProcess when it shuts down
        $this->outputContent();
        //$this->postProcess();
    }

    /**
     * Add an a alert message to the system event log
     *
     * @param int $evtid Event ID
     * @param int $type Types: 1 = information, 2 = warning, 3 = error
     * @param string $msg Message to be logged
     * @param string $source source of the event (module, snippet name, etc.)
     *                       Default: Parser
     */
    function logEvent($evtid, $type, $msg, $source= 'Parser') {
        $msg= $this->db->escape($msg);
        $source= $this->db->escape($source);
        if ($GLOBALS['database_connection_charset'] == 'utf8' && extension_loaded('mbstring')) {
            $source = mb_substr($source, 0, 50 , "UTF-8");
        } else {
            $source = substr($source, 0, 50);
        }
        $LoginUserID = $this->getLoginUserID();
        if ($LoginUserID == '') $LoginUserID = 0;
        $evtid= intval($evtid);
        $type = intval($type);
        if ($type < 1) $type= 1; // Types: 1 = information, 2 = warning, 3 = error
        if (3 < $type) $type= 3;
        $sql= "INSERT INTO " . $this->getFullTableName("event_log") . " (eventid,type,createdon,source,description,user) " .
            "VALUES($evtid,$type," . time() . ",'$source','$msg','" . $LoginUserID . "')";
        $ds= @$this->db->query($sql);
        if(!$this->db->conn) $source = 'DB connect error';
        if($this->getConfig('send_errormail') != '0')
        {
            if($this->getConfig('send_errormail') <= $type)
            {
                $subject = 'Error mail from ' . $this->getConfig('site_name');
                $this->sendmail($subject,$source);
            }
        }
        if (!$ds) {
            echo "Error while inserting event log into database.";
            exit();
        }
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

    function rotate_log($target='event_log',$limit=3000, $trim=100)
    {
        if($limit < $trim) $trim = $limit;

        $table_name = $this->getFullTableName($target);
        $count = $this->db->getValue($this->db->select('COUNT(id)',$table_name));
        $over = $count - $limit;
        if(0 < $over)
        {
            $trim = ($over + $trim);
            $this->db->delete($table_name,'','',$trim);
        }
        $this->db->optimize($table_name);
    }

    /**
     * Returns true if we are currently in the manager backend
     *
     * @return boolean
     */
    function isBackend() {
        return $this->insideManager() ? true : false;
    }

    /**
     * Returns true if we are currently in the frontend
     *
     * @return boolean
     */
    function isFrontend() {
        return !$this->insideManager() ? true : false;
    }

    /**
     * Clear the cache of MODX.
     *
     * @return boolean
     */
    function clearCache($type='', $report=false) {
        if ($type=='full') {
            include_once(MODX_MANAGER_PATH . 'processors/cache_sync.class.processor.php');
            $sync = new \synccache();
            $sync->setCachepath(MODX_BASE_PATH . 'assets/cache/');
            $sync->setReport($report);
            $sync->emptyCache();
        } else {
            $files = glob(MODX_BASE_PATH . 'assets/cache/*');
            $deletedfiles = array();
            while ($file = array_shift($files)) {
                $name = basename($file);
                if (preg_match('/\.pageCache/',$name) && !in_array($name, $deletedfiles)) {
                    $deletedfiles[] = $name;
                    unlink($file);
                }
            }
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
     * Get the TVs of a document's children. Returns an array where each element represents one child doc.
     *
     * Ignores deleted children. Gets all children - there is no where clause available.
     *
     * @param int $parentid The parent docid
     *                 Default: 0 (site root)
     * @param array $tvidnames. Which TVs to fetch - Can relate to the TV ids in the db (array elements should be numeric only)
     *                                               or the TV names (array elements should be names only)
     *                      Default: Empty array
     * @param int $published Whether published or unpublished documents are in the result
     *                      Default: 1
     * @param string $docsort How to sort the result array (field)
     *                      Default: menuindex
     * @param ASC $docsortdir How to sort the result array (direction)
     *                      Default: ASC
     * @param string $tvfields Fields to fetch from site_tmplvars, default '*'
     *                      Default: *
     * @param string $tvsort How to sort each element of the result array i.e. how to sort the TVs (field)
     *                      Default: rank
     * @param string  $tvsortdir How to sort each element of the result array i.e. how to sort the TVs (direction)
     *                      Default: ASC
     * @return boolean|array
     */
    function getDocumentChildrenTVars($parentid= 0, $tvidnames= array (), $published= 1, $docsort= "menuindex", $docsortdir= "ASC", $tvfields= "*", $tvsort= "rank", $tvsortdir= "ASC") {
        $docs= $this->getDocumentChildren($parentid, $published, 0, '*', '', $docsort, $docsortdir);
        if (!$docs)
            return false;
        else {
            $result= array ();
            // get user defined template variables
            $fields= ($tvfields == "") ? "tv.*" : 'tv.' . implode(',tv.', preg_replace("/^\s/i", "", explode(',', $tvfields)));
            $tvsort= ($tvsort == "") ? "" : 'tv.' . implode(',tv.', preg_replace("/^\s/i", "", explode(',', $tvsort)));
            if ($tvidnames == "*")
                $query= "tv.id<>0";
            else
                $query= (is_numeric($tvidnames[0]) ? "tv.id" : "tv.name") . " IN ('" . implode("','", $tvidnames) . "')";
            if ($docgrp= $this->getUserDocGroups())
                $docgrp= implode(",", $docgrp);

            $docCount= count($docs);
            for ($i= 0; $i < $docCount; $i++) {

                $tvs= array ();
                $docRow= $docs[$i];
                $docid= $docRow['id'];

                $sql= "SELECT $fields, IF(tvc.value!='',tvc.value,tv.default_text) as value ";
                $sql .= "FROM " . $this->getFullTableName('site_tmplvars') . " tv ";
                $sql .= "INNER JOIN " . $this->getFullTableName('site_tmplvar_templates')." tvtpl ON tvtpl.tmplvarid = tv.id ";
                $sql .= "LEFT JOIN " . $this->getFullTableName('site_tmplvar_contentvalues')." tvc ON tvc.tmplvarid=tv.id AND tvc.contentid = '" . $docid . "' ";
                $sql .= "WHERE " . $query . " AND tvtpl.templateid = " . $docRow['template'];
                if ($tvsort)
                    $sql .= " ORDER BY $tvsort $tvsortdir ";
                $rs= $this->db->query($sql);
                $limit= @ $this->db->getRecordCount($rs);
                for ($x= 0; $x < $limit; $x++) {
                    array_push($tvs, @ $this->db->getRow($rs));
                }

                // get default/built-in template variables
                ksort($docRow);
                foreach ($docRow as $key => $value) {
                    if ($tvidnames == "*" || in_array($key, $tvidnames))
                        array_push($tvs, array (
                            "name" => $key,
                            "value" => $value
                        ));
                }

                if (count($tvs))
                    array_push($result, $tvs);
            }
            return $result;
        }
    }

    /**
     * Get the TV outputs of a document's children.
     *
     * Returns an array where each element represents one child doc and contains the result from getTemplateVarOutput()
     *
     * Ignores deleted children. Gets all children - there is no where clause available.
     *
     * @param int $parentid The parent docid
     *                        Default: 0 (site root)
     * @param array $tvidnames. Which TVs to fetch. In the form expected by getTemplateVarOutput().
     *                        Default: Empty array
     * @param int $published Whether published or unpublished documents are in the result
     *                        Default: 1
     * @param string $docsort How to sort the result array (field)
     *                        Default: menuindex
     * @param ASC $docsortdir How to sort the result array (direction)
     *                        Default: ASC
     * @return boolean|array
     */
    function getDocumentChildrenTVarOutput($parentid= 0, $tvidnames= array (), $published= 1, $docsort= "menuindex", $docsortdir= "ASC") {
        $docs= $this->getDocumentChildren($parentid, $published, 0, '*', '', $docsort, $docsortdir);
        if (!$docs)
            return false;
        else {
            $result= array ();
            for ($i= 0; $i < count($docs); $i++) {
                $tvs= $this->getTemplateVarOutput($tvidnames, $docs[$i]["id"], $published);
                if ($tvs)
                    $result[$docs[$i]['id']]= $tvs; // Use docid as key - netnoise 2006/08/14
            }
            return $result;
        }
    }

    /**
     * Modified by Raymond for TV - Orig Modified by Apodigm - DocVars
     * Returns a single site_content field or TV record from the db.
     *
     * If a site content field the result is an associative array of 'name' and 'value'.
     *
     * If a TV the result is an array representing a db row including the fields specified in $fields.
     *
     * @param string $idname Can be a TV id or name
     * @param string $fields Fields to fetch from site_tmplvars. Default: *
     * @param type $docid Docid. Defaults to empty string which indicates the current document.
     * @param int $published Whether published or unpublished documents are in the result
     *                        Default: 1
     * @return boolean
     */
    function getTemplateVar($idname= "", $fields= "*", $docid= "", $published= 1) {
        if ($idname == "") {
            return false;
        } else {
            $result= $this->getTemplateVars(array ($idname), $fields, $docid, $published, "", ""); //remove sorting for speed
            return ($result != false) ? $result[0] : false;
        }
    }

    /**
     * Returns an array of site_content field fields and/or TV records from the db
     *
     * Elements representing a site content field consist of an associative array of 'name' and 'value'.
     *
     * Elements representing a TV consist of an array representing a db row including the fields specified in $fields.
     *
     * @param array $idnames Which TVs to fetch - Can relate to the TV ids in the db (array elements should be numeric only)
     *                                               or the TV names (array elements should be names only)
     *                        Default: Empty array
     * @param string $fields Fields to fetch from site_tmplvars.
     *                        Default: *
     * @param string $docid Docid. Defaults to empty string which indicates the current document.
     * @param int $published Whether published or unpublished documents are in the result
     *                        Default: 1
     * @param string $sort How to sort the result array (field)
     *                        Default: rank
     * @param string $dir How to sort the result array (direction)
     *                        Default: ASC
     * @return boolean|array
     */
    function getTemplateVars($idnames= array (), $fields= "*", $docid= "", $published= 1, $sort= "rank", $dir= "ASC") {
        if (($idnames != '*' && !is_array($idnames)) || count($idnames) == 0) {
            return false;
        } else {
            $result= array ();

            // get document record
            if ($docid == "") {
                $docid= $this->documentIdentifier;
                $docRow= $this->documentObject;
            } else {
                $docRow= $this->getDocument($docid, '*', $published);
                if (!$docRow)
                    return false;
            }

            // get user defined template variables
            $fields= ($fields == "") ? "tv.*" : 'tv.' . implode(',tv.', preg_replace("/^\s/i", "", explode(',', $fields)));
            $sort= ($sort == "") ? "" : 'tv.' . implode(',tv.', preg_replace("/^\s/i", "", explode(',', $sort)));
            if ($idnames == "*")
                $query= "tv.id<>0";
            else
                $query= (is_numeric($idnames[0]) ? "tv.id" : "tv.name") . " IN ('" . implode("','", $idnames) . "')";
            $sql= "SELECT $fields, IF(tvc.value!='',tvc.value,tv.default_text) as value ";
            $sql .= "FROM " . $this->getFullTableName('site_tmplvars')." tv ";
            $sql .= "INNER JOIN " . $this->getFullTableName('site_tmplvar_templates')." tvtpl ON tvtpl.tmplvarid = tv.id ";
            $sql .= "LEFT JOIN " . $this->getFullTableName('site_tmplvar_contentvalues')." tvc ON tvc.tmplvarid=tv.id AND tvc.contentid = '" . $docid . "' ";
            $sql .= "WHERE " . $query . " AND tvtpl.templateid = " . $docRow['template'];
            if ($sort)
                $sql .= " ORDER BY $sort $dir ";
            $rs= $this->db->query($sql);
            for ($i= 0; $i < @ $this->db->getRecordCount($rs); $i++) {
                array_push($result, @ $this->db->getRow($rs));
            }

            // get default/built-in template variables
            ksort($docRow);
            foreach ($docRow as $key => $value) {
                if ($idnames == "*" || in_array($key, $idnames))
                    array_push($result, array (
                        "name" => $key,
                        "value" => $value
                    ));
            }

            return $result;
        }
    }

    /**
     * Returns an associative array containing TV rendered output values.
     *
     * @param type $idnames Which TVs to fetch - Can relate to the TV ids in the db (array elements should be numeric only)
     *                                               or the TV names (array elements should be names only)
     *                        Default: Empty array
     * @param string $docid Docid. Defaults to empty string which indicates the current document.
     * @param int $published Whether published or unpublished documents are in the result
     *                        Default: 1
     * @param string $sep
     * @return boolean|array
     */
    function getTemplateVarOutput($idnames= array (), $docid= "", $published= 1, $sep='') {
        if (count($idnames) == 0) {
            return false;
        } else {
            $output= array ();
            $vars= ($idnames == '*' || is_array($idnames)) ? $idnames : array ($idnames);
            $docid= intval($docid) ? intval($docid) : $this->documentIdentifier;
            $result= $this->getTemplateVars($vars, "*", $docid, $published, "", "", $sep); // remove sort for speed
            if ($result == false)
                return false;
            else {
                $baspath= MODX_MANAGER_PATH . "includes";
                include_once $baspath . "/tmplvars.format.inc.php";
                include_once $baspath . "/tmplvars.commands.inc.php";
                for ($i= 0; $i < count($result); $i++) {
                    $row= $result[$i];
                    if (!$row['id'])
                        $output[$row['name']]= $row['value'];
                    else	$output[$row['name']]= getTVDisplayFormat($row['name'], $row['value'], $row['display'], $row['display_params'], $row['type'], $docid, $sep);
                }
                return $output;
            }
        }
    }

    /**
     * Returns the full table name based on db settings
     *
     * @param string $tbl Table name
     * @return string Table name with prefix
     */
    function getFullTableName($tbl) {
        return $this->db->config['dbase'] . ".`" . $this->db->config['table_prefix'] . $tbl . "`";
    }

    /**
     * Sends a message to a user's message box.
     *
     * @param string $type Type of the message
     * @param string $to The recipient of the message
     * @param string $from The sender of the message
     * @param string $subject The subject of the message
     * @param string $msg The message body
     * @param int $private Whether it is a private message, or not
     *                     Default : 0
     */
    function sendAlert($type, $to, $from, $subject, $msg, $private= 0) {
        $private= ($private) ? 1 : 0;
        if (!is_numeric($to)) {
            // Query for the To ID
            $sql= "SELECT id FROM " . $this->getFullTableName("manager_users") . " WHERE username='$to';";
            $rs= $this->db->query($sql);
            if ($this->db->getRecordCount($rs)) {
                $rs= $this->db->getRow($rs);
                $to= $rs['id'];
            }
        }
        if (!is_numeric($from)) {
            // Query for the From ID
            $sql= "SELECT id FROM " . $this->getFullTableName("manager_users") . " WHERE username='$from';";
            $rs= $this->db->query($sql);
            if ($this->db->getRecordCount($rs)) {
                $rs= $this->db->getRow($rs);
                $from= $rs['id'];
            }
        }
        // insert a new message into user_messages
        $sql= "INSERT INTO " . $this->getFullTableName("user_messages") . " ( id , type , subject , message , sender , recipient , private , postdate , messageread ) VALUES ( '', '$type', '$subject', '$msg', '$from', '$to', '$private', '" . time() . "', '0' );";
        $rs= $this->db->query($sql);
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
        if (!array_key_exists($evtName,$this->pluginEvent))
            $this->pluginEvent[$evtName] = array();
        return array_push($this->pluginEvent[$evtName], $pluginName); // return array count
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
        unset ($this->pluginEvent[$evtName]);
    }

    /**
     * Remove all event listners - only for use within the current execution cycle
     */
    function removeAllEventListener() {
        unset ($this->pluginEvent);
        $this->pluginEvent= array ();
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
        if (!isset ($this->pluginEvent[$evtName]))
            return false;
        $el= $this->pluginEvent[$evtName];
        $results= array ();
        $numEvents= count($el);
        if ($numEvents > 0)
            for ($i= 0; $i < $numEvents; $i++) { // start for loop
                if ($this->dumpPlugins == 1) $eventtime = $this->getMicroTime();
                $pluginName= $el[$i];
                $pluginName = stripslashes($pluginName);
                // reset event object
                $e= & $this->Event;
                $e->_resetEventObject();
                $e->name= $evtName;
                $e->activePlugin= $pluginName;

                // get plugin code
                if (isset ($this->pluginCache[$pluginName])) {
                    $pluginCode= $this->pluginCache[$pluginName];
                    $pluginProperties= isset($this->pluginCache[$pluginName . "Props"]) ? $this->pluginCache[$pluginName . "Props"] : '';
                } else {
                    $sql= "SELECT `name`, `plugincode`, `properties` FROM " . $this->getFullTableName("site_plugins") . " WHERE `name`='" . $pluginName . "' AND `disabled`=0;";
                    $result= $this->db->query($sql);
                    if ($this->db->getRecordCount($result) == 1) {
                        $row= $this->db->getRow($result);
                        $pluginCode= $this->pluginCache[$row['name']]= $row['plugincode'];
                        $pluginProperties= $this->pluginCache[$row['name'] . "Props"]= $row['properties'];
                    } else {
                        $pluginCode= $this->pluginCache[$pluginName]= "return false;";
                        $pluginProperties= '';
                    }
                }

                // load default params/properties
                $parameter= $this->parseProperties($pluginProperties);
                if (!empty ($extParams))
                    $parameter= array_merge($parameter, $extParams);

                // eval plugin
                $this->evalPlugin($pluginCode, $parameter);
                if ($this->dumpPlugins == 1) {
                    $eventtime = $this->getMicroTime() - $eventtime;
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

    /***************************************************************************************/
    /* End of API functions								       */
    /***************************************************************************************/

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
        if($this->stopOnNotice == false)
        {
            switch($nr)
            {
                case E_NOTICE:
                    if($this->error_reporting <= 2) return true;
                    break;
                case E_STRICT:
                case E_DEPRECATED:
                    if($this->error_reporting <= 1) return true;
                    break;
                default:
                    if($this->error_reporting === 0) return true;
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

    function messageQuit($msg= 'unspecified error', $query= '', $is_error= true, $nr= '', $file= '', $source= '', $text= '', $line= '', $output='') {

        $version= isset ($GLOBALS['modx_version']) ? $GLOBALS['modx_version'] : '';
        $release_date= isset ($GLOBALS['release_date']) ? $GLOBALS['release_date'] : '';
        $request_uri = "http://".$_SERVER['HTTP_HOST'].($_SERVER["SERVER_PORT"]==80?"":(":".$_SERVER["SERVER_PORT"])).$_SERVER['REQUEST_URI'];
        $request_uri = htmlspecialchars($request_uri, ENT_QUOTES, $this->getConfig('modx_charset'));
        $ua          = htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, $this->getConfig('modx_charset'));
        $referer     = htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES, $this->getConfig('modx_charset'));
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

        if(preg_match('@^[0-9]+@',$this->documentIdentifier))
        {
            $resource  = $this->getDocumentObject('id',$this->documentIdentifier);
            $url = $this->makeUrl($this->documentIdentifier,'','','full');
            $link = '<a href="' . $url . '" target="_blank">' . $resource['pagetitle'] . '</a>';
            $str .= '<tr><td valign="top">Resource : </td>';
            $str .= '<td>[' . $this->documentIdentifier . ']' . $link . '</td></tr>';
        }

        if(!empty($this->currentSnippet))
        {
            $str .= "<tr><td>Current Snippet : </td>";
            $str .= '<td>' . $this->currentSnippet . '</td></tr>';
        }

        if(!empty($this->event->activePlugin))
        {
            $str .= "<tr><td>Current Plugin : </td>";
            $str .= '<td>' . $this->event->activePlugin . '(' . $this->event->name . ')' . '</td></tr>';
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

        $totalTime= ($this->getMicroTime() - $this->tstart);

        $mem = memory_get_peak_usage(true);
        $total_mem = $mem - $this->mstart;
        $total_mem = ($total_mem / 1024 / 1024) . ' mb';

        $queryTime= $this->queryTime;
        $phpTime= $totalTime - $queryTime;
        $queries= isset ($this->executedQueries) ? $this->executedQueries : 0;
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
        if(!empty($this->currentSnippet)) $source = 'Snippet - ' . $this->currentSnippet;
        elseif(!empty($this->event->activePlugin)) $source = 'Plugin - ' . $this->event->activePlugin;
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
        $this->logEvent(0, $error_level, $str,$source);

        if($error_level === 2 && $this->error_reporting!=='99') return true;
        if($this->error_reporting==='99' && !isset($_SESSION['mgrValidated'])) return true;

        // Set 500 response header
        if($error_level !== 2) header('HTTP/1.1 500 Internal Server Error');

        // Display error
        if (isset($_SESSION['mgrValidated']))
        {
            echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html><head><title>MODX Content Manager ' . $version . ' &raquo; ' . $release_date . '</title>
	             <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	             <link rel="stylesheet" type="text/css" href="' . $this->getConfig('site_manager_url') . 'media/style/' . $this->getConfig('manager_theme') . '/style.css" />
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

    function getRegisteredClientScripts() {
        return implode("\n", $this->jscripts);
    }

    function getRegisteredClientStartupScripts() {
        return implode("\n", $this->sjscripts);
    }

    /**
     * Format alias to be URL-safe. Strip invalid characters.
     *
     * @param string Alias to be formatted
     * @return string Safe alias
     */
    function stripAlias($alias) {
        // let add-ons overwrite the default behavior
        $results = $this->invokeEvent('OnStripAlias', array ('alias'=>$alias));
        if (!empty($results)) {
            // if multiple plugins are registered, only the last one is used
            return end($results);
        } else {
            // default behavior: strip invalid characters and replace spaces with dashes.
            $alias = strip_tags($alias); // strip HTML
            $alias = preg_replace('/[^\.A-Za-z0-9 _-]/', '', $alias); // strip non-alphanumeric characters
            $alias = preg_replace('/\s+/', '-', $alias); // convert white-space to dash
            $alias = preg_replace('/-+/', '-', $alias);  // convert multiple dashes to one
            $alias = trim($alias, '-'); // trim excess
            return $alias;
        }
    }

    function getIdFromAlias($alias)
    {
        $children = array();

        $tbl_site_content = $this->getFullTableName('site_content');
        if($this->getConfig('use_alias_path')==1)
        {
            if(strpos($alias,'/')!==false) $_a = explode('/', $alias);
            else                           $_a[] = $alias;
            $id= 0;

            foreach($_a as $alias)
            {
                if($id===false) break;
                $alias = $this->db->escape($alias);
                $rs  = $this->db->select('id', $tbl_site_content, "deleted=0 and parent='{$id}' and alias='{$alias}'");
                if($this->db->getRecordCount($rs)==0) $rs  = $this->db->select('id', $tbl_site_content, "deleted=0 and parent='{$id}' and id='{$alias}'");
                $row = $this->db->getRow($rs);

                if($row) $id = $row['id'];
                else     $id = false;
            }
        }
        else
        {
            $rs = $this->db->select('id', $tbl_site_content, "deleted=0 and alias='{$alias}'", 'parent, menuindex');
            $row = $this->db->getRow($rs);

            if($row) $id = $row['id'];
            else     $id = false;
        }
        return $id;
    }

    // php compat
    function htmlspecialchars($str, $flags = ENT_COMPAT)
    {
        $this->loadExtension('PHPCOMPAT');
        return $this->phpcompat->htmlspecialchars($str, $flags);
    }
    // End of class.


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
        return $this->_pimple['Response']->sendRedirect($url, $count_attempts, $type, $responseCode);
    }
    function sendForward($id, $responseCode= '') {
        return $this->_pimple['Response']->sendForward($id, $responseCode);
    }
    function sendErrorPage() {
        return $this->_pimple['Response']->sendErrorPage();
    }
    function sendUnauthorizedPage() {
        $this->_pimple['Response']->sendUnauthorizedPage();
    }
}