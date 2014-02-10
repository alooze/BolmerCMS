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
        $pimple['debug'] = $pimple->share(function($inj){
            return new \MODxCore\Debug($inj);
        });
        $pimple['db'] = $pimple->share(function($inj){
            return $inj['modx']->db;
        });
        $pimple['config'] = $pimple->share(function($inj){
            return $inj['modx']->config;
        });
        $pimple['response'] = $pimple->share(function($inj){
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
        $pimple['log'] = $pimple->share(function($inj){
            return new \MODxCore\Log($inj);
        });
        $pimple['request'] = $pimple->share(function($inj){
            return new \MODxCore\Request($inj);
        });
        $pimple['parser'] = $pimple->share(function($inj){
            return new \MODxCore\Parser($inj);
        });
        $pimple['plugin'] = $pimple->share(function($inj){
            return new \MODxCore\Parser\Plugin($inj);
        });
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

    function toAlias($text) {
        $suff= $this->getConfig('friendly_url_suffix');
        return str_replace(array('.xml'.$suff,'.rss'.$suff,'.js'.$suff,'.css'.$suff),array('.xml','.rss','.js','.css'),$text);
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

    // php compat
    function htmlspecialchars($str, $flags = ENT_COMPAT)
    {
        $this->loadExtension('PHPCOMPAT');
        return $this->phpcompat->htmlspecialchars($str, $flags);
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