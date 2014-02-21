<?php namespace Bolmer\Presenter;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 14:25
 */

class Response
{
    /** @var \Bolmer\Pimple $_inj */
    private $_inj = null;

    /** @var \Bolmer\Core $_core */
    protected $_core = null;

    public function __construct(\Pimple $inj)
    {
        $this->_inj = $inj;
        $this->_core = $inj['core'];
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
    public function sendRedirect($url, $count_attempts = 0, $type = '', $responseCode = '')
    {
        if (empty ($url)) {
            return false;
        } else {
            if ($count_attempts == 1) {
                // append the redirect count string to the url
                $currentNumberOfRedirects = isset ($_REQUEST['err']) ? $_REQUEST['err'] : 0;
                if ($currentNumberOfRedirects > 3) {
                    $this->_core->messageQuit('Redirection attempt failed - please ensure the document you\'re trying to redirect to exists. <p>Redirection URL: <i>' . $url . '</i></p>');
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
                $header = 'Refresh: 0;URL=' . $url;
            } elseif ($type == 'REDIRECT_META') {
                $header = '<META HTTP-EQUIV="Refresh" CONTENT="0; URL=' . $url . '" />';
                echo $header;
                exit;
            } elseif ($type == 'REDIRECT_HEADER' || empty ($type)) {
                // check if url has /$base_url
                if (substr($url, 0, strlen($this->_inj['global_config']['base_url'])) == $this->_inj['global_config']['base_url']) {
                    // append $site_url to make it work with Location:
                    $url = $this->_inj['global_config']['site_url'] . substr($url, strlen($this->_inj['global_config']['base_url']));
                }
                if (strpos($url, "\n") === false) {
                    $header = 'Location: ' . $url;
                } else {
                    $this->_core->messageQuit('No newline allowed in redirect url.');
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
    public function sendForward($id, $responseCode = '')
    {
        if ($this->_core->forwards > 0) {
            $this->_core->forwards = $this->_core->forwards - 1;
            $this->_core->documentIdentifier = $id;
            $this->_core->documentMethod = 'id';
            $this->_core->documentObject = $this->_core->getDocumentObject('id', $id);
            if ($responseCode) {
                header($responseCode);
            }
            $this->_core->prepareResponse();
            exit();
        } else {
            header('HTTP/1.0 500 Internal Server Error');
            die('<h1>ERROR: Too many forward attempts!</h1><p>The request could not be completed due to too many unsuccessful forward attempts.</p>');
        }
    }

    /**
     * Redirect to the error page, by calling sendForward(). This is called for example when the page was not found.
     */
    public function sendErrorPage()
    {
        // invoke OnPageNotFound event
        $this->_core->invokeEvent('OnPageNotFound');
        $url = $this->_core->getConfig('error_page', $this->_core->getConfig('site_start'));
        $this->sendForward($url, 'HTTP/1.0 404 Not Found');
        exit();
    }

    public function sendUnauthorizedPage()
    {
        // invoke OnPageUnauthorized event
        $_REQUEST['refurl'] = $this->_core->documentIdentifier;
        $this->_core->invokeEvent('OnPageUnauthorized');
        if ($this->_core->getConfig('unauthorized_page')) {
            $unauthorizedPage = $this->_core->getConfig('unauthorized_page');
        } elseif ($this->_core->getConfig('error_page')) {
            $unauthorizedPage = $this->_core->getConfig('error_page');
        } else {
            $unauthorizedPage = $this->_core->getConfig('site_start');
        }
        $this->_core->sendForward($unauthorizedPage, 'HTTP/1.1 401 Unauthorized');
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
    public function prepareResponse()
    {
        // we now know the method and identifier, let's check the cache
        $this->_core->documentContent = $this->_core->checkCache($this->_core->documentIdentifier);

        if ($this->_core->documentContent != "") {
            // invoke OnLoadWebPageCache  event
            $this->_core->invokeEvent("OnLoadWebPageCache");
        } else {

            // get document object
            $this->_core->documentObject = $this->_core->getDocumentObject($this->_core->documentMethod, $this->_core->documentIdentifier, 'prepareResponse');
            // write the documentName to the object
            $this->_core->documentName = $this->_core->documentObject['pagetitle'];

            // validation routines
            if ($this->_core->documentObject['deleted'] == 1) {
                $this->_core->sendErrorPage();
            }

            //  && !$this->checkPreview()
            if ($this->_core->documentObject['published'] == 0) {

                // Can't view unpublished pages
                if (!$this->_inj['manager']->hasPermission('view_unpublished')) {
                    $this->_core->sendErrorPage();
                } else {
                    // Inculde the necessary files to check document permissions
                    include_once(BOLMER_MANAGER_PATH . 'processors/user_documents_permissions.class.php');
                    $udperms = new \udperms();
                    $udperms->user = $this->_inj['user']->getLoginUserID();
                    $udperms->document = $this->_core->documentIdentifier;
                    $udperms->role = $_SESSION['mgrRole'];
                    // Doesn't have access to this document
                    if (!$udperms->checkPermissions()) {
                        $this->_core->sendErrorPage();
                    }

                }

            }

            // check whether it's a reference
            if ($this->_core->documentObject['type'] == "reference") {
                if (is_numeric($this->_core->documentObject['content'])) {
                    // if it's a bare document id
                    $this->_core->documentObject['content'] = $this->_core->makeUrl($this->_core->documentObject['content']);
                } elseif (strpos($this->_core->documentObject['content'], '[~') !== false) {
                    // if it's an internal docid tag, process it
                    $this->_core->documentObject['content'] = $this->_core->rewriteUrls($this->_core->documentObject['content']);
                }
                $this->_core->sendRedirect($this->_core->documentObject['content'], 0, '', 'HTTP/1.0 301 Moved Permanently');
            }

            // check if we should not hit this document
            if ($this->_core->documentObject['donthit'] == 1) {
                $this->_core->config['track_visitors'] = 0;
            }

            // get the template and start parsing!
            if (!$this->_core->documentObject['template'])
                $this->_core->documentContent = "[*content*]"; // use blank template
            else {
                $sql = "SELECT `content` FROM " . $this->_core->getTableName("BTemplate") . " WHERE " . $this->_core->getTableName("BTemplate") . ".`id` = '" . $this->_core->documentObject['template'] . "';";
                $result = $this->_core->db->query($sql);
                $rowCount = $this->_core->db->getRecordCount($result);

                if ($rowCount > 1) {

                    $this->_core->messageQuit("Incorrect number of templates returned from database", $sql);
                } elseif ($rowCount == 1) {

                    $row = $this->_core->db->getRow($result);
                    $this->_core->documentContent = $row['content'];
                }
            }

            // invoke OnLoadWebDocument event
            $this->_core->invokeEvent("OnLoadWebDocument");

            // Parse document source
            $this->_core->documentContent = $this->_core->parseDocumentSource($this->_core->documentContent);

            // setup <base> tag for friendly urls
            //			if($this->config['friendly_urls']==1 && $this->config['use_alias_path']==1) {
            //				$this->regClientStartupHTMLBlock('<base href="'.$this->config['site_url'].'" />');
            //			}
        }
        if ($this->_core->documentIdentifier == $this->_core->getConfig('error_page') && $this->_core->getConfig('error_page') != $this->_core->getConfig('site_start')) {
            header('HTTP/1.0 404 Not Found');
        }

        register_shutdown_function(array(
            & $this,
            "postProcess"
        )); // tell PHP to call postProcess when it shuts down

        $this->_core->outputContent();
    }

    /**
     * Final jobs.
     *
     * - cache page
     */
    public function postProcess()
    {
        // if the current document was generated, cache it!
        if ($this->_core->documentGenerated == 1 && $this->_core->documentObject['cacheable'] == 1 && $this->_core->documentObject['type'] == 'document' && $this->_core->documentObject['published'] == 1) {

            // invoke OnBeforeSaveWebPageCache event
            $this->_core->invokeEvent("OnBeforeSaveWebPageCache");

            $cache = $this->_inj['cache'];
            $cacheId = $cache->getCacheId($this->_core->documentIdentifier);

            // get and store document groups inside document object.
            // Document groups will be used to check security on cache pages
            $sql = "SELECT document_group FROM " . $this->_core->getTableName("BDocGroupList") . " WHERE document='" . $this->_core->documentIdentifier . "'";
            $docGroups = $this->_core->db->getColumn("document_group", $sql);

            // Attach Document Groups and Scripts
            if (is_array($docGroups)) $this->_core->documentObject['__MODxDocGroups__'] = implode(",", $docGroups);

            $docObjSerial = serialize($this->_core->documentObject);
            $cacheContent = $docObjSerial . "<!--__MODxCacheSpliter__-->" . $this->_core->documentContent;
            $cache->set($cacheId, "<?php die('Unauthorized access.'); ?>$cacheContent");
        }

        // Useful for example to external page counters/stats packages
        $this->_core->invokeEvent('OnWebPageComplete');

        // end post processing
    }


    /**
     * Returns true if we are currently in the manager backend
     *
     * @return boolean
     */
    public function isBackend()
    {
        return $this->insideManager() ? true : false;
    }

    /**
     * Returns true if we are currently in the frontend
     *
     * @return boolean
     */
    public function isFrontend()
    {
        return !$this->insideManager() ? true : false;
    }

    # Returns true, install or interact when inside manager
    public function insideManager()
    {
        $m = false;
        if (defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == 'true') {
            $m = true;
            if (defined('SNIPPET_INTERACTIVE_MODE') && SNIPPET_INTERACTIVE_MODE == 'true')
                $m = "interact";
            else
                if (defined('SNIPPET_INSTALL_MODE') && SNIPPET_INSTALL_MODE == 'true')
                    $m = "install";
        }
        return $m;
    }

    public function sendStrictURI()
    {
        // FIX URLs
        if (empty($this->_core->documentIdentifier) || $this->_core->getConfig('seostrict') == '0' || $this->_core->getConfig('friendly_urls') == '0')
            return;
        if ($this->_core->getConfig('site_status') == 0) return;

        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
        $len_base_url = strlen($this->_core->getConfig('base_url'));
        if (strpos($_SERVER['REQUEST_URI'], '?'))
            list($url_path, $url_query_string) = explode('?', $_SERVER['REQUEST_URI'], 2);
        else $url_path = $_SERVER['REQUEST_URI'];
        $url_path = $_GET['q']; //LANG


        if (substr($url_path, 0, $len_base_url) === $this->_core->getConfig('base_url'))
            $url_path = substr($url_path, $len_base_url);

        $strictURL = $this->_core->toAlias($this->_core->makeUrl($this->_core->documentIdentifier));

        if (substr($strictURL, 0, $len_base_url) === $this->_core->getConfig('base_url'))
            $strictURL = substr($strictURL, $len_base_url);
        $http_host = $_SERVER['HTTP_HOST'];
        $requestedURL = "{$scheme}://{$http_host}" . '/' . $_GET['q']; //LANG

        $site_url = $this->_core->getConfig('site_url');

        if ($this->_core->documentIdentifier == $this->_core->getConfig('site_start')) {
            if ($requestedURL != $this->_core->getConfig('site_url')) {
                // Force redirect of site start
                // $this->sendErrorPage();
                $qstring = isset($url_query_string) ? preg_replace("#(^|&)(q|id)=[^&]+#", '', $url_query_string) : ''; // Strip conflicting id/q from query string
                if ($qstring) $url = "{$site_url}?{$qstring}";
                else          $url = $site_url;
                if ($this->_core->getConfig('base_url') != $_SERVER['REQUEST_URI']) {
                    if (empty($_POST)) {
                        if (('/?' . $qstring) != $_SERVER['REQUEST_URI']) {
                            $this->sendRedirect($url, 0, 'REDIRECT_HEADER', 'HTTP/1.0 301 Moved Permanently');
                            exit(0);
                        }
                    }
                }
            }
        } elseif ($url_path != $strictURL && $this->_core->documentIdentifier != $this->_core->getConfig('error_page')) {
            // Force page redirect
            //$strictURL = ltrim($strictURL,'/');

            if (!empty($url_query_string))
                $qstring = preg_replace("#(^|&)(q|id)=[^&]+#", '', $url_query_string); // Strip conflicting id/q from query string
            if ($qstring) $url = "{$site_url}{$strictURL}?{$qstring}";
            else          $url = "{$site_url}{$strictURL}";
            $this->sendRedirect($url, 0, 'REDIRECT_HEADER', 'HTTP/1.0 301 Moved Permanently');
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
    public function outputContent($noEvent = false)
    {
        $this->_core->documentOutput = $this->_core->documentContent;
        if ($this->_core->documentGenerated == 1 && $this->_core->documentObject['cacheable'] == 1 && $this->_core->documentObject['type'] == 'document' && $this->_core->documentObject['published'] == 1) {
            if (!empty($this->_core->sjscripts)) $this->_core->documentObject['__MODxSJScripts__'] = $this->_core->sjscripts;
            if (!empty($this->_core->jscripts)) $this->_core->documentObject['__MODxJScripts__'] = $this->_core->jscripts;
        }

        // check for non-cached snippet output
        if (strpos($this->_core->documentOutput, '[!') > -1) {
            $this->_core->documentOutput = str_replace('[!', '[[', $this->_core->documentOutput);
            $this->_core->documentOutput = str_replace('!]', ']]', $this->_core->documentOutput);

            // Parse document source
            $this->_core->documentOutput = $this->_core->parseDocumentSource($this->_core->documentOutput);
        }

        // Moved from prepareResponse() by sirlancelot
        // Insert Startup jscripts & CSS scripts into template - template must have a <head> tag
        if ($js = $this->_core->getRegisteredClientStartupScripts()) {
            // change to just before closing </head>
            // $this->documentContent = preg_replace("/(<head[^>]*>)/i", "\\1\n".$js, $this->documentContent);
            $this->_core->documentOutput = preg_replace("/(<\/head>)/i", $js . "\n\\1", $this->_core->documentOutput);
        }

        // Insert jscripts & html block into template - template must have a </body> tag
        if ($js = $this->_core->getRegisteredClientScripts()) {
            $this->_core->documentOutput = preg_replace("/(<\/body>)/i", $js . "\n\\1", $this->_core->documentOutput);
        }
        // End fix by sirlancelot

        // remove all unused placeholders
        if (strpos($this->_core->documentOutput, '[+') > -1) {
            $matches = array();
            preg_match_all('~\[\+(.*?)\+\]~s', $this->_core->documentOutput, $matches);
            if ($matches[0])
                $this->_core->documentOutput = str_replace($matches[0], '', $this->_core->documentOutput);
        }

        $this->_core->documentOutput = $this->_core->rewriteUrls($this->_core->documentOutput);

        // send out content-type and content-disposition headers
        if (IN_PARSER_MODE == "true") {
            $type = !empty ($this->_core->contentTypes[$this->_core->documentIdentifier]) ? $this->_core->contentTypes[$this->_core->documentIdentifier] : "text/html";
            header('Content-Type: ' . $type . '; charset=' . $this->_core->getConfig('modx_charset'));
//            if (($this->documentIdentifier == $this->config['error_page']) || $redirect_error)
//                header('HTTP/1.0 404 Not Found');
            if (!$this->_inj['manager']->checkPreview() && $this->_core->documentObject['content_dispo'] == 1) {
                if ($this->_core->documentObject['alias'])
                    $name = $this->_core->documentObject['alias'];
                else {
                    // strip title of special characters
                    $name = $this->_core->documentObject['pagetitle'];
                    $name = strip_tags($name);
                    $name = strtolower($name);
                    $name = preg_replace('/&.+?;/', '', $name); // kill entities
                    $name = preg_replace('/[^\.%a-z0-9 _-]/', '', $name);
                    $name = preg_replace('/\s+/', '-', $name);
                    $name = preg_replace('|-+|', '-', $name);
                    $name = trim($name, '-');
                }
                $header = 'Content-Disposition: attachment; filename=' . $name;
                header($header);
            }
        }

        $stats = $this->_core->getTimerStats($this->_core->tstart);

        $out =& $this->_core->documentOutput;
        $out = str_replace("[^q^]", $stats['queries'], $out);
        $out = str_replace("[^qt^]", $stats['queryTime'], $out);
        $out = str_replace("[^p^]", $stats['phpTime'], $out);
        $out = str_replace("[^t^]", $stats['totalTime'], $out);
        $out = str_replace("[^s^]", $stats['source'], $out);
        $out = str_replace("[^m^]", $stats['phpMemory'], $out);
        //$this->documentOutput= $out;

        // invoke OnWebPagePrerender event
        if (!$noEvent) {
            $this->_core->invokeEvent('OnWebPagePrerender');
        }
        global $sanitize_seed;
        if (strpos($this->_core->documentOutput, $sanitize_seed) !== false) {
            $this->_core->documentOutput = str_replace($sanitize_seed, '', $this->_core->documentOutput);
        }

        echo $this->_core->documentOutput;
        if ($this->_core->dumpSQL) {
            echo $this->_inj['debug']->showQuery();
        }
        if ($this->_core->dumpSnippets) {
            $sc = "";
            $tt = 0;
            foreach ($this->_core->snippetsTime as $s => $t) {
                $sc .= "$s: " . $this->_core->snippetsCount[$s] . " (" . sprintf("%2.2f ms", $t * 1000) . ")<br>";
                $tt += $t;
            }
            echo "<fieldset><legend><b>Snippets</b> (" . count($this->_core->snippetsTime) . " / " . sprintf("%2.2f ms", $tt * 1000) . ")</legend>{$sc}</fieldset><br />";
            echo $this->_core->snippetsCode;
        }
        if ($this->_core->dumpPlugins) {
            $ps = "";
            $tc = 0;
            foreach ($this->_core->pluginsTime as $s => $t) {
                $ps .= "$s (" . sprintf("%2.2f ms", $t * 1000) . ")<br>";
                $tt += $t;
            }
            echo "<fieldset><legend><b>Plugins</b> (" . count($this->_core->pluginsTime) . " / " . sprintf("%2.2f ms", $tt * 1000) . ")</legend>{$ps}</fieldset><br />";
            echo $this->_core->pluginsCode;
        }
        ob_end_flush();
    }

    /**
     * check if site is offline
     *
     * @return boolean
     */
    public function checkSiteStatus()
    {
        $siteStatus = $this->_core->getConfig('site_status');
        if ($siteStatus == 1) {
            // site online
            return true;
        } elseif ($siteStatus == 0 && $this->_inj['manager']->checkSession()) {
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
    public function checkPublishStatus()
    {
        $cacheRefreshTime = 0;
        @include BOLMER_BASE_PATH . "assets/cache/sitePublishing.idx.php";
        $timeNow = time() + $this->_core->getConfig('server_offset_time');
        if ($cacheRefreshTime <= $timeNow && $cacheRefreshTime != 0) {
            // now, check for documents that need publishing
            $sql = "UPDATE " . $this->_core->getTableName("BDoc") . " SET published=1, publishedon=" . time() . " WHERE " . $this->_core->getTableName("BDoc") . ".pub_date <= $timeNow AND " . $this->_core->getTableName("BDoc") . ".pub_date!=0 AND published=0";
            if (@ !$result = $this->_core->db->query($sql)) {
                $this->_core->messageQuit("Execution of a query to the database failed", $sql);
            }

            // now, check for documents that need un-publishing
            $sql = "UPDATE " . $this->_core->getTableName("BDoc") . " SET published=0, publishedon=0 WHERE " . $this->_core->getTableName("BDoc") . ".unpub_date <= $timeNow AND " . $this->_core->getTableName("BDoc") . ".unpub_date!=0 AND published=1";
            if (@ !$result = $this->_core->db->query($sql)) {
                $this->_core->messageQuit("Execution of a query to the database failed", $sql);
            }

            // clear the cache
            $this->_core->clearCache();

            // update publish time file
            $timesArr = array();
            $sql = "SELECT MIN(pub_date) AS minpub FROM " . $this->_core->getTableName("BDoc") . " WHERE pub_date>$timeNow";
            if (@ !$result = $this->_core->db->query($sql)) {
                $this->_core->messageQuit("Failed to find publishing timestamps", $sql);
            }
            $tmpRow = $this->_core->db->getRow($result);
            $minpub = $tmpRow['minpub'];
            if ($minpub != NULL) {
                $timesArr[] = $minpub;
            }

            $sql = "SELECT MIN(unpub_date) AS minunpub FROM " . $this->_core->getTableName("BDoc") . " WHERE unpub_date>$timeNow";
            if (@ !$result = $this->_core->db->query($sql)) {
                $this->_core->messageQuit("Failed to find publishing timestamps", $sql);
            }
            $tmpRow = $this->_core->db->getRow($result);
            $minunpub = $tmpRow['minunpub'];
            if ($minunpub != NULL) {
                $timesArr[] = $minunpub;
            }

            if (count($timesArr) > 0) {
                $nextevent = min($timesArr);
            } else {
                $nextevent = 0;
            }

            $basepath = BOLMER_BASE_PATH . "assets/cache";
            $fp = @ fopen($basepath . "/sitePublishing.idx.php", "wb");
            if ($fp) {
                @ flock($fp, LOCK_EX);
                @ fwrite($fp, "<?php \$cacheRefreshTime=$nextevent; ?>");
                @ flock($fp, LOCK_UN);
                @ fclose($fp);
            }
        }
    }
}