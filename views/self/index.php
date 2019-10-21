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

            }
        },
        methods: {
        }
    });
</script>

<?php $this->endBody() ?>
</html>
<?php $this->endPage() ?>
