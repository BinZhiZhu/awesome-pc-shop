<?php

namespace common\modules\api\controllers;

use common\components\Request;
use yii\web\Controller;

class WxappController extends Controller
{

    /**
     * 微信小程序webview-原生交互的代理接口
     */
    public function actionProxy()
    {
        return '';
        Request::getInstance()->isWeixinBrowser = true;

        $cmd = Request::getInstance()->get('cmd');
        $params = Request::getInstance()->get('params'); // $params 是 json字符串
        $params = json_decode($params, true);
        return $this->render('proxy', [
            'cmd' => $cmd,
            'params' => $params,
        ]);
    }
}
