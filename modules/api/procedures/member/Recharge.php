<?php

namespace common\modules\api\procedures\member;

use AmsClient;
use common\components\AppUser;
use common\components\Response;
use common\modules\api\procedures\ApiException;
use common\modules\api\procedures\BaseAppApi;
use Exception;
use Yii;

class Recharge extends BaseAppApi
{

    /**
     * @param int    $money
     * @param string $type
     * @param int    $couponid
     * @param string $token
     *
     * @return array
     * @throws ApiException
     * @throws \Exception
     */
    public function apiRechargeBalance($money = 0,$type = '',$couponid = 0,$token = '',$bank_id = 0,$account_email = '',$second_pwd = ''){

        global $_W;

        $money = round($money, 2);

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        $this_member = AppUser::getInstance()->identity->toArray();

        $set = $_W['shopset'];

        if($set['trade']['second_pwd'] == 1){
            $second_pwd = intval($second_pwd);
            if($second_pwd != $this_member['second_pwd']){
                throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR,Yii::t('shop_o2o_page_string','支付密码错误'));
            }
        }

        if (empty($set['trade']['minimumcharge'])) {
            $minimumcharge = 0;
        } else {
            $minimumcharge = $set['trade']['minimumcharge'];
        }


        if ($money <= 0) {
            throw new ApiException(Response::MEMBER_RECHARGE_ERROR, '充值金额必须大于0!');
        }

        if (($money < $minimumcharge) && (0 < $minimumcharge)) {
            throw new ApiException(Response::MEMBER_RECHARGE_ERROR, '最低充值金额为' . $minimumcharge . '元!');
        }

        if (empty($money)) {
            throw new ApiException(Response::MEMBER_RECHARGE_ERROR, '请填写充值金额!');
        }

        \common\models\ShopMemberLog::deleteAll([
            'openid' => $openid,
            'status' => 0,
            'type' => 0,
            'uniacid' => $uniacid
        ]);
        $logno = \common\Helper::createNO('member_log', 'logno', 'RC');
        // 日志
        $log = [
            'uniacid' => $uniacid,
            'logno' => $logno,
            'title' => $set['shop']['name'] . '会员充值',
            'openid' => $openid,
            'money' => $money,
            'type' => 0,
            'createtime' => time(),
            'status' => 0,
            'couponid' => $couponid,
            'pay_chanel' => 0
        ];
        $logid = \common\models\ShopMemberLog::insertOne($log);

        //参数
        $set = m('common')->getSysset(array('shop', 'pay'));
        if ($type == 'wechat') {
            //如果开启微信支付
            $params = array();
            $params['tid'] = $log['logno'];
            $params['fee'] = $money;
            $params['title'] = $log['title'];

            $wechat = array('success' => false);

            if(!empty($set['pay']['joinpay_wxapp'])&& \common\components\Request::getInstance()->isWxApp) {
                $tid = $params['tid'];
                $payinfo = [
                    'openid' => $_W['openid_wa'],
                    'title' => $set['shop']['name'] . '订单',
                    'tid' => $tid,
                    'fee' => $money,
                ];
                $res = \common\modules\wxapp\Module::getJoinPayWxappPayData($payinfo, 15);

                //使用汇聚支付通道
                \common\models\ShopMemberLog::updateAll(
                    array('pay_chanel' => 1),
                    array('logno' => $log['logno'])
                );

                if (!(is_error($res))) {
                    $wechat = array('success' => true, 'payinfo' => $res);
                } else {
                    $wechat['payinfo'] = $res;
                }
            }
            elseif (!empty($set['pay']['wxapp']) && \common\components\Request::getInstance()->isWxApp) {
                $payinfo = array(
                    'openid' => $_W['openid_wa'],
                    'title' => $log['title'],
                    'tid' => $params['tid'],
                    'fee' => $money
                );
                $res = \common\modules\wxapp\Module::getWxappPayData($payinfo, 15);

                if (!(is_error($res))) {
                    $wechat = array('success' => true, 'payinfo' => $res);
                } else {
                    $wechat['payinfo'] = $res;
                }
            }
            else {
                throw new ApiException(Response::MEMBER_RECHARGE_ERROR, '未开启微信支付!');
            }

            if (!($wechat['success'])) {
                throw new ApiException(Response::MEMBER_RECHARGE_ERROR, '微信支付参数错误!');
            }
            return ['wechat' => $wechat, 'logid' => $logid];

        } else if ($type == 'alipay') {
            $sec = m('common')->getSec();
            $sec = iunserializer($sec['sec']);
            $alipay_config = $sec['nativeapp']['alipay'];

            $alipay = array('success' => false);

            if (!empty($set['pay']['nativeapp_alipay']) && !\common\components\Request::getInstance()->isWxApp) {
                $params = array(
                    'out_trade_no' => $log['logno'],
                    'total_amount' => $money,
                    'subject' => $log['title'],
                    'body' => \common\components\Request::getInstance()->uniacid . ':1:NATIVEAPP'
                );

                if (!(empty($alipay_config))) {
                    $alipay = p('app')->alipay_build($params, $alipay_config);
                }

            } else {
                throw new ApiException(Response::MEMBER_RECHARGE_ERROR, '未开启支付宝支付!');
            }

            return ['alipay' => $alipay, 'logid' => $logid];
        } else if($type == 'payfirma'){

            if (empty($set['pay']['app_payfirma'])) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '未开启payfirma支付');
            }

            $bank = \common\models\BankCard::fetchOne(['user_id'=>$this_member['id'],'id'=>$bank_id,'uniacid'=>$uniacid]);

            if(empty($bank)){
                throw new ApiException(Response::ORDER_PAY_FAIL, '找不到该银行卡');
            }



            $ams_param = [
                'amount'=>$money,
                'out_trade_no'=>$log['logno'],
                'user_id'=>$this_member['id'],
                'fee_type'=>'CAD',//加拿大
                'card_number'=>$bank['number'],
                'cvv2'=>$bank['cvv2'],
                'card_expiry_year'=>substr($bank['expiry_year'],2) . '',
                'card_expiry_month'=>str_pad($bank['expiry_month'],2,"0",STR_PAD_LEFT) . '',
            ];

            //todo 先直接支付成功

//            try {
//                $ams_result =  AmsClient::call('tradeCreditPay',$ams_param);
//            } catch (Exception $e) {
//                throw new ApiException(Response::ORDER_PAY_FAIL);
//            }
//
//            Yii::info('payfirma支付,请求参数:'. json_encode($ams_param) . '返回参数:' . json_encode($ams_result));
            //todo 先直接支付成功
            $ams_result['status'] = 1;

            if($ams_result['status'] == 1){
                $log = \common\models\ShopMemberLog::fetchOne(['id' => $logid, 'uniacid' => $uniacid]);
                if(!empty($account_email)){
                    //代充
                    $account = \common\models\ShopMember::fetchOne(['openid'=>$account_email,'uniacid'=>$uniacid]);
                    if(empty($account)){
                        throw new ApiException(Response::ORDER_PAY_FAIL, '找不到该用户');
                    }
                    //代充减钱
                    $_logno = \common\Helper::createNO('member_log', 'logno', 'RC');
                    $_log = [
                        'uniacid' => $uniacid,
                        'logno' => $logno,
                        'title' => $set['shop']['name'] . '会员充值',
                        'openid' => $openid,
                        'money' => '-' . $money,
                        'type' => 0,
                        'createtime' => time(),
                        'status' => 1,
                        'rechargetype' => 'payfirma',
                        'apppay' => 1,
                        'couponid' => $couponid,
                        'recharge_openid' => $account_email
                    ];
                    \common\models\ShopMemberLog::insertOne($_log);

                    //用户加钱
                    $openid = $account_email;
                    $update_data = ['status' => 1, 'rechargetype' => 'payfirma', 'apppay' => 1 ,'openid'=>$openid];
                    $log_title = "uid【{$this_member['id']}】代充值:payfirma:credit2:" . $log['money'];
                }else{
                    $openid = $log['openid'];
                    $update_data = ['status' => 1, 'rechargetype' => 'payfirma', 'apppay' => 1];
                    $log_title = '会员充值:payfirma:credit2:' . $log['money'];
                }
                \common\models\ShopMemberLog::updateAll($update_data, array('id' => $log['id']));
                m('member')->setCredit($openid, 'credit2', $log['money'], array(0, $log_title));
                \common\helpers\Event::emit(EVENT_MEMBER_RECHARGE_SUCCESS, [
                    'log' => $log,
                ]);
                return ['payfirma' => 1,'logid' => $logid,'success_string' => Yii::t('success_string','操作成功')];

            }else{
                throw new ApiException(Response::ORDER_PAY_FAIL, $ams_result['msg']);
            }

        }


        throw new \common\modules\api\procedures\ApiException(\common\components\Response::MEMBER_RECHARGE_ERROR, '未找到支付方式');
    }




}