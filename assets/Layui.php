<?php

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * Class Layui
 * @package app\assets
 */
class Layui extends AssetBundle
{
    public $css = [
        'https://www.layuicdn.com/layui/css/layui.css',
    ];

    public $js = [
        'https://www.layuicdn.com/layui/layui.js',
    ];


    public $jsOptions = [
        'position' => View::POS_HEAD,
    ];
}
