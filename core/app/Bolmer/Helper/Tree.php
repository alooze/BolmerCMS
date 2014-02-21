<?php namespace Bolmer\Helper;

class Tree
{
    /**
     * @see: https://github.com/DmitryKoterov/DbSimple/blob/master/lib/DbSimple/Generic.php#L986
     */
    public static function build($data, $idName, $pidName, $level = 0, $levelName = 'levelNode', $childName = 'childNodes')
    {
        $children = array(); // children of each ID
        $ids = array();
        foreach ($data as $i => $r) {
            $row = & $data[$i];
            if (!isset($row[$idName])) {
                continue;
            } else {
                $id = $row[$idName];
            }
            $pid = isset($row[$pidName]) ? $row[$pidName] : 0;
            if ($id == $pid) {
                $pid = 0;
            }
            $row[$levelName] = ($pid == 0) ? 1 : $data[$pid][$levelName] + 1;
            if ($level == 0 || $row[$levelName] <= $level) {
                $children[$pid][$id] = & $row;
            } else {
                $children[self::_getParentLevel($data, $pidName, $level, $pid, $levelName)][$id] = & $row;
            }
            if (!isset($children[$id])) $children[$id] = array();
            $row[$childName] = & $children[$id];
            $ids[$id] = true;

        }
        // Root elements are elements with non-found PIDs.
        $out = array();
        foreach ($data as $i => $r) {
            $row = & $data[$i];
            if (!isset($row[$idName])) {
                continue;
            } else {
                $id = $row[$idName];
            }
            $pid = isset($row[$pidName]) ? $row[$pidName] : 0;
            if ($pid == $id) {
                $pid = 0;
            }
            if (!isset($ids[$pid])) {
                $out[$id] = & $row;
            }
            unset($row[$idName]);
        }
        return $out;
    }

    private static function _getParentLevel($data, $pidName, $level, $i = 0, $levelName = 'levelNode')
    {
        if ($data[$i][$levelName] > $level) {
            $out = self::_getParentLevel($data, $pidName, $level, $data[$i][$pidName]);
        } else {
            $out = $data[$i][$pidName];
        }
        return $out;
    }
}