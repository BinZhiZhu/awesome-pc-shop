<?php

namespace app\enums;

use yii\base\BaseObject;

/**
 * 性别枚举
 *
 * @package app\enums
 */
class GenderTypeEnum extends BaseObject
{
    const MAN = 0;

    const WOMAN = 1;

    public static $list = [
        self::MAN=>'男',
        self::WOMAN=>'女'
    ];
}