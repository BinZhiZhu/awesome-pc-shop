<?php

use yii\db\Migration;

/**
 * Class m191109_063038_add_column_on_order_entity
 */
class m191109_063038_add_column_on_order_entity extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('dev_order_entity','updated_by',$this->integer(11)->notNull()->defaultValue(0)->comment('更新人ID'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
       $this->dropColumn("dev_order_entity",'updated_by');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m191109_063038_add_column_on_order_entity cannot be reverted.\n";

        return false;
    }
    */
}
