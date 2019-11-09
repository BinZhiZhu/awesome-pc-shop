<?php

namespace app\models;

use app\interfaces\AdminArrayInterface;
use Yii;

/**
 * This is the model class for table "dev_goods_comment".
 *
 * @property int $id
 * @property int $member_id 用户ID
 * @property string $content 评价内容
 * @property int $created_at 评价时间
 * @property int $goods_id 商品ID
 * @property AppUsers memberEntity 关联用户模型
 */
class GoodsComment extends \yii\db\ActiveRecord implements AdminArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'dev_goods_comment';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['member_id', 'created_at', 'goods_id'], 'integer'],
            [['content'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'member_id' => 'Member ID',
            'content' => 'Content',
            'created_at' => 'Created At',
            'goods_id' => 'Goods ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMemberEntity()
    {
        return $this->hasOne(AppUsers::className(),['id'=>'member_id']);
    }

    /**
     * @return mixed
     */
    public function getAdminArray()
    {
        // TODO: Implement getAdminArray() method.
    }
}
