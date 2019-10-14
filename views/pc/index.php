<?php

use app\assets\AppAsset;
use app\assets\ElementUI;
use yii\helpers\Html;

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
        <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon"/>
        <?php $this->head() ?>
    </head>
    <?php $this->beginBody() ?>
    <style>
        .el-header, .el-footer {
            color: #333;
            text-align: center;
            line-height: 60px;
        }

        .el-aside {
            color: #333;
            text-align: center;
            line-height: 200px;
        }

        .el-main {
            color: #333;
            text-align: center;
            line-height: 60px;
        }

        body > .el-container {
            margin-bottom: 40px;
        }

        .el-container:nth-child(5) .el-aside,
        .el-container:nth-child(6) .el-aside {
            line-height: 260px;
        }

        .el-container:nth-child(7) .el-aside {
            line-height: 320px;
        }

        .header-img {
            background-image: url("https://img02.hua.com/pc/images/pc_newuser_order_100.png");
        }

        .el-row {
            margin-bottom: 20px;

        &
        :last-child {
            margin-bottom: 0;
        }

        }
        .el-col {
            border-radius: 4px;
        }

        .bg-purple-dark {
            background: #f2f2f2;
        }

        .grid-content {
            border-radius: 4px;
            min-height: 36px;
        }

        .row-bg {
            padding: 10px 0;
            background-color: #f9fafc;
        }

        .link-balance {
            display: flex;
            padding-top: 15px;
            padding-bottom: 15px;
            align-items: center;
            flex-direction: row;
            justify-content: space-around;
        }

        .link-balance__left a {
            padding-left: 10px;
            color: #737373;
        }

        .link-balance__right a {
            padding-left: 10px;
            /*color: #737373;*/
        }

        .link-balance__middle {
            display: flex;
            flex-direction: row;
            align-items: center;
        }

        .search-input {
        }

        .search-button {
            /*margin-left: 10px;*/
        }

        .el-carousel__item h3 {
            color: #475669;
            font-size: 18px;
            opacity: 0.75;
            line-height: 300px;
            margin: 0;
        }

        .el-carousel__indicators {
            /*height: 100px;*/
        }

        .el-carousel__item .card-img {
            width: 100%;
            height: 100%;
        }

        .el-carousel__item:nth-child(2n) {
            background-color: #99a9bf;
        }

        .el-carousel__item:nth-child(2n+1) {
            background-color: #d3dce6;
        }

        .goodsImg {
            padding: 15px;
            width: 220px;
            height: 220px;
        }

        .goods-balance__item {
            display: flex;
            flex-direction: column;
            border: 1px solid #e5e5e5;
        }

        .goods-balance {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
        }

        .goods-info {
            display: flex;
            flex-direction: column;
            line-height: 30px;
        }

    </style>
    <div id="app">
        <!--    首页容器布局-->
        <el-container>
            <el-header class="header-img" height="80px">
                <!--            <img class="header-img" src="" alt="">-->
            </el-header>
            <!--        <el-row>-->
            <el-col :span="24">
                <div class="grid-content bg-purple-dark">
                    <div class="link-balance">
                        <div class="link-balance__left">
                            <el-button type="text" @click="">一起逛逛鲜花网</el-button>
                            <el-button type="text" @click="dialogVisible = true">联系我们</el-button>

                            <el-dialog
                                    title="客服信息"
                                    :visible.sync="dialogVisible"
                                    width="30%"
                                    :before-close="handleClose">
                                <p>客服微信: 123456</p>
                                <p>客服电话: 13000000000</p>
                                <span slot="footer" class="dialog-footer">
                            <el-button @click="dialogVisible = false">取 消</el-button>
                            <el-button type="primary" @click="dialogVisible = false">确 定</el-button>
                        </span>
                            </el-dialog>
                        </div>
                        <div class="link-balance__middle">
                            <el-input
                                    class="search-input"
                                    placeholder="请输入商品名称"
                                    prefix-icon="el-icon-search"
                                    v-model="input2"
                            >
                            </el-input>
                            <el-button type="primary" class="search-button">搜索</el-button>
                        </div>
                        <div class="link-balance__right">
                            <el-button type="text" @click="loginUser">您好，请登录</el-button>
                            <el-button type="text" @click="registerUser">注册</el-button>
                            <el-button type="text" @click="">我的订单</el-button>
                            <el-button type="text" @click="">购物车</el-button>
                            <el-button type="text" @click="">我的信息</el-button>
                            <el-button type="text" @click="">中国鲜花礼品网:中国鲜花网领先品牌</el-button>
                        </div>
                        <el-dialog
                                title="用户注册"
                                :visible.sync="dialogRegisterVisible"
                                width="30%"
                                center
                                :before-close="handleClose"
                        >
                            <el-form :model="form" :rules="registerRules" ref="form">
                                <el-form-item label="账号" :label-width="formLabelWidth" prop="username">
                                    <el-input v-model="form.username" autocomplete="off"></el-input>
                                </el-form-item>
                                <el-form-item label="密码" :label-width="formLabelWidth" prop="password">
                                    <el-input type="password" v-model="form.password" autocomplete="off"></el-input>
                                </el-form-item>
                                <el-form-item label="确认密码" :label-width="formLabelWidth" prop="checkPass">
                                    <el-input type="password" v-model="form.checkPass"></el-input>
                                </el-form-item>
                            </el-form>
                            <span slot="footer" class="dialog-footer">
                          <el-tooltip class="item" effect="dark" :content="loginTip" placement="bottom">
                                    <el-button type="primary" @click="submitForm('form')">注册</el-button>
                                </el-tooltip>
                                <el-button @click="resetForm('form')">重置</el-button>
                        </span>
                        </el-dialog>
                    </div>
                </div>
            </el-col>
            <!--        </el-row>-->
            <el-container>
                <el-aside width="200px">
                    <el-menu :default-openeds="['1', '3']">
                        <el-submenu index="1">
                            <template slot="title"><i class="el-icon-message"></i>商品分类一</template>
                            <el-menu-item-group>
                                <template slot="title">分组一</template>
                                <el-menu-item index="1-1">玫瑰花</el-menu-item>
                                <el-menu-item index="1-2">百合</el-menu-item>
                            </el-menu-item-group>
                            <el-menu-item-group title="分组2">
                                <el-menu-item index="1-3">紫荆花</el-menu-item>
                            </el-menu-item-group>
                        </el-submenu>
                        <el-submenu index="2">
                            <template slot="title"><i class="el-icon-menu"></i>商品分类二</template>
                            <el-menu-item-group>
                                <template slot="title">分组一</template>
                                <el-menu-item index="2-1">玫瑰花</el-menu-item>
                                <el-menu-item index="2-2">百合</el-menu-item>
                            </el-menu-item-group>
                            <el-menu-item-group title="分组2">
                                <el-menu-item index="2-3">紫荆花</el-menu-item>
                            </el-menu-item-group>
                            <el-submenu index="2-4">
                                <template slot="title">选项4</template>
                                <el-menu-item index="2-4-1">桃花</el-menu-item>
                            </el-submenu>
                        </el-submenu>
                        <el-submenu index="3">
                            <template slot="title"><i class="el-icon-setting"></i>商品分类三</template>
                            <el-menu-item-group>
                                <template slot="title">分组一</template>
                                <el-menu-item index="3-1">玫瑰花</el-menu-item>
                                <el-menu-item index="3-2">百合花</el-menu-item>
                            </el-menu-item-group>
                            <el-menu-item-group title="分组2">
                                <el-menu-item index="3-3">紫荆花</el-menu-item>
                            </el-menu-item-group>
                            <el-submenu index="3-4">
                                <template slot="title">选项4</template>
                                <el-menu-item index="3-4-1">选项4-1</el-menu-item>
                            </el-submenu>
                        </el-submenu>
                    </el-menu>
                </el-aside>
                <el-container>
                    <el-main height="500px">
                        <!-- 轮播图-->
                        <template>
                            <el-carousel :interval="3000" type="card" height="200px">
                                <el-carousel-item v-for="item in cards" :key="item">
                                    <img :src="item" class="card-img" alt="">
                                </el-carousel-item>
                            </el-carousel>
                        </template>
                        <!-- 商品图片-->
                        <div class="goods-balance">
                            <div class="goods-balance__item" v-for="item in goodsImgs">
                                <img :src="item" alt="" class="goodsImg">
                                <div class="goods-info">
                                    <span>爱情.玫瑰花</span>
                                    <span style="color: #0C0C0C;font-weight: bold">￥ 99</span>
                                    <span>已售出1.9万件</span>
                                </div>
                            </div>
                        </div>
                    </el-main>
                    <el-footer>
                        花礼网 （中国鲜花礼品网） xxxxx版权 中国鲜花网领先品牌，鲜花速递专家！
                    </el-footer>
                </el-container>
            </el-container>
        </el-container>

    </div>
    </html>
    <script>
        new Vue({
            el: '#app',
            data() {
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
                        if (this.form.checkPass !== '') {
                            this.$refs.form.validateField('checkPass');
                        }
                        callback();
                    }
                };
                var validateCheckPass = (rule, value, callback) => {
                    if (value === '') {
                        callback(new Error('请再次输入密码'));
                    } else if (value !== this.form.password) {
                        callback(new Error('两次输入密码不一致!'));
                    } else {
                        callback();
                    }
                };
                return {
                    dialogVisible: false,
                    dialogRegisterVisible: false,
                    input2: '',
                    formLabelWidth: '120px',
                    form: {
                        username: '',
                        password: '',
                        checkPass: ''
                    },
                    //校验规则
                    registerRules: {
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
                    },
                    loginTip: '请先登录账号进行购物哦',
                    cards: [
                        'https://img0.utuku.china.com/630x0/hyzx/20180706/7e91f834-91a9-41b8-8b0d-0765e681fe79.png',
                        'http://img.chinait.com/2019/03/46-2.png',
                        'http://5b0988e595225.cdn.sohucs.com/images/20180103/d78d9fd7150543cbb5821a3c3b008294.jpeg'
                    ],
                    goodsImgs: [
                        'https://img0.utuku.china.com/630x0/hyzx/20180706/7e91f834-91a9-41b8-8b0d-0765e681fe79.png',
                        'https://img01.hua.com/uploadpic/newpic/9010011.jpg_220x240.jpg',
                        'https://img01.hua.com/uploadpic/newpic/9012154.jpg_220x240.jpg',
                        'https://img01.hua.com/uploadpic/newpic/9010966.jpg_220x240.jpg',
                        'https://img01.hua.com/uploadpic/newpic/9012243.jpg_220x240.jpg',
                        'https://img0.utuku.china.com/630x0/hyzx/20180706/7e91f834-91a9-41b8-8b0d-0765e681fe79.png',
                        'https://img01.hua.com/uploadpic/newpic/9012228.jpg_220x240.jpg',
                        'http://5b0988e595225.cdn.sohucs.com/images/20180103/d78d9fd7150543cbb5821a3c3b008294.jpeg'
                    ],
                };
            },
            methods: {
                registerSuccess() {
                    this.$notify({
                        title: '注册成功',
                        message: '请登录账号进行购物哦',
                        type: 'success',
                    });
                },
                handleClose(done) {
                    this.$confirm('确认关闭？')
                        .then(_ => {
                            done();
                        })
                        .catch(_ => {
                        });
                },
                alertMessage(msg, close, type) {
                    this.$message({
                        showClose: close,
                        message: msg,
                        type: type
                    });
                },
                //注册用户
                registerUser() {
                    console.log('注册用户')
                    this.dialogRegisterVisible = true
                },
                //登录用户
                loginUser() {
                },
                //提交表单
                submitForm(formName) {
                    let loginUrl = '<?php echo \yii\helpers\Url::toRoute('pc/register');?>';
                    const postdata = {
                        username: this.form.username,
                        password: this.form.password,
                    };
                    this.$refs[formName].validate((valid) => {
                        if (valid) {
                            console.log('res', valid)
                            console.log('--注册接口--')
                            let param = new URLSearchParams();
                            param.append('username', postdata.username);
                            param.append('password', postdata.password);
                            console.warn('注册提交过去的参数', postdata)
                            axios.post(loginUrl, param)
                                .then(response => {
                                    console.log('success', response);
                                    if (response.data.code === 100) {
                                        this.registerSuccess();
                                        // TODO 引导用户登录咯

                                    } else if (response.data.code === -100) {
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
        })
    </script>
<?php $this->endBody() ?>
    </html>
<?php $this->endPage() ?>