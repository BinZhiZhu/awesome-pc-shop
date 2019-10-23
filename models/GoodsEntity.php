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
 * @property int status 状态
 * @property int is_deleted 是否删除
 * @property int updated_at
 * @property int category_id 分类ID
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
            [['stock', 'sell_num', 'created_at', 'created_by', 'status', 'is_deleted', 'updated_at', 'category_id'], 'integer'],
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
            'status' => 'status',
            'is_deleted' => 'is_deleted',
            'updated_at' => 'updated_at',
            'category_id' => 'category_id'
        ];
    }
}
