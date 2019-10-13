<?php

namespace common\modules\api\procedures\account;

use common\modules\api\procedures\BaseAppApi;
use common\modules\api\procedures\ApiException;
use PHPMailer\PHPMailer\PHPMailer;
use Yii;

class Sms extends BaseAppApi
{


    /**
     * @param string $type reg=注册 forget=忘记密码 changepwd=修改密码 bind=绑定手机
     * @param $mobile
     *
     * @return array
     * @throws ApiException
     * @throws \Exception
     */
    public function get_verifycode($type, $mobile)
    {
        global $_W;
        $mobile = trim($mobile);

        if ($type !== 'reg' && $type !== 'forget' && $type !== 'changepwd'
            && $type !== 'bind') {
            throw new ApiException(\common\AppError::$ParamsError);
        }

        if (empty($mobile)) {
            throw new ApiException(\common\AppError::$ParamsError, '手机号不能为空');
        }

        $data = \common\models\ShopSysSet::getByKey('wap');

        $sms_id = $data['sms_' . $type];


        if (empty($sms_id)) {
            throw new ApiException(\common\AppError::$SMSTplidNull);
        }

        $key = \common\helpers\Captcha::getMobileSessionKey($mobile);
        $sendtime = \common\helpers\Captcha::getLastCodeTime();

        $time = time() - $sendtime;

        if ($time < 60) {
            throw new ApiException(\common\AppError::$SMSRateError);
        }

        $code = random(5, true);
        $ret = com('sms')->send(
            $mobile,
            $sms_id,
            ['验证码' => $code, '商城名称' => $_W['shopset']['shop']['name']]
        );

        if ($ret['status']) {
            m('cache')->set($key, $code);
            \common\helpers\Captcha::saveLastCodeTime();
            return ['success' => 1];
        }
        throw new ApiException(\common\AppError::$SystemError, $ret['message']);
    }

    /**
     * @param $email
     * @return array
     * @throws ApiException
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function apiGetVerifyCodeByEmail($email){
        global $_W;

        $email = trim($email);

        if (empty($email)) {
            throw new ApiException(\common\AppError::$ParamsError, Yii::t('shop_o2o_page_string','邮箱不能为空'));
        }

        $key = \common\helpers\Captcha::getEmailSessionKey($email);
        $sendtime = \common\helpers\Captcha::getLastCodeTime();

        $time = time() - $sendtime;

        if ($time < 60) {
            throw new ApiException(\common\AppError::$SMSRateError);
        }

        $code = random(5, true);

        try {
            $ret = $this->send_email($email,$code);
        } catch (ApiException $e){
            Yii::info("email发送失败原因：" . $e->getMessage() . "邮箱 【 $email 】");
            throw new ApiException(\common\AppError::$SystemError, '邮箱格式错误');
        }


        if ($ret) {
            m('cache')->set($key, $code);
            \common\helpers\Captcha::saveLastCodeTime();
            return [
                'success'=>1,
                'success_string' => Yii::t('success_string','操作成功'),
            ];
        }
        throw new ApiException(\common\AppError::$SystemError, '发送失败');

    }


    /**
     * @param $email
     * @param $code
     *
     * @return array
     * @throws ApiException
     * @throws \PHPMailer\PHPMailer\Exception
     */
    private function send_email($email,$code){
        //TODO 可配置项
        $sendmail = 'wulihuang@jutouit.com'; //发件人邮箱
        $sendmailpswd = "Bb649166338"; //客户端授权密码,而不是邮箱的登录密码，就是手机发送短信之后弹出来的一长串的密码
        $send_name = 'o2o校园外卖';// 设置发件人信息，如邮件格式说明中的发件人，
        $toemail = $email;//定义收件人的邮箱
        $to_name = $email;//设置收件人信息，如邮件格式说明中的收件人
        $mail = new PHPMailer();
//        $mail->SMTPDebug = 2;//打开debug调试
        $mail->isSMTP();// 使用SMTP服务
        $mail->CharSet = "utf8";// 编码格式为utf8，不设置编码的话，中文会出现乱码
        $mail->Host = "smtp.exmail.qq.com";// 发送方的SMTP服务器地址
        $mail->SMTPAuth = true;// 是否使用身份验证
        $mail->Username = $sendmail;//// 发送方的
        $mail->Password = $sendmailpswd;//客户端授权密码,而不是邮箱的登录密码！
        $mail->SMTPSecure = "ssl";// 使用ssl协议方式
        $mail->Port = 465;//  qq端口465或587）
        $mail->setFrom($sendmail, $send_name);// 设置发件人信息，如邮件格式说明中的发件人，
        $mail->addAddress($toemail, $to_name);// 设置收件人信息，如邮件格式说明中的收件人，
        $mail->Subject = "验证码邮件通知";// 邮件标题
        $mail->Body = "尊敬的用户，您的验证码是：$code ，如果非本人操作无需理会！";// 邮件正文
        if (!$mail->send()) { // 发送邮件
            throw new ApiException(\common\AppError::$SystemError,$mail->ErrorInfo);
        } else {
            return [
                'success'=>1,
                'success_string' => Yii::t('success_string','验证码发送成功'),
            ];
        }
    }
}
