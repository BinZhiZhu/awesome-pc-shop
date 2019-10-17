<?php

use yii\db\Migration;

/**
 * Class m191017_144123_add_column_on_devs_table
 */
class m191017_144123_add_column_on_devs_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('dev_users', 'is_deleted', $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否被删除'));
        $this->addColumn('dev_users', 'email', $this->string(100)->notNull()->defaultValue('')->comment('邮箱'));
        $this->addColumn('dev_users', 'gender', $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('性别'));
        $this->addColumn('dev_users', 'mobile', $this->string(100)->notNull()->defaultValue('')->comment('手机'));
        $this->addColumn('dev_users', 'avatar', $this->string(200)->notNull()->defaultValue('')->comment('头像地址'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('dev_users', 'is_deleted');
        $this->dropColumn('dev_users', 'email');
        $this->dropColumn('dev_users', 'gender');
        $this->dropColumn('dev_users', 'mobile');
        $this->dropColumn('dev_users', 'avatar');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m191017_144123_add_column_on_devs_table cannot be reverted.\n";

        return false;
    }
    */
}
