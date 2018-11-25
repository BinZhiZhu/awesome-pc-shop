<?php

namespace modules\api\components;

use modules\api\Module;
use Exception;
use JsonRPC\Server;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * JSON-RPC处理服务器
 *
 * @package common\modules\api\components
 */
class JsonRpcServer extends Server
{

    /**
     * @var static
     */
    public static $_instance;

    /**
     * @return JsonRpcServer
     */
    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self(
                '',
                [],
                null,
                null,
                null,
                new JsonRpcProcedureHandler
            );
            self::$_instance->bindObjects();
        }
        return self::$_instance;
    }

    /**
     * 强制覆写payload数据
     *
     * @param array $payload
     */
    public function setPayload($payload)
    {
        $this->payload = $payload;
    }

    /**
     * 兼容GET方法传入数据
     */
    public function compactGetPayload()
    {
        if (Request::getInstance()->isGet) {
            $payload = Request::getInstance()->get('__payload');
            if (!$payload) {
                $id = ArrayHelper::getValue($_GET, '__id', uniqid());
                $method = ArrayHelper::getValue($_GET, '__method');
                $payload = [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'method' => $method,
                    'params' => Yii::$app->request->get('__params'),
                ];
            }
            if (!is_array($payload)) {
                $payload = json_decode($payload, true);
            }
            $this->setPayload($payload);
        }
    }

    /**
     * 绑定方法
     */
    public function bindObjects()
    {
        $procedure = $this->getProcedureHandler();
        $module = Module::getInstance();
        foreach ($module->procedureClasses as $class) {
            if (class_exists($class)) {
                $procedure->withObject(Yii::createObject($class));
            } else {
                Yii::warning('找不到类：'.$class, __METHOD__);
            }
        }
    }

    /**
     * 自动检测授权
     *
     * @throws Exception
     */
    public function autoAuth()
    {
        AppUser::getInstance()->loginByOldCookie();

        // 如果header中有JWT，则直接检测
        $jwt = Request::getInstance()->headers->get('JWT');
        if ($jwt) {
            $userData = false;
            try {
                $userData = AppUser::getInstance()->verifyToken($jwt);
            } catch (Exception $e) {
                Yii::warning("解析JWT【{$jwt}】时发生错误：".(string)$e, __METHOD__);
            }
            $id = ArrayHelper::getValue($userData, 'id');
            if ($id) {
                $member = ShopMember::findOne($id);
                if ($member) {
                    AppUser::getInstance()->login($member);
                }
            }
        }

        // 不安全的校验，在旧版本里面，很多时候用户身份验证是靠openid的，这种方式十分不安全。
        // 我们计划在前后端分离完成后，正式废弃这种方法
        $openid = Request::getInstance()->headers->get('openid');
        if ($openid) {
            $member = ShopMember::getModel($openid);
            if ($member) {
                AppUser::getInstance()->login($member);
            }
        }
    }
}
