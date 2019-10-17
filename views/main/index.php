<?php
use app\assets\AppAsset;
use yii\helpers\Html;
\app\assets\ElementUI::register($this);
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
    <?php $this->head() ?>
</head>
<?php $this->beginBody() ?>
<style>
    #app{
        padding-top: 40px;
        display: flex;
        justify-content: center;
    }
</style>
<div id="app">
    <el-link type="primary">欢迎[<?php echo $user['username']?>]回来,访问花卉后台销售系统，您已经累计登录  <?php echo $user['login_count']?> 次</el-link>
</div>
<script>
    new Vue({
        el: '#app',
        data() {
            return {

            }
        },

    })
</script>
<?php $this->endBody() ?>
</html>
<?php $this->endPage() ?>
