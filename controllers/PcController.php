<?php

namespace app\controllers;

use app\models\AppUsers;
use app\models\BaseFile;
use Exception;
use yii\base\InvalidConfigException;
use yii\web\Controller;
use Yii;

class PcController extends Controller
{

    public function actionIndex()
    {
        Yii::$app->view->title = '花卉线上体验店';
        $host = Yii::$app->request->getAbsoluteUrl();//绝对路径

        return $this->render('index', [
            'host' => $host,
        ]);
    }

    /**
     * 退出登录
     *
     * @return object
     * @throws InvalidConfigException
     */
    public function actionLoginOut()
    {
        $session = Yii::$app->session;

        $session->destroySession('is_app_user_id');

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'message' => '退出成功',
                'code' => 200,
                'result' => []
            ]
        ]);
    }

    /**
     * 获取用户信息
     *
     * @return object
     * @throws InvalidConfigException
     */
    public function actionGetUserInfo()
    {
        $session = Yii::$app->session;
        $userId = $session->get('is_app_user_id');
        $isLogin = false;
        $user = [];
        if ($userId) {
            $user = AppUsers::findOne([
                'id' => $userId
            ]);
            if ($user) {
                $isLogin = true;
            }
        }
        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'message' => '',
                'code' => 200,
                'result' => [
                    'is_login' => $isLogin,
                    'user' => $user
                ]
            ]
        ]);

    }

    /**
     * @return object
     * @throws InvalidConfigException
     */
    public function actionRegister()
    {
        $username = Yii::$app->request->post('username');
        $password = Yii::$app->request->post('password');

        $username = trim($username);
        $password = trim($password);

        $appUser = AppUsers::findOne([
            'username' => $username
        ]);

        //先判断用户是否已经存在，如果存在则提示不能注册
        if ($appUser) {
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

        $username = trim($username);
        $password = trim($password);


        $user = AppUsers::findOne([
            'username' => $username,
        ]);

        if (!$user) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'message' => '用户不存在',
                    'code' => -101,
                ]
            ]);
        }


        $hash_password = Yii::$app->security->generatePasswordHash($password);//加密

        Yii::debug('--hash--' . $hash_password, __METHOD__);

        $justifyPwd = Yii::$app->security->validatePassword($password, $hash_password);//校验

        //有该用户且通过密码校验
        if ($user['password'] === md5($password) && $justifyPwd) {
            AppUsers::updateAll(
                [
                    'lastvisit_ip' => Yii::$app->request->getUserIP(),
                    'lastvisit_time' => time(),
                    'login_count' => $user->login_count + 1,//登录次数+1
                ], [
                'username' => $username,
                'id' => intval($user['id'])
            ]);

            $session['is_app_user_id'] = [
                'value' => $user['id'],
                'expire_time' => time() + 60
            ];
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'message' => '登录成功',
                    'code' => 100,
                ]
            ]);
        } else {
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

    /**
     * 保存用户信息
     *
     * @throws InvalidConfigException
     */
    public function actionEditUserInfo()
    {
        $gender = Yii::$app->request->post('gender');
        $email = Yii::$app->request->post('email');
        $mobile = Yii::$app->request->post('mobile');
        $avatar = Yii::$app->request->post('avatar');
        $address = Yii::$app->request->post('address');
        $user_id = Yii::$app->request->post('user_id');

        $gender = intval($gender);
        $email = trim($email);
        $mobile = trim($mobile);
        $avatar = trim($avatar);
        $address = trim($address);
        $user_id = intval($user_id);


        $user = AppUsers::findOne([
            'id' => $user_id
        ]);

        if (!$user) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'message' => '用户不存在',
                    'code' => -101,
                ]
            ]);
        }


        $user->gender = $gender;
        $user->email = $email;
        $user->mobile = $mobile;
        $user->avatar = $avatar;
        $user->address = $address;
        $user->save(false);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'message' => '保存成功',
                'code' => 200,
                'result' => $user
            ]
        ]);

    }

    /**
     * TODO 抽成独立的组件
     *
     * 上传文件
     *
     * @return object
     * @throws InvalidConfigException
     */
    public function actionUpload()
    {

        $session = Yii::$app->session;
        $userId = $session->get('is_app_user_id');

        $user = AppUsers::findOne([
            'id'=>$userId
        ]);

        if(!$user){
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'message' => '用户不存在',
                    'code' => -100,
                    'result' => []
                ]
            ]);
        }
        $file = $_FILES['file'];

        if ($file['error'] > 0) {
            echo '上传遇到错误,';
            switch ($file['error']) {
                case 1:
                    Yii::error('上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值', __METHOD__);
                    break;
                case 2:
                    Yii::error("上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值", __METHOD__);
                    break;
                case 3:
                    Yii::error("文件只有部分被上传", __METHOD__);
                    break;
                case 4:
                    Yii::error("没有文件被上传", __METHOD__);
                    break;
                case 5:
                    Yii::error("上传文件大小为0", __METHOD__);
                    break;
            }
        } else {
            // 显示上传文件的信息
            Yii::debug("文件名为：'.{$file['name']}", __METHOD__);
            Yii::debug("文件类型为：'.{$file['type']}", __METHOD__);
            Yii::debug("文件大小为：'.{$file['size']}字节", __METHOD__);

            // 设置文件的保存路径
            //如果文件是中文文件名，则需要使用 iconv() 函数将文件名转换为 gbk 编码，否则将会出现乱码
            $base_dir = '/uploads/' . date("Y-m-d") . '/';

            $dir = Yii::getAlias('@root') . '/web/' . $base_dir;

            // 确保有读写权限咯
            if (!is_dir($dir)) {
                Yii::debug("dir路径不是文件夹：" . $dir, __METHOD__);
                @mkdir($dir, 0777, true);
            }

            $destination = $dir . iconv('UTF-8', 'gbk', time() . '_' . basename($file['name']));

            Yii::debug("生成的文件路径：" . $dir, __METHOD__);

            if (is_uploaded_file($file['tmp_name'])) {
                // 将用户上传的文件保存到 upload 目录中
                if (move_uploaded_file($file['tmp_name'], $destination)) {

                    $url = Yii::$app->request->getHostInfo() . $base_dir . $file['name'];

                    $baseFile = new BaseFile();
                    $baseFile->url = trim($url);
                    $baseFile->file_name = trim($file['name']);
                    $baseFile->created_at = time();
                    $baseFile->app_user_id = intval($user->id);
                    $baseFile->save(false);

                    return Yii::createObject([
                        'class' => 'yii\web\Response',
                        'format' => \yii\web\Response::FORMAT_JSON,
                        'data' => [
                            'message' => '上传成功',
                            'code' => 200,
                            'result' => [
                                'url' => $url,
                                'file_name'=>$file['name']
                            ]
                        ]
                    ]);
                } else {
                    Yii::error("Stored failed:file save error", __METHOD__);
                    return Yii::createObject([
                        'class' => 'yii\web\Response',
                        'format' => \yii\web\Response::FORMAT_JSON,
                        'data' => [
                            'message' => '上传失败',
                            'code' => -100,
                            'result' => []
                        ]
                    ]);
                }
            } else {
                Yii::error("Stored failed:no pos. {$file['tmp_name']}", __METHOD__);
                return Yii::createObject([
                    'class' => 'yii\web\Response',
                    'format' => \yii\web\Response::FORMAT_JSON,
                    'data' => [
                        'message' => '上传失败',
                        'code' => -100,
                        'result' => []
                    ]
                ]);
            }

        }

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'message' => '上传失败',
                'code' => -100,
                'result' => []
            ]
        ]);
    }

}