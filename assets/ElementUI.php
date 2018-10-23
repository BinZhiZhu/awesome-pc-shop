<?php

namespace common\assets;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * Element是一个基于vue的后台框架
 * http://element-cn.eleme.io/#/zh-CN/component/installation
 *
 * @package common\assets
 */
class ElementUI extends AssetBundle
{
    public $css = [
        'https://cdn.staticfile.org/element-ui/2.4.6/theme-chalk/index.css',
    ];

    public $js = [
        'https://cdn.staticfile.org/element-ui/2.4.6/index.js',
    ];

    public $depends = [
        'common\assets\VueJs',
        'yii\web\JqueryAsset',
    ];

    public $jsOptions = [
        'position' => View::POS_HEAD,
    ];
}
