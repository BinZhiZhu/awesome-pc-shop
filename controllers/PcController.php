<?php

namespace app\controllers;

use app\models\AppUsers;
use app\models\DevUsers;
use yii\web\Controller;
use Yii;

class PcController extends Controller
{

    public function actionIndex()
    {
        Yii::$app->view->title = '花卉线上体验店';
        $host = Yii::$app->request->getAbsoluteUrl();//绝对路径
        return $this->render('index', [
            'host' => $host
        ]);
    }

    /**
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionRegister()
    {
        $username = Yii::$app->request->post('username');
        $password = Yii::$app->request->post('password');

        $username =  trim($username);
        $password = trim($password);

        $appUser = AppUsers::findOne([
            'username'=>$username
        ]);

        //先判断用户是否已经存在，如果存在则提示不能注册
        if($appUser){
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'message' => '该账号已存在',
                    'code' => -100,
                ]
            ]);
        }

        // 保存用户
        $user_data = [
            'username' => $username,
            'password' => md5($password),
            'salt' => '',
            'status' => 1,
            'register_ip' => Yii::$app->request->getUserIP(),
            'register_time' => time(),
            'lastvisit_time' => time(),
            'lastvisit_ip' => Yii::$app->request->getUserIP(),
        ];

        $user = new AppUsers();
        $user->attributes = $user_data;
        $user->save(false);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'message' => '注册成功',
                'code' => 100,
            ]
        ]);

    }

}