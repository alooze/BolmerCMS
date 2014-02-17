<?php namespace Bolmer\Presenter;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 14:25
 */

    class Response{
        /** @var \Bolmer\Pimple $_inj */
        private $_inj = null;

        /** @var \Bolmer\Core $_modx */
        protected $_modx = null;

        public function __construct(\Pimple $inj){
            $this->_inj= $inj;
            $this->_modx = $inj['modx'];
        }

        /**
         * Redirect
         *
         * @global string $base_url
         * @global string $site_url
         * @param string $url
         * @param int $count_attempts
         * @param type $type
         * @param type $responseCode
         * @return boolean
         */
        function sendRedirect($url, $count_attempts= 0, $type= '', $responseCode= '') {
            if (empty ($url)) {
                return false;
            } else {
                if ($count_attempts == 1) {
                    // append the redirect count string to the url
                    $currentNumberOfRedirects= isset ($_REQUEST['err']) ? $_REQUEST['err'] : 0;
                    if ($currentNumberOfRedirects > 3) {
                        $this->_modx->messageQuit('Redirection attempt failed - please ensure the document you\'re trying to redirect to exists. <p>Redirection URL: <i>' . $url . '</i></p>');
                    } else {
                        $currentNumberOfRedirects += 1;
                        if (strpos($url, "?") > 0) {
                            $url .= "&err=$currentNumberOfRedirects";
                        } else {
                            $url .= "?err=$currentNumberOfRedirects";
                        }
                    }
                }
                if ($type == 'REDIRECT_REFRESH') {
                    $header= 'Refresh: 0;URL=' . $url;
                }
                elseif ($type == 'REDIRECT_META') {
                    $header= '<META HTTP-EQUIV="Refresh" CONTENT="0; URL=' . $url . '" />';
                    echo $header;
                    exit;
                }
                elseif ($type == 'REDIRECT_HEADER' || empty ($type)) {
                    // check if url has /$base_url
                    if (substr($url, 0, strlen($this->_inj['global_config']['base_url'])) == $this->_inj['global_config']['base_url']) {
                        // append $site_url to make it work with Location:
                        $url= $this->_inj['global_config']['site_url'] . substr($url, strlen($this->_inj['global_config']['base_url']));
                    }
                    if (strpos($url, "\n") === false) {
                        $header= 'Location: ' . $url;
                    } else {
                        $this->_modx->messageQuit('No newline allowed in redirect url.');
                    }
                }
                if ($responseCode && (strpos($responseCode, '30') !== false)) {
                    header($responseCode);
                }
                header($header);
                exit();
            }
        }

        /**
         * Forward to another page
         *
         * @param int $id
         * @param string $responseCode
         */
        function sendForward($id, $responseCode= '') {
            if ($this->_modx->forwards > 0) {
                $this->_modx->forwards= $this->_modx->forwards - 1;
                $this->_modx->documentIdentifier= $id;
                $this->_modx->documentMethod= 'id';
                $this->_modx->documentObject= $this->_modx->getDocumentObject('id', $id);
                if ($responseCode) {
                    header($responseCode);
                }
                $this->_modx->prepareResponse();
                exit();
            } else {
                header('HTTP/1.0 500 Internal Server Error');
                die('<h1>ERROR: Too many forward attempts!</h1><p>The request could not be completed due to too many unsuccessful forward attempts.</p>');
            }
        }

        /**
         * Redirect to the error page, by calling sendForward(). This is called for example when the page was not found.
         */
        function sendErrorPage() {
            // invoke OnPageNotFound event
            $this->_modx->invokeEvent('OnPageNotFound');
            $url = $this->_modx->getConfig('error_page', $this->_modx->getConfig('site_start'));
            $this->sendForward($url, 'HTTP/1.0 404 Not Found');
            exit();
        }

        function sendUnauthorizedPage() {
            // invoke OnPageUnauthorized event
            $_REQUEST['refurl'] = $this->_modx->documentIdentifier;
            $this->_modx->invokeEvent('OnPageUnauthorized');
            if ($this->_modx->getConfig('unauthorized_page')) {
                $unauthorizedPage= $this->_modx->getConfig('unauthorized_page');
            } elseif ($this->_modx->getConfig('error_page')) {
                $unauthorizedPage= $this->_modx->getConfig('error_page');
            } else {
                $unauthorizedPage= $this->_modx->getConfig('site_start');
            }
            $this->_modx->sendForward($unauthorizedPage, 'HTTP/1.1 401 Unauthorized');
            exit();
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
            $this->_modx->documentContent= $this->_modx->checkCache($this->_modx->documentIdentifier);

            if ($this->_modx->documentContent != "") {
                // invoke OnLoadWebPageCache  event
                $this->_modx->invokeEvent("OnLoadWebPageCache");
            } else {

                // get document object
                $this->_modx->documentObject= $this->_modx->getDocumentObject($this->_modx->documentMethod, $this->_modx->documentIdentifier, 'prepareResponse');
                // write the documentName to the object
                $this->_modx->documentName= $this->_modx->documentObject['pagetitle'];

                // validation routines
                if ($this->_modx->documentObject['deleted'] == 1) {
                    $this->_modx->sendErrorPage();
                }

                //  && !$this->checkPreview()
                if ($this->_modx->documentObject['published'] == 0) {

                    // Can't view unpublished pages
                    if (!$this->_modx->hasPermission('view_unpublished')) {
                        $this->_modx->sendErrorPage();
                    } else {
                        // Inculde the necessary files to check document permissions
                        include_once (MODX_MANAGER_PATH . 'processors/user_documents_permissions.class.php');
                        $udperms= new \udperms();
                        $udperms->user= $this->_modx->getLoginUserID();
                        $udperms->document= $this->_modx->documentIdentifier;
                        $udperms->role= $_SESSION['mgrRole'];
                        // Doesn't have access to this document
                        if (!$udperms->checkPermissions()) {
                            $this->_modx->sendErrorPage();
                        }

                    }

                }

                // check whether it's a reference
                if ($this->_modx->documentObject['type'] == "reference") {
                    if (is_numeric($this->_modx->documentObject['content'])) {
                        // if it's a bare document id
                        $this->_modx->documentObject['content']= $this->_modx->makeUrl($this->_modx->documentObject['content']);
                    }
                    elseif (strpos($this->_modx->documentObject['content'], '[~') !== false) {
                        // if it's an internal docid tag, process it
                        $this->_modx->documentObject['content']= $this->_modx->rewriteUrls($this->_modx->documentObject['content']);
                    }
                    $this->_modx->sendRedirect($this->_modx->documentObject['content'], 0, '', 'HTTP/1.0 301 Moved Permanently');
                }

                // check if we should not hit this document
                if ($this->_modx->documentObject['donthit'] == 1) {
                    $this->_modx->config['track_visitors'] = 0;
                }

                // get the template and start parsing!
                if (!$this->_modx->documentObject['template'])
                    $this->_modx->documentContent= "[*content*]"; // use blank template
                else {
                    $sql= "SELECT `content` FROM " . $this->_modx->getFullTableName("site_templates") . " WHERE " . $this->_modx->getFullTableName("site_templates") . ".`id` = '" . $this->_modx->documentObject['template'] . "';";
                    $result= $this->_modx->db->query($sql);
                    $rowCount= $this->_modx->db->getRecordCount($result);

                    if ($rowCount > 1) {

                        $this->_modx->messageQuit("Incorrect number of templates returned from database", $sql);
                    }
                    elseif ($rowCount == 1) {

                        $row= $this->_modx->db->getRow($result);
                        $this->_modx->documentContent= $row['content'];
                    }
                }

                // invoke OnLoadWebDocument event
                $this->_modx->invokeEvent("OnLoadWebDocument");

                // Parse document source
                $this->_modx->documentContent = $this->_modx->parseDocumentSource($this->_modx->documentContent);

                // setup <base> tag for friendly urls
                //			if($this->config['friendly_urls']==1 && $this->config['use_alias_path']==1) {
                //				$this->regClientStartupHTMLBlock('<base href="'.$this->config['site_url'].'" />');
                //			}
            }
            if($this->_modx->documentIdentifier==$this->_modx->getConfig('error_page') &&  $this->_modx->getConfig('error_page')!=$this->_modx->getConfig('site_start')){
                header('HTTP/1.0 404 Not Found');
            }

            register_shutdown_function(array (
                & $this,
                "postProcess"
            )); // tell PHP to call postProcess when it shuts down

            $this->_modx->outputContent();
        }

        /**
         * Final jobs.
         *
         * - cache page
         */
        function postProcess() {
            // if the current document was generated, cache it!
            if ($this->_modx->documentGenerated == 1 && $this->_modx->documentObject['cacheable'] == 1 && $this->_modx->documentObject['type'] == 'document' && $this->_modx->documentObject['published'] == 1) {
                
                // invoke OnBeforeSaveWebPageCache event
                $this->_modx->invokeEvent("OnBeforeSaveWebPageCache");

                $cache = $this->_inj['cache'];
                $cacheId = $cache->getCacheId($this->_modx->documentIdentifier);

                // get and store document groups inside document object. 
                // Document groups will be used to check security on cache pages
                $sql = "SELECT document_group FROM " . $this->_modx->getFullTableName("document_groups") . " WHERE document='" . $this->_modx->documentIdentifier . "'";
                $docGroups= $this->_modx->db->getColumn("document_group", $sql);

                // Attach Document Groups and Scripts
                if (is_array($docGroups)) $this->_modx->documentObject['__MODxDocGroups__'] = implode(",", $docGroups);

                $docObjSerial= serialize($this->_modx->documentObject);
                $cacheContent= $docObjSerial . "<!--__MODxCacheSpliter__-->" . $this->_modx->documentContent;
                $cache->set($cacheId, "<?php die('Unauthorized access.'); ?>$cacheContent");
            }

            // Useful for example to external page counters/stats packages
            $this->_modx->invokeEvent('OnWebPageComplete');

            // end post processing
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

        # Returns true, install or interact when inside manager
        function insideManager() {
            $m= false;
            if (defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == 'true') {
                $m= true;
                if (defined('SNIPPET_INTERACTIVE_MODE') && SNIPPET_INTERACTIVE_MODE == 'true')
                    $m= "interact";
                else
                    if (defined('SNIPPET_INSTALL_MODE') && SNIPPET_INSTALL_MODE == 'true')
                        $m= "install";
            }
            return $m;
        }

        function sendStrictURI(){
            // FIX URLs
            if (empty($this->_modx->documentIdentifier) || $this->_modx->getConfig('seostrict')=='0' || $this->_modx->getConfig('friendly_urls')=='0')
                return;
            if ($this->_modx->getConfig('site_status') == 0) return;

            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
            $len_base_url = strlen($this->_modx->getConfig('base_url'));
            if(strpos($_SERVER['REQUEST_URI'],'?'))
                list($url_path,$url_query_string) = explode('?', $_SERVER['REQUEST_URI'],2);
            else $url_path = $_SERVER['REQUEST_URI'];
            $url_path = $_GET['q'];//LANG


            if(substr($url_path,0,$len_base_url)===$this->_modx->getConfig('base_url'))
                $url_path = substr($url_path,$len_base_url);

            $strictURL =  $this->_modx->toAlias($this->_modx->makeUrl($this->_modx->documentIdentifier));

            if(substr($strictURL,0,$len_base_url)===$this->_modx->getConfig('base_url'))
                $strictURL = substr($strictURL,$len_base_url);
            $http_host = $_SERVER['HTTP_HOST'];
            $requestedURL = "{$scheme}://{$http_host}" . '/'.$_GET['q']; //LANG

            $site_url = $this->_modx->getConfig('site_url');

            if ($this->_modx->documentIdentifier == $this->_modx->getConfig('site_start')){
                if ($requestedURL != $this->_modx->getConfig('site_url')){
                    // Force redirect of site start
                    // $this->sendErrorPage();
                    $qstring = isset($url_query_string) ? preg_replace("#(^|&)(q|id)=[^&]+#", '', $url_query_string) : ''; // Strip conflicting id/q from query string
                    if ($qstring) $url = "{$site_url}?{$qstring}";
                    else          $url = $site_url;
                    if ($this->_modx->getConfig('base_url') != $_SERVER['REQUEST_URI']){
                        if (empty($_POST)){
                            if (('/?'.$qstring) != $_SERVER['REQUEST_URI']) {
                                $this->sendRedirect($url,0,'REDIRECT_HEADER', 'HTTP/1.0 301 Moved Permanently');
                                exit(0);
                            }
                        }
                    }
                }
            }elseif ($url_path != $strictURL && $this->_modx->documentIdentifier != $this->_modx->getConfig('error_page')){
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
            $this->_modx->documentOutput= $this->_modx->documentContent;
            if ($this->_modx->documentGenerated == 1 && $this->_modx->documentObject['cacheable'] == 1 && $this->_modx->documentObject['type'] == 'document' && $this->_modx->documentObject['published'] == 1) {
                if (!empty($this->_modx->sjscripts)) $this->_modx->documentObject['__MODxSJScripts__'] = $this->_modx->sjscripts;
                if (!empty($this->_modx->jscripts)) $this->_modx->documentObject['__MODxJScripts__'] = $this->_modx->jscripts;
            }

            // check for non-cached snippet output
            if (strpos($this->_modx->documentOutput, '[!') > -1) {
                $this->_modx->documentOutput= str_replace('[!', '[[', $this->_modx->documentOutput);
                $this->_modx->documentOutput= str_replace('!]', ']]', $this->_modx->documentOutput);

                // Parse document source
                $this->_modx->documentOutput= $this->_modx->parseDocumentSource($this->_modx->documentOutput);
            }

            // Moved from prepareResponse() by sirlancelot
            // Insert Startup jscripts & CSS scripts into template - template must have a <head> tag
            if ($js= $this->_modx->getRegisteredClientStartupScripts()) {
                // change to just before closing </head>
                // $this->documentContent = preg_replace("/(<head[^>]*>)/i", "\\1\n".$js, $this->documentContent);
                $this->_modx->documentOutput= preg_replace("/(<\/head>)/i", $js . "\n\\1", $this->_modx->documentOutput);
            }

            // Insert jscripts & html block into template - template must have a </body> tag
            if ($js= $this->_modx->getRegisteredClientScripts()) {
                $this->_modx->documentOutput= preg_replace("/(<\/body>)/i", $js . "\n\\1", $this->_modx->documentOutput);
            }
            // End fix by sirlancelot

            // remove all unused placeholders
            if (strpos($this->_modx->documentOutput, '[+') > -1) {
                $matches= array ();
                preg_match_all('~\[\+(.*?)\+\]~s', $this->_modx->documentOutput, $matches);
                if ($matches[0])
                    $this->_modx->documentOutput= str_replace($matches[0], '', $this->_modx->documentOutput);
            }

            $this->_modx->documentOutput= $this->_modx->rewriteUrls($this->_modx->documentOutput);

            // send out content-type and content-disposition headers
            if (IN_PARSER_MODE == "true") {
                $type= !empty ($this->_modx->contentTypes[$this->_modx->documentIdentifier]) ? $this->_modx->contentTypes[$this->_modx->documentIdentifier] : "text/html";
                header('Content-Type: ' . $type . '; charset=' . $this->_modx->getConfig('modx_charset'));
//            if (($this->documentIdentifier == $this->config['error_page']) || $redirect_error)
//                header('HTTP/1.0 404 Not Found');
                if (!$this->_modx->checkPreview() && $this->_modx->documentObject['content_dispo'] == 1) {
                    if ($this->_modx->documentObject['alias'])
                        $name= $this->_modx->documentObject['alias'];
                    else {
                        // strip title of special characters
                        $name= $this->_modx->documentObject['pagetitle'];
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

            $stats = $this->_modx->getTimerStats($this->_modx->tstart);

            $out =& $this->_modx->documentOutput;
            $out= str_replace("[^q^]", $stats['queries'] , $out);
            $out= str_replace("[^qt^]", $stats['queryTime'] , $out);
            $out= str_replace("[^p^]", $stats['phpTime'] , $out);
            $out= str_replace("[^t^]", $stats['totalTime'] , $out);
            $out= str_replace("[^s^]", $stats['source'] , $out);
            $out= str_replace("[^m^]", $stats['phpMemory'], $out);
            //$this->documentOutput= $out;

            // invoke OnWebPagePrerender event
            if (!$noEvent) {
                $this->_modx->invokeEvent('OnWebPagePrerender');
            }
            global $sanitize_seed;
            if(strpos($this->_modx->documentOutput, $sanitize_seed)!==false) {
                $this->_modx->documentOutput = str_replace($sanitize_seed, '', $this->_modx->documentOutput);
            }

            echo $this->_modx->documentOutput;
            if ($this->_modx->dumpSQL) {
                echo \Bolmer\Debug::showQuery();
            }
            if ($this->_modx->dumpSnippets) {
                $sc = "";
                $tt = 0;
                foreach ($this->_modx->snippetsTime as $s=>$t) {
                    $sc .= "$s: ".$this->_modx->snippetsCount[$s]." (".sprintf("%2.2f ms", $t*1000).")<br>";
                    $tt += $t;
                }
                echo "<fieldset><legend><b>Snippets</b> (".count($this->_modx->snippetsTime)." / ".sprintf("%2.2f ms", $tt*1000).")</legend>{$sc}</fieldset><br />";
                echo $this->_modx->snippetsCode;
            }
            if ($this->_modx->dumpPlugins) {
                $ps = "";
                $tc = 0;
                foreach ($this->_modx->pluginsTime as $s=>$t) {
                    $ps .= "$s (".sprintf("%2.2f ms", $t*1000).")<br>";
                    $tt += $t;
                }
                echo "<fieldset><legend><b>Plugins</b> (".count($this->_modx->pluginsTime)." / ".sprintf("%2.2f ms", $tt*1000).")</legend>{$ps}</fieldset><br />";
                echo $this->_modx->pluginsCode;
            }
            ob_end_flush();
        }

        /**
         * check if site is offline
         *
         * @return boolean
         */
        function checkSiteStatus() {
            $siteStatus= $this->_modx->getConfig('site_status');
            if ($siteStatus == 1) {
                // site online
                return true;
            }
            elseif ($siteStatus == 0 && $this->_modx->checkSession()) {
                // site offline but launched via the manager
                return true;
            } else {
                // site is offline
                return false;
            }
        }

        /**
         * Checks the publish state of page
         */
        function checkPublishStatus() {
            $cacheRefreshTime= 0;
            @include MODX_BASE_PATH . "assets/cache/sitePublishing.idx.php";
            $timeNow= time() + $this->_modx->getConfig('server_offset_time');
            if ($cacheRefreshTime <= $timeNow && $cacheRefreshTime != 0) {
                // now, check for documents that need publishing
                $sql = "UPDATE ".$this->_modx->getFullTableName("site_content")." SET published=1, publishedon=".time()." WHERE ".$this->_modx->getFullTableName("site_content").".pub_date <= $timeNow AND ".$this->_modx->getFullTableName("site_content").".pub_date!=0 AND published=0";
                if (@ !$result= $this->_modx->db->query($sql)) {
                    $this->_modx->messageQuit("Execution of a query to the database failed", $sql);
                }

                // now, check for documents that need un-publishing
                $sql= "UPDATE " . $this->_modx->getFullTableName("site_content") . " SET published=0, publishedon=0 WHERE " . $this->_modx->getFullTableName("site_content") . ".unpub_date <= $timeNow AND " . $this->_modx->getFullTableName("site_content") . ".unpub_date!=0 AND published=1";
                if (@ !$result= $this->_modx->db->query($sql)) {
                    $this->_modx->messageQuit("Execution of a query to the database failed", $sql);
                }

                // clear the cache
                $this->_modx->clearCache();

                // update publish time file
                $timesArr= array ();
                $sql= "SELECT MIN(pub_date) AS minpub FROM " . $this->_modx->getFullTableName("site_content") . " WHERE pub_date>$timeNow";
                if (@ !$result= $this->_modx->db->query($sql)) {
                    $this->_modx->messageQuit("Failed to find publishing timestamps", $sql);
                }
                $tmpRow= $this->_modx->db->getRow($result);
                $minpub= $tmpRow['minpub'];
                if ($minpub != NULL) {
                    $timesArr[]= $minpub;
                }

                $sql= "SELECT MIN(unpub_date) AS minunpub FROM " . $this->_modx->getFullTableName("site_content") . " WHERE unpub_date>$timeNow";
                if (@ !$result= $this->_modx->db->query($sql)) {
                    $this->_modx->messageQuit("Failed to find publishing timestamps", $sql);
                }
                $tmpRow= $this->_modx->db->getRow($result);
                $minunpub= $tmpRow['minunpub'];
                if ($minunpub != NULL) {
                    $timesArr[]= $minunpub;
                }

                if (count($timesArr) > 0) {
                    $nextevent= min($timesArr);
                } else {
                    $nextevent= 0;
                }

                $basepath= MODX_BASE_PATH . "assets/cache";
                $fp= @ fopen($basepath . "/sitePublishing.idx.php", "wb");
                if ($fp) {
                    @ flock($fp, LOCK_EX);
                    @ fwrite($fp, "<?php \$cacheRefreshTime=$nextevent; ?>");
                    @ flock($fp, LOCK_UN);
                    @ fclose($fp);
                }
            }
        }
    }