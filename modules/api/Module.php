<?php

namespace modules\api;

use modules\api\components\JsonRpcServer;

use Exception;

class Module extends \yii\base\Module
{

    /**
     * @var array 过程处理类列表
     */
    public $procedureClasses = [];

    /**
     * 执行接口方法
     *
     * @param string $method
     * @param mixed $params
     * @return mixed
     * @throws Exception
     */
    public function call($method, $params = []) {
        $server = JsonRpcServer::getInstance();
        return $server->getProcedureHandler()->executeProcedure($method, $params);
    }
}
