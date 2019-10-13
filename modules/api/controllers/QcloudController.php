<?php

namespace common\modules\api\controllers;

use common\components\Response;
use common\models\QcloudOrderInfo;
use common\models\ShopMember;
use EasyWeChat\Core\Exception;
use Overtrue\EasySms\Exceptions\InvalidArgumentException;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;
use Yii;
use yii\web\Controller;
use Overtrue\EasySms\EasySms;

/**
 * 腾讯云市场自动发货处理
 *
 * @package common\modules\api\controllers
 */
class QcloudController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionIndex()
    {
        global $_GPC;
        $payload = file_get_contents('php://input');
        Yii::debug('腾讯云的数据：' . $payload);
        $payload = json_decode($payload, true);

        Yii::$app->response->format = Response::FORMAT_JSON;
        global $_W;
        $notice_mobile = Yii::$app->params['notice_mobile'];
        $config = [
            // HTTP 请求的超时时间（秒）
            'timeout' => 5.0,

            // 默认发送配置
            'default' => [
                // 网关调用策略，默认：顺序调用
                'strategy' => \Overtrue\EasySms\Strategies\OrderStrategy::class,

                // 默认可用的发送网关
                'gateways' => [
                    'aliyun',
                ],
            ],
            // 可用的网关配置
            'gateways' => [
                'errorlog' => [
                    'file' => Yii::getAlias('@runtime/sms-aliyun.log'),
                ],
                'aliyun' => [
                    'access_key_id' => Yii::$app->params['aliyun_access_key_id'],
                    'access_key_secret' => Yii::$app->params['aliyun_access_key_secret'],
                    'sign_name' => Yii::$app->params['aliyun_sign_name'],
                ]
            ],
        ];
        $easySms = new EasySms($config);
        switch ($payload['action']) {
            case 'verifyInterface':
                return [
                    'echoback' => $payload['echoback'],
                ];
                break;
            case 'createInstance':
                // 实例创建通知接口
                // 根据productId来为用户分配指定的用户组、使用时间等等
                $orderId = $payload['orderId'];
                $openId = $payload['openId'];
                $productId = $payload['productId'];
                $productInfo = $payload['productInfo'];
                if ($productInfo['isTrial'] == 'true') {
                    $product = [
                        'productName' => $productInfo['productName'],
                        'isTrial' => $productInfo['isTrial'],
                        'spec'=>'试用版'
                    ];
                } else {
                    $timeSpan = $productInfo['timeSpan'];
                    $timeUnit = $productInfo['timeUnit'];
                    $product = [
                        'productName' => $productInfo['productName'],
                        'isTrial' => $productInfo['isTrial'],
                        'spec' => $productInfo['spec'],
                        'timeSpan' => $timeSpan,
                        'timeUnit' => $timeUnit
                    ];
                }
                $mobile = $payload['mobile'];
                $email = $payload['email'];
                $groupid = intval($_W['setting']['register']['groupid']);
                if (empty($groupid)) {
                    $groupid = pdo_fetchcolumn('SELECT id FROM ' . tablename('users_group') . ' ORDER BY id ASC LIMIT 1');
                    $groupid = intval($groupid);
                }
                $endtime = strtotime('7 days');
                $starttime = TIMESTAMP;
                $uid = \common\models\User::fetchOne([
                    'openid' => $openId
                ], 'uid');
                Yii::debug($uid,__METHOD__);
                Yii::debug($groupid,__METHOD__);
                if (!empty($uid)) {
                    $userid = $uid;
                } else {
                    $userid = \common\models\User::insertOne([
                        'username' => $openId,
                        'openid' => $openId,
                        'groupid' => $groupid,
                        'password' => '',
                        'starttime' => $starttime,
                        'endtime' => $endtime,
                    ]);
                }
                \common\models\UsersProfile::insertOne([
                    'mobile' => $mobile,
                    'uid' => $userid,
                    'createtime' => TIMESTAMP
                ]);

                $signId = \common\models\QcloudOrderSign::insertOne([
                    'open_id' => $openId,
                    'product_id' => $productId,
                    'mobile' => $mobile,
                    'email' => $email
                ]);
                if (!$signId) {
                    return [
                        'signId' => 0,
                    ];
                }
                \common\models\QcloudOrderInfo::insertOne([
                    'order_id' => $orderId,
                    'sign_id' => $signId,
                    'open_id' => $openId,
                    'product_id' => $productId,
                    'product_istrial' => $productInfo['isTrial'],
                    'product_name' => $productInfo['productName'],
                    'product_spec' => $productInfo['spec'],
                    'product_timespan' => $productInfo['timeSpan'],
                    'product_timeunit' => $productInfo['timeUnit'],
                    'mobile' => $mobile,
                    'email' => $email
                ]);


                $authUrl = \OAuth2Client::create('qcloud', $_W['setting']['thirdlogin']['qcloud']['appid'], $_W['setting']['thirdlogin']['qcloud']['appsecret'])->showLoginUrl();
                try {
                    $easySms->send($mobile, [
                        'template' => 'SMS_142015372',
                        'data' => [
                            'name' => $product['spec']
                        ]
                    ]);
                } catch (InvalidArgumentException $e) {
                } catch (NoGatewayAvailableException $e) {
                }
                try {
                    $easySms->send($notice_mobile, [
                        'template' => 'SMS_142010325',
                        'data' => [
                            'tel' => $mobile,
                            'name' => $product['spec'],
                            'time' => date("Y-m-d H:m:s", time())
                        ]
                    ]);
                } catch (InvalidArgumentException $e) {
                } catch (NoGatewayAvailableException $e) {
                }
                return [
                    'signId' => $signId,
                    'appInfo' => [
                        'website' => 'http://www.jutouit.com'
                    ]
                ];
                break;
            case 'renewInstance':
                // 实例续费通知
                // 根据productId为用户增加时长
                $success = false;
                $orderId = $payload['orderId'];
                $openId = $payload['openId'];
                $productId = $payload['productId'];
                $requestId = $payload['requestId'];
                $signId = $payload['signId'];
                $instanceExpireTime = $payload['instanceExpireTime'];

                \common\models\QcloudOrderInfo::insertOne([
                    'order_id' => $orderId,
                    'sign_id' => $signId,
                    'open_id' => $openId,
                    'product_id' => $productId,
                    'instanceExpireTime' => $instanceExpireTime
                ]);
                $mobile = \common\models\QcloudOrderSign::fetchOne([
                    'open_id' => $openId,
                    'id' => $signId
                ], 'mobile');
Yii::debug($mobile,__METHOD__);


                try {
                    $easySms->send($mobile['mobile'], [
                        'template' => 'SMS_142015372',
                        'data' => [
                            'name' => '续费操作'
                        ]
                    ]);

                    $success  = true;
                } catch (InvalidArgumentException $e) {
                    $success  = false;
                    Yii::debug($e,__METHOD__);
                } catch (NoGatewayAvailableException $e) {
                    $success  = false;
                    Yii::debug($e,__METHOD__);
                }
                try {
                    $easySms->send($notice_mobile, [
                        'template' => 'SMS_142010325',
                        'data' => [
                            'tel' => $mobile['mobile'],
                            'name' => '续费操作',
                            'time' => date("Y-m-d H:m:s", time())
                        ]
                    ]);
                } catch (InvalidArgumentException $e) {
                    $success  = false;
                    Yii::debug($e,__METHOD__);
                } catch (NoGatewayAvailableException $e) {
                    $success  = false;
                    Yii::debug($e,__METHOD__);
                }
                return [
                    'success' => $success
                ];
                break;
            case 'modifyInstance':
                // 实例配置变更
                // 为用户切换权限组
                $success = false;
                $orderId = $payload['orderId'];
                $openId = $payload['openId'];
                $productId = $payload['productId'];
                $requestId = $payload['requestId'];
                $signId = $payload['signId'];
                $spec = $payload['spec'];
                $timeSpan = $payload['timeSpan'];
                $timeUnit = $payload['timeUnit'];
                $instanceExpireTime = $payload['instanceExpireTime'];

                $id = \common\models\QcloudOrderInfo::insertOne([
                    'order_id' => $orderId,
                    'sign_id' => $signId,
                    'open_id' => $openId,
                    'product_id' => $productId,
                    'product_spec' => $spec,
                    'product_timespan' => $timeSpan,
                    'product_timeunit' => $timeUnit,
                ]);
                if ($id) {
                    $success = true;
                }
                $mobile = \common\models\QcloudOrderSign::fetchOne([
                    'open_id' => $openId,
                    'id' => $signId
                ], 'mobile');
                if(empty($mobile['mobile'])){
                    $success = false;
                }
                try{
                    $easySms->send($mobile['mobile'], [
                        'template' => 'SMS_142015372',
                        'data' => [
                            'name' => $spec
                        ]
                    ]);
                    $success  = true;
                }catch (\Exception $e){
                    $success  = false;
                    Yii::debug($e,__METHOD__);
                }
                try {
                    $easySms->send($notice_mobile, [
                        'template' => 'SMS_142010325',
                        'data' => [
                            'tel' => $mobile['mobile'],
                            'name' => '配置变更为' . $spec,
                            'time' => date('Y-m-d H:m:s', time())
                        ]
                    ]);
                } catch (InvalidArgumentException $e) {
                    $success  = false;
                    Yii::debug($e,__METHOD__);
                } catch (NoGatewayAvailableException $e) {
                    $success  = false;
                    Yii::debug($e,__METHOD__);
                }
                return [
                    'success' => $success
                ];
                break;
            case 'expireInstance':
                // 实例过期，去除用户的权限组，公众号要过期
                $success = false;
                $orderId = $payload['orderId'];
                $openId = $payload['openId'];
                $productId = $payload['productId'];
                $requestId = $payload['requestId'];
                $signId = $payload['signId'];
                $id = \common\models\QcloudOrderInfo::insertOne([
                    'order_id' => $orderId,
                    'sign_id' => $signId,
                    'open_id' => $openId,
                    'product_id' => $productId,
                    'expire_time' => date("Y-m-d H:m:s", time())
                ]);
                if ($id) {
                    $success = true;
                }
                $mobile = \common\models\QcloudOrderSign::fetchOne([
                    'id' => $signId
                ], 'mobile');
                $productName = \common\models\QcloudOrderInfo::fetchOne([
                    'sign_id' => $signId,
                    'open_id' => $openId,
                    'mobile' => $mobile
                ], 'product_name');

               try{
                   $easySms->send($notice_mobile, [
                       'template' => 'SMS_142015400',
                       'data' => [
                           'tel' => $mobile['mobile'],
                           'type' => $productName['product_name'] . '过期'
                       ]
                   ]);
                   $success  = true;
               }catch (\Exception $e){
                   $success  = false;
                   Yii::debug($e,__METHOD__);
               }
                return [
                    'success' => $success
                ];
                break;
            case 'destroyInstance':
                // 实例销毁，禁用登录
                $success = false;
                $orderId = $payload['orderId'];
                $openId = $payload['openId'];
                $productId = $payload['productId'];
                $signId = $payload['signId'];
                $id = \common\models\QcloudOrderInfo::insertOne([
                    'order_id' => $orderId,
                    'sign_id' => $signId,
                    'open_id' => $openId,
                    'product_id' => $productId,
                    'destroy_time' => date("Y-m-d H:m:s", time())
                ]);
                if ($id) {
                    $success = true;
                 }
                $mobile = \common\models\QcloudOrderSign::fetchOne([
                    'open_id' => $openId,
                    'id' => $signId
                ], 'mobile');
                $productName = \common\models\QcloudOrderInfo::fetchOne([
                    'sign_id' => $signId,
                    'open_id' => $openId,
                    'mobile' => $mobile
                ], 'product_name');
                \common\models\QcloudOrderSign::deleteAll([
                    'id'=>$signId
                ]);
                try{
                    $easySms->send($notice_mobile, [
                        'template' => 'SMS_142015400',
                        'data' => [
                            'tel' => $mobile['mobile'],
                            'type' => $productName['product_name'] . '已销毁'
                        ]
                    ]);
                    $success  = true;
                }catch (\Exception $e){
                    $success  = false;
                    Yii::debug($e,__METHOD__);
                }
                return [
                    'success' => $success
                ];
                break;
            default:
                return [];
        }
    }

    public function checkSignature($signature, $token, $timestamp, $eventid)
    {
        $currentTimeStamp = time();
        if ($currentTimeStamp - $timestamp > 30) {
            return false;
        }
        $timestamp = (string)$timestamp;
        $eventid = (string)$eventid;
        $params = array($token, $timestamp, $eventid);
        sort($params, SORT_STRING);
        $str = implode('', $params);
        $requestSignature = hash('sha256', $str);
        return $signature === $requestSignature;

    }
}
