<?php

namespace app\common;

use app\models\DevUsers;
use Yii;
use yii\base\Security;

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

    /**
     * @param $length
     * @param bool $numeric
     * @return string
     */
    public static function random($length, $numeric = false)
    {
        $seed = base_convert(md5(microtime() . Security::getInstance()->generateRandomString(32)), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));
        if ($numeric) {
            $hash = '';
        } else {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            $length--;
        }
        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $seed{mt_rand(0, $max)};
        }
        return $hash;
    }
}