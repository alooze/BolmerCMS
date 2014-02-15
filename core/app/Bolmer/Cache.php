<?php namespace Bolmer;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 11.02.14
 * Time: 4:50
 */

    class Cache{
        /** @var \Bolmer\Pimple $_inj */
        private $_inj = null;

        public function __construct(\Pimple $inj){
            $this->_inj= $inj;
        }
        /**
         * Check the cache for a specific document/resource
         *
         * @param int $id
         * @return string
         */
        function checkCache($id) {
            $tbl_document_groups= $this->_inj['modx']->getFullTableName("document_groups");
            if ($this->_inj['modx']->getConfig('cache_type') == 2) {
                $md5_hash = '';
                if(!empty($_GET)) $md5_hash = '_' . md5(http_build_query($_GET));
                $cacheFile= "assets/cache/docid_" . $id .$md5_hash. ".pageCache.php";
            }else{
                $cacheFile= "assets/cache/docid_" . $id . ".pageCache.php";
            }
            if (file_exists($cacheFile)) {
                $this->_inj['modx']->documentGenerated= 0;
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
                        $usrGrps= $this->_inj['modx']->getUserDocGroups();
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
                            if ($this->_inj['modx']->getConfig('unauthorized_page')) {
                                // check if file is not public
                                $secrs= $this->_inj['db']->select('id', $tbl_document_groups, "document='{$id}'", '', '1');
                                if ($secrs)
                                    $seclimit= $this->_inj['db']->getRecordCount($secrs);
                            }
                            if ($seclimit > 0) {
                                // match found but not publicly accessible, send the visitor to the unauthorized_page
                                $this->_inj['modx']->sendUnauthorizedPage();
                                exit; // stop here
                            } else {
                                // no match found, send the visitor to the error_page
                                $this->_inj['modx']->sendErrorPage();
                                exit; // stop here
                            }
                        }
                    }
                    // Grab the Scripts
                    if (isset($docObj['__MODxSJScripts__'])) $this->_inj['modx']->sjscripts = $docObj['__MODxSJScripts__'];
                    if (isset($docObj['__MODxJScripts__']))  $this->_inj['modx']->jscripts = $docObj['__MODxJScripts__'];

                    // Remove intermediate variables
                    unset($docObj['__MODxDocGroups__'], $docObj['__MODxSJScripts__'], $docObj['__MODxJScripts__']);

                    $this->_inj['modx']->documentObject= $docObj;
                    return $a[1]; // return document content
                }
            } else {
                $this->_inj['modx']->documentGenerated= 1;
                return "";
            }
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
         * Returns the cache relative URL/path with respect to the site root.
         *
         * @global string $base_url
         * @return string The complete URL to the cache folder
         */
        function getCachePath() {
            return MODX_BASE_URL . 'assets/cache/';
        }
    }