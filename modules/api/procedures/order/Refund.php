<?php

namespace common\modules\api\procedures\order;


use common\components\AppUser;
use common\components\Response;
use common\models\ShopOrder;
use common\modules\api\procedures\ApiException;
use common\modules\api\procedures\BaseAppApi;
use Yii;

class Refund extends BaseAppApi
{
    public function getRefundData($orderid)
    {

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        $orderid = intval($orderid);

        $order = ShopOrder::find()
            ->select('id,status,price,refundid,goodsprice,dispatchprice,deductprice,deductcredit2,finishtime,isverify,virtual,refundstate,merchid')
            ->where([
                'id' => $orderid,
                'uniacid' => $uniacid,
                'openid' => $openid
            ])
            ->asArray()
            ->one();

        if (empty($order)) {
            throw new ApiException(Response::ORDER_NOT_FOUND);
        }

        $_err = '';

        if ($order['status'] == 0) {
            $_err = '订单未付款，不能申请退款!';
        } else {
            if ($order['status'] == 3) {
                if (!empty($order['virtual']) || ($order['isverify'] == 1)) {
                    $_err = '此订单不允许退款!';
                } else {
                    if ($order['refundstate'] == 0) {
                        //申请退款
                        $tradeset = \common\models\ShopSysSet::getByKey('trade');
                        $refunddays = intval($tradeset['refunddays']);

                        if (0 < $refunddays) {
                            $days = intval((time() - $order['finishtime']) / 3600 / 24);

                            if ($refunddays < $days) {
                                $_err = '订单完成已超过 ' . $refunddays . ' 天, 无法发起退款申请!';
                            }
                        } else {
                            $_err = '订单完成, 无法申请退款!';
                        }
                    }
                }
            }
        }

        if (!empty($_err)) {
            throw new ApiException(Response::ORDER_CAN_NOT_REFUND, $_err);
        }

        //订单不能退货商品
        $order['cannotrefund'] = false;

        if ($order['status'] == 2) {
            $goods = pdo_fetchall('select og.goodsid, og.price, og.total, og.optionname, g.cannotrefund, g.thumb, g.title from ' . \common\models\ShopOrderGoods::tableName() . ' og left join ' . tablename('new_shop_goods') . ' g on g.id=og.goodsid where og.orderid=' . $order['id']);

            if (!empty($goods)) {
                foreach ($goods as $g) {
                    if ($g['cannotrefund'] == 1) {
                        $order['cannotrefund'] = true;
                        break;
                    }
                }
            }
        }

        if ($order['cannotrefund']) {
            throw new ApiException(Response::ORDER_CAN_NOT_REFUND, '此订单不可退换货');
        }

        //应该退的钱 在线支付的+积分抵扣的+余额抵扣的(运费包含在在线支付或余额里）
        $order['refundprice'] = $order['price'] + $order['deductcredit2'];
        if ($order['status'] >= 2) {
            //如果发货，扣除运费
            $order['refundprice'] -= $order['dispatchprice'];
        }

        $order['refundprice'] = round($order['refundprice'], 2);

        return [
            'uniacid' => $uniacid,
            'openid' => $openid,
            'orderid' => $orderid,
            'order' => $order,
            'refundid' => $order['refundid']
        ];

    }

    public function submit_refund($orderid = 0, $price = '', $rtype = 0, $images = null, $content = '', $reason = '', $token = '')
    {
        //兼容旧接口,以后删除
        return $this->apiRefundOrder($orderid, $price, $rtype, $images, $content, $reason, $token);
    }

    public function apiRefundOrder($orderid = 0, $price = '', $rtype = 0, $images = null, $content = '', $reason = '', $token = '')
    {

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        $refund_data = $this->getRefundData($orderid);
        $order = $refund_data['order'];
        $refundid = $refund_data['refundid'];

        if ($order['status'] == '-1') {
            throw new ApiException(Response::ORDER_CAN_NOT_REFUND, '订单已经处理完毕');
        }

        $reason = empty($reason) ? '其他' : $reason;

        if ($rtype != 2) {
            if (empty($price)) {
                $price = $order['refundprice'];
            }
            if (empty($price) && ($order['deductprice'] == 0)) {
                throw new ApiException(Response::ORDER_CAN_NOT_REFUND, '退款金额不能为0元');
            }

            if ($order['refundprice'] < $price) {
                throw new ApiException(Response::ORDER_CAN_NOT_REFUND, '退款金额不能超过' . $order['refundprice'] . '元');
            }
        }


        if (is_string($images)) {
            $images = htmlspecialchars_decode(str_replace('\\', '', $images));
            $images = @json_decode($images, true);
        }

        $refund = [
            'uniacid' => $uniacid,
            'merchid' => $order['merchid'],
            'applyprice' => $price,
            'rtype' => $rtype,
            'reason' => $reason,
            'content' => $content,
            'imgs' => iserializer($images)
        ];

        if ($refund['rtype'] == 2) {
            $refundstate = 2;
        } else {
            $refundstate = 1;
        }

        if ($order['refundstate'] == 0) {
            //新建一条退款申请
            $refund['createtime'] = time();
            $refund['orderid'] = $orderid;
            $refund['orderprice'] = $order['refundprice'];
            $refund['refundno'] = \common\Helper::createNO('order_refund', 'refundno', 'SR');
            $refund['refundtime'] = time();
            $refundid = pdo_insert_get_id('new_shop_order_refund', $refund);
            ShopOrder::updateAll(array('refundid' => $refundid, 'refundstate' => $refundstate), array('id' => $orderid, 'uniacid' => $uniacid));
        } else {
            ShopOrder::updateAll(array('refundstate' => $refundstate), array('id' => $orderid, 'uniacid' => $uniacid));
            \common\models\ShopOrderRefund::updateAll($refund, array('id' => $refundid, 'uniacid' => $uniacid));
        }
        //模板消息
        m('notice')->sendOrderMessage($orderid, true);
        return [
            'success' => 1,
            'success_string' => Yii::t('success_string', '操作成功'),
        ];
    }

}