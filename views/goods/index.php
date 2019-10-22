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
        .avatar-uploader .el-upload {
            width: 178px;
            height: 178px;
            border: 1px dashed #d9d9d9;
            border-radius: 6px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .avatar-uploader .el-upload:hover {
            border-color: #409EFF;
        }
        .avatar-uploader-icon {
            font-size: 28px;
            color: #8c939d;
            width: 178px;
            height: 178px;
            line-height: 178px;
            text-align: center;
        }
        .avatar {
            width: 178px;
            height: 178px
            display: block;
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
        <el-form-item label="商品图片" prop="thumb">
        <el-upload
            class="avatar-uploader"
            action="https://jsonplaceholder.typicode.com/posts/"
            :show-file-list="false"
            :on-success="handleAvatarSuccess"
            :before-upload="beforeAvatarUpload">
            <img v-if="ruleForm.thumb" :src="ruleForm.thumb" class="thumb">
            <i v-else class="el-icon-plus avatar-uploader-icon"></i>
        </el-upload>
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
                    sell_num: 0,
                    price: 0,
                    thumb: ''
                },
                imageUrl: '',
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
                    thumb: [
                        { required: true, message: '请上传商品图片', trigger: 'blur' },
                    ],
                    stock: [
                        { required: true, message: '请设置商品库存', trigger: 'blur' },
                    ],
                },
                labelPosition: 'right',
            }
        },
        methods: {
            handleAvatarSuccess(res, file) {
                console.log('handleAvatarSuccess', res, file)
                this.ruleForm.thumb = URL.createObjectURL(file.raw);
                console.log('imageUrl', this.ruleForm.ruleForm)
                //生成了blob文件
                let url = '<?php echo \yii\helpers\Url::toRoute('pc/upload');?>';
                const data = new FormData();
                data.append('file',file.raw);
                axios.post(url,data)
                    .then(response => {
                        console.log('获取图片上传结果', response.data);
                        const resp = response.data.result;
                        this.imageUrl = resp.url
                    })
                    .catch(error => {
                        console.log(error)
                    });
            },
            beforeAvatarUpload(file) {
                console.log('beforeAvatarUpload',file)
                const isJPG = file.type === 'image/jpeg';
                const isLt2M = file.size / 1024 / 1024 < 2;

                if (!isJPG) {
                    this.$message.error('上传头像图片只能是 JPG 格式!');
                }
                if (!isLt2M) {
                    this.$message.error('上传头像图片大小不能超过 2MB!');
                }
                return isJPG && isLt2M;
            },
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
                        const postData = this.ruleForm
                        postData.thumb = this.imageUrl
                        console.log('postData',postData)
                        let url = '<?php echo \yii\helpers\Url::toRoute('goods/add');?>';
                        let param = new URLSearchParams();
                        param.append('title',postData.title);
                        param.append('subtitle',postData.subtitle);
                        param.append('price',postData.price);
                        param.append('stock',postData.stock);
                        param.append('sell_num',postData.sell_num);
                        param.append('thumb',postData.thumb);
                        axios.post(url,param)
                            .then(response => {
                                console.log('获取发布商品结果', response.data);
                                if(response.data.code === 200){
                                    const resp = response.data.result;
                                    this.$notify({
                                        title: response.data.message,
                                        message: '快去商品列表查看吧',
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
