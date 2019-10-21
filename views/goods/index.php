<?php

/* @var $this \yii\web\View */

/* @var $content string */

use yii\helpers\Html;
use app\assets\AppAsset;
use app\assets\ElementUI;

ElementUI::register($this);
AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <!--    <link rel="shortcut icon" href="--><? //=$host  ?><!--/favicon.ico" type="image/x-icon"/>-->
    <?php $this->head() ?>
    <style>
        #app{
            padding: 20px;
        }
        .tab{
            padding-left: 10px;
            padding-bottom: 20px;
        }

    </style>
</head>
<?php $this->beginBody() ?>
<div id="app">
    <div class="tab">
        <el-breadcrumb separator-class="el-icon-arrow-right">
            <el-breadcrumb-item :to="{ path: '/' }">商品管理</el-breadcrumb-item>
            <el-breadcrumb-item>发布商品</el-breadcrumb-item>
        </el-breadcrumb>
    </div>
    <el-form :label-position="labelPosition" label-width="100px"  :model="ruleForm" :rules="rules" ref="ruleForm">
        <el-form-item label="商品标题" prop="title">
            <el-input v-model="ruleForm.title"></el-input>
        </el-form-item>
        <el-form-item label="商品副标题" prop="subtitle">
            <el-input v-model="ruleForm.subtitle"></el-input>
        </el-form-item>
        <el-form-item label="商品价格" prop="price">
            <el-input v-model="ruleForm.price"></el-input>
        </el-form-item>
        <el-form-item label="商品库存" prop="stock">
            <el-input v-model="ruleForm.stock"></el-input>
        </el-form-item>
        <el-form-item label="已售数量" >
            <el-input v-model="ruleForm.sell_num"></el-input>
        </el-form-item>
        <el-form-item align="center">
            <el-button type="primary" @click="submitForm('ruleForm')">发布商品</el-button>
            <el-button @click="resetForm('ruleForm')">重置</el-button>
        </el-form-item>
    </el-form>
</div>
<script>
    axios.defaults.baseURL = "";
    const router = new VueRouter({
        routes: [
            {path: '/site', name: 'indexPage'},
        ]
    });

    new Vue({
        el: '#app',
        router,
        data() {
            return {
                ruleForm: {
                    title: '',
                    subtitle: '',
                    stock: 0,
                    sell_numL: 0,
                    price: 0
                },
                rules: {
                    title: [
                        { required: true, message: '请输入商品标题', trigger: 'blur' },
                    ],
                    subtitle: [
                        { required: true, message: '请输入商品副标题', trigger: 'blur' },
                    ],
                    price: [
                        { required: true, message: '请输入商品价格', trigger: 'blur' },
                    ],
                    stock: [
                        { required: true, message: '请设置商品库存', trigger: 'blur' },
                    ],
                },
                labelPosition: 'right',
            }
        },
        methods: {
            submitForm(formName) {
                this.$refs[formName].validate((valid) => {
                    if (valid) {
                        alert('submit!');
                    } else {
                        console.log('error submit!!');
                        return false;
                    }
                });
            },
            resetForm(formName) {
                this.$refs[formName].resetFields();
            }
        }
    });
</script>

<?php $this->endBody() ?>
</html>
<?php $this->endPage() ?>
