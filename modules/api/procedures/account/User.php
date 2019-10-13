<?php

namespace common\modules\api\procedures\account;

use common\components\AppUser;
use common\models\ShopMember;
use common\modules\api\procedures\BaseAppApi;
use Yii;

class User extends BaseAppApi
{


    /**
     * @category 用户相关
     *
     * @param $mobile
     * @param $pwd
     * @param $verifycode
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     * @throws \Exception
     *
     *@resultKey int id 用户ID
     * @resultKey string mobile 手机号码
     * @resultKey string nickname 名称
     * @resultKey string avatar 头像
     * @resultKey string openid openid
     * @resultKey string token token
     */
    public function apiRegisterMemberByMobileCode($mobile, $pwd, $verifycode, $captcha_debug = "")
    {
        global $_W;
        if (empty($mobile) || empty($pwd) || empty($verifycode)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::PARAMS_ERROR
            );
        }

        // 检查验证码
        Yii::$app->session->open();
        $key = \common\helpers\Captcha::getMobileSessionKey($mobile);
        $key_time = \common\helpers\Captcha::getCodeTimeKey();
        $sendcode = m('cache')->get($key);
        $sendtime = m('cache')->get($key_time);
        // 检查验证码正确
        if(!$captcha_debug){
            if (!(isset($sendcode)) || ($sendcode !== $verifycode)) {
                throw new \common\modules\api\procedures\ApiException(
                    \common\components\Response::VERIFY_CODE_ERROR
                );
            }
            // 检查验证码超时
            if (!(isset($sendtime)) || ((600 * 1000) < (time() - $sendtime))) {
                throw new \common\modules\api\procedures\ApiException(
                    \common\components\Response::VERIFY_CODE_TIMEOUT
                );
            }
        }

        $member = \common\models\ShopMember::fetchOne(
            ['mobile'       => $mobile, 'uniacid' => \common\components\Request::getInstance()->uniacid,
             'mobileverify' => 1]
        );
        if (!empty($member)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\AppError::$ParamsError,
                '该手机已注册，请直接登录！'
            );
        }

        $salt = random(16);
        $openid = \common\models\McMappingFan::genWapUserOpenID($mobile);
        $nickname = \common\Helper::getNicknameByMobile($mobile);

        $member = [
            'uniacid'      => \common\components\Request::getInstance()->uniacid,
            'mobile'       => $mobile,
            'nickname'     => $nickname,
            'openid'       => $openid,
            'pwd'          => md5($pwd . $salt),
            'salt'         => $salt,
            'createtime'   => time(),
            'mobileverify' => 1,
            'comefrom'     => 'app_mobile'
        ];
        $mid = \common\models\ShopMember::ensureShopMember(
            $member,
            0,
            \common\models\McMappingFan::PLATFORM_MOBILE_USER,
            $member
        );
        $member['id'] = $mid;

        \common\helpers\Event::emit(
            EVENT_MEMBER_REGISTER_SUCCESS,
            [
                'openid' => $openid,
            ]
        );

        $user_data = [
            'id'       => $member['id'],
            'mobile'   => $member['mobile'],
            'nickname' => $member['nickname'],
            'avatar'   => $member['avatar'],
            'openid'   => $member['openid']
        ];
        $user_data['token'] = \common\components\AppUser::getInstance()->generateUserToken($user_data);

        return $user_data;
    }

    /**
     * @category 用户相关
     *
     *
     * @param $mobile
     * @param $pwd
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     *
     * @resultKey int id 用户ID
     * @resultKey string mobile 手机号码
     * @resultKey string nickname 名称
     * @resultKey string avatar 头像
     * @resultKey string openid openid
     * @resultKey string token token
     */
    public function apiLoginMemberByMobileUser($mobile, $pwd)
    {
        global $_W;

        if (empty($mobile) || empty($pwd)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::PARAMS_ERROR
            );
        }

        $member = \common\models\ShopMember::fetchOne(
            ['mobile'       => $mobile, 'uniacid' => \common\components\Request::getInstance()->uniacid,
             'mobileverify' => 1]
        );

        if (empty($member)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::USER_NOT_FOUND
            );
        }

        if (md5($pwd . $member['salt']) !== $member['pwd']) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::USER_LOGIN_FAIL
            );
        }
        $user_data = [
            'id'       => $member['id'],
            'mobile'   => $member['mobile'],
            'nickname' => $member['nickname'],
            'avatar'   => $member['avatar'],
            'openid'   => $member['openid']
        ];
        $user_data['token'] = \common\components\AppUser::getInstance()->generateUserToken($user_data);

        return $user_data;
    }


    /**
     * 重置密码
     * @category 用户相关
     *
     * @param $mobile
     * @param $pwd
     * @param $verifycode
     * @param $captcha_debug
     *
     * @resultKey string  __message 返回信息
     * @resultDemo
     * {
     * "__message":'密码充值成功'
     * }
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     */
    public function apiResetMemberPasswordByMobile($mobile, $pwd, $verifycode, $captcha_debug = '')
    {
        if (empty($mobile) || empty($pwd) || empty($verifycode)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::PARAMS_ERROR
            );
        }

        // 检查验证码
        $key = \common\helpers\Captcha::getMobileSessionKey($mobile);
        $key_time = \common\helpers\Captcha::getCodeTimeKey();
        $sendcode = m('cache')->get($key);
        $sendtime = m('cache')->get($key_time);
        if (!$captcha_debug) {
            // 检查验证码正确
            if (!(isset($sendcode)) || ($sendcode !== $verifycode)) {
                throw new \common\modules\api\procedures\ApiException(
                    \common\components\Response::VERIFY_CODE_ERROR
                );
            }
            // 检查验证码超时
            if (!(isset($sendtime)) || ((600 * 1000) < (time() - $sendtime))) {
                throw new \common\modules\api\procedures\ApiException(
                    \common\components\Response::VERIFY_CODE_TIMEOUT
                );
            }
        }

        $member = \common\models\ShopMember::findOne([
            'mobile' => $mobile, 'uniacid' => \common\components\Request::getInstance()->uniacid,
            'mobileverify' => 1,
        ]);

        if (empty($member)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::USER_NOT_FOUND
            );
        }

        $salt = random(16);

        $member->salt = $salt;
        $member->pwd = md5($pwd . $salt);
        $member->save(false);
        return [
            '__message' => '密码重置成功'
        ];
    }

    /**
     * @param        $token
     * @param int    $mobile
     * @param string $nickname
     * @param string $avatar
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     * @throws \Exception
     */
    public function apiUpdateMemberInfo($token,$mobile = 0,$nickname = '',$avatar = ''){
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        $data = [];
        if (empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::USER_NOT_LOGIN);
        }
        if(!empty($mobile)){
            $data['mobile'] = $mobile;
        }
        if(!empty($nickname)){
            $data['nickname'] = $nickname;
        }
        if(!empty($avatar)){
            $data['avatar'] = $avatar;
        }
        $res = ShopMember::updateAll($data,['openid'=>$openid]);

        return [
            'success'=>1,
            'success_string' => Yii::t('success_string','操作成功'),
        ];
    }

    /**
     * 余额明细列表
     * @param int    $type
     * @param int    $pindex
     * @param int    $psize
     * @param string $token
     *
     * @return array
     * @throws \yii\db\Exception
     * @throws \Exception
     */
    public function apiGetBalanceRecordList($type = 0,$pindex = 1,$psize = 10,$token = ''){
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if(empty($openid)){
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ERROR_PARAM_ERROR);
        }

        $condition = ' and openid=:openid and uniacid=:uniacid and type=:type and status = 1';
        $params = array(
            ':uniacid' => \common\components\Request::getInstance()->uniacid,
            ':openid' => $openid,
            ':type' => $type
        );
        $list = pdo_fetchall('select * from ' . tablename('new_shop_member_log') . ' where 1 ' . $condition . ' order by createtime desc LIMIT ' . (($pindex - 1) * $psize) . ',' . $psize, $params);
        $total = \common\models\ShopMemberLog::countAll([
            'openid' => $openid,
            'type' => $type,
            'uniacid' => \common\components\Request::getInstance()->uniacid,
        ]);
        $newList = array();
        if (is_array($list) && !empty($list)) {
            foreach ($list as $row) {
                if (intval($row['type']) == 2) {
                    $rechargetype_str = [
                        'credit' => Yii::t('shop_o2o_page_string','余额支付'),
                        'payfirma' => Yii::t('shop_o2o_page_string','payfirma支付'),
                    ];

                } else if (intval($row['type']) == 3) {
                    $rechargetype_str = [
                        'credit' => Yii::t('shop_o2o_page_string','余额退款'),
                        'payfirma' => Yii::t('shop_o2o_page_string','payfirma退款'),
                    ];
                } else {
                    $rechargetype_str = [
                        'wechat' => '微信充值',
                        'alipay' => '支付宝充值',
                        'system' => Yii::t('shop_o2o_page_string','后台充值'),
                        'exchange' => Yii::t('shop_o2o_page_string','后台充值'),
                        'payfirma' => Yii::t('shop_o2o_page_string','payfirma充值'),
                    ];
                }
                $key = $row['rechargetype'];

                if (!empty($rechargetype_str[$key])) {
                    $row['rechargetype_text'] = $rechargetype_str[$key];
                } else {
                    $row['rechargetype_text'] = '';
                }

                if (intval($row['type']) == 0) {
                    $row['type_text'] = '未充值';
                } else {
                    $row['type_text'] = '申请中';
                }
                //
                if (intval($row['type']) == 0) {
                    if (intval($row['status']) == 1) {
                        $row['status_text'] = '到账';
                    } else {
                        $row['status_text'] = '金额';
                    }
                }
                // +号
                if (floatval($row['money']) >= 0 ) {
                    $row['symbol'] = '+';
                } else {
                    $row['symbol'] = '-';
                    $row['money'] = substr($row['money'], 1);
                }

                $newList[] = array(
                    'id' => $row['id'],
                    'type' => $row['type'],
                    'symbol' => $row['symbol'],
                    'money' => $row['money'],
                    'typestr' => \common\models\ShopMemberLog::getTypeName($row),
                    'type_text' => $row['type_text'],
                    'status' => $row['status'],
                    'status_text' => $row['status_text'],
                    'deductionmoney' => $row['deductionmoney'],
                    'realmoney' => $row['realmoney'],
                    'rechargetype' => $row['rechargetype'],
                    'rechargetype_text' => $row['rechargetype_text'],
                    'createtime' => date('Y-m-d H:i', $row['createtime']),
                );
            }
        }

        return array(
            'list' => $newList,
            'total' => $total,
            'pagesize' => $psize,
            'page' => $pindex,
            'type' => $type,
            'isopen' => $_W['shopset']['trade']['withdraw'],
            'moneytext' => $_W['shopset']['trade']['moneytext'],
            'tabText' => [
                ['text' => Yii::t('shop_o2o_page_string','充值记录'), 'type' => 0],
//                ['text' => '提现记录', 'type' => 1],
                ['text' => Yii::t('shop_o2o_page_string','消费记录'), 'type' => 2],
                ['text' => Yii::t('shop_o2o_page_string','退款记录'), 'type' => 3],
            ]
        );
    }

    /**
     * 余额明细详情
     * @param $id
     * @param $token
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     */
    public function apiGetBalanceRecordDetail($id,$token)
    {
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::ERROR_PARAM_ERROR
            );
        }

        $data = \common\models\ShopMemberLog::fetchOne(
            ['openid' => $openid, 'uniacid' => $uniacid, 'id' => $id]
        );

        if (empty($data)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::ERROR_PARAM_ERROR, '找不到该记录'
            );
        }
        $res = [];
        if(!empty($data['recharge_openid'])){
            $res['recharge_openid'] = $data['recharge_openid'];
        }
        if (floatval($data['money']) >= 0) {
            $res['money'] = '+' . $data['money'];
        } else {
            $res['money'] = '-' . substr($data['money'], 1);
        }
        $res['id'] = $data['id'];
        $res['logno'] = $data['logno'];
        $res['createtime'] = date('Y-m-d H:i', $data['createtime']);

        if (intval($data['type']) == 2) {
            $rechargetype_str = [
                'credit' => '余额支付',
                'payfirma' => 'payfirma支付',
            ];
        } else if (intval($data['type']) == 3) {
            $rechargetype_str = [
                'credit' => '余额退款',
                'payfirma' => 'payfirma退款',
            ];
        }else{
            $rechargetype_str = [
                'wechat' => '微信充值',
                'alipay' => '支付宝充值',
                'system' => '后台充值',
                'exchange' => '后台充值',
                'payfirma' => 'payfirma充值',
            ];
        }
        $key = $data['rechargetype'];
        if (!empty($rechargetype_str[$key])) {
            $res['rechargetype_text'] = $rechargetype_str[$key];
        } else {
            $res['rechargetype_text'] = '';
        }


        return $res;
    }

    /**
     * 更新二级密码
     * @param $token
     * @param $pwd
     * @param $verifycode
     *
     * @return array
     * @throws \Exception
     */
    public function apiUpdateSecondPassword($token,$pwd,$verifycode){
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $pwd = intval($pwd);
        if(strlen($pwd) !== 6){
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR,'请输入正确的二级密码');
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::USER_NOT_LOGIN);
        }

        $email = $openid;
        // 检查验证码
        $key = \common\helpers\Captcha::getEmailSessionKey($email);
        $key_time = \common\helpers\Captcha::getCodeTimeKey();
        $sendcode = m('cache')->get($key);
        $sendtime = m('cache')->get($key_time);
        // 检查验证码正确
        if (!(isset($sendcode)) || ($sendcode !== $verifycode)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::VERIFY_CODE_ERROR);

        }
        // 检查验证码超时
        if (!(isset($sendtime)) || ((600 * 1000) < (time() - $sendtime))) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::VERIFY_CODE_TIMEOUT);

        }

        \common\models\ShopMember::updateAll(['second_pwd'=>$pwd],['openid'=>$openid,'uniacid'=>$uniacid]);
        return [
            'success'=>1,
            'success_string' => Yii::t('success_string','操作成功'),
        ];


    }

    /**
     * @param     $agent_id
     * @param     $uniacid
     * @param     $type
     * @param int $page_num
     * @param int $page_size
     */
    public function apiGetAgentMembers($agent_id,$uniacid,$page_num = 1,$page_size = 10)
    {

        $memebers = \common\models\ShopMember::find()
            ->select('id,nickname,avatar,uniacid')
            ->where(array('agentid'=>$agent_id,'uniacid'=>$uniacid))
            ->limit($page_size)->offset($page_size*($page_num-1))
            ->asArray()
            ->all();
        foreach ($memebers as &$member)
        {

            $count = \common\models\ShopOrder::countAll(
                [
                    'id' => $member['id'],
                    'status' => 3,
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                ]);
            $member['order_num'] = $count;
        }
        return $memebers;

    }

    /**
     * @param $token
     * @param $pwd
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     */
    public function apiCheckMemberRechargePassword($token,$pwd){
        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $pwd = intval($pwd);
        if(strlen($pwd) !== 6){
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR,'请输入正确的充值密码');
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::USER_NOT_LOGIN);
        }
        $this_member = AppUser::getInstance()->identity->toArray();

        if(empty(intval($this_member['recharge_pwd']))){
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR,'请输入正确的充值密码');
        }
        if(intval($this_member['recharge_pwd']) !== $pwd){
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR,'请输入正确的充值密码');
        }

        return ['success' => 1];
    }


    /**
     * @param $uniacid
     * @param $id
     *
     * @return array|null|\yii\db\ActiveRecord
     */
    public function apiGetRadarMemberInfo($uniacid,$id)
    {
        $member = \common\models\ShopMember::find()
            ->select('id,nickname,gender,avatar,uniacid')
            ->where(array('id'=>$id,'uniacid'=>$uniacid))
            ->asArray()
            ->one();
        $count = \common\models\ShopOrder::countAll(
                [
                    'id' => $member['id'],
                    'status' => 3,
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                ]);
        $member['order_num'] = $count;

        $beginLast0month=mktime(0,0,0,date('m'),1,date('Y'));
        $endLast0month=mktime(23,59,59,date('m'),date('t'),date('Y'));


        $beginLast1month=mktime(0,0,0,date('m')-1,1,date('Y'));
        $endLast1month=mktime(23,59,59,date('m')-1,date('t'),date('Y'));

        $beginLast2month=mktime(0,0,0,date('m')-2,1,date('Y'));
        $endLast2month=mktime(23,59,59,date('m')-2,date('t'),date('Y'));

        $beginLast3month=mktime(0,0,0,date('m')-3,1,date('Y'));
        $endLast3month=mktime(23,59,59,date('m')-3,date('t'),date('Y'));

        $beginLast4month=mktime(0,0,0,date('m')-4,1,date('Y'));
        $endLast4month=mktime(23,59,59,date('m')-4,date('t'),date('Y'));


        $redis = redis();
        $month_price = $redis->get('_month_total_price_new_'.$uniacid.'_'.$id.'_'.date('m'));
        if($month_price)
        {
            $member['month_price'] = json_decode($month_price,false);
            return $member;
        }
        else
        {
            $count1 = \common\models\ShopOrder::sumAll(
                [
                    'id' => $member['id'],
                    'status' => 3,
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                    ['between','createtime',  $beginLast1month, $endLast1month],
                ],'price');

            $count2 = \common\models\ShopOrder::sumAll(
                [
                    'id' => $member['id'],
                    'status' => 3,
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                    ['between','createtime',  $beginLast2month, $endLast2month],
                ],'price');

            $count3 = \common\models\ShopOrder::sumAll(
                [
                    'id' => $member['id'],
                    'status' => 3,
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                    ['between','createtime',  $beginLast3month, $endLast3month],
                ],'price');

            $count4 = \common\models\ShopOrder::sumAll(
                [
                    'id' => $member['id'],
                    'status' => 3,
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                    ['between', 'createtime', $beginLast4month, $endLast4month],
                ],'price');

            $count0 = \common\models\ShopOrder::sumAll(
                [
                    'id' => $member['id'],
                    'status' => 3,
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                    ['between','createtime', $beginLast0month, $endLast0month],
                ],'price');

            $array = [];

            $array[] =  array(
                'month' =>   (date('m')-4).'月',
                //'total_price' => '¥'.round(doubleval($count4),2),
                'total_price' => intval($count4)
            );
            $array[] =  array(
                'month' =>   (date('m')-3).'月',
                //'total_price' => '¥'.round(doubleval($count3),2),
                'total_price' => intval($count3)
            );
            $array[] =  array(
                'month' =>   (date('m')-2).'月',
                //'total_price' => '¥'.round(doubleval($count2),2),
                'total_price' => intval($count2)
            );
            $array[] =  array(
                'month' =>   (date('m')-1).'月',
                //'total_price' => '¥'.round(doubleval($count1),2),
                'total_price' => intval($count1)
            );
            $array[] =  array(
                'month' =>   (date('m')).'月',
                //'total_price' => '¥'.round(doubleval($count5),2),
                'total_price' => intval($count0)

            );

            $redis->setex('_month_total_price_new_'.$uniacid.'_'.$id.'_'.date('m'),60*60*24*2,json_encode($array));
            $member['month_price'] = $array;
            return $member;
        }



    }


    //todo 改造
    public function apiWxappAuth($code, $vi, $encryptedData)
    {
        $data = '';
        $encryptedData = trim($encryptedData);
        $iv = trim($vi);
        $code = trim($code);
        if (empty($encryptedData) || empty($iv)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR);
        }

        $sessionurl = "https://api.weixin.qq.com/sns/jscode2session?appid=" . $this->appid . "&secret=" . $this->appsecret . "&js_code=" . $code . "&grant_type=authorization_code";
        $content = file_get_contents($sessionurl);
        $sessionInfo = @json_decode($content, true);
        Yii::info('使用code获取session，请求【' . $sessionurl . '】，返回：' . $content, __METHOD__);
        if (isset($sessionInfo['errcode'])) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::WXAPP_ERROR, '登录错误, 错误代码: ' . $sessionInfo['errmsg']);
        } else {
            $sessionKey = $sessionInfo['session_key'];
            $pc = new WXBizDataCrypt($this->appid, $sessionKey);
            $errCode = $pc->decryptData($encryptedData, $iv, $data);
            if ($errCode == 0) {
                Yii::debug('获得微信小程序用户数据：' . $data, __METHOD__);
                $data = json_decode($data, true);
                $id = \common\models\ShopMember::ensureShopMember($data, 0, \common\models\McMappingFan::PLATFORM_WX_APP);
                $data['id'] = $id;
                $data['uniacid'] = \common\components\Request::getInstance()->uniacid;
                // 生成并返回JWT
                if ($id) {
                    $data['jwt'] = \common\helpers\JWT::encode([
                        'id' => $id,
                    ]);
                }
                \common\helpers\Event::emit(EVENT_MEMBER_CHECK_LOGIN_SUCCESS, [
                    'openid' => $id,
                ]);
                return $data;
            } else {
                throw new \common\modules\api\procedures\ApiException(\common\components\Response::WXAPP_ERROR, '登录错误, 错误代码: ' . $errCode);

            }
        }


    }

}
