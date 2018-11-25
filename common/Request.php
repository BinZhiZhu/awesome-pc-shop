<?php

namespace app\common;

use Yii;

class Request extends \yii\web\Response
{
    public static function getApiPostParam($param)
    {
        if (!$param) {
            return false;
        }

        $param = trim($param);

        return Yii::$app->request->post($param);

    }

    public static function getApiGetParam($param)
    {
        if (!$param) {
            return false;
        }

        $param = trim($param);

        return Yii::$app->request->get($param);

    }

    //todo  封装response
    public static function formatResponse($obj)
    {
        if (!$obj) {
            return null;
        }

        if ($obj && is_array($obj)) {

            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'message' => '',
                    'code' => '',
                ]
            ]);
        }


    }

}