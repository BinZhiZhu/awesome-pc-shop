<?php

namespace app\common;

use app\models\DevUsers;
use Yii;

class Helper
{
    public static function requestApi($url)
    {
        if(empty($url)){
            return false;
        }
        $url = trim($url);

        $routes = explode('/',$url);
        $url = 'index.php?r='.$routes[0] .'/'.$routes[1];

        return $url;

    }

    public static function deep_array($value, $array)
    {
        foreach ($array as $item) {
            if (!is_array($item)) {
                if ($item == $value) {
                    return true;
                } else {
                    continue;
                }
            }

            if (in_array($value, $item)) {
                return true;
            }
        }
        return false;
    }

    public static function tableExists($table_name)
    {
        $table_name = trim($table_name);
        $check = Yii::$app->db->createCommand("show tables ")->queryAll();
        //判断是否存在值是否存在二维数组中
        $res = self::deep_array($table_name, $check);
        if ($res) {
            return true;
        }

        return false;

    }

}