<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'com.binzhizhu.demo',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'language'=>'zh-CN',
    'aliases' => [
        '@root' => realpath(__DIR__ . '/../'),
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@casbin' => '@vendor/casbin',
        '@bin' => dirname(__DIR__),
        '@adapter'=> '@vendor/yii-adapter',
    ],
    'modules' => [
        'api' => [
            'class' => 'app\modules\api\Module::class',
            ]
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'enableCsrfValidation'=>false, //取消enableCookieValidation的验证  隐藏表单的_csrf
            'cookieValidationKey' => '33VvOVnAPHPR6oqvzlbpa4J_ryENWIBJ',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'session' => [
            'class' =>'yii\web\session',
            'timeout' => 60 * 60 * 24 * 30,
            'cookieParams' => ['lifetime' => 30 * 24 * 60 * 60]
        ],
            'errorHandler' => [
        //    'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        /*
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
        */
    ],
    'params' => $params,
    'defaultRoute' => 'pc',
     'layout' =>false,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        'historySize'=>'5000',
        'traceLine' => '<a href="phpstorm://open?url={file}&line={line}">{file}:{line}</a>',
        'allowedIPs' => ['127.0.0.1', '::1', '*'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
        'generators' => [ //这里配置生成器
//            'myCrud' => [ // 生成器名称
//                'class' => 'app\myTemplates\crud\Generator', // 生成器类
//                'templates' => [ //配置模版文件
//                    'my' => '@app/myTemplates/crud/default', // 模版名称 => 模版路径
//                ]
//            ],
        ],
    ];

}

return $config;
