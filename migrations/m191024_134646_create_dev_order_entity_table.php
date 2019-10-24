<?php

use yii\db\Migration;

/**
 * Handles the creation of table `dev_order_entity`.
 */
class m191024_134646_create_dev_order_entity_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('dev_order_entity', [
            'id' => $this->primaryKey(),
            'member_id'=>$this->integer(11)->notNull()->defaultValue(0)->comment('用户ID'),
            'price'=>$this->decimal(10,2)->notNull()->defaultValue(0)->comment('订单价格'),
            'order_sn'=>$this->string(255)->notNull()->defaultValue('')->comment('订单号'),
            'status'=>$this->integer(11)->notNull()->defaultValue(0)->comment('订单状态'),
            'total'=>$this->integer(11)->notNull()->defaultValue(0)->comment('数量'),
            'goods_id'=>$this->integer(11)->notNull()->defaultValue(0)->comment('商品ID'),
            'created_at'=>$this->integer(11)->notNull()->defaultValue(0)->comment('创建时间'),
            'is_deleted'=>$this->integer(11)->notNull()->defaultValue(0)->comment('是否删除'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('dev_order_entity');
    }
}
