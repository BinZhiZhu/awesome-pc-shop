<?php

namespace app\controllers;

use yii\web\Controller;
use Yii;

class AdminController extends Controller
{
    public $layout = false;

    public function actionIndex()
    {
        Yii::$app->view->title = '后台管理系统';
        $host = Yii::$app->request->getAbsoluteUrl();//绝对路径
        return $this->render('index',[
            'host'=>$host
        ]);
    }

}