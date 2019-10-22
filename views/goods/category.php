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
            <el-breadcrumb-item>添加商品分类</el-breadcrumb-item>
        </el-breadcrumb>
    </div>
    <el-form :label-position="labelPosition" label-width="100px"  :model="ruleForm" :rules="rules" ref="ruleForm">
        <el-form-item label="分类名称" prop="title">
            <el-input v-model="ruleForm.title"></el-input>
        </el-form-item>
        <el-form-item align="center">
            <el-button type="primary" @click="submitForm('ruleForm')">添加分类</el-button>
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
                },
                imageUrl: '',
                rules: {
                    title: [
                        { required: true, message: '请输入商品标题', trigger: 'blur' },
                    ]
                },
                labelPosition: 'right',
            }
        },
        methods: {
            alertMessage(msg, close, type) {
                this.$message({
                    showClose: close,
                    message: msg,
                    type: type
                });
            },
            submitForm(formName) {
                this.$refs[formName].validate((valid) => {
                    if (valid) {
                        let url = '<?php echo \yii\helpers\Url::toRoute('goods/add-category');?>';
                        let param = new URLSearchParams();
                        param.append('title',this.ruleForm.title);
                        axios.post(url,param)
                            .then(response => {
                                console.log('添加商品分类结果', response.data);
                                if(response.data.code === 200){
                                    const resp = response.data.result;
                                    this.$notify({
                                        title: response.data.message,
                                        message: '快去商品分类查看吧',
                                        type: 'success',
                                    });
                                }else {
                                    this.alertMessage(response.data.message, true, 'error');
                                }
                            })
                            .catch(error => {
                                console.log(error)
                            });
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
