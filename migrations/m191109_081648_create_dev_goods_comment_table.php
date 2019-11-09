<?php

use yii\db\Migration;

/**
 * Handles the creation of table `dev_goods_comment`.
 */
class m191109_081648_create_dev_goods_comment_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('dev_goods_comment', [
            'id' => $this->primaryKey(),
            'member_id' => $this->integer(11)->notNull()->defaultValue(0)->comment('用户ID'),
            'content' => $this->text()->comment('评价内容'),
            'created_at' => $this->integer(11)->notNull()->defaultValue(0)->comment('评价时间'),
            'goods_id' => $this->integer(11)->notNull()->defaultValue(0)->comment('商品ID'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('dev_goods_comment');
    }
}
