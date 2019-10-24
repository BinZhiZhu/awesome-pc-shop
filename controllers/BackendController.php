<?php

namespace app\controllers;

use app\enums\GenderTypeEnum;
use app\enums\RoleTypeEnum;
use app\enums\StatusTypeEnum;
use app\models\AppUsers;
use app\models\DevUsers;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\Controller;

/**
 * Class BackendUserController
 * @package app\controllers
 */
class BackendController extends Controller
{

    public function actionIndex()
    {
        return $this->render('list');
    }


    public function actionPc()
    {
        return $this->render('pc');
    }

    public function actionGoodsList()
    {
        return $this->render('goods-list');
    }


    /**
     * 获取后台用户列表
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionGetUserList()
    {
        $users = DevUsers::find()
            ->where([
                'status' => StatusTypeEnum::ON,
                'is_deleted'=>StatusTypeEnum::OFF
            ])
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();

        foreach ($users as &$user) {
            $user['register_time'] = date("Y:m:d H:i", $user['register_time']);
            $user['lastvisit_time'] = date("Y:m:d H:i", $user['lastvisit_time']);
            $user['host_info'] = Yii::$app->request->getHostInfo();
            $user['role_name'] = ArrayHelper::getValue(RoleTypeEnum::$list, intval($user['role']));
        }

        unset($user);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'result' => [
                    'list'=>$users,
                    'total'=>count($users)
                ]
            ]
        ]);
    }



    /**
     * 获取前台用户
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionGetAppUserList()
    {
        $users = AppUsers::find()
            ->where([
                'status' => StatusTypeEnum::ON,
                'is_deleted'=>StatusTypeEnum::OFF
            ])
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();

        foreach ($users as &$user) {
            $user['register_time'] = date("Y:m:d H:i", $user['register_time']);
            $user['lastvisit_time'] = date("Y:m:d H:i", $user['lastvisit_time']);
            $user['host_info'] = Yii::$app->request->getHostInfo();
            $user['gender'] = ArrayHelper::getValue(GenderTypeEnum::$list,$user['gender']);
        }

        unset($user);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'result' => [
                    'list'=>$users,
                    'total'=>count($users)
                ]
            ]
        ]);
    }

    /**
     * 删除后台用户
     *
     * @return object
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\StaleObjectException
     */
    public function actionDeleteUser()
    {
        $id = Yii::$app->request->post('id');

        $user = DevUsers::findOne([
            'id' => intval($id)
        ]);

        if (!$user) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '用户不存在',
                    'result' => []
                ]
            ]);
        }

        $user->is_deleted = StatusTypeEnum::ON;
        $user->save(false);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => '删除成功',
                'result' => []
            ]
        ]);
    }


    /**
     * 删除后台用户
     *
     * @return object
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\StaleObjectException
     */
    public function actionDeleteAppUser()
    {
        $id = Yii::$app->request->post('id');

        $user = AppUsers::findOne([
            'id' => intval($id)
        ]);

        if (!$user) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '用户不存在',
                    'result' => []
                ]
            ]);
        }

        $user->is_deleted = StatusTypeEnum::ON;
        $user->save(false);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => '删除成功',
                'result' => []
            ]
        ]);
    }
}