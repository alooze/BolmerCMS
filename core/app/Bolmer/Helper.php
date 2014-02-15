<?php namespace Bolmer;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 6:17
 */
class Helper{
    /**
     * Checks, if a the result is a preview
     *
     * @return boolean
     */
    public static function checkPreview() {
        if (\Bolmer\Operations\User\Manager::checkSession() == true) {
            if (isset ($_REQUEST['z']) && $_REQUEST['z'] == 'manprev') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the current micro time
     *
     * @return float
     */
    public static function getMicroTime() {
        return microtime(true);
    }

    public static function nicesize($size) {
        $sizes = array('Tb'=>1099511627776, 'Gb'=>1073741824, 'Mb'=>1048576, 'Kb'=>1024, 'b'=>1);
        $precisions = count($sizes)-1;
        foreach ($sizes as $unit=>$bytes) {
            if ($size>=$bytes)
                return number_format($size/$bytes, $precisions).' '.$unit;
            $precisions--;
        }
        return '0 b';
    }

    /**
     * Remove unwanted html tags and snippet, settings and tags
     *
     * @param string $html
     * @param string $allowed Default: Empty string
     * @return string
     */
    public static function stripTags($html, $allowed= "") {
        $t= strip_tags($html, $allowed);
        $t= preg_replace('~\[\*(.*?)\*\]~', "", $t); //tv
        $t= preg_replace('~\[\[(.*?)\]\]~', "", $t); //snippet
        $t= preg_replace('~\[\!(.*?)\!\]~', "", $t); //snippet
        $t= preg_replace('~\[\((.*?)\)\]~', "", $t); //settings
        $t= preg_replace('~\[\+(.*?)\+\]~', "", $t); //placeholders
        $t= preg_replace('~{{(.*?)}}~', "", $t); //chunks
        $t= preg_replace('~&#x005B;\*(.*?)\*&#x005D;~', "", $t); //encoded tv
        $t= preg_replace('~&#x005B;&#x005B;(.*?)&#x005D;&#x005D;~', "", $t); //encoded snippet
        $t= preg_replace('~&#x005B;\!(.*?)\!&#x005D;~', "", $t); //encoded snippet
        $t= preg_replace('~&#x005B;\((.*?)\)&#x005D;~', "", $t); //encoded settings
        $t= preg_replace('~&#x005B;\+(.*?)\+&#x005D;~', "", $t); //encoded placeholders
        $t= preg_replace('~&#x007B;&#x007B;(.*?)&#x007D;&#x007D;~', "", $t); //encoded chunks
        return $t;
    }

    /**
     * Make a timestamp from a string corresponding to the format in $this->config['datetime_format']
     *
     * @param string $str
     * @return string
     */
    public static function toTimeStamp($str) {
        $str = trim($str);
        if (empty($str)) {return '';}

        switch(with(modx())->getConfig('datetime_format')) {
            case 'YYYY/mm/dd':
                if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}[0-9 :]*$/', $str)) {return '';}
                list ($Y, $m, $d, $H, $M, $S) = sscanf($str, '%4d/%2d/%2d %2d:%2d:%2d');
                break;
            case 'dd-mm-YYYY':
                if (!preg_match('/^[0-9]{2}-[0-9]{2}-[0-9]{4}[0-9 :]*$/', $str)) {return '';}
                list ($d, $m, $Y, $H, $M, $S) = sscanf($str, '%2d-%2d-%4d %2d:%2d:%2d');
                break;
            case 'mm/dd/YYYY':
                if (!preg_match('/^[0-9]{2}\/[0-9]{2}\/[0-9]{4}[0-9 :]*$/', $str)) {return '';}
                list ($m, $d, $Y, $H, $M, $S) = sscanf($str, '%2d/%2d/%4d %2d:%2d:%2d');
                break;
            /*
            case 'dd-mmm-YYYY':
            	if (!preg_match('/^[0-9]{2}-[0-9a-z]+-[0-9]{4}[0-9 :]*$/i', $str)) {return '';}
            	list ($m, $d, $Y, $H, $M, $S) = sscanf($str, '%2d-%3s-%4d %2d:%2d:%2d');
                break;
            */
        }
        if (!$H && !$M && !$S) {$H = 0; $M = 0; $S = 0;}
        $timeStamp = mktime($H, $M, $S, $m, $d, $Y);
        $timeStamp = intval($timeStamp);
        return $timeStamp;
    }

    /**
     * Returns the timestamp in the date format defined in $this->config['datetime_format']
     *
     * @param int $timestamp Default: 0
     * @param string $mode Default: Empty string (adds the time as below). Can also be 'dateOnly' for no time or 'formatOnly' to get the datetime_format string.
     * @return string
     */
    public static function toDateFormat($timestamp = 0, $mode = '') {
        $timestamp = trim($timestamp);
        if($mode !== 'formatOnly' && empty($timestamp)) return '-';
        $timestamp = intval($timestamp);

        switch(with(modx())->getConfig('datetime_format')) {
            case 'YYYY/mm/dd':
                $dateFormat = '%Y/%m/%d';
                break;
            case 'dd-mm-YYYY':
                $dateFormat = '%d-%m-%Y';
                break;
            case 'mm/dd/YYYY':
                $dateFormat = '%m/%d/%Y';
                break;
            /*
            case 'dd-mmm-YYYY':
                $dateFormat = '%e-%b-%Y';
                break;
            */
        }

        if (empty($mode)) {
            $strTime = strftime($dateFormat . " %H:%M:%S", $timestamp);
        } elseif ($mode == 'dateOnly') {
            $strTime = strftime($dateFormat, $timestamp);
        } elseif ($mode == 'formatOnly') {
            $strTime = $dateFormat;
        }
        return $strTime;
    }

    public static function htmlchars($str, $flags = ENT_COMPAT){
        $charset = getkey(modx('config'), 'modx_charset', 'UTF-8');
        if(!is_scalar($str)){
            $str = '';
        }
        $ent_str = htmlspecialchars($str, $flags, $charset);
        if(!empty($str) && empty($ent_str)){
            $detect_order = join(',', mb_detect_order());
            $ent_str = mb_convert_encoding($str,$charset,$detect_order);
        }
        return $ent_str;
    }

    /**
     * Returns the full table name based on db settings
     *
     * @param string $tbl Table name
     * @return string Table name with prefix
     */
    public static function getFullTableName($tbl, $config) {
        return getkey($config, 'dbase') . ".`" . getkey($config, 'table_prefix') . $tbl . "`";
    }
}