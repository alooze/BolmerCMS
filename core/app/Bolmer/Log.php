<?php namespace Bolmer;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 20:50
 */

    class Log{
        /** @var \Bolmer\Pimple $_inj */
        private $_inj = null;

        public function __construct(\Pimple $inj){
            $this->_inj= $inj;
        }

        function rotate_log($target='event_log',$limit=3000, $trim=100)
        {
            if($limit < $trim) $trim = $limit;

            $table_name = $this->_inj['modx']->getFullTableName($target);
            $count = $this->_inj['db']->getValue($this->_inj['db']->select('COUNT(id)',$table_name));
            $over = $count - $limit;
            if(0 < $over)
            {
                $trim = ($over + $trim);
                $this->_inj['db']->delete($table_name,'','',$trim);
            }
            $this->_inj['db']->optimize($table_name);
        }

        /**
         * Add an a alert message to the system event log
         *
         * @param int $evtid Event ID
         * @param int $type Types: 1 = information, 2 = warning, 3 = error
         * @param string $msg Message to be logged
         * @param string $source source of the event (module, snippet name, etc.)
         *                       Default: Parser
         */
        function logEvent($evtid, $type, $msg, $source= 'Parser') {
            $msg= $this->_inj['db']->escape($msg);
            $source= $this->_inj['db']->escape($source);
            if ($this->_inj['global_config']['database_connection_charset'] == 'utf8' && extension_loaded('mbstring')) {
                $source = mb_substr($source, 0, 50 , "UTF-8");
            } else {
                $source = substr($source, 0, 50);
            }
            $LoginUserID = $this->_inj['modx']->getLoginUserID();
            if ($LoginUserID == '') $LoginUserID = 0;
            $evtid= intval($evtid);
            $type = intval($type);
            if ($type < 1) $type= 1; // Types: 1 = information, 2 = warning, 3 = error
            if (3 < $type) $type= 3;
            $sql= "INSERT INTO " . $this->_inj['modx']->getFullTableName("event_log") . " (eventid,type,createdon,source,description,user) " .
                "VALUES($evtid,$type," . time() . ",'$source','$msg','" . $LoginUserID . "')";
            $ds= @$this->_inj['db']->query($sql);
            if(!$this->_inj['db']->conn) $source = 'DB connect error';
            if($this->_inj['modx']->getConfig('send_errormail') != '0')
            {
                if($this->_inj['modx']->getConfig('send_errormail') <= $type)
                {
                    $subject = 'Error mail from ' . $this->_inj['modx']->getConfig('site_name');
                    $this->_inj['modx']->sendmail($subject,$source);
                }
            }
            if (!$ds) {
                echo "Error while inserting event log into database.";
                exit();
            }
        }
    }