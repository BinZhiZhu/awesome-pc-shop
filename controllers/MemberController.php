<?php

namespace app\controllers;

use app\common\Helper;
use app\enums\StatusTypeEnum;
use app\models\AppUsers;
use app\models\GoodsEntity;
use app\models\MemberCartEntity;
use app\models\OrderEntity;
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

        if (!isset($array) || !$array) {
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

        $cart = MemberCartEntity::findOne([
            'member_id' => $member->id,
            'goods_id' => $goods->id,
            'is_deleted'=>StatusTypeEnum::OFF
        ]);

        if ($cart) {
            $cart->total += $total;
        } else {
            $cart = new MemberCartEntity();
            $cart->created_at = time();
            $cart->goods_id = $goods->id;
            $cart->total = $total;
            $cart->member_id = $member->id;
            $cart->market_price = $goods->price;

        }

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

        /** @var MemberCartEntity $item */
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
     * 移除购物车
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionDeleteCart()
    {
        $cart_id = Yii::$app->request->post('cart_id');
        if (!(strpos($cart_id, ',') !== false)) {
            $cart_id = intval($cart_id);
            $cart = MemberCartEntity::findOne([
                'id' => $cart_id
            ]);

            if (!$cart) {
                return Yii::createObject([
                    'class' => 'yii\web\Response',
                    'format' => \yii\web\Response::FORMAT_JSON,
                    'data' => [
                        'code' => -100,
                        'message' => '找不到购物车记录',
                        'result' => []
                    ]
                ]);
            }

            $cart->is_deleted = StatusTypeEnum::ON;
            $cart->save(false);

            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => 200,
                    'message' => '移除成功',
                    'result' => [
                        'cart_id' => $cart->id
                    ]
                ]
            ]);
        } else {
            //多选处理

            $cart_id = explode(',', $cart_id);
            foreach ($cart_id as $item) {
                $cart = MemberCartEntity::findOne([
                    'id' => $item
                ]);

                if (!$cart) {
                    return Yii::createObject([
                        'class' => 'yii\web\Response',
                        'format' => \yii\web\Response::FORMAT_JSON,
                        'data' => [
                            'code' => -100,
                            'message' => '找不到购物车记录',
                            'result' => []
                        ]
                    ]);
                }

                $cart->is_deleted = StatusTypeEnum::ON;
                $cart->save(false);
            }

            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => 200,
                    'message' => '移除成功',
                    'result' => []
                ]
            ]);
        }

    }

    /**
     * 更新购物车
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionUpdateCart()
    {
        $cart_id = Yii::$app->request->post('id');
        $total = Yii::$app->request->post('total');

        $cart_id = intval($cart_id);
        $total = intval($total);
        $cart = MemberCartEntity::findOne([
            'id' => $cart_id
        ]);

        if (!$cart) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -100,
                    'message' => '找不到购物车记录',
                    'result' => []
                ]
            ]);
        }

        $cart->total = $total;
        $cart->save(false);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => '更新成功',
                'result' => $cart
            ]
        ]);
    }


    /**
     *  从购物车结算
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     * @throws \Throwable
     * @package 用户模块
     */
    public function actionPayOrderFromCart()
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

        $cart_id = Yii::$app->request->post('cart_id');

        //单选结算
        if (!(strpos($cart_id, ',') !== false)) {
            $cart_id = intval($cart_id);
            $cart = MemberCartEntity::findOne([
                'id' => $cart_id
            ]);
            if (!$cart) {
                return Yii::createObject([
                    'class' => 'yii\web\Response',
                    'format' => \yii\web\Response::FORMAT_JSON,
                    'data' => [
                        'code' => -100,
                        'message' => '找不到购物车记录',
                        'result' => []
                    ]
                ]);
            }

            // 现在需要根据购物车ID去查关联的商品
            $goods = GoodsEntity::findOne([
                'id' => $cart->goods_id
            ]);

            if (!$goods) {
                return Yii::createObject([
                    'class' => 'yii\web\Response',
                    'format' => \yii\web\Response::FORMAT_JSON,
                    'data' => [
                        'code' => -100,
                        'message' => '找不到商品记录',
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

            //如果购物车库存比实际库存要多的话 那么我们引导他减少购物车库存
            if ($cart->total > $goods->stock) {
                return Yii::createObject([
                    'class' => 'yii\web\Response',
                    'format' => \yii\web\Response::FORMAT_JSON,
                    'data' => [
                        'code' => -100,
                        'message' => "抱歉，商品[{$goods->title}]库存不足,最多只能购买{$goods->stock}件",
                        'result' => []
                    ]
                ]);
            }

            //计算订单价格
            $order_price = number_format($goods->price * intval($cart->total), 2);

            try {
                $order = new OrderEntity();
                $order->member_id = $user_id;
                $order->created_at = time();
                $order->price = (float)$order_price;
                $order->order_sn = Helper::generateOrderSn();
                $order->total = $cart->total;
                $order->goods_id = $goods->id;
                $order->status = StatusTypeEnum::ON;
                $order->merchant_id = $goods->created_by; //把商家改商品的商家的ID记录起来，方便后面查询商家的订单列表

                Yii::$app->getDb()->transaction(function () use ($order, $goods, $cart) {

                    $order->save(false);

                    $goods->stock--;
                    $goods->save();

                    //下单了成功了,然后把当前选中的购物车记录给移除掉

                    $cart->is_deleted = StatusTypeEnum::ON;
                    $cart->save(false);

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

        } else {
            //多选结算
            $cart_id = explode(',', $cart_id);
            // 循环去下单
            foreach ($cart_id as $item) {
                $cart = MemberCartEntity::findOne([
                    'id' => $item
                ]);
                if (!$cart) {
                    return Yii::createObject([
                        'class' => 'yii\web\Response',
                        'format' => \yii\web\Response::FORMAT_JSON,
                        'data' => [
                            'code' => -100,
                            'message' => '找不到购物车记录',
                            'result' => []
                        ]
                    ]);
                }

                // 现在需要根据购物车ID去查关联的商品
                $goods = GoodsEntity::findOne([
                    'id' => $cart->goods_id
                ]);

                if (!$goods) {
                    return Yii::createObject([
                        'class' => 'yii\web\Response',
                        'format' => \yii\web\Response::FORMAT_JSON,
                        'data' => [
                            'code' => -100,
                            'message' => '找不到商品记录',
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

                //如果购物车库存比实际库存要多的话 那么我们引导他减少购物车库存
                if ($cart->total > $goods->stock) {
                    return Yii::createObject([
                        'class' => 'yii\web\Response',
                        'format' => \yii\web\Response::FORMAT_JSON,
                        'data' => [
                            'code' => -100,
                            'message' => "抱歉，商品[{$goods->title}]库存不足,最多只能购买{$goods->stock}件",
                            'result' => []
                        ]
                    ]);
                }

                //计算订单价格
                $order_price = number_format($goods->price * intval($cart->total), 2);

                try {
                    $order = new OrderEntity();
                    $order->member_id = $user_id;
                    $order->created_at = time();
                    $order->price = (float)$order_price;
                    $order->order_sn = Helper::generateOrderSn();
                    $order->total = $cart->total;
                    $order->goods_id = $goods->id;
                    $order->status = StatusTypeEnum::ON;
                    $order->merchant_id = $goods->created_by; //把商家改商品的商家的ID记录起来，方便后面查询商家的订单列表

                    Yii::$app->getDb()->transaction(function () use ($order, $goods, $cart) {

                        $order->save(false);

                        $goods->stock--;
                        $goods->save();

                        //下单了成功了,然后把当前选中的购物车记录给移除掉

                        $cart->is_deleted = StatusTypeEnum::ON;
                        $cart->save(false);

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
            }

            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => 200,
                    'message' => '购买成功，快去我的订单查看吧',
                    'result' => []
                ]
            ]);


        }
    }


}