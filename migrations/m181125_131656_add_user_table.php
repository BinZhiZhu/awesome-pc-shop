<?php

use yii\db\Migration;

/**
 * Class m181125_131656_add_user_table
 */
class m181125_131656_add_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC';
        $this->createTable('dev_users', [
            'id' => $this->primaryKey(11),
            'username' => $this->string(50)->notNull()->defaultValue('')->comment('用户姓名'),
            'password' => $this->string(20)->notNull()->defaultValue('')->comment('用户密码'),
            'salt' => $this->string(20)->notNull()->defaultValue('')->comment('加密盐'),
            'status' => $this->integer(11)->notNull()->defaultValue(0)->comment('状态'),
            'register_ip' => $this->string(20)->notNull()->defaultValue('')->comment('注册ip'),
            'lastvisit_time' => $this->integer(11)->notNull()->defaultValue(0)->comment('最后一次访问时间'),
            'lastvisit_ip' => $this->string(20)->notNull()->defaultValue('')->comment('最后一次访问ip'),
            'register_time' => $this->integer(10)->notNull()->defaultValue(0)->comment('注册时间'),
        ], $tableOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('dev_users');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m181125_131656_add_user_table cannot be reverted.\n";

        return false;
    }
    */
}
