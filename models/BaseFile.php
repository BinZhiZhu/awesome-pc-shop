<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "base_file".
 *
 * @property int $id
 * @property int $created_at 创建时间
 * @property string $url 文件地址
 * @property string $file_name 文件名称
 * @property int $app_user_id 前台用户ID
 * @property int $web_user_id 后台用户ID
 */
class BaseFile extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'base_file';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['created_at', 'app_user_id', 'web_user_id'], 'integer'],
            [['url'], 'string'],
            [['file_name'], 'string', 'max' => 200],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created_at' => 'Created At',
            'url' => 'Url',
            'file_name' => 'File Name',
            'app_user_id' => 'App User ID',
            'web_user_id' => 'Web User ID',
        ];
    }
}
