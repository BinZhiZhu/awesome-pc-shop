<?php

namespace app\enums;

use yii\base\BaseObject;

/**
 * 状态枚举类
 *
 * @package app\enums
 */
class StatusTypeEnum extends BaseObject
{
    const ON = 1;
    const OFF = 0;

    public static $list = [
        self::ON => '开启',
        self::OFF => '关闭'
    ];

}