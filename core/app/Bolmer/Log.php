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

        /** @var \Bolmer\Core $_core */
        protected $_core = null;

        public function __construct(\Pimple $inj){
            $this->_inj= $inj;
            $this->_core = $inj['core'];
        }

        public function rotate_log($target='event_log',$limit=3000, $trim=100)
        {
            if($limit < $trim) $trim = $limit;

            $table_name = $this->_core->getFullTableName($target);
            $count = $this->_core->db->getValue($this->_core->db->select('COUNT(id)',$table_name));
            $over = $count - $limit;
            if(0 < $over)
            {
                $trim = ($over + $trim);
                $this->_core->db->delete($table_name,'','',$trim);
            }
            $this->_core->db->optimize($table_name);
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
        public function logEvent($evtid, $type, $msg, $source= 'Parser') {
            $msg= $this->_core->db->escape($msg);
            $source= $this->_core->db->escape($source);
            if ($this->_inj['global_config']['database_connection_charset'] == 'utf8' && extension_loaded('mbstring')) {
                $source = mb_substr($source, 0, 50 , "UTF-8");
            } else {
                $source = substr($source, 0, 50);
            }
            $LoginUserID = $this->_inj['user']->getLoginUserID();
            if ($LoginUserID == '') $LoginUserID = 0;
            $usertype = $this->_inj['response']->isFrontend() ? 1 : 0;
            $evtid= intval($evtid);
            $type = intval($type);
            // Types: 1 = information, 2 = warning, 3 = error
            if ($type < 1){
                $type= 1;
            } elseif ( $type > 3 ) {
                $type= 3;
            }
            $ds = $this->_core->db->insert(array(
                'eventid' => $evtid,
                'type' =>$type,
                'createdon' => time(),
                'source' => $source,
                'description' => $msg,
                'user' => $LoginUserID,
                'usertype' => $usertype
            ), $this->_core->getTableName("BEventLog"));

            if($this->_core->getConfig('send_errormail') != '0')
            {
                if($this->_core->getConfig('send_errormail') <= $type)
                {
                    $subject = 'Error mail from ' . $this->_core->getConfig('site_name');
                    $this->_core->sendmail($subject, $source);
                }
            }
            if (!$ds) {
                echo "Error while inserting event log into database.";
                exit();
            }
        }
    }