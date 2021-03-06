<?php

namespace app\controllers;

use app\models\DevUsers;
use Yii;
use yii\web\Controller;

class MainController extends Controller
{

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        // 返回用户信息
        $session = Yii::$app->session;
        $userId = $session['is_user_id'];
        $user = DevUsers::findOne([
            'id'=>$userId
        ]);

        Yii::debug("当前用户信息".var_export($user->toArray(),true),__METHOD__);

        //TODO 找不到先跳登录页
        if(!$user){
            Yii::$app->view->title = 'Admin管理系统';
            return $this->redirect('user/index');
        }

        return $this->render('index', [
            'user'=>$user->toArray()
        ]);
    }

}
