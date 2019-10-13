<?php

namespace app\controllers;

use yii\web\Controller;
use Yii;
use Casbin\Enforcer;

class AdminController extends Controller
{
    public $layout = false;

    public function actionIndex()
    {
        $path =realpath(__DIR__ . '/../config/casbin');
        $modelPath = $path.'/model.conf';
        $policyPath = $path .'/policy.csv';
        $casbin = new Enforcer($modelPath,$policyPath);
        $sub = 'alice'; // the user that wants to access a resource.
        $obj = 'data1'; // the resource that is going to be accessed.
        $act = 'read'; // the operation that the user performs on the resource.

        if (true === $casbin->enforce($sub, $obj, $act)) {
            // permit alice to read data1x
        } else {
            // deny the request, show an error
            throw new \Exception('deny the request');
        }

        Yii::$app->view->title = '后台管理系统';
        $host = Yii::$app->request->getAbsoluteUrl();//绝对路径
        return $this->render('index',[
            'host'=>$host
        ]);
    }

}