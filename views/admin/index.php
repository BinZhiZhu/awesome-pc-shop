<?php

use app\assets\AppAsset;
use app\assets\ElementUI;
use yii\helpers\Html;
\app\assets\Layui::register($this);
AppAsset::register($this);
ElementUI::register($this);

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
    <style>
        .user{
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-left: 20px;
            padding-right: 20px;
            padding-bottom: 20px;
        }
        .user-info{
            display: flex;
            flex-direction: row;
            padding-top: 20px;
            padding-bottom: 20px;
        }
        .username{
            padding-left: 10px;
        }
        .username-label{

        }
    </style>
</head>
<?php $this->beginBody() ?>
<body class="layui-layout-body">
<div class="layui-layout layui-layout-admin" id="app">
    <div class="layui-header">
        <div class="layui-logo">后台销售系统</div>
        <!-- 头部区域（可配合layui已有的水平导航） -->
        <ul class="layui-nav layui-layout-left">
<!--            <li class="layui-nav-item"><a href="">控制台</a></li>-->
<!--            <li class="layui-nav-item"><a href="">商品管理</a></li>-->
<!--            <li class="layui-nav-item"><a href="--><?php //echo \yii\helpers\Url::to(['site/index'])?><!--">用户</a></li>-->
<!--            <li class="layui-nav-item">-->
<!--                <a href="javascript:;">其它系统</a>-->
<!--                <dl class="layui-nav-child">-->
<!--                    <dd><a href="">邮件管理</a></dd>-->
<!--                    <dd><a href="">消息管理</a></dd>-->
<!--                    <dd><a href="">授权管理</a></dd>-->
<!--                </dl>-->
<!--            </li>-->
        </ul>
        <ul class="layui-nav layui-layout-right">
            <li class="layui-nav-item">
                <a href="javascript:;">
                    <img src="http://t.cn/RCzsdCq" class="layui-nav-img">
                    <?php echo $user['username']; ?>
                </a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript::"><el-button type="text" @click="drawer = true">基本资料</el-button></a></dd>
<!--                    <dd><a href="javascript::"><el-button type="text">安全设置</el-button></a></dd>-->
                </dl>
            </li>
            <li class="layui-nav-item"><a href="<?php echo \yii\helpers\Url::to(['user/login-out'])?>">退出</a></li>
        </ul>
    </div>

    <div class="layui-side layui-bg-black">
        <div class="layui-side-scroll">
            <!-- 左侧导航区域（可配合layui已有的垂直导航） -->
            <ul class="layui-nav layui-nav-tree"  lay-filter="test">
                <?php if($user['role'] == \app\enums\RoleTypeEnum::ADMIN) { ?>
                    <li class="layui-nav-item layui-nav-itemed">
                    <a class="" href="javascript:;">用户管理</a>
                    <dl class="layui-nav-child">
                        <dd><a href="<?php echo \yii\helpers\Url::to(['backend/index'])?>" target="mainFrame">后台用户列表</a></dd>
                        <dd><a href="<?php echo \yii\helpers\Url::to(['backend/pc'])?>" target="mainFrame">前台用户列表</a></dd>
                    </dl>
                </li>
                 <li class="layui-nav-item">
                    <a href="javascript:;">订单管理</a>
                    <dl class="layui-nav-child">
                        <dd><a href="javascript:;">订单列表</a></dd>
                    </dl>
                </li>
           <?php } ?>
                <?php if($user['role'] == \app\enums\RoleTypeEnum::MERCHANT) { ?>
                <li class="layui-nav-item">
                    <a href="javascript:;">商品管理</a>
                    <dl class="layui-nav-child">
                        <dd><a href="javascript:;">发布商品</a></dd>
                    </dl>
                </li>
            <?php } ?>
                <li class="layui-nav-item"><a href="">帮助</a></li>
                <li class="layui-nav-item"><a href="">关于我们</a></li>
            </ul>
        </div>
    </div>

    <div class="layui-body">
        <!-- 内容主体区域 -->
<!--        <div style="padding: 15px;">内容主体区域</div>-->
        <iframe id="mainFrame" name="mainFrame" src="<?php echo $user['role'] == \app\enums\RoleTypeEnum::ADMIN ? \yii\helpers\Url::to(['backend/index']) : \yii\helpers\Url::to(['main/index'])?>" style="overflow: visible;" scrolling="auto" frameborder="no" width="100%" height="100%"></iframe>

    </div>

    <div class="layui-footer">
        <!-- 底部固定区域 -->
        © 版权归 鲜花网 所有
    </div>
    <el-drawer
            title="管理员信息"
            :visible.sync="drawer"
            :direction="direction"
          >
        <div class="user">
            <div class="user-info">
                <div class="username-label">管理员编号：</div>
                <div class="username"><?php echo $user['id'] ?></div>
            </div>
            <div class="user-info">
                <div class="username-label">管理员账号：</div>
                <div class="username"><?php echo $user['username'] ?></div>
            </div>
            <div class="user-info">
                <div class="username-label">管理员角色：</div>
                <div class="username"><?php echo  $user['role_name']?></div>
            </div>
            <div class="user-info">
                <div class="username-label">登录次数：</div>
                <div class="username"><?php echo  $user['login_count']?> 次</div>
            </div>
            <div class="user-info">
                <div class="username-label">注册时间：</div>
                <div class="username"><?php echo  $user['register_time'] ?></div>
            </div>
            <div class="user-info">
                <div class="username-label">最近登录时间：</div>
                <div class="username"><?php echo  $user['visit_time'] ?></div>
            </div>
            <div class="user-info">
                <div class="username-label">域名信息：</div>
                <div class="username"><?php echo  $user['host_info'] ?></div>
            </div>
        </div>
    </el-drawer>
</div>
<script src="https://www.layuicdn.com/layui/layui.js"></script>
<script>
    //JavaScript代码区域
    layui.use('element', function(){
        var element = layui.element;

    });
</script>
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
                drawer: false,
                direction: 'rtl',
            }
        },
        methods: {
            handleClose(done) {
                this.$confirm('确认关闭？')
                    .then(_ => {
                        done();
                    })
                    .catch(_ => {});
            }
        }
    });
</script>
</body>
</html>
<?php $this->endBody() ?>
    </html>
<?php $this->endPage() ?>