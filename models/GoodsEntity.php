<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "dev_goods_entity".
 *
 * @property int $id
 * @property string $title 商品标题
 * @property string $subtitle 商品标题
 * @property string $thumb 商品图片
 * @property int $stock 商品库存
 * @property int $sell_num 商品已售数量
 * @property string $price 商品价格
 * @property int $created_at 创建时间
 * @property int $created_by 创建人ID
 */
class GoodsEntity extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'dev_goods_entity';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['stock', 'sell_num', 'created_at', 'created_by'], 'integer'],
            [['price'], 'number'],
            [['title', 'subtitle', 'thumb'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'thumb' => 'Thumb',
            'stock' => 'Stock',
            'sell_num' => 'Sell Num',
            'price' => 'Price',
            'created_at' => 'Created At',
            'created_by' => 'Created By',
        ];
    }
}
