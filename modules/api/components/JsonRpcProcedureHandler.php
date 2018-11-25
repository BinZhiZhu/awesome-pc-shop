<?php

namespace modules\api\components;

use Anodoc\Parser;
use Anodoc\Tags\Tag;
use Exception;
use JsonRPC\ProcedureHandler;
use ReflectionClass;
use ReflectionMethod;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class JsonRpcProcedureHandler extends ProcedureHandler
{

    /**
     * @param string $procedure
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function executeProcedure($procedure, array $params = [])
    {
        Yii::info('执行JsonRPC过程【{'.$procedure.'}】，参数：'. Json::encode($params), __METHOD__);
        try {
            // 这是旧的jsonrpc接口兼容，已经无用了
            if (strpos($procedure, ':') > 0) {
                $pairs = explode(':', $procedure, 2);
                $moduleName = array_shift($pairs);
                $availableModules = uni_modules();
                if (!isset($availableModules[$moduleName])) {
                    throw new Exception("当前用户不能使用{$moduleName}功能");
                }

                $className = ucfirst($moduleName).'ModuleRpc';
                if (!class_exists($className)) {
                    $file = IA_ROOT . "/addons/{$moduleName}/rpc.php";
                    if (!is_file($file)) {
                        throw new Exception("模块{$moduleName}未对外开放接口");
                    }
                    include $file;
                    if (!class_exists($className)) {
                        throw new Exception("模块{$moduleName}的RPC服务异常");
                    }
                }

                $obj = new $className;
                $moduleFunc = array_shift($pairs);
                if (!method_exists($obj, $moduleFunc)) {
                    throw new Exception("模块{$moduleName}未对外开放{$moduleFunc}接口");
                }
                $result = call_user_func_array([$obj, $moduleFunc], $params);
            } else {
                $result = parent::executeProcedure($procedure, $params);
            }
        } catch (Exception $e) {
            Yii::error('JsonRPC抛出异常：'.(string) $e, __METHOD__);
            throw $e;
        }
        Yii::info('JsonRPC返回结果：'. Json::encode($result), __METHOD__);
        return $result;
    }

    /**
     * 获取所有过程
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getProcedureList()
    {
        $result = [];

        // 直接写的回调
        foreach ($this->callbacks as $name => $callback) {
            $result[$name] = [];
        }

        // 直接绑定了实例
        foreach ($this->instances as $instance) {
            $reflectionClass = new ReflectionClass($instance);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                if (substr($method->name, 0, 2) == '__') {
                    continue;
                }
                if (in_array($method->name, ['className', 'hasMethod', 'hasProperty', 'canGetProperty', 'canSetProperty', 'init'])) {
                    continue;
                }
                $params = [];
                foreach ($method->getParameters() as $parameter) {
                    $params[$parameter->name] = [
                        'is_optional' => $parameter->isOptional(),
                        'allow_null' => $parameter->allowsNull(),
                    ];
                }
                $result[$method->name] = [
                    'class_name' => $method->class,
                    'method_name' => $method->name,
                    'method_params' => $params,
                    'docComment' => $method->getDocComment(),
                ];
            }
        }

        // withClassAndMethod
        foreach ($this->classes as $name => $data) {
            $method = new ReflectionMethod($data[0], $data[1]);
            $params = [];
            foreach ($method->getParameters() as $parameter) {
                $params[$parameter->name] = [
                    'is_optional' => $parameter->isOptional(),
                    'allow_null' => $parameter->allowsNull(),
                ];
            }
            $result[$name] = [
                'class_name' => $method->class,
                'method_name' => $method->name,
                'method_params' => $params,
                'docComment' => $method->getDocComment(),
            ];
        }

        $parser = new Parser;
        foreach ($result as &$item) {
            $doc = $parser->parse($item['docComment']);

            $item['desc'] = $doc->getDescription();

            /** @var Tag[] $tags */
            $tags = $doc->getTags('param');
            $item['params'] = [];
            foreach ($tags as $tag) {
                $value = $tag->getValue();
                $value = str_replace("\t", ' ', $value);
                $value = str_replace('  ', ' ', $value);
                $value = str_replace('  ', ' ', $value);
                $value = str_replace('  ', ' ', $value);
                $value = str_replace('  ', ' ', $value);
                $value = str_replace('  ', ' ', $value);
                $value = explode(' ', $value, 3);
                $paramName = trim(ArrayHelper::getValue($value, 1), '$');

                // 判断是否为必选参数
                $isRequired = true;
                if (isset($item['method_params'][$paramName])) {
                    $isRequired = !$item['method_params'][$paramName]['is_optional'];
                }

                $item['params'][] = [
                    'name' => $paramName,
                    'type' => ArrayHelper::getValue($value, 0),
                    'desc' => ArrayHelper::getValue($value, 2),
                    'required' => $isRequired,
                ];
            }

            /** @var Tag[] $tags */
            $tags = $doc->getTags('resultKey');
            $item['result_key'] = [];
            foreach ($tags as $tag) {
                $value = $tag->getValue();
                $value = str_replace("\t", ' ', $value);
                $value = str_replace('  ', ' ', $value);
                $value = str_replace('  ', ' ', $value);
                $value = str_replace('  ', ' ', $value);
                $value = str_replace('  ', ' ', $value);
                $value = str_replace('  ', ' ', $value);
                $value = explode(' ', $value, 3);
                $paramName = trim(ArrayHelper::getValue($value, 1), '$');

                $item['result_key'][] = [
                    'name' => $paramName,
                    'type' => ArrayHelper::getValue($value, 0),
                    'desc' => ArrayHelper::getValue($value, 2),
                ];
            }

            $item['result_demo'] = $doc->getTagValue('resultDemo');
            $item['exception'] = $doc->getTagValue('throws');
            $item['category'] = $doc->getTagValue('category');
        }

        $data = [];
        $tail = [];
        foreach ($result as $k=>$v){
            if(!empty($v['category'])){
                $data[$v['category']][] = $v;
            }else{
                $tail[] = $v;
            }
        }
        $data['无分类'] = $tail;
        return $data;
    }
}
