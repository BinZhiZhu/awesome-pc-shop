<?php

use yii\db\Migration;

/**
 * Class m191022_133444_add_column_on_goods_entity
 */
class m191022_133444_add_column_on_goods_entity extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('dev_goods_entity','status',$this->tinyInteger(1)->notNull()->defaultValue(0)->comment('状态'));
        $this->addColumn('dev_goods_entity','is_deleted',$this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否删除'));
        $this->addColumn('dev_goods_entity','updated_at',$this->integer(11)->notNull()->defaultValue(0)->comment('更新时间'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('dev_goods_entity','status');
        $this->dropColumn('dev_goods_entity','is_deleted');
        $this->dropColumn('dev_goods_entity','updated_at');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m191022_133444_add_column_on_goods_entity cannot be reverted.\n";

        return false;
    }
    */
}
