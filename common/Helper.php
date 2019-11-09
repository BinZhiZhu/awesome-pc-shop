<?php

namespace app\common;

use Yii;
use yii\base\Security;

class Helper
{
    /**
     * @param $url
     * @return bool|string
     */
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

    /**
     * @param $value
     * @param $array
     * @return bool
     */
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

    /**
     * @param $table_name
     * @return bool
     * @throws \yii\db\Exception
     */
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
     *
     */
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

    /**
     * 生成订单号
     *
     * @return string
     */
    public static function generateOrderSn()
    {
        $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $orderSn = $yCode[intval(date('Y')) - 2011] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        return $orderSn;
    }
}