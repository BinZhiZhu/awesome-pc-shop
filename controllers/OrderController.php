<?php

namespace app\controllers;

use app\common\Helper;
use app\enums\RoleTypeEnum;
use app\enums\StatusTypeEnum;
use app\models\AppUsers;
use app\models\DevUsers;
use app\models\GoodsEntity;
use app\models\OrderEntity;
use Yii;
use yii\web\Controller;

/**
 * 订单相关控制器
 *
 * @package 订单模块
 */
class OrderController extends Controller
{
    public $layout = false;


    /**
     * 渲染管理员订单列表
     *
     * @return string
     */
    public function actionList()
    {
        return $this->render('list');
    }

    /**
     * 渲染商家订单列表
     *
     * @return string
     */
    public function actionMerchant()
    {
        return $this->render('merchant');
    }

    /**
     * 创建订单接口
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     * @throws \Throwable
     */
    public function actionCreateOrder()
    {
        $goods_id = Yii::$app->request->post('goods_id');
        $total = Yii::$app->request->post('total');

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

        //判断一下这个用户是否有收获地址
        if (!$member->address) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -100,
                    'message' => '请先添加用户地址',
                    'result' => []
                ]
            ]);
        }

        $goods = GoodsEntity::findOne([
            'id' => intval($goods_id)
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

        //先校验库存

        if ($goods->stock <= 0) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -100,
                    'message' => '抱歉，库存不足',
                    'result' => []
                ]
            ]);
        }

        //计算订单价格
        $order_price = number_format($goods->price * intval($total), 2);


        try {
            $order = new OrderEntity();
            $order->member_id = $user_id;
            $order->created_at = time();
            $order->price = (float)$order_price;
            $order->order_sn =  Helper::generateOrderSn();
            $order->total = $total;
            $order->goods_id = $goods_id;
            $order->status = StatusTypeEnum::ON;
            $order->merchant_id = $goods->created_by; //把商家改商品的商家的ID记录起来，方便后面查询商家的订单列表

            Yii::$app->getDb()->transaction(function () use ($order, $goods) {

                $order->save(false);

                $goods->stock--;
                $goods->save();

            });

        } catch (\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -100,
                    'message' => '购买失败',
                    'result' => []
                ]
            ]);
        }

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => '购买成功，快去我的订单查看吧',
                'result' => [
                    'order' => $order
                ]
            ]
        ]);
    }

    /**
     * 获取前台订单列表
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionGetOrderList()
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

        $list = OrderEntity::find()
            ->where([
                'status' => StatusTypeEnum::ON,
                'member_id' => $user_id,
                'is_deleted' => StatusTypeEnum::OFF
            ])
            ->orderBy(['id' => SORT_DESC])
            ->all();


        /** @var OrderEntity $item */
        foreach ($list as &$item) {
            $item = $item->getApiArray();
        }
        unset($item);

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
     * 获取后台订单列表
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionGetBackendOrderList()
    {

        $session = Yii::$app->session;
        $array = $session['is_user_id'];
        $user_id = $array['value'];

        $member = DevUsers::findOne([
            'id' => $user_id,
            'role' => RoleTypeEnum::ADMIN
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

        $list = OrderEntity::find()
            ->where([
                'status' => StatusTypeEnum::ON,
                'is_deleted' => StatusTypeEnum::OFF
            ])
            ->orderBy(['id' => SORT_DESC])
            ->all();


        /** @var OrderEntity $item */
        foreach ($list as &$item) {
            $item = $item->getApiArray();
        }
        unset($item);

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
     * 后台删除订单
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionDeleteOrder()
    {
        $order_id = Yii::$app->request->post('id');
        $order_id = intval($order_id);

        $session = Yii::$app->session;
        $array = $session['is_user_id'];
        $user_id = $array['value'];

        $member = DevUsers::findOne([
            'id' => $user_id,
//            'role' => RoleTypeEnum::ADMIN
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

        $order = OrderEntity::findOne([
            'id' => $order_id
        ]);

        if (!$order) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -100,
                    'message' => '订单不存在',
                    'result' => []
                ]
            ]);
        }

        $order->updated_by = intval($user_id);
        $order->is_deleted = StatusTypeEnum::ON;
        $order->save(false);

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
     * 获取商家的订单列表
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionGetMerchantOrderList()
    {
        $session = Yii::$app->session;
        $array = $session['is_user_id'];
        $user_id = $array['value'];

        //确保后台用户是商家身份
        $member = DevUsers::findOne([
            'id' => $user_id,
            'role' => RoleTypeEnum::MERCHANT
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

        $list = OrderEntity::find()
            ->where([
                'status' => StatusTypeEnum::ON,
                'is_deleted' => StatusTypeEnum::OFF
            ])
            ->andWhere([
                'merchant_id' => $user_id
            ])
            ->orderBy(['id' => SORT_DESC])
            ->all();


        /** @var OrderEntity $item */
        foreach ($list as &$item) {
            $item = $item->getApiArray();
        }
        unset($item);

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

}