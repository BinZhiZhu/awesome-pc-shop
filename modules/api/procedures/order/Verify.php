<?php
namespace common\modules\api\procedures\order;


use common\components\AppUser;
use common\modules\api\procedures\BaseAppApi;
use Yii;

class Verify extends BaseAppApi{

    /**
     * @param $token
     * @param $verifycode
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     * @throws \yii\db\Exception
     * @throws \Exception
     */
    public function apiSearchVerifyCode($token,$verifycode){
        global $_W;

        $verifycode = intval($verifycode);

        if (empty($verifycode)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR);
        }

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::USER_NOT_LOGIN);
        }

        $orderid = pdo_fetchcolumn('select id from ' . tablename('new_shop_order') . ' where uniacid=:uniacid and ( verifycode=:verifycode or verifycodes like :verifycodes ) limit 1 ', array(':uniacid' => $uniacid, ':verifycode' => $verifycode, ':verifycodes' => '%|' . $verifycode . '|%'));
        Yii::debug($orderid, __METHOD__);

        if (empty($orderid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ORDER_NOT_FOUND);
        }

        $allow = com('verify')->allow($orderid);
        if (is_error($allow)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::SYSTEM_ERROR,$allow['message']);
        }

        extract($allow);

        $verifyinfo = iunserializer($order['verifyinfo']);
        if ($order['verifytype'] == 2) {
            foreach ($verifyinfo as &$v) {
                unset($v['select']);

                if ($v['verifycode'] == $verifycode) {
                    if ($v['verified']) {
                        throw new \common\modules\api\procedures\ApiException(\common\components\Response::SYSTEM_ERROR,'此消费码已经使用');
                    }

                    $v['select'] = 1;
                }
            }

            unset($v);
            \common\models\ShopOrder::updateAll(['verifyinfo' => iserializer($verifyinfo)], ['id' => $orderid]);
        }
        if (!empty($order['merchid'])) {
            $merch_user = p('merch')->getListUser($order['merchid']);
            $order['shopname'] = $merch_user['merchname'];
            $order['shoplogo']= tomedia($merch_user['logo']);
        }
        foreach ($allgoods as $k=>$v){
            $allgoods[$k]['thumb']= tomedia($v['thumb']);
        }

        $order['carrier'] = $carrier;
        $order['goods'] = $allgoods;

//        var_dump($allow);die;
        return ['order' => $order, 'istrade' => intval($order['istrade'])];
    }

    /**
     * @param $token
     * @param $orderid
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     * @throws \Exception
     */
    public function apiVerifyOrder($token,$orderid){
        global $_W;

        $orderid = intval($orderid);

        if (empty($orderid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR);
        }

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::USER_NOT_LOGIN);
        }

        $res = com('verify')->verify($orderid);

        return [
            'success'=>1,
            'success_string' => Yii::t('success_string','操作成功'),
        ];
    }


}