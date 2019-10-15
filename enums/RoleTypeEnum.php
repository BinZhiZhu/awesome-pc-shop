<?php

namespace app\enums;

use yii\base\BaseObject;

/**
 * 后台角色枚举类
 *
 * @package app\enums
 */
class RoleTypeEnum extends BaseObject
{
    const MERCHANT = 0;
    const ADMIN = 1;

    public static $list = [
        self::MERCHANT => '商家',
        self::ADMIN => '管理员'
    ];

}