<?php

/* @var $this \yii\web\View */

/* @var $content string */

use yii\helpers\Html;
use app\assets\AppAsset;
use app\assets\ElementUI;

ElementUI::register($this);
AppAsset::register($this);
$this->off(\yii\web\View::EVENT_END_BODY, [\yii\debug\Module::getInstance(), 'renderToolbar']);

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
        * {
            margin: 0;
            padding: 0;
        }

        #app, body, html {
            height: 100%;
            width: 100%;
        }

        body {
            background-color: #f2f2f2;
            overflow-y: hidden;
        }

        .login {
            background-image: url("https://uploadfile.huiyi8.com/2014/0708/20140708050046711.jpg");
            width: 100%;
            background-position: 50%;
            background-size: cover; /*铺满*/
            background-repeat: no-repeat;
            height: 100%;
            position: relative;
        }

        .login-box {
            color: #ffffff !important;
            position: relative;
            transform: translateY(-60%);
            right: 35px;
            top: 50%;
            margin: 0 auto;
            width: 300px;
        }

        .user-login-form {
            position: relative;
            right: 20px;
            top: 20px;
        }

        .user-login-header {
            position: relative;
            right: -30px;
            padding: 15px;
        }

        .login-btn {
            position: relative;
            width: 300px;
        }

        .dev-process {
            display: none;
            position: relative;
            width: 150px;
            left: 30px;
            top: 40%;
        }

        .el-form-item__label {
            color: #ffffff;
        }

    </style>
</head>
<?php $this->beginBody() ?>
<div id="app">
    <div class="login">
        <div class="login-box">
            <template>
                <div class="user-login-header">
                    <h2>商家后台管理系统</h2>
                    <p>这里是后台管理系统这里是后台管理系统</p>
                </div>
            </template>
            <template>
                <div class="user-login-form">
                    <el-form :model="loginForm" status-icon :rules="loginRules" ref="loginForm" label-width="100px">
                        <el-form-item label="账号" prop="username">
                            <el-input type="text" v-model="loginForm.username" autocomplete="off"></el-input>
                        </el-form-item>
                        <el-form-item label="角色" prop="role">
                        <el-select v-model="loginForm.role" placeholder="请选择">
                            <el-option
                                    v-for="item in options"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value">
                            </el-option>
                        </el-select>
                        </el-form-item>
                        <el-form-item label="密码" prop="password">
                            <el-input type="password" v-model="loginForm.password" autocomplete="off"></el-input>
                        </el-form-item>
                        <el-form-item label="确认密码" prop="checkPass">
                            <el-input type="password" v-model="loginForm.checkPass"></el-input>
                        </el-form-item>
                        <div class="login-btn">
                            <el-form-item>
                                <el-tooltip class="item" effect="dark" :content="loginTip" placement="bottom">
                                    <el-button type="primary" @click="submitForm('loginForm',0)">登录</el-button>
                                </el-tooltip>
<!--                                <el-button @click="resetForm('loginForm')">重置</el-button>-->
                                <el-tooltip class="item" effect="dark" :content="registerTip" placement="bottom">
                                    <el-button type="primary" @click="submitForm('loginForm',1)">注册</el-button>
                                </el-tooltip>
                            </el-form-item>
                        </div>
                    </el-form>
                </div>
            </template>
        </div>
        <div class="dev-process">
            <template>
                <el-tooltip class="item" effect="light" :content="tooltip" placement="top-start" width="150px">
                    <el-progress type="circle" :percentage="percentage" stroke-width="8"></el-progress>
                </el-tooltip>
            </template>
        </div>

    </div>
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
            //定义校验规则 validate
            var validateUsername = (rule, value, callback) => {
                if (value === '') {
                    callback(new Error('请输入账号'));
                } else if (value.length < 5) {
                    callback(new Error('账号不能少于六位数'));
                    //todo  可以加正则匹配 账号只能是字母+数字组合
                } else {
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
                    checkPass: '',
                    role: null
                },
                options: [
                    {
                        value: 0,
                        label: '商家'
                    },
                    {
                        value: 1,
                        label: '管理员'
                    }
                ],
                loginTip: '请填写账号密码，登录',
                registerTip: '请点击进行注册',
                tooltip: '当前开发进度为5%',
                percentage: '5',
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
            //登入成功提示
            loginSuccess() {
                this.$notify({
                    title: '登入成功',
                    message: '欢迎回来哦~',
                    type: 'success',
                });
            },

            alertMessage(msg, close, type) {
                this.$message({
                    showClose: close,
                    message: msg,
                    type: type
                });
            },

            //进入系统页提示
            initTip() {
                this.$notify({
                    title: '温馨提示',
                    message: '为了方便演示,用户名与密码可随意输入',
                    type: 'success'
                })
            },
            //提交表单
            submitForm(formName,type) {
                // let formData = this.loginForm;
                //formData = JSON.stringify(formData);
                let loginUrl = '<?php echo \yii\helpers\Url::toRoute('user/login');?>';
                let registerUrl = '<?php echo \yii\helpers\Url::toRoute('user/register');?>';

                if(this.loginForm.role === null){
                    this.alertMessage('请选择角色', true, 'error');
                }
                const postdata = {
                    username: this.loginForm.username,
                    password: this.loginForm.password,
                    role: this.loginForm.role,
                };
                this.$refs[formName].validate((valid) => {
                    if (valid) {
                        console.log('res', valid)
                        let param = new URLSearchParams();
                        param.append('username', postdata.username);
                        param.append('password', postdata.password);
                        param.append('role', postdata.role);
                        console.log('--登录接口--',postdata)
                        // type: 0 是登录场景 1 注册场景
                        var apiName = type === 0 ? loginUrl : registerUrl;
                        console.warn('请求接口',apiName)
                        axios.post(apiName, param)
                            .then(response => {
                                console.log('success', response);
                                if (response.data.code === 100) {
                                    switch (type) {
                                        case 0:
                                            this.loginSuccess();
                                            // this.$router.push({path:'site/index'});
                                            // window.location.href = 'http://binzhizhu.top';
                                            let link = '<?php echo \yii\helpers\Url::to(['admin/index'])?>';
                                            setTimeout(function () {
                                                window.location.href = link;
                                            }, 2000)
                                            break;
                                        case 1:
                                            //TODO 成功引导登录吧
                                            this.resetForm('loginForm')
                                            this.alertMessage(response.data.message, true, 'success');
                                            break;
                                    }
                                } else if (response.data.code !== 100) {
                                    this.alertMessage(response.data.message, true, 'error');
                                }
                            })
                            .catch(error => {
                                console.log(error)
                            });
                    } else {
                        console.log('res', valid)
                        console.log('--data--', postdata)

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
</html>
<?php $this->endPage() ?>
