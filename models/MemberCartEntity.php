<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "dev_member_cart_entity".
 *
 * @property int $id
 * @property int $created_at 创建时间
 * @property int $member_id 用户ID
 * @property int $goods_id 商品ID
 * @property int $total 购物车数量
 * @property string $market_price 价格
 * @property int $is_deleted 是否移除
 */
class MemberCartEntity extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'dev_member_cart_entity';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['created_at', 'member_id', 'goods_id', 'total', 'is_deleted'], 'integer'],
            [['market_price'], 'number'],
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
            'member_id' => 'Member ID',
            'goods_id' => 'Goods ID',
            'total' => 'Total',
            'market_price' => 'Market Price',
            'is_deleted' => 'Is Deleted',
        ];
    }
}
