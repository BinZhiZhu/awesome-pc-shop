<?php

use yii\db\Migration;

/**
 * Class m191022_153846_add_column_on_goods_entity
 */
class m191022_153846_add_column_on_goods_entity extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('dev_goods_entity','category_id',$this->integer(11)->notNull()->defaultValue(0)->comment('分类ID'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
      $this->dropColumn('dev_goods_entity','category_id');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m191022_153846_add_column_on_goods_entity cannot be reverted.\n";

        return false;
    }
    */
}
