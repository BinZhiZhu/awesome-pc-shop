<?php

use yii\db\Migration;

/**
 * Class m191015_135820_add_column_on_dev_users_table
 */
class m191015_135820_add_column_on_dev_users_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('dev_users','role',$this->integer(11)->notNull()->defaultValue(0)->comment('角色'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
       $this->dropColumn('dev_users','role');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m191015_135820_add_column_on_dev_users_table cannot be reverted.\n";

        return false;
    }
    */
}
