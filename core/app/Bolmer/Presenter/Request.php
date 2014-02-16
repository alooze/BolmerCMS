<?php namespace Bolmer\Presenter;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 22:30
 */

class Request{
    /** @var \Bolmer\Pimple $_inj */
    private $_inj = null;

    public function __construct(\Pimple $inj){
        $this->_inj= $inj;
    }

    /**
     * Get the method by which the current document/resource was requested
     *
     * @return string 'alias' (friendly url alias) or 'id'
     */
    public static function getDocumentMethod() {
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
        $docIdentifier= $this->_inj['modx']->getConfig('site_start');
        switch ($method) {
            case 'alias' :
                if (!is_scalar($_REQUEST['q'])) {
                    $this->_inj['modx']->sendErrorPage();
                }else{
                    $docIdentifier= $this->_inj['db']->escape($_REQUEST['q']);
                }
                break;
            case 'id' :
                if (!is_numeric($_REQUEST['id'])) {
                    $this->_inj['modx']->sendErrorPage();
                } else {
                    $docIdentifier= intval($_REQUEST['id']);
                }
                break;
        }
        return $docIdentifier;
    }
}