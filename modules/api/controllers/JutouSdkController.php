<?php

namespace common\modules\api\controllers;

use common\components\Request;
use yii\web\Controller;

class JutouSdkController extends Controller
{
    public function actionIndex()
    {
        Request::getInstance()->isWeixinBrowser = true;
        return $this->render('index');
    }
}
