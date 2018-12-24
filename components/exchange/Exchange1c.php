<?php

namespace app\components\exchange;

use app\components\debug\Debug;
use Yii;
use app\components\exchange\ParsingXml;

class Exchange1c
{
    private static $filename;


    public static function getResponse($type, $mode, $filename)
    {
        self::$filename=$filename;
        self::$mode();
    }

    private static function checkauth()
    {
        $request = Yii::$app->request;
        $user = $request->getAuthUser();
        $pass = $request->getAuthPassword();

        if($user == "user" && $pass == "123") {
            echo "success\n";
            echo session_name() . "\n";
            echo session_id() . "\n";
            exit;
        }
        else {
            echo "failure\n";
            exit;
        }
    }

    private static function init()
    {
        $zip = extension_loaded('zip') ? 'yes' : 'no';
        echo 'zip='.$zip."\n";
        echo "file_limit=0\n";
        exit;
    }

    private static function file()
    {
        $data = file_get_contents('php://input');
        file_put_contents(self::$filename, $data);

        if(file_exists(self::$filename)) {
            $zip = new \ZipArchive;
            if($res = $zip->open(self::$filename, \ZIPARCHIVE::CREATE)) {
                $zip->extractTo(\Yii::$app->basePath."/upload/catalog1c/");
                $zip->close();
                unlink(self::$filename);
                echo "success\n";
                exit;
            }
        }
        echo "failure\n";
        exit;
    }

    private function import()
    {
        $filePath = \Yii::$app->basePath."/upload/catalog1c/".self::$filename;
        ParsingXml::dataTypeInFile($filePath);
        echo "success\n";
            exit;

//        echo "failure\n";
//            exit;

    }

    private function complete()
    {

        Debug::debugInFile( '1',\Yii::$app->basePath."/upload/catalog1c/debug/Complete");
        echo "success\n";
        exit;
    }
}