<?php

namespace common\modules\api\procedures\merch;

use common\components\AppUser;
use common\components\Request;
use common\modules\api\procedures\BaseAppApi;
use common\modules\api\procedures\ApiException;
use Yii;

class Shop extends BaseAppApi
{

    /**
     * 获取多商户商户分类
     * @param bool $is_recommand  显示推荐
     * @param int  $size
     * @param int  $page
     *
     * @return array
     */
    public function apiGetMerchCategoryList($is_recommand = false, $size = 20, $page = 1)
    {
        global $_W;

        $where = ['uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1];

        if ($is_recommand) {
            $where['isrecommand'] = 1;
        }
        $category = \common\models\MerchCategory::fetchAll(
            $where,
            'displayorder DESC',
            $size,
            ($page-1)*$size

        );

        foreach ($category as $k=>$v) {
            $category[$k]['thumb'] = tomedia($v['thumb']);
            unset($category[$k]['uniacid'], $category[$k]['status'], $category[$k]['createtime']);
        }

        return [
            'page' => [],
            'items' => [
                'o2o_store_category_list' => [
                    'id' => 'o2o_store_category_list',
                    'data' => $category,
                    'params' => [],
                    'style' => [],
                ],
            ],
        ];
    }

    /**
     * 获取商户列表
     * @param int    $category_id 分类id
     * @param int    $sort_type 排序类型 1销量 2好评 3距离
     * @param string $longitude 经度
     * @param string $latitude 纬度
     * @param bool   $is_recommand 显示推荐
     * @param int    $size
     * @param int    $page
     *
     * @return array
     */
    public function apiGetMerchList($category_id = 0, $sort_type = 0, $longitude = '', $latitude = '', $is_recommand = false, $size = 20, $page = 1)
    {
        global $_W;
        $where = ['uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1];

        if (!empty($category_id)) {
            $where['cateid'] = $category_id;
        }

        if ($is_recommand) {
            $where['isrecommand'] = 1;
        }

        $merch = \common\models\MerchUser::fetchAll(
            $where,
            'id DESC',
            $size,
            ($page-1)*$size

        );

        $list = [];
        foreach ($merch as $k=>$v) {
            $distance = 0;
            $distance_m = 0;
            if(!empty($longitude) && !empty($latitude)){
                $map = new \common\modules\api\procedures\util\Map();
                $distance = $map->get_distance([$longitude,$latitude],[$v['lng'],$v['lat']]);
                if(substr($distance,-2) == 'km'){
                    $distance_m = substr($distance,0,-2) * 1000;
                }
            }


            $comment_order = \common\models\ShopOrder::fetchAll([
                'uniacid' => \common\components\Request::getInstance()->uniacid,
                'status'  => 3,
                ['>', 'iscomment', 0],
                'deleted' => 0,
                'merchid' => $v['id'],
            ]);
            $star_total = 0;
            $comment_total = 0;
            foreach ($comment_order as $comment_k=>$comment_v) {
                $comment = \common\models\ShopOrderComment::fetchOne(['orderid'=>$comment_v['id'],'deleted'=>0,'checked'=>0]);
                if ($comment) {
                    $comment_total += 1;
                    $star_total += $comment['level'];
                }
            }
            $star = 0;
            $star = (intval($star_total / $comment_total * 100)) / 100;



            $sales = \common\models\ShopGoods::sumAll(
                [
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                    'status'  => 1,
                    'deleted' => 0,
                    'checked' => 0,
                    ['merchid' => $v['id']],
                    ['<>', 'type', 10],
                ],'salesreal'
            );
            if(empty($sales)){
                $sales = '0';
            }
            $list[$k]['id'] = $v['id'];
            $list[$k]['logo'] = tomedia($v['logo']);
            $list[$k]['merchname'] = $v['merchname'];
            $list[$k]['distance'] = $distance;
            $list[$k]['distance_m'] = $distance_m;
            $list[$k]['star'] = $star;
            $list[$k]['sales'] = Yii::t('shop_o2o_page_string','月销') . $sales ;
        }

        if (!empty($sort_type) && !empty($list)) {
            //SORT_DESC为降序，SORT_ASC为升序
            if ($sort_type === 1) {
                //销量
                $list = \common\Helper::multiArraySort($list, 'sales');
            } elseif ($sort_type === 2) {
                //好评
                $list = \common\Helper::multiArraySort($list, 'star');
            } elseif ($sort_type === 3) {
                //距离
                $list = \common\Helper::multiArraySort($list, 'distance_m',SORT_ASC);
            }
        }

        return [
            'page' => [],
            'items' => [
                'o2o_store_list' => [
                    'id' => 'o2o_store_list',
                    'data' => $list,
                    'params' => [
                        'sort_type'=>[
                            ['type'=>1,'value'=>Yii::t('shop_o2o_page_string','销量最高')],
                            ['type'=>2,'value'=>Yii::t('shop_o2o_page_string','好评优先')],
                            ['type'=>3,'value'=>Yii::t('shop_o2o_page_string','距离优先')],
                        ]
                    ],
                    'style' => [],
                ],
            ],
        ];
    }

    /**
     * 获取商户详情
     *
     * @param        $merch_id
     * @param string $token
     *
     * @return array
     * @throws ApiException
     * @throws \Exception
     */
    public function apiGetMerchDetail($merch_id,$token = '')
    {
        global $_W;


        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        $merch_id = intval($merch_id);
        $merch = \common\models\MerchUser::fetchOne(
            ['uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1,'id'=>$merch_id]
        );
        if (empty($merch_id) || empty($merch)) {
            throw new ApiException(\common\AppError::$ParamsError, '找不到该商户！');
        }
        $is_attention = 0;
        if(!empty($openid)){
            $attention = \common\models\MemberFavorite::fetchOne(
                [
                    'uniacid' => $uniacid,
                    'merchid'   => $merch['id'],
                    'openid'  => $openid,
                    'type' => 41,
                    'deleted' => 0,

                ]
            );
            if($attention){
                $is_attention = 1;
            }
        }

        $notice = \common\models\MerchNotice::fetchAll(['merchid'=>$merch['id'],'uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1]);
        $notice_data = [];
        foreach ($notice as $k=>$v) {
            $notice_data[$k]['id'] = $v['id'];
            $notice_data[$k]['title'] = $v['title'];
        }

        $store = \common\models\Store::fetchOne(['merchid'=>$merch['id'],'uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1]);

        $data = [];
        $data['merch_id'] = $merch['id'];
        $data['merch_name'] = $merch['merchname'];
        $data['merch_logo'] = tomedia($merch['logo']);
        $data['merch_longitude'] = $merch['lng'];
        $data['merch_latitude'] = $merch['lat'];
        $data['merch_address'] = ['key'=>Yii::t('shop_o2o_page_string','商家地址'),'value'=>$merch['address']];
        $data['merch_realname'] = ['key'=>Yii::t('shop_o2o_page_string','商家负责人'),'value'=>$merch['realname']];
        $data['merch_mobile'] = ['key'=>Yii::t('shop_o2o_page_string','商家电话'),'value'=>$merch['mobile']];
        $data['merch_distribution_time'] = ['key'=>Yii::t('shop_o2o_page_string','支持自取时间'),'value'=>$store['saletime']];
        $data['merch_distribution_service'] = ['key'=>Yii::t('shop_o2o_page_string','配送服务'),'value'=>Yii::t('shop_o2o_page_string','上门自取')];
        $data['merch_notice'] = $notice_data;
        $data['is_attention'] = empty($is_attention) ? 0 : 1;
        $data['goods_category'] = [];

        $goods_where = [
            'uniacid' => \common\components\Request::getInstance()->uniacid,
            'status'  => 1,
            'deleted' => 0,
            'checked' => 0,
            ['merchid' => $merch['id']],
            ['<>', 'type', 10],
        ];
        $goods = \common\models\ShopGoods::fetchAll(
            $goods_where,
            'merch_display_order DESC'
        );

        $merch_goods = [];
        foreach ($goods as $k=>$v) {
            $category = \common\models\ShopCategory::fetchOne(['id'=>$v['pcate']]);
            $cart_sum = 0;
            if(!empty($openid)){
                $cart_sum = \common\models\MemberCart::sumAll([
                    'openid' => $openid,
                    'goodsid' => $v['id'],
                    'deleted' => 0,
                    'selected' => 1,
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                ], 'total');
            }

            $goods_data = [];
            $goods_data['goodsid'] = $v['id'];
            $goods_data['title'] = $v['title'];
            $goods_data['subtitle'] = $v['subtitle'];
            $goods_data['thumb'] = tomedia($v['thumb']);
            $goods_data['price'] = $v['minprice'];
            $goods_data['hasoption'] = $v['hasoption'];
            $goods_data['sales'] = Yii::t('shop_o2o_page_string','月销') . $v['salesreal'] ;
            $goods_data['praise'] =Yii::t('shop_o2o_page_string','赞') . '19';
            $goods_data['cart_sum'] = $cart_sum;
            $goods_data['total'] = $v['total'];

            if ($v['ishot'] == 1) {
                //热销
                $hot_goods['id'] = 'hot_sale';
                $hot_goods['name'] = Yii::t('shop_o2o_page_string','热销');
                $hot_goods['goods'][] = $goods_data;
            }
            if ($category) {
                $merch_goods[$category['id']]['id'] = $category['id'];
                $merch_goods[$category['id']]['name'] = $category['name'];
                $merch_goods[$category['id']]['goods'][] = $goods_data;
            } else {
                //没分类的归属到其他
                $other['id'] = 'other';
                $other['name'] = Yii::t('shop_o2o_page_string','其他');
                $other['goods'][] = $goods_data;
            }
        }
        if (!empty($hot_goods)) {
            //热销分类在最前
            array_unshift($merch_goods, $hot_goods);
        }
        if (!empty($other)) {
            //其他分类在最后
            array_push($merch_goods, $other);
        }
        $data['goods_category'] = array_values($merch_goods);

        return [
            'page' => [],
            'items' => [
                'o2o_store_detail' => [
                    'id' => 'o2o_store_detail',
                    'data' => $data,
                    'params' => [],
                    'style' => [],
                ]
            ],
        ];
    }

    /**
     * 多商户店内搜索
     * @param        $merch_id
     * @param        $keyword
     * @param string $token
     *
     * @return array
     * @throws ApiException
     */
    public function apiGetMerchSearchResult($merch_id, $keyword,$token = '')
    {
        global $_W;

        $merch_id = intval($merch_id);
        $merch = \common\models\MerchUser::fetchOne(
            ['uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1,'id'=>$merch_id]
        );
        if (empty($merch_id) || empty($merch)) {
            throw new ApiException(\common\AppError::$ParamsError, '找不到该商户！');
        }
        if (empty($keyword)) {
            throw new ApiException(\common\AppError::$ParamsError, '请输入搜索关键词！');
        }

        $goods_where = [
            'uniacid' => \common\components\Request::getInstance()->uniacid,
            'status'  => 1,
            'deleted' => 0,
            'checked' => 0,
            ['merchid' => $merch['id']],
            ['<>', 'type', 10],
            ['like', 'title', $keyword],
        ];
        $goods = \common\models\ShopGoods::fetchAll(
            $goods_where,
            'merch_display_order DESC'
        );
        $data = [];
        foreach ($goods as $k=>$v) {
            $data[$k]['goods_id'] = $v['id'];
            $data[$k]['title'] = $v['title'];
            $data[$k]['subtitle'] = $v['subtitle'];
            $data[$k]['thumb'] = tomedia($v['thumb']);
            $data[$k]['price'] = $v['marketprice'];
            $data[$k]['sales'] =  Yii::t('shop_o2o_page_string','月销') . $v['salesreal'] ;
            $data[$k]['praise'] = Yii::t('shop_o2o_page_string','赞') .'19';
            $data[$k]['cart_sum'] = '0'; //todo
        }
        return [
            'page' => [],
            'items' => [
                'o2o_store_search' => [
                    'id' => 'o2o_store_search',
                    'data' => $data,
                    'params' => [],
                    'style' => [],
                ]
            ],
        ];
    }


    /**
     * 获取商户评价
     * @param     $merch_id
     * @param int $filter_type
     * @param int $size
     * @param int $page
     *
     * @return array
     * @throws ApiException
     */
    public function apiGetMerchComment($merch_id,$filter_type = 0,$size = 20, $page = 1) {
        global $_W;
        //todo 分页
        $merch_id = intval($merch_id);
        $merch = \common\models\MerchUser::fetchOne(
            ['uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1,'id'=>$merch_id]
        );
        if (empty($merch_id) || empty($merch)) {
            throw new ApiException(\common\AppError::$ParamsError, '找不到该商户！');
        }


        if ($filter_type === 1) {
            //有图
        } elseif ($filter_type === 2) {
            //好评
        } elseif ($filter_type === 3) {
            //差评
        }

        $comment_order = \common\models\ShopOrder::fetchAll([
            'uniacid' => \common\components\Request::getInstance()->uniacid,
            'status'  => 3,
            ['>', 'iscomment', 0],
            'deleted' => 0,
            'merchid' => $merch['id'],
        ]);

        $merch_comment = [];
        $star_total = 0;
        $comment_total = 0;
        foreach ($comment_order as $k=>$v) {
            $comment = \common\models\ShopOrderComment::fetchOne(['orderid'=>$v['id'],'deleted'=>0,'checked'=>0]);
            if ($comment) {
                $comment_total += 1;
                $star_total += $comment['level'];

                $merch_comment[$k]['comment_id'] = $comment['id'];
                $merch_comment[$k]['nickname'] = $comment['nickname'];
                $merch_comment[$k]['headimgurl'] = tomedia($comment['headimgurl']);
                $merch_comment[$k]['star'] = $comment['level'];
                $merch_comment[$k]['content'] = $comment['content'];
                $merch_comment[$k]['createtime'] = date('Y-m-d H:i:s', $comment['createtime']);
                $merch_comment[$k]['images'] = [];
                $merch_comment_img = iunserializer($comment['images']);
                foreach ($merch_comment_img as $img_k=>$img_v) {
                    $merch_comment[$k]['images'][]  = tomedia($img_v);
                }
            }
        }
        sort($merch_comment);
        $data['list'] = $merch_comment;
        $data['comprehensive_star_content'] = Yii::t('shop_o2o_page_string','综合评分');
        $data['comprehensive_star'] = (intval($star_total / $comment_total * 100)) / 100;
        $data['comment_total'] = $comment_total;


        return [
            'page' => [],
            'items' => [
                'o2o_get_store_comment' => [
                    'id' => 'o2o_get_store_comment',
                    'data' => $data,
                    'params' => [
                        'filter_type'=>[
                            ['type'=>0,'value'=>Yii::t('shop_o2o_page_string','全部')],
                            ['type'=>1,'value'=>Yii::t('shop_o2o_page_string','有图')],
                            ['type'=>2,'value'=>Yii::t('shop_o2o_page_string','好评')],
                            ['type'=>3,'value'=>Yii::t('shop_o2o_page_string','差评')],
                        ]
                    ],
                    'style' => [],
                ]
            ],
        ];
    }

    /**
     * @return array
     */
    public function apiGetAllMerchCategory(){
        global $_W;

        $category = \common\models\MerchCategory::fetchAll(
            ['uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1],
            'displayorder DESC'
        );
        $data = [];
        foreach ($category as $k=>$v){
            $merch = \common\models\MerchUser::fetchAll(
                ['uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1,'cateid'=>$v['id']],
                'id DESC'
            );
            $_merch = [];
            foreach ($merch as $m_k=>$m_v){
                $_merch[$m_k]['id'] = $m_v['id'];
                $_merch[$m_k]['merchname'] = $m_v['merchname'];
                $_merch[$m_k]['logo'] = tomedia($m_v['logo']);
            }
            $data[$k]['id'] = $v['id'];
            $data[$k]['catename'] = $v['catename'];
            $data[$k]['thumb'] = tomedia($v['thumb']);
            $data[$k]['merch'] = $_merch;

        }

        return ['data'=>$data];
    }

    /**
     * 注或取消关注商户
     * @param $token
     * @param $merch_id
     *
     * @return array
     * @throws ApiException
     * @throws \Exception
     */
    public function apiConcernOrCancelAttentionMerch($token,$merch_id = array()){
        global $_W;

        $user_info = \common\components\AppUser::getInstance()->verifyToken($token);

        foreach ($merch_id as $k=>$v){
            $id = $v;
            $merch = \common\models\MerchUser::fetchOne(
                ['uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1,'id'=>$id]
            );
            if (empty($id) || empty($merch)) {
                throw new ApiException(\common\AppError::$ParamsError, '找不到该商户！');
            }

            $data = \common\models\MemberFavorite::fetchOne(
                [
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                    'merchid'   => $merch['id'],
                    'openid'  => $user_info['openid'],
                    'type' => 41,
                ]
            );
            if (empty($data)) {
                $data = [
                    'deleted' => 0,
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
                    'merchid' => $merch['id'],
                    'type' => 41,
                    'openid' => $user_info['openid'],
                    'createtime' => time()
                ];
                \common\models\MemberFavorite::insertOne($data);
                $res['status'] = 1;
            }
            else {
                if(empty($data['deleted'])){
                    \common\models\MemberFavorite::updateAll(['deleted'=>1],['id'=>$data['id']]);
                    $res['status'] = 0;
                }else{
                    \common\models\MemberFavorite::updateAll(['deleted'=>0],['id'=>$data['id']]);
                    $res['status'] = 1;
                }
            }
        }

        $res['success'] = 1;
        $res['success_string'] = Yii::t('success_string','操作成功');

        return $res;
    }

    /**
     * @param $token
     *
     * @return array
     * @throws \Exception
     */
    public function apiGetAttentionMerchList($token){

        AppUser::getInstance()->loginByJwt($token);

        $openid = AppUser::getInstance()->openid;
        $uniacid = Request::getInstance()->uniacid;

        if(empty($openid)){
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ERROR_PARAM_ERROR);
        }

        $user_info = AppUser::getInstance()->identity->toArray();

        $data = \common\models\MemberFavorite::fetchAll(
            [
                'uniacid' => \common\components\Request::getInstance()->uniacid,
                'openid'  => $user_info['openid'],
                'type' => 41,
                'deleted'=> 0
            ]
        );
        $attention_data = [];
        foreach ($data as $k=>$v){
            $merch = \common\models\MerchUser::fetchOne(
                ['id'=>$v['merchid']]
            );
            $distance = 0;
            if(!empty($user_info['lng']) && !empty($user_info['lat'])){
                $map = new \common\modules\api\procedures\util\Map();
                $distance = $map->get_distance([$user_info['lng'],$user_info['lat']],[$merch['lng'],$merch['lat']]);
            }
            $sales = \common\models\ShopGoods::sumAll(
                [
                    'uniacid' => $uniacid,
                    'status'  => 1,
                    'deleted' => 0,
                    'checked' => 0,
                    ['merchid' => $merch['id']],
                    ['<>', 'type', 10],
                ],'salesreal'
            );
            if(empty($sales)){
                $sales = 0;
            }
            $attention_data[$k]['merch_id'] = $merch['id'];
            $attention_data[$k]['logo'] = tomedia($merch['logo']);
            $attention_data[$k]['merchname'] = $merch['merchname'];
            $attention_data[$k]['distance'] = $distance;
            $attention_data[$k]['star'] = '4';
            $attention_data[$k]['sales'] = Yii::t('shop_o2o_page_string','月销') . $sales ;


        }
        return [
            'page' => [],
            'items' => [
                'o2o_get_my_attention_store' => [
                    'id' => 'o2o_get_my_attention_store',
                    'data' => $attention_data,
                    'params' => [
                        'title' => Yii::t('shop_o2o_page_string','我的收藏'),
                    ],
                    'style' => [],
                ]
            ],
        ];
    }

    /**
     * @param     $token
     * @param int $sort_type
     *
     * @return array
     * @throws ApiException
     * @throws \yii\db\Exception
     * @throws \Exception
     */
    public function apiGetMerchSalesStatistics($token,$sort_type = 1){

        AppUser::getInstance()->loginByJwt($token);

        $openid = AppUser::getInstance()->openid;
        $uniacid = Request::getInstance()->uniacid;

        if(empty($openid)){
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ERROR_PARAM_ERROR);
        }

        $user_info = AppUser::getInstance()->identity->toArray();

        $verification_man = \common\models\Saler::fetchOne([
            'uniacid'=>\common\components\Request::getInstance()->uniacid,
            'openid'=>$openid,
            'status'=>1
        ]);

        if(empty($verification_man) || empty($verification_man['merchid'])){
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ERROR_PARAM_ERROR);
        }

        $merchid = $verification_man['merchid'];


        $condition = ' and og.uniacid=' . $uniacid . ' and og.merchid=' . $merchid;

        $condition1 = ' and g.uniacid=:uniacid and g.merchid=:merchid';
        $params1 = array(':uniacid' => $uniacid, ':merchid' => $merchid);

        $orderby_count = 'count';

        $sql = 'SELECT g.id,g.title,g.thumb,g.minprice,' . '(select ifnull(sum(og.price),0) from  ' . \common\models\ShopOrderGoods::tableName() . ' og left join ' . tablename('new_shop_order') . ' o on og.orderid=o.id  where o.status>=1 and og.goodsid=g.id ' . $condition . ')  as money,' . '(select ifnull(sum(og.total),0) from  ' . \common\models\ShopOrderGoods::tableName() . ' og left join ' . tablename('new_shop_order') . ' o on og.orderid=o.id  where o.status>=1 and og.goodsid=g.id ' . $condition . ') as count  ' . 'from ' . tablename('new_shop_goods') . ' g  ' . ' where 1 ' . $condition1 . '  order by ' . $orderby_count . ' desc ';
        $list_sql = $sql . 'LIMIT 10' ;
        $list = pdo_fetchall($list_sql, $params1);
        foreach ($list as $k=>$v){
            $comment = \common\models\ShopOrderComment::fetchAll(['goodsid'=>$v['id'],'deleted'=>0,'checked'=>0,'uniacid'=>$uniacid]);

            $star_total = 0;
            $comment_total = 0;
            foreach ($comment as $comment_k=>$comment_v) {
                $comment_total += 1;
                $star_total += $comment_v['level'];
            }
            $star = 0;
            $star = (intval($star_total / $comment_total * 100)) / 100;


            $list[$k]['sales'] = Yii::t('shop_o2o_page_string','月销') . $v['count'];
            $list[$k]['thumb'] = tomedia($v['thumb']);
            $list[$k]['star'] = $star;
            $list[$k]['price'] = $v['minprice'];
            $list[$k]['praise'] = Yii::t('shop_o2o_page_string','赞') .'19';

            unset($list[$k]['count']);
            unset($list[$k]['money']);
        }


        if($sort_type == 1){
            $starttime = strtotime('-1 days');
        }else if($sort_type == 2){
            $starttime = strtotime('-7 days');
        }else {
            $starttime = strtotime('-30 days');
        }
        $condition .= ' AND o.createtime >= ' . $starttime;


        $sales_money_sql = 'SELECT g.id,g.title,g.thumb,' . '(select ifnull(sum(og.price),0) from  ' . \common\models\ShopOrderGoods::tableName() . ' og left join ' . tablename('new_shop_order') . ' o on og.orderid=o.id  where o.status>=1 and og.goodsid=g.id ' . $condition . ')  as money,' . '(select ifnull(sum(og.total),0) from  ' . \common\models\ShopOrderGoods::tableName() . ' og left join ' . tablename('new_shop_order') . ' o on og.orderid=o.id  where o.status>=1 and og.goodsid=g.id ' . $condition . ') as count  ' . 'from ' . tablename('new_shop_goods') . ' g  ' . ' where 1 ' . $condition1 . '  order by ' . $orderby_count . ' desc ';
        $all_list = pdo_fetchall($sales_money_sql, $params1);

        $sum = 0;
        foreach($all_list as $item){
            $sum += $item['money'];
        }

        return [
            'title' => Yii::t('shop_o2o_page_string','销售额'),
            'title2' => Yii::t('shop_o2o_page_string','销量排行榜'),
            'title2_text' => Yii::t('shop_o2o_page_string','商品销量前十'),
            'sort_type'=> [
                ['type'=>1,'value'=>Yii::t('shop_o2o_page_string','近1天')],
                ['type'=>2,'value'=>Yii::t('shop_o2o_page_string','近7天')],
                ['type'=>3,'value'=>Yii::t('shop_o2o_page_string','近30天')],
            ],
            'sales_money' => $sum,
            'list' => $list,

        ];
    }

    /**
     * @param int    $show_status
     * @param int    $pindex
     * @param int    $psize
     * @param string $token
     *
     * @return array
     * @throws ApiException
     * @throws \yii\db\Exception
     * @throws \Exception
     */
    public function apiGetMerchOrderList($show_status = 0,$pindex = 1,$psize = 10,$token = ''){

        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ERROR_PARAM_ERROR);
        }

        $verification_man = \common\models\Saler::fetchOne([
            'uniacid'=>\common\components\Request::getInstance()->uniacid,
            'openid'=>$openid,
            'status'=>1
        ]);

        if(empty($verification_man) || empty($verification_man['merchid'])){
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ERROR_PARAM_ERROR);
        }

        $merchid = $verification_man['merchid'];

        $r_type = array(Yii::t('shop_o2o_page_string','待退款'), Yii::t('shop_o2o_page_string','待退货退款'), Yii::t('shop_o2o_page_string','待换货'));
        $condition = ' and merchid=:merchid and deleted=0 and uniacid=:uniacid ';
        $params = array(':uniacid' => $uniacid, ':merchid' => $merchid);

        $merch_plugin = p('merch');
        $merch_data = m('common')->getPluginset('merch');
        if ($merch_plugin && $merch_data['is_openmerch']) {
            $is_openmerch = 1;
        }
        else {
            $is_openmerch = 0;
        }

        $condition .= ' and merchshow=0 ';

        if ($show_status != '') {
            $show_status = intval($show_status);

            switch ($show_status) {
                case 0:
                    $condition .= ' and status=0 and paytype!=3';
                    break;

                case 2:
                    $condition .= ' and (status=2 or status=0 and paytype=3)';
                    break;

                case 4:
                    $condition .= ' and refundstate>0';
                    break;

                case 5:
                    $condition .= ' and userdeleted=1 ';
                    break;

                default:
                    $condition .= ' and status=' . intval($show_status);
            }

            if ($show_status != 5) {
                $condition .= ' and userdeleted=0 ';
            }

        }
        else {
            $condition .= ' and userdeleted=0 ';
        }

        $com_verify = com('verify');
        $list = pdo_fetchall('select id,createtime,ordersn,price,userdeleted,isparent,refundstate,paytype,status,addressid,refundid,isverify,dispatchtype,verifytype,verifyinfo,verifycode,iscomment,merchid from ' . tablename('new_shop_order') . ' where 1 ' . $condition . ' order by createtime desc LIMIT ' . (($pindex - 1) * $psize) . ',' . $psize, $params);
        $total = pdo_fetchcolumn('select count(*) from ' . tablename('new_shop_order') . ' where 1 ' . $condition, $params);
        $refunddays = intval($_W['shopset']['trade']['refunddays']);

        if ($is_openmerch == 1) {
            $merch_user = $merch_plugin->getListUser($list, 'merch_user');
        }


        foreach ($list as &$row ) {

            $param = array();

            $row['createtime'] = date('Y-m-d H:i',$row['createtime']);

            if ($row['isparent'] == 1) {
                $scondition = ' og.parentorderid=:parentorderid';
                $param[':parentorderid'] = $row['id'];
            }
            else {
                $scondition = ' og.orderid=:orderid';
                $param[':orderid'] = $row['id'];
            }

            $sql = 'SELECT og.goodsid,og.total,g.title,g.thumb,og.price,og.optionname as optiontitle,og.optionid,op.specs,g.merchid,g.status FROM ' . \common\models\ShopOrderGoods::tableName() . ' og ' . ' left join ' . tablename('new_shop_goods') . ' g on og.goodsid = g.id ' . ' left join ' . \common\models\ShopGoodsOption::tableName() . ' op on og.optionid = op.id ' . ' where ' . $scondition . ' order by og.id asc';
            $goods = pdo_fetchall($sql, $param);
            $goods = \common\models\ShopOrder::formatGoods($goods);
            $ismerch = 0;
            $merch_array = array();
            $g = 0;
            $nog = 0;


            if (!(empty($merch_array))) {
                if (1 < count($merch_array)) {
                    $ismerch = 1;
                }
            }

            $goods_list = array();
            $i = 0;

            if ($ismerch) {
                $getListUser = $merch_plugin->getListUser($goods);
                $merch_user = $getListUser['merch_user'];

                foreach ($getListUser['merch'] as $k => $v ) {
                    if (empty($merch_user[$k]['merchname'])) {
                        $goods_list[$i]['shopname'] = $_W['shopset']['shop']['name'];
                    }
                    else {
                        $goods_list[$i]['shopname'] = $merch_user[$k]['merchname'];
                    }

                    $goods_list[$i]['goods'] = $v;
                    ++$i;
                }
            }
            else {
                if ($merchid == 0) {
                    $goods_list[$i]['shopname'] = $_W['shopset']['shop']['name'];
                }
                else {
                    $merch_data = \common\models\MerchUser::getOneByMerchId($merchid);
                    $goods_list[$i]['shopname'] = $merch_data['merchname'];
                }

                $goods_list[$i]['goods'] = $goods;
            }

            $row['goods'] = $goods_list;
            $statuscss = 'text-cancel';

            switch ($row['status']) {
                case '-1':
                    $status = Yii::t('shop_o2o_page_string','已取消');
                    break;

                case '0':
                    if ($row['paytype'] == 3) {
                        $status = '待发货';
                    }
                    else {
                        $status = Yii::t('shop_o2o_page_string','待付款');
                    }

                    $statuscss = 'text-cancel';
                    break;

                case '1':
                    if ($row['isverify'] == 1) {
                        $status = '使用中';
                    }
                    else if (empty($row['addressid'])) {
                        $status = Yii::t('shop_o2o_page_string','待取货');
                    }
                    else {
                        $status = '待发货';
                    }

                    $statuscss = 'text-warning';
                    break;

                case '2':
                    $status = '待收货';
                    $statuscss = 'text-danger';
                    break;

                case '3':
                    if (empty($row['iscomment'])) {
                        if ($show_status == 5) {
                            $status = '已完成';
                        }
                        else {
                            $status = ((empty($_W['shopset']['trade']['closecomment']) ? Yii::t('shop_o2o_page_string','待评价') : Yii::t('shop_o2o_page_string','已完成')));
                        }
                    }
                    else {
                        $status = Yii::t('shop_o2o_page_string','交易完成');
                    }

                    $statuscss = 'text-success';
                    break;
            }

            $row['statusstr'] = $status;
            $row['statuscss'] = $statuscss;

            if ((0 < $row['refundstate']) && !(empty($row['refundid']))) {
                $refund = pdo_get('new_shop_order_refund', [
                    'id' => $row['refundid'],
                    'orderid' => $row['id'],
                    'uniacid' => $uniacid,
                ]);
                if (!empty($refund)) {
                    $row['statusstr'] = $r_type[$refund['rtype']];
                }
            }

            //是否可以核销
            $row['canverify'] = false;
            $canverify = false;

            if ($com_verify) {
                $showverify = $row['dispatchtype'] || $row['isverify'];

                if ($row['isverify']) {
                    if (($row['verifytype'] == 0) || ($row['verifytype'] == 1)) {
                        $vs = iunserializer($row['verifyinfo']);
                        $verifyinfo = array(
                            array('verifycode' => $row['verifycode'], 'verified' => ($row['verifytype'] == 0 ? $row['verified'] : $row['goods'][0]['total'] <= count($vs)))
                        );

                        if ($row['verifytype'] == 0) {
                            $canverify = empty($row['verified']) && $showverify;
                        } else if ($row['verifytype'] == 1) {
                            $canverify = (count($vs) < $row['goods'][0]['total']) && $showverify;
                        }

                    } else {
                        $verifyinfo = iunserializer($row['verifyinfo']);
                        $last = 0;

                        foreach ($verifyinfo as $v) {
                            if (!($v['verified'])) {
                                ++$last;
                            }
                        }
                        $canverify = (0 < $last) && $showverify;
                    }
                } else if (!(empty($row['dispatchtype']))) {
                    $canverify = ($row['status'] == 1) && $showverify;
                }
            }


            $row['canverify'] = $canverify;

            if ($is_openmerch == 1) {
                $row['merchname'] = (($merch_user[$row['merchid']]['merchname'] ? $merch_user[$row['merchid']]['merchname'] : $_W['shopset']['shop']['name']));
            }

        }
        unset($row);

        $olist = [
            ['orderText' => Yii::t('shop_o2o_page_string','全部'), 'dataType' => ''],
            ['orderText' => Yii::t('shop_o2o_page_string','待付款'), 'dataType' => '0'],
            ['orderText' => Yii::t('shop_o2o_page_string','待取货'), 'dataType' => '1'],
            ['orderText' => Yii::t('shop_o2o_page_string','已完成'), 'dataType' => '3'],
            ['orderText' => Yii::t('shop_o2o_page_string','退换货'), 'dataType' => '4'],
        ];

        $olist_o2o = $olist;
        return array(
            'title' => Yii::t('shop_o2o_page_string','店铺订单'),
            'list' => $list,
            'pagesize' => $psize,
            'total' => $total,
            'page' => $pindex,
            'olist_o2o' => $olist_o2o,
        );
    }


}
