<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "dev_goods_category_entity".
 *
 * @property int $id
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 * @property int $created_by 创建人ID
 * @property string $title 分类名称
 * @property int $status 状态
 * @property int $is_deleted 是否被删除
 */
class GoodsCategoryEntity extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'dev_goods_category_entity';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['created_at', 'updated_at', 'created_by', 'status', 'is_deleted'], 'integer'],
            [['title'], 'string', 'max' => 255],
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
            'updated_at' => 'Updated At',
            'created_by' => 'Created By',
            'title' => 'Title',
            'status' => 'Status',
            'is_deleted' => 'Is Deleted',
        ];
    }
}
