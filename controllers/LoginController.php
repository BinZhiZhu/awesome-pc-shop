<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;

class LoginController extends Controller
{
    public $layout = 'login';


    public function actionIndex()
    {
        Yii::$app->view->title = '系统登录页面';

        $name = 'xxx';

        return $this->render('index',[
            'name'=>$name
        ]);
    }

    public function actionLogin()
    {

        $username = Yii::$app->request->post('username');
        $password = Yii::$app->request->post('password');

        Yii::debug('xx'.$username,__METHOD__);
    }

}