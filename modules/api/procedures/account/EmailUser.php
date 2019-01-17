<?php
//这些接口都是o2o外卖项目用的。
//「内部」o2o校园订餐APP项目（加拿大)
namespace common\modules\api\procedures\account;

use common\components\Response;
use common\modules\api\procedures\ApiException;
use common\modules\api\procedures\BaseAppApi;
use Yii;

class EmailUser extends BaseAppApi
{


    /**
     * @category 用户相关
     *
     * @param $email
     * @param $pwd
     * @param $verifycode
     * @param $nickname
     * @param $birthday
     * @param $mobile
     * @param $gender
     * @return array
     *
     * @resultKey string email 邮箱
     * @resultKey string pwd 密码
     * @resultKey string verifycode 验证码
     * @resultKey string nickname 昵称
     * @resultKey string birthday 生日
     * @resultKey string mobile 电话号码
     * @resultKey string gender 性别
     *
     * @throws ApiException
     */
    public function apiRegisterMemberByEmail($email, $pwd, $verifycode, $nickname, $birthday, $mobile, $gender, $captcha_debug = '')
    {
        global $_W;
        if (empty($email) || empty($pwd) || empty($verifycode) || empty($nickname) || empty($birthday) || empty($mobile) || empty($gender)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::PARAMS_ERROR
            );
        }
        // 检查验证码
        Yii::$app->session->open();
        $key = \common\helpers\Captcha::getEmailSessionKey($email);
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

        $member = \common\models\ShopMember::fetchOne(
            ['openid' => $email, 'uniacid' => \common\components\Request::getInstance()->uniacid]
        );
        if (!empty($member)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\AppError::$ParamsError, '该邮箱已注册，请直接登录！'
            );
        }

        $salt = random(16);
        $openid = $email;
        $birthday_cutting = explode('-',$birthday);


        $member = array(
            'uniacid'      => \common\components\Request::getInstance()->uniacid,
            'nickname'     => $nickname,
            'openid'       => $openid,
            'pwd'          => md5($pwd . $salt),
            'salt'         => $salt,
            'createtime'   => time(),
            'avatar'   => '',
            'mobile'   => $mobile,
            'gender'   => $gender,
            'birthyear'   => $birthday_cutting[0],
            'birthmonth'   => $birthday_cutting[1],
            'birthday'   => $birthday_cutting[2],
            'comefrom'     => 'app_email'
        );

        $mid = \common\models\ShopMember::insertOne($member);
        $member['id'] = $mid;

        \common\helpers\Event::emit(
            EVENT_MEMBER_REGISTER_SUCCESS, [
                'openid' => $openid,
            ]
        );

        $unread_message = \common\modules\message\models\Store::countAll([
            'uniacid'=>\common\components\Request::getInstance()->uniacid,
            'uid'=>$mid,
            'message_type'=>'APP',
            'is_read'=>0
        ]);


        $user_data = [
            'id' => $member['id'],
            'mobile' => $member['mobile'],
            'nickname' => $member['nickname'],
            'avatar' => $member['avatar'],
            'openid' => $member['openid'],
            'credit2' => '0.00',
            'birthday' => $birthday,
            'gender' => $gender == 1 ? '男' : '女',
            'is_verification_man' => 0,
            'has_second_pwd' => 0,
            'unread_message' => intval($unread_message),

        ];

        $user_data['token'] = \common\components\AppUser::getInstance()->generateUserToken($user_data);

        return $user_data;


    }

    /**
     * @category 用户相关
     *
     * @param $email
     * @param $pwd
     * @return array
     * @throws ApiException
     * @resultKey int id 用户ID
     * @resultKey string mobile 手机号码
     * @resultKey string nickname 名称
     * @resultKey string avatar 头像
     * @resultKey string openid
     * @resultKey string credit2 积分
     * @resultKey string has_second_pwd  二次密码
     * @resultKey sting birthday 生日
     * @resultKey string gender 性别
     * @resultKey int is_verification_man
     * @resultKey int unread_message
     */
    public function apiLoginMemberByEmail($email, $pwd)
    {

        global $_W;

        if (empty($email) || empty($pwd)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::PARAMS_ERROR
            );
        }

        $member = \common\models\ShopMember::fetchOne(
            ['openid' => $email, 'uniacid' => \common\components\Request::getInstance()->uniacid]
        );

        if (empty($member)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::USER_NOT_FOUND
            );
        }

        if (md5($pwd . $member['salt']) !== $member['pwd']) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::USER_LOGIN_FAIL,Yii::t('shop_o2o_page_string','密码错误')
            );
        }
        $unread_message = \common\modules\message\models\Store::countAll([
            'uniacid'=>\common\components\Request::getInstance()->uniacid,
            'uid'=>$member['id'],
            'message_type'=>'APP',
            'is_read'=>0
        ]);
        $is_verification_man = \common\models\Saler::fetchOne([
            'uniacid'=>\common\components\Request::getInstance()->uniacid,
            'openid'=>$email,
            'status'=>1
        ]);
        $is_verification_man = empty($is_verification_man) ? 0 : 1;

        $user_data = [
            'id'       => $member['id'],
            'mobile'   => $member['mobile'],
            'nickname' => $member['nickname'],
            'avatar'   => $member['avatar'],
            'openid'   => $member['openid'],
            'credit2' => $member['credit2'],
            'has_second_pwd' => empty($member['second_pwd']) ? 0 : 1,
            'birthday' => $member['birthyear'] . '-' . $member['birthmonth'] . '-' .$member['birthday'],
            'gender' => $member['gender'] == 1 ? '男' : '女',
            'is_verification_man' => $is_verification_man,
            'unread_message' => intval($unread_message),
        ];
        $user_data['token'] =  \common\components\AppUser::getInstance()->generateUserToken($user_data);

        return $user_data;

    }


    /**
     * @category 用户相关
     *
     * @param $email
     * @param $pwd
     * @param $verifycode
     * @return array
     * @throws ApiException
     * @resultKey int success 返回码
     * @resultKey string success_string 返回信息
     * @resultDemo {
     *          "success":1,
     *      "success_string":"操作成功"
     * }
     */
    public function apiResetMemberPasswordByEmail($email, $pwd, $verifycode,$captcha_debug="")
    {

        global $_W;

        if (empty($email) || empty($pwd) || empty($verifycode)) {
            throw new \common\modules\api\procedures\ApiException(
                \common\components\Response::PARAMS_ERROR
            );
        }

        // 检查验证码
        $key = \common\helpers\Captcha::getEmailSessionKey($email);
        $key_time = \common\helpers\Captcha::getCodeTimeKey();
        $sendcode = m('cache')->get($key);
        $sendtime = m('cache')->get($key_time);
        // 检查验证码正确
        if (!$captcha_debug){
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

        $member = \common\models\ShopMember::findOne(['openid' => $email, 'uniacid' => \common\components\Request::getInstance()->uniacid]);

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
            'success'=>1,
            'success_string' => Yii::t('success_string','操作成功'),
        ];
    }

    /**
     * @param $token
     *
     * @category 用户相关
     * @return array
     * @throws \Exception
     *
     * @resultKey int id 用户id
     * @resultKey string mobile 手机号码
     * @resultKey string nickname 名称
     * @resultKey string avatar 头像
     * @resultKey string openid openid
     * @resultKey decimal credit2 佣金
     * @resultKey int has_second_pwd 是否拥有二级密码
     * @resultKey date birthday 生日
     * @resultKey string gender 性别
     * @resultKey int is_verification_man
     * @resultKey string unread_message
     * @resultKey int update_language_number
     */
    public function apiGetMemberInfo($token,$openid = ''){
        global $_W;

        $user_info = \common\components\AppUser::getInstance()->verifyToken($token);

        if(!empty($openid)){
            $user_info['openid'] = $openid;
        }

        $member = \common\models\ShopMember::fetchOne(
            ['openid' => $user_info['openid'], 'uniacid' => \common\components\Request::getInstance()->uniacid]
        );
        if(empty($member)){
            throw new ApiException(Response::USER_NOT_FOUND);
        }

        $unread_message = \common\modules\message\models\Store::countAll([
            'uniacid'=>\common\components\Request::getInstance()->uniacid,
            'uid'=>$member['id'],
            'message_type'=>'APP',
            'is_read'=>0
        ]);
        $is_verification_man = \common\models\Saler::fetchOne([
            'uniacid'=>\common\components\Request::getInstance()->uniacid,
            'openid'=>$member['openid'],
            'status'=>1
        ]);
        $is_verification_man = empty($is_verification_man) ? 0 : 1;
        $user_data = [
            'id'       => $member['id'],
            'mobile'   => $member['mobile'],
            'nickname' => $member['nickname'],
            'avatar'   => $member['avatar'],
            'openid'   => $member['openid'],
            'credit2' => $member['credit2'],
            'has_second_pwd' => empty($member['second_pwd']) ? 0 : 1,
            'birthday' => $member['birthyear'] . '-' . $member['birthmonth'] . '-' .$member['birthday'],
            'gender' => $member['gender'] == 1 ? '男' : '女',
            'is_verification_man' => $is_verification_man,
            'unread_message' => intval($unread_message),
            'update_language_number' => rand(),//调试阶段一直保持更新
        ];
        return $user_data;
    }


}

