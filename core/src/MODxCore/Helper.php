<?php namespace MODxCore;
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
        if (\MODxCore\User\Manager::checkSession() == true) {
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
        list ($usec, $sec)= explode(' ', microtime());
        return ((float) $usec + (float) $sec);
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
}