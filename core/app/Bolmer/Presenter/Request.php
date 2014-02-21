<?php namespace Bolmer\Presenter;

class Request
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
     * Get the method by which the current document/resource was requested
     *
     * @return string 'alias' (friendly url alias) or 'id'
     */
    public function getDocumentMethod()
    {
        // function to test the query and find the retrieval method
        if (!empty ($_REQUEST['q'])) { //LANG
            return "alias";
        } elseif (isset ($_REQUEST['id'])) {
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
    public function getDocumentIdentifier($method)
    {
        // function to test the query and find the retrieval method
        $docIdentifier = $this->_core->getConfig('site_start');
        switch ($method) {
            case 'alias' :
                if (!is_scalar($_REQUEST['q'])) {
                    $this->_core->sendErrorPage();
                } else {
                    $docIdentifier = $this->_core->db->escape($_REQUEST['q']);
                }
                break;
            case 'id' :
                if (!is_numeric($_REQUEST['id'])) {
                    $this->_core->sendErrorPage();
                } else {
                    $docIdentifier = intval($_REQUEST['id']);
                }
                break;
        }
        return $docIdentifier;
    }
}