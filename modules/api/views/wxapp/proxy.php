<?php

use common\assets\WeixinJssdk;
use common\components\View;
use yii\helpers\Json;
use yii\web\JqueryAsset;

/** @var string $cmd */
/** @var array $params */
/** @var array params */
/** @var View $this */
WeixinJssdk::register($this);
JqueryAsset::register($this);

?>
<script type="text/javascript">
    wx.miniProgram.postMessage(<?php echo Json::encode([
        'cmd' => $cmd,
        'params' => $params,
    ]) ?>);
    wx.miniProgram.navigateBack();
</script>
