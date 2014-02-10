<?php namespace MODxCore;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 14:25
 */

    class Response{
        /** @var \MODxCore\Pimple $_inj */
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
            $this->_inj['modx']->sendForward($unauthorizedPage, 'HTTP/1.1 401 Unauthorized');
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
                $this->_inj['modx']->documentContent = $this->_inj['modx']->parseDocumentSource($this->_inj['modx']->documentContent);

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
                $basepath= MODX_BASE_PATH . "assets/cache";
                // invoke OnBeforeSaveWebPageCache event
                $this->_inj['modx']->invokeEvent("OnBeforeSaveWebPageCache");
                if ($this->_inj['modx']->getConfig('cache_type') == 2) {
                    $md5_hash = '';
                    if(!empty($_GET)) $md5_hash = '_' . md5(http_build_query($_GET));
                    $pageCache = $md5_hash .".pageCache.php";
                }else{
                    $pageCache = ".pageCache.php";
                }

                if ($fp= @ fopen($basepath . "/docid_" . $this->_inj['modx']->documentIdentifier . $pageCache, "w")) {
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
    }