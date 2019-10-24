<?php

use yii\db\Migration;

/**
 * Handles the creation of table `dev_member_card_entity`.
 */
class m191023_153015_create_dev_member_card_entity_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('dev_member_cart_entity', [
            'id' => $this->primaryKey(),
            'created_at'=>$this->integer(11)->notNull()->defaultValue(0)->comment('创建时间'),
            'member_id'=>$this->integer(11)->notNull()->defaultValue(0)->comment('用户ID'),
            'goods_id'=>$this->integer(11)->notNull()->defaultValue(0)->comment('商品ID'),
            'total'=>$this->integer(11)->notNull()->defaultValue(0)->comment('购物车数量'),
            'market_price'=>$this->decimal(10,2)->notNull()->defaultValue(0)->comment('价格'),
            'is_deleted'=>$this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否移除'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('dev_member_cart_entity');
    }
}
