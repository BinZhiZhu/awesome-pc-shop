<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "dev_order_entity".
 *
 * @property int $id
 * @property int $member_id 用户ID
 * @property string $price 订单价格
 * @property string $order_sn 订单号
 * @property int $status 订单状态
 * @property int total
 * @property int $created_at 创建时间
 * @property GoodsEntity goodsEntity
 * @property int goods_id
 * @property int is_deleted
 */
class OrderEntity extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'dev_order_entity';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['member_id', 'status', 'created_at', 'total','goods_id','is_deleted'], 'integer'],
            [['price'], 'number'],
            [['order_sn'], 'string', 'max' => 255],
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
            'price' => 'Price',
            'order_sn' => 'Order Sn',
            'status' => 'Status',
            'total' => 'total',
            'created_at' => 'Created At',
            'goods_id'=>'goods_id',
            'is_deleted'=>'is_deleted'
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getGoodsEntity()
    {
        return $this->hasOne(GoodsEntity::className(), ['id' => 'goods_id']);
    }

    public function getApiArray()
    {

        return [
            'id' => $this->id,
            'order_price' => "￥{$this->price}",
            'order_sn' => $this->order_sn,
            'goods_id' => $this->goods_id,
            'title' => $this->goodsEntity->title,
            'total' => "{$this->total}/件",
            'created_at' => date("Y-m-d H:i:s", $this->created_at),
            'created_by'=>$this->goodsEntity->memberEntity->username
        ];

    }
}
