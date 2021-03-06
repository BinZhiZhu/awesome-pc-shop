<?php

namespace app\controllers;

use app\enums\RoleTypeEnum;
use app\models\DevUsers;
use yii\helpers\Url;
use yii\web\Controller;
use Yii;

/**
 * Class AdminController
 *
 * @package app\controllers
 */
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
            // 返回用户信息
            $session = Yii::$app->session;
            $userId = $session['is_user_id'];
            $user = DevUsers::findOne([
                'id'=>$userId
            ]);

            $user = $user->toArray();
            $user['role_name'] = $user['role'] == RoleTypeEnum::ADMIN ? '管理员' :'商家';
            $user['visit_time'] = date("Y-m-d H:i:s",$user['lastvisit_time']);
            $user['register_time'] =date("Y-m-d H:i:s",$user['register_time']);
            $user['host_info'] = Yii::$app->request->getHostInfo();

            //TODO 找不到先跳登录页
            if(!$user){
                Yii::$app->view->title = 'Admin管理系统';
                return Url::to('user/index');
            }

            Yii::debug("当前用户信息".var_export($user,true),__METHOD__);

            return $this->render('index', [
                'host' => $host,
                'user'=>$user
            ]);
        } else {
            Yii::$app->view->title = 'Admin管理系统';
            return Url::to('user/index');
        }

    }
}