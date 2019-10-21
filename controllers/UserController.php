<?php

namespace app\controllers;

use app\models\DevUsers;
use yii\helpers\Url;
use yii\web\Controller;
use Exception;
use Yii;

class UserController extends Controller
{

    public $layout = false;

    public function actionIndex()
    {

        Yii::$app->view->title = 'Admin管理系统';
        $host = Yii::$app->request->getAbsoluteUrl();//绝对路径
        return $this->render('index', [
            'host' => $host
        ]);
    }

    public function actionLoginOut()
    {
        //统一清除session
       $session = Yii::$app->session;

       $session->destroySession('is_user_id');

       return $this->redirect(Url::toRoute('user/index'));
    }

    /**
     *  用户登录
     *
     * @return object
     * @throws Exception
     */
    public function actionLogin()
    {

        $session = Yii::$app->session;

        $username = Yii::$app->request->post('username');
        $password = Yii::$app->request->post('password');
        $role = Yii::$app->request->post('role');

        $username = trim($username);
        $password = trim($password);
        $role = intval($role);

        $hash_password = Yii::$app->security->generatePasswordHash($password);//加密

        Yii::debug('--hash--' . $hash_password, __METHOD__);

        $justifyPwd = Yii::$app->security->validatePassword($password, $hash_password);//校验


        $user = DevUsers::findOne([
            'username' => $username,
            'role' => $role
        ]);

        if (!$user) {
            Yii::debug("用户不存在",__METHOD__);
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'message' => '账号不存在，请先注册',
                    'code' => -102,
                ]
            ]);
        }else{
            Yii::debug("用户存在:".var_export($user->toArray(),true),__METHOD__);
            //有该用户且通过密码校验
            if ($user && $user['password'] === md5($password) && $justifyPwd) {
                DevUsers::updateAll(
                    [
                        'lastvisit_ip' => Yii::$app->request->getUserIP(),
                        'lastvisit_time' => time(),
                        'login_count' => $user->login_count + 1,//登录次数+1
                    ], [
                    'username' => $username,
                    'id' => intval($user['id']),
                    'role' => $role
                ]);

                // 登录成功后操作
                $session['is_user_id'] = [
                    'value' => $user['id'],
                    'expire_time' => time() + 60
                ];

                return Yii::createObject([
                    'class' => 'yii\web\Response',
                    'format' => \yii\web\Response::FORMAT_JSON,
                    'data' => [
                        'message' => '登录成功，欢迎回来~',
                        'code' => 100,
                    ]
                ]);
            } else if($user && $user['password'] !== md5($password)) {
                //有该用户但是密码没有通过验证
                return Yii::createObject([
                    'class' => 'yii\web\Response',
                    'format' => \yii\web\Response::FORMAT_JSON,
                    'data' => [
                        'message' => '密码错误',
                        'code' => -101,
                    ]
                ]);
            }

        }

    }


    /**
     * 注册用户
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionRegister()
    {
        $username = Yii::$app->request->post('username');
        $password = Yii::$app->request->post('password');
        $role = Yii::$app->request->post('role');

        $username = trim($username);
        $password = trim($password);
        $role = intval($role);

        $user = DevUsers::findOne([
            'username' => $username,
            'role' => $role
        ]);

        if ($user) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'message' => '改账号已注册，请重新填写',
                    'code' => -100,
                ]
            ]);
        }

        $user_data = [
            'username' => $username,
            'password' => md5($password),
            'salt' => '',
            'status' => 1,
            'register_ip' => Yii::$app->request->getUserIP(),
            'register_time' => time(),
            'lastvisit_time' => time(),
            'lastvisit_ip' => Yii::$app->request->getUserIP(),
            'role' => $role
        ];


        $user = new DevUsers();
        $user->attributes = $user_data;
        $user->save(false);


        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'message' => '注册成功，马上登录后台吧',
                'code' => 100,
            ]
        ]);
    }

    /**
     * 产生随机令牌
     *
     * @return string
     */
    protected function getToken()
    {
        $n = 'qwertyuioplkjhgfdsazxcvbnm+=-1234567890QWERTYUIOPASDFGHJKLZXCVBNM';
        $token = '';
        for ($i = 0; $i < 30; $i++) {
            $token .= $n[mt_rand(0, strlen($n) - 1)];
        }
        //每次登陆
        return $token;
    }

}