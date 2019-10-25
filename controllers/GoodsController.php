<?php

namespace app\controllers;

use app\enums\RoleTypeEnum;
use app\enums\StatusTypeEnum;
use app\libs\Tools;
use app\models\DevUsers;
use app\models\GoodsCategoryEntity;
use app\models\GoodsEntity;
use Yii;
use yii\web\Controller;

class GoodsController extends Controller
{

    public $layout = false;


    /**
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * @return string
     */
    public function actionCategory()
    {
        return $this->render('category');
    }

    /**
     * @return string
     */
    public function actionCategoryList()
    {
        return $this->render('category-list');
    }

    /**
     * @return string
     */
    public function actionList()
    {
        return $this->render('list');
    }


    /**
     * 获取前台商品列表
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionGetGoodsList()
    {
        $session = Yii::$app->session;
        $array = $session->get('is_user_id');
        $user_id = $array['value'];

        $user = DevUsers::findOne($user_id);
        if (!$user) {
            //TODO
        }

        //查找该商家发布的所有商品
        $goods = GoodsEntity::find()
            ->where([
                'status' => StatusTypeEnum::ON,
                'is_deleted' => StatusTypeEnum::OFF
            ])
            ->andWhere(['created_by' => $user->id])
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();

        foreach ($goods as &$good) {
            $good['created_at'] = date("Y:m:d H:i", $good['created_at']);
            $category = GoodsCategoryEntity::findOne([
                'id' => $good['category_id']
            ]);
            $good['category_title'] = $category ? $category->title : '';
        }

        unset($good);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'result' => [
                    'list' => $goods,
                    'total' => count($goods)
                ]
            ]
        ]);
    }

    /**
     * 获取后台商品列表
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionGetBackendGoodsList()
    {
        $session = Yii::$app->session;
        $array = $session->get('is_user_id');
        $user_id = $array['value'];

        $user = DevUsers::findOne($user_id);
        if (!$user) {
            //TODO
        }

        $goods = GoodsEntity::find()
            ->where([
                'status' => StatusTypeEnum::ON,
                'is_deleted' => StatusTypeEnum::OFF
            ])
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();

        foreach ($goods as &$good) {
            $good['created_at'] = date("Y:m:d H:i", $good['created_at']);
            $category = GoodsCategoryEntity::findOne([
                'id' => $good['category_id']
            ]);
            $good['category_title'] = $category ? $category->title : '';
        }

        unset($good);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'result' => [
                    'list' => $goods,
                    'total' => count($goods)
                ]
            ]
        ]);
    }


    /**
     * 获取商品分类列表
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionGetCategoryList()
    {
        $session = Yii::$app->session;
        $array = $session->get('is_user_id');
        $user_id = $array['value'];

        $user = DevUsers::findOne($user_id);
        if (!$user) {
            //TODO
        }

        $categorys = GoodsCategoryEntity::find()
            ->where([
                'status' => StatusTypeEnum::ON,
                'is_deleted' => StatusTypeEnum::OFF
            ])
            ->andWhere(['created_by' => $user->id])
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();

        foreach ($categorys as &$category) {
            $category['created_at'] = date("Y:m:d H:i", $category['created_at']);
            $category['updated_at'] = date("Y:m:d H:i", $category['updated_at']);
            $category['created_by'] = $user->username;
        }

        unset($category);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'result' => [
                    'list' => $categorys,
                    'total' => count($categorys)
                ]
            ]
        ]);
    }

    /**
     * 商家发布商品
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     * @throws \Throwable
     */
    public function actionAdd()
    {
        $title = Yii::$app->request->post('title');
        $subtitle = Yii::$app->request->post('subtitle');
        $price = Yii::$app->request->post('price');
        $stock = Yii::$app->request->post('stock');
        $sell_num = Yii::$app->request->post('sell_num');
        $thumb = Yii::$app->request->post('thumb');
        $category = Yii::$app->request->post('category');

        $session = Yii::$app->session;

        $array = $session->get('is_user_id');
//        Yii::debug("缓存中的数据:".var_dump($array),__METHOD__);

        $user_id = $array['value'];

        $title = trim($title);
        $subtitle = trim($subtitle);
        $price = (float)$price;
        $stock = intval($stock);
        $sell_num = intval($sell_num);
        $thumb = trim($thumb);
        $user_id = intval($user_id);
        $category = intval($category);

        //商家才能发布商品
        $user = DevUsers::findOne([
            'id' => $user_id,
        ]);

        if (!$user) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '用户不存在',
                    'result' => []
                ]
            ]);
        }

        if ($user->role !== RoleTypeEnum::MERCHANT) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '您没有权限发布商品',
                    'result' => []
                ]
            ]);
        }


        $model = new GoodsEntity();
        $model->created_at = time();
        $model->created_by = $user_id;
        $model->title = $title;
        $model->subtitle = $subtitle;
        $model->price = $price;
        $model->stock = $stock;
        $model->category_id = $category;
        $model->sell_num = $sell_num;
        $model->thumb = $thumb;
        $model->status = StatusTypeEnum::ON;

        Yii::$app->getDb()->transaction(function () use ($model) {
            $model->save(false);
        });

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => "发布商品【{$model->title}】成功",
                'result' => [
                    'goods' => $model,
                    'user_id' => $user_id
                ]
            ]
        ]);
    }

    /**
     * 商家编辑商品
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     * @throws \Throwable
     */
    public function actionEdit()
    {
        $title = Yii::$app->request->post('title');
        $subtitle = Yii::$app->request->post('subtitle');
        $price = Yii::$app->request->post('price');
        $stock = Yii::$app->request->post('stock');
        $sell_num = Yii::$app->request->post('sell_num');
        $thumb = Yii::$app->request->post('thumb');
        $goods_id = Yii::$app->request->post('goods_id');
        $category = Yii::$app->request->post('category');

        $session = Yii::$app->session;

        $array = $session->get('is_user_id');

        $user_id = $array['value'];

        $title = trim($title);
        $subtitle = trim($subtitle);
        $price = (float)$price;
        $stock = intval($stock);
        $sell_num = intval($sell_num);
        $thumb = trim($thumb);
        $user_id = intval($user_id);
        $goods_id = intval($goods_id);
        $category = intval($category);

        //商家才能发布商品
        $user = DevUsers::findOne([
            'id' => $user_id,
        ]);

        if (!$user) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '用户不存在',
                    'result' => []
                ]
            ]);
        }

        if ($user->role !== RoleTypeEnum::MERCHANT) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '您没有权限发布商品',
                    'result' => []
                ]
            ]);
        }

        $goods = GoodsEntity::findOne([
            'id' => $goods_id,
            'status' => StatusTypeEnum::ON
        ]);

        if (!$goods) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '商品不存在',
                    'result' => []
                ]
            ]);
        }

        if ($goods->created_by !== $user_id) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '您没有权限发布商品',
                    'result' => []
                ]
            ]);
        }

        $goods->created_by = $user_id;
        $goods->updated_at = time();
        $goods->title = $title;
        $goods->subtitle = $subtitle;
        $goods->price = $price;
        $goods->stock = $stock;
        $goods->category_id = $category;
        $goods->sell_num = $sell_num;
        $goods->thumb = $thumb;
        $goods->status = StatusTypeEnum::ON;

        Yii::$app->getDb()->transaction(function () use ($goods) {
            $goods->save(false);
        });

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => "编辑商品【{$goods->title}】成功",
                'result' => [
                    'goods' => $goods,
                    'user_id' => $user_id
                ]
            ]
        ]);


    }


    /**
     * 获取所有商品列表
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionGoodsList()
    {
        $category_id = Yii::$app->request->post('category_id');
        if ($category_id) {
            $category_id = intval($category_id);
        }

        $search_title = Yii::$app->request->post('title');
        if ($search_title) {
            $search_title = trim($search_title);
        }

        //查找发布的所有商品
        $query = GoodsEntity::find()
            ->where([
                'status' => StatusTypeEnum::ON,
                'is_deleted' => StatusTypeEnum::OFF
            ]);

        //带分类搜索
        if ($category_id) {
            $query->andWhere(['category_id' => $category_id]);
        }

        if ($search_title) {
            $query->andWhere(['LIKE', 'title', $search_title]);
        }

        $goods = $query->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();

        foreach ($goods as &$good) {
            $category = GoodsCategoryEntity::findOne(['id' => $good['category_id']]);
            $good['category_title'] = $category ? $category->title : '';
        }

        unset($good);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'result' => [
                    'list' => $goods,
                    'total' => count($goods)
                ]
            ]
        ]);
    }

    /**
     * 添加商品分类
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     * @throws \Throwable
     */
    public function actionAddCategory()
    {
        $title = Yii::$app->request->post('title');

        $session = Yii::$app->session;

        $array = $session->get('is_user_id');

        $user_id = $array['value'];

        $title = trim($title);
        $user_id = intval($user_id);

        //商家才能发布商品
        $user = DevUsers::findOne([
            'id' => $user_id,
        ]);

        if (!$user) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '用户不存在',
                    'result' => []
                ]
            ]);
        }

        if ($user->role !== RoleTypeEnum::ADMIN) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '您没有权限发布商品',
                    'result' => []
                ]
            ]);
        }

        $category = GoodsCategoryEntity::findOne([
            'title' => $title
        ]);

        if ($category) {
            $category->updated_at = time();
            $category->save(false);

            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '该商品分类已存在，请重新添加',
                    'result' => []
                ]
            ]);
        }

        $category = new GoodsCategoryEntity();
        $category->created_at = time();
        $category->created_by = $user_id;
        $category->title = $title;
        $category->status = StatusTypeEnum::ON;
        $category->updated_at = time();

        Yii::$app->getDb()->transaction(function () use ($category) {
            $category->save(false);
        });

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => "添加商品分类【{$category->title}】成功",
                'result' => [
                    'category' => $category,
                    'user_id' => $user_id
                ]
            ]
        ]);
    }

    /**
     * 编辑分类
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionEditCategory()
    {
        $title = Yii::$app->request->post('title');
        $category_id = Yii::$app->request->post('category_id');

        $session = Yii::$app->session;

        $array = $session->get('is_user_id');

        $user_id = $array['value'];

        $title = trim($title);
        $category_id = intval($category_id);

        //管理员才能编辑分类
        $user = DevUsers::findOne([
            'id' => $user_id,
        ]);

        if (!$user) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '用户不存在',
                    'result' => []
                ]
            ]);
        }

        if ($user->role !== RoleTypeEnum::ADMIN) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '您没有权限发布商品',
                    'result' => []
                ]
            ]);
        }


        $category = GoodsCategoryEntity::findOne([
            'id' => $category_id,
            'status' => StatusTypeEnum::ON
        ]);

        if (!$category) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '分类不存在',
                    'result' => []
                ]
            ]);
        }

        $category->updated_at = time();
        $category->title = $title;
        $category->save(false);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => "商品分类【{$category->title}】更新成功",
                'result' => [
                    'category' => $category,
                    'user_id' => $user_id
                ]
            ]
        ]);

    }

    /**
     * 删除分类
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionDeleteCategory()
    {
        $category_id = Yii::$app->request->post('id');

        $category_id = intval($category_id);

        $category = GoodsCategoryEntity::findOne([
            'id' => $category_id
        ]);

        if (!$category) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '分类不存在',
                    'result' => []
                ]
            ]);
        }

        $category->is_deleted = StatusTypeEnum::ON;
        $category->save(false);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => '删除成功',
                'result' => []
            ]
        ]);
    }


    /**
     * 删除商品
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionDeleteGoods()
    {
        $goods_id = Yii::$app->request->post('id');

        $goods_id = intval($goods_id);

        $goods = GoodsEntity::findOne([
            'id' => $goods_id
        ]);

        if (!$goods) {
            return Yii::createObject([
                'class' => 'yii\web\Response',
                'format' => \yii\web\Response::FORMAT_JSON,
                'data' => [
                    'code' => -200,
                    'message' => '商品不存在',
                    'result' => []
                ]
            ]);
        }

        $goods->is_deleted = StatusTypeEnum::ON;
        $goods->save(false);

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'message' => '删除成功',
                'result' => []
            ]
        ]);
    }

    /**
     * 获取商品分类列表
     *
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function actionGetGoodsCategoryList()
    {
        $categorys = GoodsCategoryEntity::find()
            ->where([
                'status' => StatusTypeEnum::ON,
                'is_deleted' => StatusTypeEnum::OFF
            ])
            ->orderBy(['id' => SORT_DESC])
            ->limit(12)
            ->asArray()
            ->all();

        return Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'code' => 200,
                'result' => [
                    'list' => $categorys,
                    'total' => count($categorys)
                ]
            ]
        ]);
    }
}