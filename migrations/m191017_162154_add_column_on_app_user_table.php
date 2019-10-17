<?php

use yii\db\Migration;

/**
 * Class m191017_162154_add_column_on_app_user_table
 */
class m191017_162154_add_column_on_app_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('dev_app_users', 'is_deleted', $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否被删除'));
        $this->addColumn('dev_app_users', 'email', $this->string(100)->notNull()->defaultValue('')->comment('邮箱'));
        $this->addColumn('dev_app_users', 'gender', $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('性别'));
        $this->addColumn('dev_app_users', 'mobile', $this->string(100)->notNull()->defaultValue('')->comment('手机'));
        $this->addColumn('dev_app_users', 'avatar', $this->string(200)->notNull()->defaultValue('')->comment('头像地址'));
        $this->addColumn('dev_app_users', 'address', $this->string(255)->notNull()->defaultValue('')->comment('地址'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('dev_app_users', 'is_deleted');
        $this->dropColumn('dev_app_users', 'email');
        $this->dropColumn('dev_app_users', 'gender');
        $this->dropColumn('dev_app_users', 'mobile');
        $this->dropColumn('dev_app_users', 'avatar');
        $this->dropColumn('dev_app_users', 'address');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m191017_162154_add_column_on_app_user_table cannot be reverted.\n";

        return false;
    }
    */
}
