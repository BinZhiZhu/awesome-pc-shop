<?php

namespace app\controllers;

use app\models\DevUsers;
use app\common\Request;
use yii\web\Controller;
use Exception;
use Yii;

class LoginController extends Controller
{

    public $layout = false;

    public function actionIndex()
    {
        Yii::$app->view->title = '系统登录页面';

        $name = 'xxx';

        return $this->render('index',[
            'name'=>$name
        ]);
    }

    /**
     *  用户登录
     * @return array
     * @throws Exception
     */
    public function actionLogin()
    {

        $username = Yii::$app->request->post('username');
        $password = Yii::$app->request->post('password');

        $username = trim($username);
        $password = trim($password);

        $user = DevUsers::findOne([
            'username' => $username,
            'password' => $password
        ]);

        if (!$user) {
            //  throw new Exception('用户不存在');
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'message' => '用户不存在',
                    'code' => -100,
                ]
            ]);
        }

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'message' => '登录成功',
                'code' => 100,
            ]
        ]);
    }

}