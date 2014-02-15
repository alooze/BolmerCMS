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

        public function __construct(\Pimple $inj){
            $this->_inj= $inj;
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
                        $this->_inj['modx']->messageQuit('Redirection attempt failed - please ensure the document you\'re trying to redirect to exists. <p>Redirection URL: <i>' . $url . '</i></p>');
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
                        $this->_inj['modx']->messageQuit('No newline allowed in redirect url.');
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
            if ($this->_inj['modx']->forwards > 0) {
                $this->_inj['modx']->forwards= $this->_inj['modx']->forwards - 1;
                $this->_inj['modx']->documentIdentifier= $id;
                $this->_inj['modx']->documentMethod= 'id';
                $this->_inj['modx']->documentObject= $this->_inj['modx']->getDocumentObject('id', $id);
                if ($responseCode) {
                    header($responseCode);
                }
                $this->_inj['modx']->prepareResponse();
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
            $this->_inj['modx']->invokeEvent('OnPageNotFound');
            $url = $this->_inj['modx']->getConfig('error_page', $this->_inj['modx']->getConfig('site_start'));
            $this->sendForward($url, 'HTTP/1.0 404 Not Found');
            exit();
        }

        function sendUnauthorizedPage() {
            // invoke OnPageUnauthorized event
            $_REQUEST['refurl'] = $this->_inj['modx']->documentIdentifier;
            $this->_inj['modx']->invokeEvent('OnPageUnauthorized');
            if ($this->_inj['modx']->getConfig('unauthorized_page')) {
                $unauthorizedPage= $this->_inj['modx']->getConfig('unauthorized_page');
            } elseif ($this->_inj['modx']->getConfig('error_page')) {
                $unauthorizedPage= $this->_inj['modx']->getConfig('error_page');
            } else {
                $unauthorizedPage= $this->_inj['modx']->getConfig('site_start');
            }
            // Changed by TimGS 22/6/2012. Originally was a 401 but this HTTP code appears intended for situations
            // where the client can authenticate via HTTP authentication and send a www-authenticate header.
            $this->_inj['modx']->sendForward($unauthorizedPage, 'HTTP/1.1 403 Forbidden');
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
            $this->_inj['modx']->documentContent= $this->_inj['modx']->checkCache($this->_inj['modx']->documentIdentifier);

            if ($this->_inj['modx']->documentContent != "") {
                // invoke OnLoadWebPageCache  event
                $this->_inj['modx']->invokeEvent("OnLoadWebPageCache");
            } else {

                // get document object
                $this->_inj['modx']->documentObject= $this->_inj['modx']->getDocumentObject($this->_inj['modx']->documentMethod, $this->_inj['modx']->documentIdentifier, 'prepareResponse');
                // write the documentName to the object
                $this->_inj['modx']->documentName= $this->_inj['modx']->documentObject['pagetitle'];

                // validation routines
                if ($this->_inj['modx']->documentObject['deleted'] == 1) {
                    $this->_inj['modx']->sendErrorPage();
                }

                //  && !$this->checkPreview()
                if ($this->_inj['modx']->documentObject['published'] == 0) {

                    // Can't view unpublished pages
                    if (!$this->_inj['modx']->hasPermission('view_unpublished')) {
                        $this->_inj['modx']->sendErrorPage();
                    } else {
                        // Inculde the necessary files to check document permissions
                        include_once (MODX_MANAGER_PATH . 'processors/user_documents_permissions.class.php');
                        $udperms= new \udperms();
                        $udperms->user= $this->_inj['modx']->getLoginUserID();
                        $udperms->document= $this->_inj['modx']->documentIdentifier;
                        $udperms->role= $_SESSION['mgrRole'];
                        // Doesn't have access to this document
                        if (!$udperms->checkPermissions()) {
                            $this->_inj['modx']->sendErrorPage();
                        }

                    }

                }

                // check whether it's a reference
                if ($this->_inj['modx']->documentObject['type'] == "reference") {
                    if (is_numeric($this->_inj['modx']->documentObject['content'])) {
                        // if it's a bare document id
                        $this->_inj['modx']->documentObject['content']= $this->_inj['modx']->makeUrl($this->_inj['modx']->documentObject['content']);
                    }
                    elseif (strpos($this->_inj['modx']->documentObject['content'], '[~') !== false) {
                        // if it's an internal docid tag, process it
                        $this->_inj['modx']->documentObject['content']= $this->_inj['modx']->rewriteUrls($this->_inj['modx']->documentObject['content']);
                    }
                    $this->_inj['modx']->sendRedirect($this->_inj['modx']->documentObject['content'], 0, '', 'HTTP/1.0 301 Moved Permanently');
                }

                // check if we should not hit this document
                if ($this->_inj['modx']->documentObject['donthit'] == 1) {
                    $this->_inj['modx']->config['track_visitors'] = 0;
                }

                // get the template and start parsing!
                if (!$this->_inj['modx']->documentObject['template'])
                    $this->_inj['modx']->documentContent= "[*content*]"; // use blank template
                else {
                    $sql= "SELECT `content` FROM " . $this->_inj['modx']->getFullTableName("site_templates") . " WHERE " . $this->_inj['modx']->getFullTableName("site_templates") . ".`id` = '" . $this->_inj['modx']->documentObject['template'] . "';";
                    $result= $this->_inj['db']->query($sql);
                    $rowCount= $this->_inj['db']->getRecordCount($result);

                    if ($rowCount > 1) {

                        $this->_inj['modx']->messageQuit("Incorrect number of templates returned from database", $sql);
                    }
                    elseif ($rowCount == 1) {

                        $row= $this->_inj['db']->getRow($result);
                        $this->_inj['modx']->documentContent= $row['content'];
                    }
                }

                // invoke OnLoadWebDocument event
                $this->_inj['modx']->invokeEvent("OnLoadWebDocument");

                // Parse document source
                $this->_inj['modx']->documentContent = $this->_inj['modx']->parseDocumentSource($this->_inj['modx']->documentContent, true);

                // setup <base> tag for friendly urls
                //			if($this->config['friendly_urls']==1 && $this->config['use_alias_path']==1) {
                //				$this->regClientStartupHTMLBlock('<base href="'.$this->config['site_url'].'" />');
                //			}
            }
            if($this->_inj['modx']->documentIdentifier==$this->_inj['modx']->getConfig('error_page') &&  $this->_inj['modx']->getConfig('error_page')!=$this->_inj['modx']->getConfig('site_start')){
                header('HTTP/1.0 404 Not Found');
            }

            register_shutdown_function(array (
                & $this,
                "postProcess"
            )); // tell PHP to call postProcess when it shuts down

            $this->_inj['modx']->outputContent();
        }

        /**
         * Final jobs.
         *
         * - cache page
         */
        function postProcess() {
            // if the current document was generated, cache it!
            if ($this->_inj['modx']->documentGenerated == 1 && $this->_inj['modx']->documentObject['cacheable'] == 1 && $this->_inj['modx']->documentObject['type'] == 'document' && $this->_inj['modx']->documentObject['published'] == 1) {
                // invoke OnBeforeSaveWebPageCache event
                $this->_inj['modx']->invokeEvent("OnBeforeSaveWebPageCache");
                $cacheFile = $this->_inj['cache']->pageCacheFile($this->_inj['modx']->documentIdentifier);

                if ($fp= @ fopen($cacheFile, "w")) {
                    // get and store document groups inside document object. Document groups will be used to check security on cache pages
                    $sql= "SELECT document_group FROM " . $this->_inj['modx']->getFullTableName("document_groups") . " WHERE document='" . $this->_inj['modx']->documentIdentifier . "'";
                    $docGroups= $this->_inj['db']->getColumn("document_group", $sql);

                    // Attach Document Groups and Scripts
                    if (is_array($docGroups)) $this->_inj['modx']->documentObject['__MODxDocGroups__'] = implode(",", $docGroups);

                    $docObjSerial= serialize($this->_inj['modx']->documentObject);
                    $cacheContent= $docObjSerial . "<!--__MODxCacheSpliter__-->" . $this->_inj['modx']->documentContent;
                    fputs($fp, "<?php die('Unauthorized access.'); ?>$cacheContent");
                    fclose($fp);
                }
            }

            // Useful for example to external page counters/stats packages
            $this->_inj['modx']->invokeEvent('OnWebPageComplete');

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
            if (empty($this->_inj['modx']->documentIdentifier) || $this->_inj['modx']->getConfig('seostrict')=='0' || $this->_inj['modx']->getConfig('friendly_urls')=='0')
                return;
            if ($this->_inj['modx']->getConfig('site_status') == 0) return;

            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
            $len_base_url = strlen($this->_inj['modx']->getConfig('base_url'));
            if(strpos($_SERVER['REQUEST_URI'],'?'))
                list($url_path,$url_query_string) = explode('?', $_SERVER['REQUEST_URI'],2);
            else $url_path = $_SERVER['REQUEST_URI'];
            $url_path = $_GET['q'];//LANG


            if(substr($url_path,0,$len_base_url)===$this->_inj['modx']->getConfig('base_url'))
                $url_path = substr($url_path,$len_base_url);

            $strictURL =  $this->_inj['modx']->toAlias($this->_inj['modx']->makeUrl($this->_inj['modx']->documentIdentifier));

            if(substr($strictURL,0,$len_base_url)===$this->_inj['modx']->getConfig('base_url'))
                $strictURL = substr($strictURL,$len_base_url);
            $http_host = $_SERVER['HTTP_HOST'];
            $requestedURL = "{$scheme}://{$http_host}" . '/'.$_GET['q']; //LANG

            $site_url = $this->_inj['modx']->getConfig('site_url');

            if ($this->_inj['modx']->documentIdentifier == $this->_inj['modx']->getConfig('site_start')){
                if ($requestedURL != $this->_inj['modx']->getConfig('site_url')){
                    // Force redirect of site start
                    // $this->sendErrorPage();
                    $qstring = isset($url_query_string) ? preg_replace("#(^|&)(q|id)=[^&]+#", '', $url_query_string) : ''; // Strip conflicting id/q from query string
                    if ($qstring) $url = "{$site_url}?{$qstring}";
                    else          $url = $site_url;
                    if ($this->_inj['modx']->getConfig('base_url') != $_SERVER['REQUEST_URI']){
                        if (empty($_POST)){
                            if (('/?'.$qstring) != $_SERVER['REQUEST_URI']) {
                                $this->sendRedirect($url,0,'REDIRECT_HEADER', 'HTTP/1.0 301 Moved Permanently');
                                exit(0);
                            }
                        }
                    }
                }
            }elseif ($url_path != $strictURL && $this->_inj['modx']->documentIdentifier != $this->_inj['modx']->getConfig('error_page')){
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
            $this->_inj['modx']->documentOutput= $this->_inj['modx']->documentContent;
            if ($this->_inj['modx']->documentGenerated == 1 && $this->_inj['modx']->documentObject['cacheable'] == 1 && $this->_inj['modx']->documentObject['type'] == 'document' && $this->_inj['modx']->documentObject['published'] == 1) {
                if (!empty($this->_inj['modx']->sjscripts)) $this->_inj['modx']->documentObject['__MODxSJScripts__'] = $this->_inj['modx']->sjscripts;
                if (!empty($this->_inj['modx']->jscripts)) $this->_inj['modx']->documentObject['__MODxJScripts__'] = $this->_inj['modx']->jscripts;
            }

            // check for non-cached snippet output
            if (strpos($this->_inj['modx']->documentOutput, '[!') !== false) {
                // Parse document source
                $this->_inj['modx']->documentOutput= $this->_inj['modx']->parseDocumentSource($this->_inj['modx']->documentOutput, true);
            }

            // Moved from prepareResponse() by sirlancelot
            // Insert Startup jscripts & CSS scripts into template - template must have a <head> tag
            if ($js= $this->_inj['modx']->getRegisteredClientStartupScripts()) {
                // change to just before closing </head>
                // $this->documentContent = preg_replace("/(<head[^>]*>)/i", "\\1\n".$js, $this->documentContent);
                $this->_inj['modx']->documentOutput= preg_replace("/(<\/head>)/i", $js . "\n\\1", $this->_inj['modx']->documentOutput);
            }

            // Insert jscripts & html block into template - template must have a </body> tag
            if ($js= $this->_inj['modx']->getRegisteredClientScripts()) {
                $this->_inj['modx']->documentOutput= preg_replace("/(<\/body>)/i", $js . "\n\\1", $this->_inj['modx']->documentOutput);
            }
            // End fix by sirlancelot

            // remove all unused placeholders
            if (strpos($this->_inj['modx']->documentOutput, '[+') > -1) {
                $matches= array ();
                preg_match_all('~\[\+(.*?)\+\]~s', $this->_inj['modx']->documentOutput, $matches);
                if ($matches[0])
                    $this->_inj['modx']->documentOutput= str_replace($matches[0], '', $this->_inj['modx']->documentOutput);
            }

            $this->_inj['modx']->documentOutput= $this->_inj['modx']->rewriteUrls($this->_inj['modx']->documentOutput);

            // send out content-type and content-disposition headers
            if (IN_PARSER_MODE == "true") {
                $type= !empty ($this->_inj['modx']->contentTypes[$this->_inj['modx']->documentIdentifier]) ? $this->_inj['modx']->contentTypes[$this->_inj['modx']->documentIdentifier] : "text/html";
                header('Content-Type: ' . $type . '; charset=' . $this->_inj['modx']->getConfig('modx_charset'));
//            if (($this->documentIdentifier == $this->config['error_page']) || $redirect_error)
//                header('HTTP/1.0 404 Not Found');
                if (!$this->_inj['modx']->checkPreview() && $this->_inj['modx']->documentObject['content_dispo'] == 1) {
                    if ($this->_inj['modx']->documentObject['alias'])
                        $name= $this->_inj['modx']->documentObject['alias'];
                    else {
                        // strip title of special characters
                        $name= $this->_inj['modx']->documentObject['pagetitle'];
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

            $stats = $this->_inj['modx']->getTimerStats($this->_inj['modx']->tstart);

            $out =& $this->_inj['modx']->documentOutput;
            $out= str_replace("[^q^]", $stats['queries'] , $out);
            $out= str_replace("[^qt^]", $stats['queryTime'] , $out);
            $out= str_replace("[^p^]", $stats['phpTime'] , $out);
            $out= str_replace("[^t^]", $stats['totalTime'] , $out);
            $out= str_replace("[^s^]", $stats['source'] , $out);
            $out= str_replace("[^m^]", $stats['phpMemory'], $out);
            //$this->documentOutput= $out;

            // invoke OnWebPagePrerender event
            if (!$noEvent) {
                $this->_inj['modx']->invokeEvent('OnWebPagePrerender');
            }
            global $sanitize_seed;
            if(strpos($this->_inj['modx']->documentOutput, $sanitize_seed)!==false) {
                $this->_inj['modx']->documentOutput = str_replace($sanitize_seed, '', $this->_inj['modx']->documentOutput);
            }

            echo $this->_inj['modx']->documentOutput;
            if ($this->_inj['modx']->dumpSQL) {
                echo \Bolmer\Debug::showQuery();
            }
            if ($this->_inj['modx']->dumpSnippets) {
                $sc = "";
                $tt = 0;
                foreach ($this->_inj['modx']->snippetsTime as $s=>$t) {
                    $sc .= "$s: ".$this->_inj['modx']->snippetsCount[$s]." (".sprintf("%2.2f ms", $t*1000).")<br>";
                    $tt += $t;
                }
                echo "<fieldset><legend><b>Snippets</b> (".count($this->_inj['modx']->snippetsTime)." / ".sprintf("%2.2f ms", $tt*1000).")</legend>{$sc}</fieldset><br />";
                echo $this->_inj['modx']->snippetsCode;
            }
            if ($this->_inj['modx']->dumpPlugins) {
                $ps = "";
                $tc = 0;
                foreach ($this->_inj['modx']->pluginsTime as $s=>$t) {
                    $ps .= "$s (".sprintf("%2.2f ms", $t*1000).")<br>";
                    $tt += $t;
                }
                echo "<fieldset><legend><b>Plugins</b> (".count($this->_inj['modx']->pluginsTime)." / ".sprintf("%2.2f ms", $tt*1000).")</legend>{$ps}</fieldset><br />";
                echo $this->_inj['modx']->pluginsCode;
            }
            ob_end_flush();
        }

        /**
         * check if site is offline
         *
         * @return boolean
         */
        function checkSiteStatus() {
            $siteStatus= $this->_inj['modx']->getConfig('site_status');
            if ($siteStatus == 1) {
                // site online
                return true;
            }
            elseif ($siteStatus == 0 && $this->_inj['modx']->checkSession()) {
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
            $timeNow= time() + $this->_inj['modx']->getConfig('server_offset_time');
            if ($cacheRefreshTime <= $timeNow && $cacheRefreshTime != 0) {
                // now, check for documents that need publishing
                $sql = "UPDATE ".$this->_inj['modx']->getFullTableName("site_content")." SET published=1, publishedon=".time()." WHERE ".$this->_inj['modx']->getFullTableName("site_content").".pub_date <= $timeNow AND ".$this->_inj['modx']->getFullTableName("site_content").".pub_date!=0 AND published=0";
                if (@ !$result= $this->_inj['db']->query($sql)) {
                    $this->_inj['modx']->messageQuit("Execution of a query to the database failed", $sql);
                }

                // now, check for documents that need un-publishing
                $sql= "UPDATE " . $this->_inj['modx']->getFullTableName("site_content") . " SET published=0, publishedon=0 WHERE " . $this->_inj['modx']->getFullTableName("site_content") . ".unpub_date <= $timeNow AND " . $this->_inj['modx']->getFullTableName("site_content") . ".unpub_date!=0 AND published=1";
                if (@ !$result= $this->_inj['db']->query($sql)) {
                    $this->_inj['modx']->messageQuit("Execution of a query to the database failed", $sql);
                }

                // clear the cache
                $this->_inj['modx']->clearCache();

                // update publish time file
                $timesArr= array ();
                $sql= "SELECT MIN(pub_date) AS minpub FROM " . $this->_inj['modx']->getFullTableName("site_content") . " WHERE pub_date>$timeNow";
                if (@ !$result= $this->_inj['db']->query($sql)) {
                    $this->_inj['modx']->messageQuit("Failed to find publishing timestamps", $sql);
                }
                $tmpRow= $this->_inj['db']->getRow($result);
                $minpub= $tmpRow['minpub'];
                if ($minpub != NULL) {
                    $timesArr[]= $minpub;
                }

                $sql= "SELECT MIN(unpub_date) AS minunpub FROM " . $this->_inj['modx']->getFullTableName("site_content") . " WHERE unpub_date>$timeNow";
                if (@ !$result= $this->_inj['db']->query($sql)) {
                    $this->_inj['modx']->messageQuit("Failed to find publishing timestamps", $sql);
                }
                $tmpRow= $this->_inj['db']->getRow($result);
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