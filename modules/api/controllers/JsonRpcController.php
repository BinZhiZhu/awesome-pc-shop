<?php

namespace modules\api\controllers;

use modules\api\components\JsonRpcProcedureHandler;
use modules\api\components\JsonRpcServer;
use Yii;
use yii\web\Controller;

class JsonRpcController extends Controller
{

    /**
     * @var bool 禁止CSRF校验
     */
    public $enableCsrfValidation = false;

    /**
     * @return JsonRpcServer
     * @throws \Exception
     */
    protected function getServer()
    {
        $server = JsonRpcServer::getInstance();
        $server->compactGetPayload();
        $server->autoAuth();
        return $server;
    }

    /**
     * RPC服务实例
     *
     * @return array|mixed|string
     * @throws \Exception
     */
    public function actionIndex()
    {
        if (Yii::$app->request->isOptions)
        {
            return '';
        }
        $language = Yii::$app->request->get('__language');
        if($language === 'EN'){
            Yii::$app->language = 'en-US';
        }
        // 兼容旧的客户ID获取方式
        global $_W;
        if (Yii::$app->request->get('i')) {
            Request::getInstance()->uniacid = $_W['uniacid'] = (int) Yii::$app->request->get('i');
            $_W['uniaccount'] = $_W['account'] = uni_fetch($_W['uniacid']);
            $_W['acid'] = (int) $_W['uniaccount']['acid'];
//            $_W['shopset'] = \common\models\ShopSysSet::getByKey();
            $global_set = m('cache')->getArray('globalset');

            if (empty($global_set)) {
                $global_set = m('common')->setGlobalSet();
            }

            if (!is_array($global_set)) {
                $global_set = array();
            }

            empty($global_set['trade']['credittext']) && $global_set['trade']['credittext'] = '积分';
            empty($global_set['trade']['moneytext']) && $global_set['trade']['moneytext'] = '余额';
            $GLOBALS['_S'] = $_W['shopset'] = $global_set;
        }

        $server = $this->getServer();
        // 执行
        $result = $server->execute();
        $result = json_decode($result, true);

        $callback = Yii::$app->request->get('callback');
        if ($callback) {
            Yii::$app->response->format = Response::FORMAT_JSONP;
            return [
                'callback' => $callback,
                'data' => $result,
            ];
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        return $result;
    }

    /**
     * 生成当前所有接口的文档
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function actionDoc()
    {
        $server = $this->getServer();

        /** @var JsonRpcProcedureHandler $procedureHandler */
        $procedureHandler = $server->getProcedureHandler();

        $list = $procedureHandler->getProcedureList();

        if (Yii::$app->request->get('json')) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $list;
        }

        return $this->render('doc', [
            'list' => $list,
        ]);
    }
}
