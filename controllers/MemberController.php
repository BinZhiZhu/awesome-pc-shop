<?php

namespace app\controllers;

use app\enums\StatusTypeEnum;
use app\models\AppUsers;
use app\models\GoodsEntity;
use app\models\MemberCartEntity;
use Yii;
use yii\web\Controller;

/**
 * Class MemberController
 * @package app\controllers
 */
class MemberController extends Controller
{
    public $layout = false;

    /**
     * 加入购物车
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     * @throws \Throwable
     */
    public function actionAddCart()
    {
        $goods_id = Yii::$app->request->post('goods_id');
        $total = Yii::$app->request->post('total');

        $goods_id = intval($goods_id);
        $total = intval($total);

        $session = Yii::$app->session;
        $array = $session['is_app_user_id'];
        $user_id = $array['value'];

        $member = AppUsers::findOne([
            'id' => $user_id
        ]);

        if (!$member) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -100,
                    'message' => '用户不存在',
                    'result' => []
                ]
            ]);
        }

        $goods = GoodsEntity::findOne([
            'id' => $goods_id
        ]);


        if (!$goods) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -100,
                    'message' => '商品不存在',
                    'result' => []
                ]
            ]);
        }

        $cart = new MemberCartEntity();
        $cart->created_at = time();
        $cart->goods_id = $goods->id;
        $cart->total = $total;
        $cart->member_id = $member->id;
        $cart->market_price = $goods->price;

        Yii::$app->getDb()->transaction(function () use ($cart) {
            $cart->save(false);

        });

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => '加入成功',
                'result' => [
                    'cart_id' => $cart->id
                ]
            ]
        ]);
    }

    /**
     * 查看我的购物车列表
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionMyCartList()
    {
        $session = Yii::$app->session;
        $array = $session['is_app_user_id'];
        $user_id = $array['value'];


        $member = AppUsers::findOne([
            'id' => $user_id
        ]);

        if (!$member) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -100,
                    'message' => '用户不存在',
                    'result' => []
                ]
            ]);
        }

        $list = MemberCartEntity::find()
            ->where([
                'is_deleted' => StatusTypeEnum::OFF,
                'member_id' => $user_id
            ])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'result' => [
                    'list' => $list,
                    'total' => count($list)
                ]
            ]
        ]);
    }

    /**
     * TODO 移除购物车
     */
    public function actionDeletedCart()
    {
      $cart_id = Yii::$app->request->post('cart_id');
      $cart_id = intval($cart_id);

    }

}