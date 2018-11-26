<?php

use yii\db\Migration;

/**
 * Class m181126_154615_add_column_on_dev_users
 */
class m181126_154615_add_column_on_dev_users extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('dev_users', 'login_count', $this->integer(11)->defaultValue(0)->comment('登入次数'));

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('dev_users', 'login_count');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m181126_154615_add_column_on_dev_users cannot be reverted.\n";

        return false;
    }
    */
}
