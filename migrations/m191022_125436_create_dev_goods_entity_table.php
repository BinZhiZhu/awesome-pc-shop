<?php

use yii\db\Migration;

/**
 * Handles the creation of table `dev_goods_entity`.
 */
class m191022_125436_create_dev_goods_entity_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('dev_goods_entity', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull()->defaultValue('')->comment('商品标题'),
            'subtitle' => $this->string(255)->notNull()->defaultValue('')->comment('商品标题'),
            'thumb' => $this->string(255)->notNull()->defaultValue('')->comment('商品图片'),
            'stock' => $this->integer(11)->notNull()->defaultValue(0)->comment('商品库存'),
            'sell_num' => $this->integer(11)->notNull()->defaultValue(0)->comment('商品已售数量'),
            'price' => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('商品价格'),
            'created_at' => $this->integer(11)->notNull()->defaultValue(0)->comment('创建时间'),
            'created_by' => $this->integer(11)->notNull()->defaultValue(0)->comment('创建人ID'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('dev_goods_entity');
    }
}
