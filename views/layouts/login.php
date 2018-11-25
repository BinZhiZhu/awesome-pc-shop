<?php

/* @var $this \yii\web\View */
$this->title = 'Admin管理系统';

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
    <link rel="shortcut icon" href="<?php echo Yii::$app->getHomeUrl(); ?>/favicon.ico" type="image/x-icon"/>
    <?php $this->head() ?>
    <style>
        *{margin: 0px;padding: 0px;}
        body{
            background-color: #f2f2f2;
            overflow-y: hidden;
        }
        .login-box{
            text-align: center;
            margin: 50px auto;
            width: 300px;
        }

        .user-login-header {
            padding: 15px;
        }

        .user-login-form {

        }

    </style>
</head>
<body>
<?php $this->beginBody() ?>
<div class="login-box" id="app">
    <template>
        <div class="user-login-header">
            <h2>ElementuiAdmin</h2>
            <p>基于Vue+Element-ui实现的后台管理系统</p>
        </div>
    </template>
    <template>
        <div class="user-login-form">
            <el-form :model="loginForm" status-icon :rules="loginRules" ref="loginForm" label-width="68px">
                <el-form-item label="账号" prop="username">
                    <el-input type="text" v-model="loginForm.username"></el-input>
                </el-form-item>
                <el-form-item label="密码" prop="password">
                    <el-input type="password" v-model="loginForm.password"></el-input>
                </el-form-item>
                <el-form-item label="确认密码" prop="checkPass">
                    <el-input type="password" v-model="loginForm.checkPass"></el-input>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="submitForm('loginForm')">登录</el-button>
                    <el-button @click="resetForm('loginForm')">重置</el-button>
                </el-form-item>
            </el-form>
        </div>
    </template>

</div>
<script>
    new Vue({
        el: '#app',
        data() {
            //定义校验规则 validate
            var validateUsername =(rule,value,callback)=> {
                if (value ===''){
                    callback(new Error('请输入账号'));
                }else if(value.length <5){
                    callback(new Error('账号不能少于六位数'));
                    //todo  可以加正则匹配 账号只能是字母+数字组合
                } else{
                    //todo 规则
                    callback();
                }
            };
            var validatePassword = (rule, value, callback) => {
                if (value === '') {
                    callback(new Error('请输入密码'));
                } else {
                    if (this.loginForm.checkPass !== '') {
                        this.$refs.loginForm.validateField('checkPass');
                    }
                    callback();
                }
            };
            var validateCheckPass = (rule, value, callback) => {
                if (value === '') {
                    callback(new Error('请再次输入密码'));
                } else if (value !== this.loginForm.password) {
                    callback(new Error('两次输入密码不一致!'));
                } else {
                    callback();
                }
            };
            return {
                loginForm: {
                    username: '',
                    password: '',
                    checkPass: ''
                },
                //校验规则
                loginRules: {
                    username: [
                        {
                            validator: validateUsername,
                            trigger: 'blur'
                        }
                    ],
                    password: [
                        {
                            validator: validatePassword,
                            trigger: 'blur'
                        }
                    ],
                    checkPass: [
                        {
                            validator: validateCheckPass,
                            trigger: 'blur'
                        }
                    ],
                }
            };
        },
        methods: {
            //提交表单
            submitForm(formName) {
               // let formData = this.loginForm;
                //formData = JSON.stringify(formData);
                let loginUrl = '<?php echo Yii::$app->urlManager->createUrl('login/login');?>'
                this.$refs[formName].validate((valid) => {
                    if (valid) {
                        console.log('res',valid)
                        //todo  接口
                        console.log('--登录接口--')
                       // alert('别催，再撸接口了！');
                        const postdata = {
                            username:this.loginForm.username,
                            password:this.loginForm.password,
                        };

                        let param = new URLSearchParams();
                        param.append('username',postdata.username);
                        param.append('password',postdata.password);
                        axios.post(loginUrl,param)
                            .then(response=> {
                                console.log(response);
                            })
                            .catch(response=>{
                                console.log(response);
                            });
                    } else {
                        console.log('error submit!!');
                        return false;
                    }
                });
            },
            //重置表单 移除校验结果
            resetForm(formName) {
                this.$refs[formName].resetFields();
            }
        }
    });
</script>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
