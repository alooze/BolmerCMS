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

    /** @var \Bolmer\Core $_core */
    protected $_core = null;

    /**
     * @param Pimple $inj
     * @param string $namespace
     * @param int $defaultTTL
     * @param int $ttlVariation
     */
    public function __construct(\Pimple $inj, $namespace = '', $defaultTTL = null, $ttlVariation = 0)
    {
        $this->_inj= $inj;
        $this->_core = $inj['core'];
        
        // создаем основной кеш
        $options = array('dir'=>BOLMER_BASE_PATH.'assets/cache',
                        'sub_dirs'=>false,
                        'id_as_filename'=>true,
                        'file_extension'=>'.pageCache.php'
            );

        $backend = new \Tcache\Backends\File($options);
        
        parent::__construct($backend, $namespace, $defaultTTL, $ttlVariation);
        // файловый кеш не поддерживает теги, создаем отдельный кеш для тегов
        $options = array('dir'=>BOLMER_BASE_PATH.'assets/cache/tags',
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
        $tbl_document_groups= $this->_core->getFullTableName("document_groups");
        
        $cacheId = $this->getCacheId($id);

        $cacheContent = $this->get($cacheId);

        if ($cacheContent !== NULL) {
            $this->_core->documentGenerated = 0;
            $cacheContent = substr($flContent, 37); // remove php header
            $a = explode("<!--__MODxCacheSpliter__-->", $cacheContent, 2);
            if (count($a) == 1) {
                return $a[0]; // return only document content
            } else {
                $docObj= unserialize($a[0]); // rebuild document object
                if ($docObj['privateweb'] && isset ($docObj['__MODxDocGroups__'])) {
                    $pass= false;
                    $usrGrps= $this->_inj['user']->getUserDocGroups();
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
                        if ($this->_core->getConfig('unauthorized_page')) {
                            // check if file is not public
                            $secrs= $this->_core->db->select('id', $tbl_document_groups, "document='{$id}'", '', '1');
                            if ($secrs)
                                $seclimit= $this->_core->db->getRecordCount($secrs);
                        }
                        if ($seclimit > 0) {
                            // match found but not publicly accessible, send the visitor to the unauthorized_page
                            $this->_core->sendUnauthorizedPage();
                            exit; // stop here
                        } else {
                            // no match found, send the visitor to the error_page
                            $this->_core->sendErrorPage();
                            exit; // stop here
                        }
                    }
                }
                // Grab the Scripts
                if (isset($docObj['__MODxSJScripts__'])) $this->_core->sjscripts = $docObj['__MODxSJScripts__'];
                if (isset($docObj['__MODxJScripts__']))  $this->_core->jscripts = $docObj['__MODxJScripts__'];

                // Remove intermediate variables
                unset($docObj['__MODxDocGroups__'], $docObj['__MODxSJScripts__'], $docObj['__MODxJScripts__']);

                $this->_core->documentObject= $docObj;
                return $a[1]; // return document content
            }                
        } else {
            $this->_core->documentGenerated= 1;
            return "";
        }
    }

    /**
     * 
     */
    public function getCacheId($id)
    {
        if ($this->_core->getConfig('cache_type') == 2) {
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
            include_once(BOLMER_MANAGER_PATH . 'processors/cache_sync.class.processor.php');
            $sync = new \synccache();
            $sync->setCachepath($this->backend->getCachePath());
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
     * @param string $fullLocalPath
     * @return string The complete URL/path to the cache folder
     */
    public function getCachePath($fullLocalPath = false) 
    {
        switch ($fullLocalPath) {
            case !false:
                $out = $this->backend->getCachePath();
                break;

            default:
                $out = str_replace(BOLMER_BASE_PATH, BOLMER_BASE_URL, $this->backend->getCachePath());
                break;
        }
        return $out;        
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
                    $id = http_build_query($this->_core->documentObject);
                } else {
                    foreach ($rules as $rule) {
                        if (isset($this->_core->documentObject[$rule])) {
                            $id.= serialize($this->_core->documentObject[$rule]);
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
     * Выполнение сниппета в контексте Bolmer и сохранение кеша "на лету"
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
        // получаем ключ кеша
        $id = serialize($name).serialize($options);

        foreach ($consider as $type => $rules) {
            // в зависимости от указанных для отслеживания переменных, получаем данные для id
            $id.= $this->getIdGivenThe($type, $rules);
        }

        $id = $name.'.'.md5($id); // пока искусственно добавляем "неймспейс"
        
        if (($value = $this->get($id)) === null) {
            //готовим данные для сохранения в кеше

            //получаем все установленные плейсхолдеры ДО вызова сниппета
            $tmpAr = $this->_inj['parser']->getPlaceholders();

            $value = $this->_inj['snippet']->runSnippet($name, $options);

            // отделяем плейсхолдеры, которые установил вызванный сниппет
            $phAr = array_diff($this->_inj['parser']->getPlaceholders(), $tmpAr);

            //дописываем плейсхолдеры в кеш
            $res = serialize($phAr).'~~~SPLITTER~~~'.$value;
            $this->add($id, $res);
            return $value;
        }

        $tmpAr = explode('~~~SPLITTER~~~', $value);
        $this->_inj['parser']->toPlaceholders(unserialize($tmpAr[0]));

        return $tmpAr[1];
    }
}
