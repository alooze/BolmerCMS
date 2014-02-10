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
    }