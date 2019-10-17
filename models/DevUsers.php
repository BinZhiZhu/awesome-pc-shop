<?php

namespace app\models;

use app\interfaces\AdminArrayInterface;
use Yii;

/**
 * This is the model class for table "dev_users".
 *
 * @property int $id
 * @property string $username 用户姓名
 * @property string $password 用户密码
 * @property string $salt 加密盐
 * @property int $status 状态
 * @property string $register_ip 注册ip
 * @property int $lastvisit_time 最后一次访问时间
 * @property string $lastvisit_ip 最后一次访问ip
 * @property int $register_time 注册时间
 * @property int $login_count 登录次数
 * @property int $hash_pwd 加密密码
 * @property int $role 角色
 * @property int is_deleted
 * @property string avatar
 * @property string mobile
 * @property string email
 * @property int gender
 */
class DevUsers extends \yii\db\ActiveRecord implements AdminArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'dev_users';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['status', 'lastvisit_time', 'register_time', 'login_count','role','is_deleted','gender'], 'integer'],
            [['username'], 'string', 'max' => 255],
            [['avatar'], 'string', 'max' => 200],
            [['email','mobile'], 'string', 'max' => 100],
            [['password', 'salt', 'register_ip', 'lastvisit_ip'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => 'Username',
            'password' => 'Password',
            'salt' => 'Salt',
            'status' => 'Status',
            'register_ip' => 'Register Ip',
            'lastvisit_time' => 'Lastvisit Time',
            'lastvisit_ip' => 'Lastvisit Ip',
            'register_time' => 'Register Time',
            'login_count' => 'Login Count',
            'hash_pwd0' => 'Hash Pwd',
            'role'=>'role',
            'is_deleted'=>'is_deleted',
            'mobile'=>'mobile',
            'email'=>'email',
            'avatar'=>'avatar',
            'gender'=>'gender'
        ];
    }

    public function getAdminArray()
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'password' => $this->password,
            'salt' => $this->salt,
            'status' => $this->status,
            'register_ip' => $this->register_ip,
            'lastvisit_time' => $this->lastvisit_time,
            'lastvisit_ip' => $this->lastvisit_ip,
            'register_time' => $this->register_time,
            'login_count' => $this->login_count,
            'hash_pwd0' => $this->hash_pwd,
            'role'=>$this->role
        ];
    }
    /**
     * 登录认证
     *
     * @return bool
     */
    public static function checkLogin()
    {
        $session = Yii::$app->session;
        Yii::info('session---'.$session->get('is_user_id')['value']);
        if ($session->get('is_user_id')) {
            return true;
        } else {
            $userId = $session->get('is_user_id');
//            $userToken = $session->get('is_user_token');
            if (empty($userid)) {
                return false;
            } else {
//                $sql['token'] = $userToken;
                $user = self::findOne($userId);
                if (!empty($user)) {
                    $session['is_user_id'] = ['value'=>$user['id'],'expire_time'=>time()+3600*2];
                    return true;
                } else {
                    return false;
                }
            }
        }
    }
}
