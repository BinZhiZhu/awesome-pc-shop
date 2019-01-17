<?php
/**
 * Created by PhpStorm.
 * User: lh
 * Date: 2018/7/23
 * Time: 下午6:54
 */
//这些接口都是o2o外卖项目用的。
//「内部」o2o校园订餐APP项目（加拿大)

namespace common\modules\api\procedures\shop;

use common\components\AppUser;
use common\handlers\CommissionLevel;
use common\modules\api\procedures\BaseAppApi;
use common\modules\api\procedures\ApiException;
use Yii;

class Shop extends BaseAppApi
{

    /**
     * 获取商户分类
     * @param bool $is_recommand  显示推荐
     * @param int  $size
     * @param int  $page
     *
     * @return array
     */
    public function apiGetUserShops($mobile)
    {
        $shops = \common\models\ShopMember::find()
            ->select(\common\models\ShopMember::tableName().'.id,'.\common\models\ShopMember::tableName().'.uniacid,'.\common\models\UniAccount::tableName().'.name as shop_name,'.\common\models\UniAccount::tableName().'.default_acid,'.\common\models\CommissionLevel::tableName().'.levelname as level_name')
            ->leftJoin(\common\models\CommissionLevel::tableName(),\common\models\ShopMember::tableName().'.agentlevel = '.\common\models\CommissionLevel::tableName().'.id')
            ->leftJoin(\common\models\UniAccount::tableName(),\common\models\UniAccount::tableName().'.uniacid = '.\common\models\ShopMember::tableName().'.uniacid')
            ->where(array('mobile'=>$mobile))
            ->andWhere(['>', 'isagent', 0])
            ->asArray()
            //->limit($page_num)->offset(($page_num - 1) * $page_size)
            ->all();
        foreach ($shops as &$shop) {
            $shop['shop_img'] = tomedia('headimg_' . $shop['default_acid'] . '.jpg');
        }
        return $shops;
    }

    /**
     * 获取热门搜索关键字
     * @param string $token
     *
     * @return array
     */
    public function apiGetHotSearchKeyword($token = '',$merch_id = 0)
    {
        //todo $merch_id 获取店内热门搜索

        global $_W;

        $hot_search = [];

        $hot_search = ['真功夫','沙拉','酸菜鱼','粥','麦当劳','九毛九','奶茶'];

        return [
            'page' => [],
            'items' => [
                'o2o_hot_search' => [
                    'id' => 'o2o_hot_search',
                    'data' => $hot_search,
                    'params' => [
                        'title' => Yii::t('shop_o2o_page_string','热门搜索'),
                    ],
                    'style' => [],
                ]
            ],
        ];
    }

    /**
     * 获取搜索结果
     * @param string $token
     * @param        $keyword
     * @param int    $sort_type
     * @param string $longitude
     * @param string $latitude
     * @param int    $size
     * @param int    $page
     *
     * @return array
     * @throws ApiException
     */
    public function apiGetSearchResult($keyword, $token = '',$sort_type = 0, $longitude = '', $latitude = '', $size = 20, $page = 1)
    {
        global $_W;
        //todo token 记录历史搜索
        if (empty($keyword)) {
            throw new ApiException(\common\AppError::$ParamsError, '请输入搜索关键词！');
        }

        $merch_data = \common\models\MerchUser::fetchAll(
            ['uniacid' => \common\components\Request::getInstance()->uniacid,'status'=>1]
        );
        $merch = [];
        foreach ($merch_data as $k => $v) {
            $merch[] = $v['id'];
        }

        $where = [
            'uniacid' => \common\components\Request::getInstance()->uniacid,
            'status'  => 1,
            'deleted' => 0,
            'checked' => 0,
            ['in', 'merchid', $merch],
            ['<>', 'type', 10],
            ['like', 'title', $keyword],
        ];

        if (!empty($sort_type)) {
            if ($sort_type === 1) {
                //销量
            } elseif ($sort_type === 2) {
                //好评
            } elseif ($sort_type === 3) {
                //距离
            }
        }

        $goods = \common\models\ShopGoods::fetchAll(
            $where,
            'merch_display_order DESC',
            $size,
            ($page-1)*$size

        );

        $list = [];
        foreach ($goods as $k=>$v) {
            $merch_id = $v['merchid'];
            $merch = \common\models\MerchUser::fetchOne(
                ['id'=>$merch_id]
            );
            $distance = 0;
            if(!empty($longitude) && !empty($latitude)){
                $map = new \common\modules\api\procedures\util\Map();
                $distance = $map->get_distance([$longitude,$latitude],[$merch['lng'],$merch['lat']]);
            }
            $sales = \common\models\ShopGoods::sumAll(
                [
                    'uniacid' => \common\components\Request::getInstance()->uniacid,
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
            $list[$merch_id]['merch_id'] = $merch['id'];
            $list[$merch_id]['logo'] = tomedia($merch['logo']);
            $list[$merch_id]['merchname'] = $merch['merchname'];
            $list[$merch_id]['distance'] = $distance;
            $list[$merch_id]['star'] = '4';
            $list[$merch_id]['sales'] = Yii::t('shop_o2o_page_string','月销') . $sales ;

            $merch_goods = [];
            $merch_goods['goods_id'] = $v['id'];
            $merch_goods['title'] = $v['title'];
            $merch_goods['thumb'] = tomedia($v['thumb']);
            $merch_goods['price'] = $v['marketprice'];
            $merch_goods['sales'] = Yii::t('shop_o2o_page_string','月销') . $v['salesreal'] ;


            $list[$merch_id]['goods'][] = $merch_goods;
        }
        $list = array_values($list);

        return [
            'page' => [],
            'items' => [
                'o2o_search_result' => [
                    'id' => 'o2o_search_result',
                    'data' => $list,
                    'params' => [
                        'show_more_button' => '展开更多商品',
                        'show_goods_index' => '2',
                        'sort_type'=>[
                            ['type'=>1,'value'=>Yii::t('shop_o2o_page_string','销量最高')],
                            ['type'=>2,'value'=>Yii::t('shop_o2o_page_string','好评优先')],
                            ['type'=>3,'value'=>Yii::t('shop_o2o_page_string','距离优先')],
                        ]
                    ],
                    'style' => [],
                ]
            ],
        ];
    }

    /**
     * 获取问题列表
     * @return array
     */
    public function apiGetQuestionList(){
        $data = \common\models\QaQuestion::fetchAll([
            'uniacid'=>\common\components\Request::getInstance()->uniacid,
            'status'=>1
        ]);
        $result = [];
        foreach ($data as $k=>$v){
            $result[$k]['id'] = $v['id'];
            $result[$k]['title'] = $v['title'];

            $html_string = htmlspecialchars_decode($v["content"]);
            $content = str_replace(" ", "", $html_string);
            $contents = strip_tags($content);

            $result[$k]['content'] = $contents;
        }
        return ['list'=>$result];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function apiGetRegisterAgreement(){
        $data = \common\models\ShopSysSet::getByKey('wap');
        return [
            'title'=>$data['applytitle'],
            'content'=>$data['applycontent']
        ];
    }


}
