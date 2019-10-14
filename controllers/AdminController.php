<?php

namespace app\controllers;

use app\models\DevUsers;
use yii\web\Controller;
use Yii;

class AdminController extends Controller
{
    /**
     * @return string
     */
    public function actionIndex()
    {
        if (DevUsers::checkLogin()) {
            Yii::$app->view->title = '后台管理系统';
            $host = Yii::$app->request->getAbsoluteUrl();//绝对路径
            return $this->render('index', [
                'host' => $host
            ]);
        } else {
            Yii::$app->view->title = 'Admin管理系统';
            return $this->redirect('user/index');
        }

    }

}