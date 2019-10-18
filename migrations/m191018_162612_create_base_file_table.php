<?php

use yii\db\Migration;

/**
 * Handles the creation of table `base_file`.
 */
class m191018_162612_create_base_file_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('base_file', [
            'id' => $this->primaryKey(),
            'created_at'=>$this->integer(11)->notNull()->defaultValue(0)->comment('创建时间'),
            'url'=>$this->text()->comment('文件地址'),
            'file_name'=>$this->string(200)->notNull()->defaultValue('')->comment('文件名称'),
            'app_user_id'=>$this->integer(11)->notNull()->defaultValue(0)->comment('前台用户ID'),
            'web_user_id'=>$this->integer(11)->notNull()->defaultValue(0)->comment('后台用户ID'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('base_file');
    }
}
