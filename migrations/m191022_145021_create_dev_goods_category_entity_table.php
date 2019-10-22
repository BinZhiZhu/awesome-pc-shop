<?php

use yii\db\Migration;

/**
 * Handles the creation of table `dev_goods_category_entity`.
 */
class m191022_145021_create_dev_goods_category_entity_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('dev_goods_category_entity', [
            'id' => $this->primaryKey(),
            'created_at'=>$this->integer(11)->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at'=>$this->integer(11)->notNull()->defaultValue(0)->comment('更新时间'),
            'created_by'=>$this->integer(11)->notNull()->defaultValue(0)->comment('创建人ID'),
            'title'=>$this->string(255)->notNull()->defaultValue('')->comment('分类名称'),
            'status'=>$this->tinyInteger(1)->notNull()->defaultValue(0)->comment('状态'),
            'is_deleted'=>$this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否被删除'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('dev_goods_category_entity');
    }
}
