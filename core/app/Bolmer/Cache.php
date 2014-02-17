<?php 
namespace Bolmer;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 11.02.14
 * Time: 4:50
 */
use Tcache\Cache as Tcache;

class Cache extends Tcache
{
    /** @var \Bolmer\Pimple $_inj */
    private $_inj = null;

    /** @var \Bolmer\Core $_modx */
    protected $_modx = null;

    /**
     * @param Pimple $inj
     * @param string $namespace
     * @param int $defaultTTL
     * @param int $ttlVariation
     */
    public function __construct(\Pimple $inj, $namespace = 'B', $defaultTTL = null, $ttlVariation = 0)
    {
        $this->_inj= $inj;
        $this->_modx = $inj['modx'];
        
        // создаем основной кеш
        $options = array('dir'=>MODX_BASE_PATH.'assets/cache',
                        'sub_dirs'=>false,
                        'id_as_filename'=>true,
                        'file_extension'=>'.pageCache.php'
            );

        $backend = new \Tcache\Backends\File($options);
        
        parent::__construct($backend, $namespace, $defaultTTL, $ttlVariation);
        // файловый кеш не поддерживает теги, создаем отдельный кеш для тегов
        $options = array('dir'=>MODX_BASE_PATH.'assets/cache/tags',
                        'sub_dirs'=>false,
                        'id_as_filename'=>true,
                        'file_extension'=>'.tag'
            );

        $this->setTagBackend(new \Tcache\Backends\File($options)); 
    }

    /**
     * Check the cache for a specific document/resource
     *
     * @param int $id
     * @return string
     */
    public function checkCache($id) 
    {
        $tbl_document_groups= $this->_modx->getFullTableName("document_groups");
        
        $cacheId = $this->getCacheId($id);

        $cacheContent = $this->get($cacheId);

        if ($cacheContent !== NULL) {
            $this->_modx->documentGenerated = 0;
            $cacheContent = substr($flContent, 37); // remove php header
            $a = explode("<!--__MODxCacheSpliter__-->", $cacheContent, 2);
            if (count($a) == 1) {
                return $a[0]; // return only document content
            } else {
                $docObj= unserialize($a[0]); // rebuild document object
                if ($docObj['privateweb'] && isset ($docObj['__MODxDocGroups__'])) {
                    $pass= false;
                    $usrGrps= $this->_modx->getUserDocGroups();
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
                        if ($this->_modx->getConfig('unauthorized_page')) {
                            // check if file is not public
                            $secrs= $this->_modx->db->select('id', $tbl_document_groups, "document='{$id}'", '', '1');
                            if ($secrs)
                                $seclimit= $this->_modx->db->getRecordCount($secrs);
                        }
                        if ($seclimit > 0) {
                            // match found but not publicly accessible, send the visitor to the unauthorized_page
                            $this->_modx->sendUnauthorizedPage();
                            exit; // stop here
                        } else {
                            // no match found, send the visitor to the error_page
                            $this->_modx->sendErrorPage();
                            exit; // stop here
                        }
                    }
                }
                // Grab the Scripts
                if (isset($docObj['__MODxSJScripts__'])) $this->_modx->sjscripts = $docObj['__MODxSJScripts__'];
                if (isset($docObj['__MODxJScripts__']))  $this->_modx->jscripts = $docObj['__MODxJScripts__'];

                // Remove intermediate variables
                unset($docObj['__MODxDocGroups__'], $docObj['__MODxSJScripts__'], $docObj['__MODxJScripts__']);

                $this->_modx->documentObject= $docObj;
                return $a[1]; // return document content
            }                
        } else {
            $this->_modx->documentGenerated= 1;
            return "";
        }
    }

    /**
     * 
     */
    public function getCacheId($id)
    {
        if ($this->_modx->getConfig('cache_type') == 2) {
            $cacheId = 'docid_'.$id.'_'.$this->getIdGivenThe('GET','*');
            // $md5_hash = '';
            // if(!empty($_GET)) $md5_hash = '_' . md5(http_build_query($_GET));
            // $cacheFile= "assets/cache/docid_" . $id .$md5_hash. ".pageCache.php";
        } else {
            $cacheId = 'docid_'.$id;
            // $cacheFile= "assets/cache/docid_" . $id . ".pageCache.php";
        }
        return $cacheId;
    }


    /**
     * Clear the cache of MODX.
     *
     * @return boolean
     */
    public function clearCache($type='', $report=false) 
    {
        if ($type=='full') {
            include_once(MODX_MANAGER_PATH . 'processors/cache_sync.class.processor.php');
            $sync = new \synccache();
            $sync->setCachepath(MODX_BASE_PATH . 'assets/cache/');
            $sync->setReport($report);
            $sync->emptyCache();
            return $this->flushAll();
        } else {
            return $this->flushAll();
        }
    }

    /**
     * Returns the cache relative URL/path with respect to the site root.
     *
     * @global string $base_url
     * @return string The complete URL to the cache folder
     */
    public function getCachePath() 
    {
        // return MODX_BASE_URL . 'assets/cache/';
        return $this->backend->$dir;
    }

    /**
     * Возвращает уникальный ID в зависимости от переданных условий
     *
     *
     * @param string $name Вид условия
     * @param mixed $rules Список значений
     * @return string
     */
    public function getIdGivenThe($name, $rules='*')
    {
        // принудительно приводим список к массиву
        if (!is_array($rules)) {
            $rules = explode(',', $rules);
        }

        // унифицируем название условия
        $name = ucfirst(strtolower($name));

        $id = '';

        // используем условия
        // пока примитивно сериализуем
        switch ($name) {
            case 'Get':
                if ($rules[0] == '*') {
                    $id = http_build_query($_GET);
                } else {
                    foreach ($rules as $rule) {
                        if (isset($_GET[$rule])) {
                            $id.= serialize($_GET[$rule]);
                        }
                    }
                } 
                break;
            case 'Post':
                if ($rules[0] == '*') {
                    $id = http_build_query($_POST);
                } else {
                    foreach ($rules as $rule) {
                        if (isset($_POST[$rule])) {
                            $id.= serialize($_POST[$rule]);
                        }
                    }
                }
                break;

            case 'Request':
                if ($rules[0] == '*') {
                    $id = http_build_query($_REQUEST);
                } else {
                    foreach ($rules as $rule) {
                        if (isset($_REQUEST[$rule])) {
                            $id.= serialize($_REQUEST[$rule]);
                        }
                    }
                }
                break;

            case 'Session':
                if ($rules[0] == '*') {
                    $id = http_build_query($_SESSION);
                } else {
                    foreach ($rules as $rule) {
                        if (isset($_SESSION[$rule])) {
                            $id.= serialize($_SESSION[$rule]);
                        }
                    }
                }
                break;

            case 'Document':
            case 'Resource':
                if ($rules[0] == '*') {
                    $id = http_build_query($this->_modx->documentObject);
                } else {
                    foreach ($rules as $rule) {
                        if (isset($this->_modx->documentObject[$rule])) {
                            $id.= serialize($this->_modx->documentObject[$rule]);
                        }
                    }
                }
                break;
            
            default:
                return '';
                break;
        }
        return md5($id);
    }

    /**
     * Выполнение сниппета в контексте modx и сохранение кеша "на лету"
     *
     * Следующий код выполнит сниппет только первый раз, последующие вызовы
     * будут отдавать код из кеша. При этом отслеживается УНИКАЛЬНОСТЬ
     * GET['start'] (для пагинации Ditto) и поля 'parent' у текущего документа
     *
     * <code>
     *      $options = array('parents'=>1, 'tpl'=>'ditto.tpl');
     *      $consider = array('GET'=>'start,ditto_order','document'=>'parent');
     *      $cache->runSnippet('Ditto', $options, $consider);
     * </code>
     *
     *
     * @param string $name Название сниппета
     * @param array $options Параметры вызова сниппета
     * @param array $consider Массив с переменными, которые нужно учитывать при сохранении
     * @return mixed
     */
    public function runSnippet($name, array $options=array(), array $consider=array())
    {
        $modx = $this->_modx;

        // получаем ключ кеша
        $id = serialize($name).serialize($options);

        foreach ($consider as $type => $rules) {
            // в зависимости от указанных для отслеживания переменных, получаем данные для id
            $id.= $this->getIdGivenThe($type, $rules);
        }

        $id = $name.':'.md5($id); // пока искусственно добавляем неймспейс
        
        if (($value = $this->get($id)) === null) {
            //готовим данные для сохранения в кеше

            //получаем все установленные плейсхолдеры ДО вызова сниппета
            if (!is_array($modx->placeholders)) {
                $tmpAr = array();
            } else {
                $tmpAr = $modx->placeholders;
            }

            $value = $modx->runSnippet($name, $options);

            // отделяем плейсхолдеры, которые установил вызванный сниппет
            if (!is_array($modx->placeholders)) {
                $phAr = array();
            } else {
                $phAr = array_diff($modx->placeholders, $tmpAr);
            }

            //дописываем плейсхолдеры в кеш
            $res = serialize($phAr).'~~~SPLITTER~~~'.$value;
            $this->add($id, $res);
            return $value;
        }

        $tmpAr = explode('~~~SPLITTER~~~', $value);
        $modx->toPlaceholders(unserialize($tmpAr[0]));

        return $tmpAr[1];
    }
}
