<?php

namespace app\components\debug;

class Debug
{
    public static function debug($value)
    {
        echo '<pre>';
        var_dump($value);
        echo '<pre>';
    }

    public static function debugInFile($value, $fname)
    {
        ob_start();
        var_dump($value);
        $str_value = ob_get_clean();

        $f = fopen($fname, 'a');
        fwrite($f, $str_value);
        fclose($f);
    }
}