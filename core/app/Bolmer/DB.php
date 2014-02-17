<?php namespace Bolmer;

use \Granada\ORM as ORM;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 5:34
 */

class DB{
    public $dataTypes = array();
    public $conn;
    public $config;
    public $isConnected;

    function initDataTypes() {
        $this->dataTypes['numeric'] = array (
            'INT',
            'INTEGER',
            'TINYINT',
            'BOOLEAN',
            'DECIMAL',
            'DEC',
            'NUMERIC',
            'FLOAT',
            'DOUBLE PRECISION',
            'REAL',
            'SMALLINT',
            'MEDIUMINT',
            'BIGINT',
            'BIT'
        );
        $this->dataTypes['string'] = array (
            'CHAR',
            'VARCHAR',
            'BINARY',
            'VARBINARY',
            'TINYBLOB',
            'BLOB',
            'MEDIUMBLOB',
            'LONGBLOB',
            'TINYTEXT',
            'TEXT',
            'MEDIUMTEXT',
            'LONGTEXT',
            'ENUM',
            'SET'
        );
        $this->dataTypes['date'] = array (
            'DATE',
            'DATETIME',
            'TIMESTAMP',
            'TIME',
            'YEAR'
        );
    }

    function disconnect() {
        ORM::set_db(null);
    }
    function escape($s, $safecount=0) {
        $safecount++;
        if(1000<$safecount) exit("Too many loops '{$safecount}'");

        $conn = ORM::get_db();
        if(!is_object($conn)){
            $this->connect();
        }

        if(is_array($s)) {
            if(count($s) === 0) $s = '';
            else {
                foreach($s as $i=>$v) {
                    $s[$i] = $this->escape($v,$safecount);
                }
            }
        }
        else $s = substr(ORM::get_db()->quote($s), 1, -1);
        return $s;
    }
    function delete($from, $where='', $orderby='', $limit = '') {
        if (!$from)
            return false;
        else {
            $from = $this->replaceFullTableName($from);
            if($where != '') $where = "WHERE {$where}";
            if($orderby !== '') $orderby = "ORDER BY {$orderby}";
            if($limit != '') $limit = "LIMIT {$limit}";
            return $this->query("DELETE FROM {$from} {$where} {$orderby} {$limit}");
        }
    }
    function update($fields, $table, $where = "") {
        if (!$table)
            return false;
        else {
            $table = $this->replaceFullTableName($table);
            if (!is_array($fields))
                $flds = $fields;
            else {
                $flds = '';
                foreach ($fields as $key => $value) {
                    if (!empty ($flds))
                        $flds .= ",";
                    $flds .= $key . "=";
                    $flds .= "'" . $value . "'";
                }
            }
            $where = ($where != "") ? "WHERE $where" : "";
            return $this->query("UPDATE $table SET $flds $where");
        }
    }
    function optimize($table_name)
    {
        $table_name = $this->replaceFullTableName($table_name);
        $rs = $this->query("OPTIMIZE TABLE {$table_name}");
        if($rs) $rs = $this->query("ALTER TABLE {$table_name}");
        return $rs;
    }
    /*function freeResult($rs) {
        mysql_free_result($rs);
    }*/

    /**
     * @param ORM $rs
     * @return mixed
     */
    function numFields($rs) {
        return $rs->get_last_statement()->columnCount();
    }
    function fieldName($rs,$col=0) {
        if( ! $rs instanceof \PDOStatement){
            $rs = ORM::get_last_statement();
        }
        $out = $rs->getColumnMeta($col);
        return isset($out['name']) ? $out['name'] : '';
    }
    function selectDb($name) {
        ORM::get_db();
        //SELECT Database $name
    }
    function getVersion() {
        return ORM::get_db()->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }
    /**
     * @TODO УДАЛИТЬ НАХУЙ
     * @param $str
     * @param null $force
     * @return mixed|string
     */
    function replaceFullTableName($str,$force=null) {
        $str = trim($str);
        $dbase  = trim($this->config['dbase'],'`');
        $prefix = $this->config['table_prefix'];
        if(!empty($force))
        {
            $result = "`{$dbase}`.`{$prefix}{$str}`";
        }
        elseif(strpos($str,'[+prefix+]')!==false)
        {
            $result = preg_replace('@\[\+prefix\+\]([0-9a-zA-Z_]+)@', "`{$dbase}`.`{$prefix}$1`", $str);
        }
        else $result = $str;

        return $result;
    }
    protected function _getTable($dbTable){
        $table = '';
        $dbTable = explode(".", $this->replaceFullTableName($dbTable), 2);
        if(count($dbTable)>0){
            $table = end($dbTable);
            if(count($dbTable)==2){
                $db = trim($dbTable[0], '`');
                //ORM::set_db($db);
                /* @TODO: select DB */
            };
        }
        $out = trim($table, '`');
        return $out;
    }
    public function query($q){
        $db = ORM::get_db();
        $q = ORM::raw_execute($q);
        return ORM::get_last_statement();
    }

    /**
     * @TODO вернуть старый билдер запросов
     */
    function select($fields = "*", $from = "", $where = "", $orderby = "", $limit = "") {
        if (!$from)
            return false;
        else {
            $from = $this->replaceFullTableName($from);
            $where = ($where != "") ? "WHERE $where" : "";
            $orderby = ($orderby != "") ? "ORDER BY $orderby " : "";
            $limit = ($limit != "") ? "LIMIT $limit" : "";
            return $this->query("SELECT $fields FROM $from $where $orderby $limit");
        }
    }
    function insert($fields, $intotable, $fromfields = "*", $fromtable = "", $where = "", $limit = "") {
        if(empty($intotable)) return false;
        if (!is_array($fields))
            $flds = $fields;
        else {
            $keys = array_keys($fields);
            $values = array_values($fields);
            $flds = "(" . implode(",", $keys) . ") " .
                (!$fromtable && $values ? "VALUES('" . implode("','", $values) . "')" : "");
            if ($fromtable) {
                $fromtable = $this->replaceFullTableName($fromtable);
                $where = ($where != "") ? "WHERE $where" : "";
                $limit = ($limit != "") ? "LIMIT $limit" : "";
                $sql = "SELECT $fromfields FROM $fromtable $where $limit";
            }
        }
        $intotable = $this->replaceFullTableName($intotable);
        $rt = $this->query("INSERT INTO $intotable $flds $sql");
        $lid = $this->getInsertId();
        return $lid ? $lid : $rt;
        /*if (!$intotable)
            return false;
        else {
            if (!is_array($fields))
                $flds = $fields;
            else {
                $keys = array_keys($fields);
                $values = array_values($fields);
                $flds = "(" . implode(",", $keys) . ") " .
                    (!$fromtable && $values ? "VALUES('" . implode("','", $values) . "')" : "");
                if ($fromtable) {
                    $fromtable = $this->replaceFullTableName($fromtable);
                    $where = ($where != "") ? "WHERE $where" : "";
                    $limit = ($limit != "") ? "LIMIT $limit" : "";
                    $sql = "SELECT $fromfields FROM $fromtable $where $limit";
                }
            }
            $intotable = $this->replaceFullTableName($intotable);
            $rt = $this->query("INSERT INTO $intotable $flds $sql");
            $lid = $this->getInsertId();
            return $lid ? $lid : $rt;
        }*/
    }
    function makeArray($q){
        $out = array();
        switch(true){
            case ($q instanceof \PDOStatement):{
                while($row = $this->getRow($q)){
                    $out[] = $row;
                }
                break;
            }
            case ($q instanceof ORM):{
                $out = $q->find_array();
                break;
            }
        }
        return $out;
    }
    function getInsertId($conn=NULL) {
        if(!is_object($conn)){
            $conn = ORM::get_db();
        }
        return $conn->lastInsertId();
    }

    /**
     * @param ORM|null $conn
     * @return mixed
     */
    function getAffectedRows($conn=NULL) {
        if (!is_object($conn)) {
            $conn = ORM::get_last_statement();
        }
        return $conn->rowCount();
    }
    /*function getLastError($conn=NULL) {
        if (!is_resource($conn)) $conn =& $this->conn;
        return mysql_error($conn);
    }*/
    /**
     * @param ORM $ds
     */
    function getRecordCount($ds) {
        return ($ds instanceof \PDOStatement) ? $ds->rowCount() : 0;
    }

    /**
     * @param ORM $ds
     * @param string $mode
     */
    function getRow($ds, $mode = 'assoc') {
        if($ds instanceof \PDOStatement){
            switch($mode){
                case 'assoc':{
                    return $ds->fetch(\PDO::FETCH_ASSOC);
                }
                case 'num':{
                    return $ds->fetch(\PDO::FETCH_NUM);
                }
                case 'object':{
                    return $ds->fetch(\PDO::FETCH_OBJ);
                }
                case 'both':{
                    return $ds->fetch(\PDO::FETCH_BOTH);
                }
                default:{
                getService('modx')->messageQuit("Unknown get type ($mode) specified for fetchRow - must be empty, 'assoc', 'num' or 'both'.");
                }
            }
        }
    }
    function getColumn($name, $dsq) {
        if (is_string($dsq))
            $dsq = $this->query($dsq);
        if ($dsq instanceof \PDOStatement) {
            $col = array ();
            while ($row = $this->getRow($dsq)) {
                $col[] = $row[$name];
            }
            return $col;
        }
    }
    function getColumnNames($dsq) {
        if (is_string($dsq))
            $dsq = $this->query($dsq);
        if ($dsq instanceof \PDOStatement) {
            $names = array ();
            $limit = $dsq->columnCount($dsq);
            for ($i = 0; $i < $limit; $i++) {
                $names[] = $this->fieldName($dsq, $i);
            }
            return $names;
        }
    }
    function getValue($dsq) {
        $out = null;
        if (is_string($dsq)){
            $dsq = $this->query($dsq);
        }
        if ($dsq instanceof \PDOStatement) {
            $r = $this->getRow($dsq, "num");
            $out = $r[0];
        }
        return $out;
    }

    function getXML($dsq) {
        if (!is_resource($dsq))
            $dsq = $this->query($dsq);
        $xmldata = "<xml>\r\n<recordset>\r\n";
        while ($row = $this->getRow($dsq, "both")) {
            $xmldata .= "<item>\r\n";
            for ($j = 0; $line = each($row); $j++) {
                if ($j % 2) {
                    $xmldata .= "<$line[0]>$line[1]</$line[0]>\r\n";
                }
            }
            $xmldata .= "</item>\r\n";
        }
        $xmldata .= "</recordset>\r\n</xml>";
        return $xmldata;
    }

    function getTableMetaData($table) {
        $metadata = false;
        if (!empty ($table)) {
            $sql = "SHOW FIELDS FROM $table";
            if ($ds = $this->query($sql)) {
                while ($row = $this->getRow($ds)) {
                    $fieldName = $row['Field'];
                    $metadata[$fieldName] = $row;
                }
            }
        }
        return $metadata;
    }

    function prepareDate($timestamp, $fieldType = 'DATETIME') {
        $date = '';
        if (!$timestamp === false && $timestamp > 0) {
            switch ($fieldType) {
                case 'DATE' :
                    $date = date('Y-m-d', $timestamp);
                    break;
                case 'TIME' :
                    $date = date('H:i:s', $timestamp);
                    break;
                case 'YEAR' :
                    $date = date('Y', $timestamp);
                    break;
                default :
                    $date = date('Y-m-d H:i:s', $timestamp);
                    break;
            }
        }
        return $date;
    }

    function getHTMLGrid($dsq, $params) {
        if (is_string($dsq))
            $dsq = $this->query($dsq);
        if ($dsq instanceof \PDOStatement) {
            include_once MODX_MANAGER_PATH . 'includes/controls/datagrid.class.php';
            $grd = new \DataGrid('', $dsq);
            $grd->noRecordMsg = $params['noRecordMsg'];

            $grd->columnHeaderClass = $params['columnHeaderClass'];
            $grd->cssClass = $params['cssClass'];
            $grd->itemClass = $params['itemClass'];
            $grd->altItemClass = $params['altItemClass'];

            $grd->columnHeaderStyle = $params['columnHeaderStyle'];
            $grd->cssStyle = $params['cssStyle'];
            $grd->itemStyle = $params['itemStyle'];
            $grd->altItemStyle = $params['altItemStyle'];

            $grd->columns = $params['columns'];
            $grd->fields = $params['fields'];
            $grd->colWidths = $params['colWidths'];
            $grd->colAligns = $params['colAligns'];
            $grd->colColors = $params['colColors'];
            $grd->colTypes = $params['colTypes'];
            $grd->colWraps = $params['colWraps'];

            $grd->cellPadding = $params['cellPadding'];
            $grd->cellSpacing = $params['cellSpacing'];
            $grd->header = $params['header'];
            $grd->footer = $params['footer'];
            $grd->pageSize = $params['pageSize'];
            $grd->pagerLocation = $params['pagerLocation'];
            $grd->pagerClass = $params['pagerClass'];
            $grd->pagerStyle = $params['pagerStyle'];
            return $grd->render();
        }
    }

    function __construct($host='',$dbase='', $uid='',$pwd='',$pre=NULL,$charset='',$connection_method='SET CHARACTER SET') {
        $MainConfig = getService('global_config');
        $this->config['host'] = $host ? $host : getkey($MainConfig, 'database_server');
        $this->config['dbase'] = $dbase ? $dbase : getkey($MainConfig, 'dbase');
        $this->config['user'] = $uid ? $uid : getkey($MainConfig, 'database_user');
        $this->config['pass'] = $pwd ? $pwd : getkey($MainConfig,'database_password');
        $this->config['charset'] = $charset ? $charset : getkey($MainConfig,'database_connection_charset');
        $this->config['connection_method'] =  $this->_dbconnectionmethod = getkey($MainConfig, 'database_connection_method', $connection_method);
        $this->config['table_prefix'] = ($pre !== NULL) ? $pre : $MainConfig['table_prefix'];
        $this->initDataTypes();
        $this->connect();
    }

    function connect($host = '', $dbase = '', $uid = '', $pwd = '', $persist = 0, $prefix = '') {
        $uid = $uid ? $uid : $this->config['user'];
        $pwd = $pwd ? $pwd : $this->config['pass'];
        $host = $host ? $host : $this->config['host'];
        $dbase = $dbase ? $dbase : $this->config['dbase'];
        $charset = $this->config['charset'];
        $prefix = $prefix ? $prefix : $this->config['table_prefix'];
        $connection_method = $this->config['connection_method'];
        ORM::configure(array(
            'connection_string' => "mysql:host={$host};dbname=".trim($dbase,'`'),
            'username' => $uid,
            'password' => $pwd,
            'prefix' => $prefix,
            'driver_options' => array(\PDO::MYSQL_ATTR_INIT_COMMAND => $connection_method.' '.$charset),
            'logger' => function($q, $time) {
                    \Bolmer\Debug::addQuery($q, $time);
                },
            'logging' => true
        ));
    }
}