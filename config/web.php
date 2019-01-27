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
        '@casbin' => '@vendor/casbin',
        '@adapter'=> '@vendor/yii-adapter'
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
        'casbin' => [
            'class' => '@casbin',
            /*
             * Yii-casbin model setting.
             */
            'model' => [
                // Available Settings: "file", "text"
                'config_type' => 'file',
                'config_file_path' => __DIR__.'/casbin/model.conf',
                'config_text' => __DIR__ .'/casbin/policy.csv',
            ],

            // Yii-casbin adapter .
            'adapter' => '@adapter',

            /*
             * Yii-casbin database setting.
             */
            'database' => [
                // Database connection for following tables.
                'connection' => '',
                // CasbinRule tables and model.
                'casbin_rules_table' => '{{%casbin_rule}}',
            ],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
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
    'defaultRoute' => 'user',
//     'layout' =>false,
];

//if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
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
            'curdCest' => [ // 生成器名称
                'class' => 'app\myTemplates\modelCest\Generator', // 生成器类
                'templates' => [ //配置模版文件
                    'my' => '@app/myTemplates/modelCest/default', // 模版名称 => 模版路径
                ]
            ],
        ],
    ];

//}

return $config;
