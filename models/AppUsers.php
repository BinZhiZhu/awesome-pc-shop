<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "dev_app_users".
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
 * @property int $login_count 登入次数
 * @property int is_deleted
 * @property string avatar
 * @property string mobile
 * @property string email
 * @property int gender
 * @property string address
 */
class AppUsers extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'dev_app_users';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['status', 'lastvisit_time', 'register_time', 'login_count','is_deleted','gender'], 'integer'],
            [['username'], 'string', 'max' => 50],
            [['password','address'], 'string', 'max' => 255],
            [['avatar'], 'string', 'max' => 200],
            [['email','mobile'], 'string', 'max' => 100],
            [['salt', 'register_ip', 'lastvisit_ip'], 'string', 'max' => 20],
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
            'is_deleted'=>'is_deleted',
            'mobile'=>'mobile',
            'email'=>'email',
            'avatar'=>'avatar',
            'gender'=>'gender',
            'address'=>'address'
        ];
    }
}
