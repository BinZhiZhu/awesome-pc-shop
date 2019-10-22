<?php

namespace app\controllers;

use app\enums\RoleTypeEnum;
use app\models\DevUsers;
use app\models\GoodsEntity;
use Yii;
use yii\web\Controller;

class GoodsController extends Controller
{

    public $layout = false;


    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionList()
    {
        return $this->render('list');
    }

    /**
     * 商家发布商品
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     * @throws \Throwable
     */
    public function actionAdd()
    {
        $title = Yii::$app->request->post('title');
        $subtitle = Yii::$app->request->post('subtitle');
        $price = Yii::$app->request->post('price');
        $stock = Yii::$app->request->post('stock');
        $sell_num = Yii::$app->request->post('sell_num');
        $thumb = Yii::$app->request->post('thumb');

        $session = Yii::$app->session;

        $array = $session->get('is_user_id');
//        Yii::debug("缓存中的数据:".var_dump($array),__METHOD__);

        $user_id = $array['value'];

        $title = trim($title);
        $subtitle = trim($subtitle);
        $price = (float)$price;
        $stock = intval($stock);
        $sell_num = intval($sell_num);
        $thumb = trim($thumb);
        $user_id = intval($user_id);

        //商家才能发布商品
        $user = DevUsers::findOne([
            'id' => $user_id,
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

        if ($user->role !== RoleTypeEnum::MERCHANT) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '您没有权限发布商品',
                    'result' => []
                ]
            ]);
        }


        $model = new GoodsEntity();
        $model->created_at = time();
        $model->created_by = $user_id;
        $model->title = $title;
        $model->subtitle = $subtitle;
        $model->price = $price;
        $model->stock = $stock;
        $model->sell_num = $sell_num;
        $model->thumb = $thumb;

        Yii::$app->getDb()->transaction(function () use ($model) {
            $model->save(false);
        });

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => "发布商品【{$model->title}】成功",
                'result' => [
                    'goods' => $model,
                    'user_id' => $user_id
                ]
            ]
        ]);


    }

}