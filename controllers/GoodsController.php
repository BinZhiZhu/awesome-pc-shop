<?php

namespace app\controllers;

use yii\web\Controller;

class GoodsController extends Controller
{

    public $layout = false;


    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionList()
    {
        return $this->render('list');
    }
}