<?php namespace MODxCore;
/**
 * Created by PhpStorm.
 * User: Agel_Nash
 * Date: 10.02.14
 * Time: 6:15
 */

class Parser{
    /**
     * Parses a resource property string and returns the result as an array
     *
     * @param string $propertyString
     * @return array Associative array in the form property name => property value
     */
    public static function parseProperties($propertyString) {
        $parameter= array ();
        if (!empty ($propertyString)) {
            $tmpParams= explode("&", $propertyString);
            for ($x= 0; $x < count($tmpParams); $x++) {
                if (strpos($tmpParams[$x], '=', 0)) {
                    $pTmp= explode("=", $tmpParams[$x]);
                    $pvTmp= explode(";", trim($pTmp[1]));
                    if ($pvTmp[1] == 'list' && $pvTmp[3] != "")
                        $parameter[trim($pTmp[0])]= $pvTmp[3]; //list default
                    else
                        if ($pvTmp[1] != 'list' && $pvTmp[2] != "")
                            $parameter[trim($pTmp[0])]= $pvTmp[2];
                }
            }
        }
        return $parameter;
    }

    public static function getTagsFromContent($content,$left='[+',$right='+]') {
        $hash = explode($left,$content);
        foreach($hash as $i=>$v) {
            if(0<$i) $hash[$i] = $left.$v;
        }

        $i=0;
        $count = count($hash);
        $safecount = 0;
        $temp_hash = array();
        while(0<$count) {
            $open  = 1;
            $close = 0;
            $safecount++;
            if(1000<$safecount) break;
            while($close < $open && 0 < $count) {
                $safecount++;
                if(!isset($temp_hash[$i])) $temp_hash[$i] = '';
                if(1000<$safecount) break;
                $remain = array_shift($hash);
                $remain = explode($right,$remain);
                foreach($remain as $v)
                {
                    if($close < $open)
                    {
                        $close++;
                        $temp_hash[$i] .= $v . $right;
                    }
                    else break;
                }
                $count = count($hash);
                if(0<$i && strpos($temp_hash[$i],$right)===false) $open++;
            }
            $i++;
        }
        $matches=array();
        $i = 0;
        foreach($temp_hash as $v) {
            if(strpos($v,$left)!==false) {
                $v = substr($v,0,strrpos($v,$right));
                $matches[0][$i] = $v . $right;
                $matches[1][$i] = substr($v,strlen($left));
                $i++;
            }
        }
        return $matches;
    }

    /**
     * Returns the chunk content for the given chunk name
     *
     * @param string $chunkName
     * @return boolean|string
     */
    public static function getChunk($chunkName) {
        $modx = modx();
        return isset($modx->chunkCache[$chunkName]) ? $modx->chunkCache[$chunkName] : null;
    }

    /**
     * parseText
     * @version 1.0 (2013-10-17)
     *
     * @desc Replaces placeholders in text with required values.
     *
     * @param $chunk {string} - String to parse. @required
     * @param $chunkArr {array} - Array of values. Key — placeholder name, value — value. @required
     * @param $prefix {string} - Placeholders prefix. Default: '[+'.
     * @param $suffix {string} - Placeholders suffix. Default: '+]'.
     *
     * @return {string} - Parsed text.
     */
    public static function parseText($chunk, $chunkArr, $prefix = '[+', $suffix = '+]'){
        if (!is_array($chunkArr)){
            return $chunk;
        }

        foreach ($chunkArr as $key => $value){
            $chunk = str_replace($prefix.$key.$suffix, $value, $chunk);
        }

        return $chunk;
    }

    /**
     * parseChunk
     * @version 1.1 (2013-10-17)
     *
     * @desc Replaces placeholders in a chunk with required values.
     *
     * @param $chunkName {string} - Name of chunk to parse. @required
     * @param $chunkArr {array} - Array of values. Key — placeholder name, value — value. @required
     * @param $prefix {string} - Placeholders prefix. Default: '{'.
     * @param $suffix {string} - Placeholders suffix. Default: '}'.
     *
     * @return {string; false} - Parsed chunk or false if $chunkArr is not array.
     */
    public static function parseChunk($chunkName, $chunkArr, $prefix = '{', $suffix = '}'){
        //TODO: Wouldn't it be more practical to return the contents of a chunk instead of false?
        if (!is_array($chunkArr)){
            return false;
        }

        return self::parseText(self::getChunk($chunkName), $chunkArr, $prefix, $suffix);
    }


    /**
     * Returns the placeholder value
     *
     * @param string $name Placeholder name
     * @return string Placeholder value
     */
    function getPlaceholder($name) {
        $modx = modx();
        return isset($modx->placeholders[$name]) ? $modx->placeholders[$name] : null;
    }

    /**
     * Sets a value for a placeholder
     *
     * @param string $name The name of the placeholder
     * @param string $value The value of the placeholder
     */
    function setPlaceholder($name, $value) {
        return with(modx())->placeholders[$name]= $value;
    }

    /**
     * Set placeholders en masse via an array or object.
     *
     * @param object|array $subject
     * @param string $prefix
     */
    function toPlaceholders($subject, $prefix= '') {
        if (is_object($subject)) {
            $subject= get_object_vars($subject);
        }
        if (is_array($subject)) {
            foreach ($subject as $key => $value) {
                self::toPlaceholder($key, $value, $prefix);
            }
        }
    }

    /**
     * For use by toPlaceholders(); For setting an array or object element as placeholder.
     *
     * @param string $key
     * @param object|array $value
     * @param string $prefix
     */
    function toPlaceholder($key, $value, $prefix= '') {
        if (is_array($value) || is_object($value)) {
            self::toPlaceholders($value, "{$prefix}{$key}.");
        } else {
            self::setPlaceholder("{$prefix}{$key}", $value);
        }
    }
}