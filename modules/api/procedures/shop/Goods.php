<?php

namespace common\modules\api\procedures\shop;

use common\components\AppUser;
use common\components\Request;
use common\components\Response;
use common\models\BargainActor;
use common\models\BargainGoods;
use common\models\MemberCart;
use common\models\MerchUser;
use common\models\SeckillTaskGoods;
use common\models\ShopGoods;
use common\models\ShopGoodsOption;
use common\models\ShopGoodsParam;
use common\models\ShopGoodsSpec;
use common\models\ShopOrderGoods;
use common\modules\api\procedures\ApiException;
use common\modules\api\procedures\BaseAppApi;
use GoodsShopModel;
use Yii;
use yii\base\BaseObject;

class Goods extends BaseAppApi
{


    public function get_goods_detail($id, $mid = 0, $merch_id = 0, $token = '')
    {
        //兼容旧接口,以后删除
        return $this->apiGetGoodsDetail($id, $mid, $merch_id, $token);
    }

    /**
     * 商品详情
     * @param $id
     * @param int $mid
     * @param int $merch_id
     * @param string $token
     * @return array
     * @throws ApiException
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function apiGetGoodsDetail($id, $mid = 0, $merch_id = 0, $token = '')
    {

        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $mid = intval($mid);
        $merch_id = intval($merch_id);
        $uniacid = Request::getInstance()->uniacid;
        $openid = AppUser::getInstance()->openid;

        if (empty($id)) {
            throw new ApiException(Response::PARAMS_ERROR);
        }

        $base_merch_user = array();
        $merch_plugin = p('merch');
        $merch_data = m('common')->getPluginset('merch');

        if (!empty($merch_id) && $merch_plugin && $merch_data['is_openmerch']) {
            $base_merch_user = MerchUser::fetchOneCache(['id' => $merch_id]);
        }

        if ($merch_plugin && $merch_data['is_openmerch']) {
            $is_openmerch = 1;
        } else {
            $is_openmerch = 0;
        }

        $goods = ShopGoods::fetchOne(['id' => $id, 'uniacid' => $uniacid]);
        if (empty($goods)) {
            throw new ApiException(Response::GOODS_NOT_FOUND);
        } else {
            //检查一下商品浏览权限
            $goodsVisit = m('goods')->visit($goods);
            if (!$goodsVisit) {
                throw new ApiException(Response::GOODS_NOT_FOUND);
            }
        }

        $member = m('member')->getMember($openid);
        // 商品浏览权限: 用户等级 | 用户组
        $showlevels = (($goods['showlevels'] != '' ? explode(',', $goods['showlevels']) : array()));
        $showgroups = (($goods['showgroups'] != '' ? explode(',', $goods['showgroups']) : array()));

        $showgoods = 0; // 是否可以展示
        if (!(empty($member))) {
            if ((!(empty($showlevels)) && in_array($member['level'], $showlevels)) || (!(empty($showgroups)) && in_array($member['groupid'], $showgroups)) || (empty($showlevels) && empty($showgroups))) {
                $showgoods = 1;
            }
        } else {
            if (empty($showlevels) && empty($showgroups)) {
                $showgoods = 1;
            }
        }
        //处理秒杀数据
        $goods['seckillinfo'] = false;
        $seckill = p('seckill');

        if ($seckill) {
            $goods['seckillinfo'] = $seckill->getSeckill($goods['id'], 0, false);
            $goods['seckillinfo'] = \common\modules\seckill\Module::formatSeckillStatus($goods['seckillinfo']);
        }

        if (empty($goods) || empty($showgoods)) {
            throw new ApiException(Response::GOODS_NOT_FOUND);
        }

        $merchid = $goods['merchid'];

        if (!empty($is_openmerch)) {
            //判断多商户商品是否通过审核
            if ((0 < $merchid) && ($goods['checked'] == 1)) {
                throw new ApiException(Response::GOODS_NOT_CHECKED);
            }
        }

        $goods['sales'] = $goods['sales'] + $goods['salesreal'];
        $goods['buycontentshow'] = 0;

        if ($goods['buyshow'] == 1) {
            $sql = 'select o.id from ' . tablename('new_shop_order') . ' o left join ' . ShopOrderGoods::tableName() . ' g on o.id = g.orderid';
            $sql .= ' where o.openid=:openid and g.goodsid=:id and o.status>0 and o.uniacid=:uniacid limit 1';
            $buy_goods = pdo_fetch($sql, array(':openid' => $openid, ':id' => $id, ':uniacid' => Request::getInstance()->uniacid));

            if (!(empty($buy_goods))) {
                $goods['buycontentshow'] = 1;
                $goods['buycontent'] = m('common')->html_to_images2($goods['buycontent'], 720);
            }
        }
        $goods['unit'] = ((empty($goods['unit']) ? '件' : $goods['unit']));

        //使用的快递是否有不配送区域
        $citys = m('dispatch')->getNoDispatchAreas($goods);
        if (isset($citys['type']) && $citys['type'] == 'virtual') {
            //虚拟物品
            $citys = $citys['dispath_citys'];
            $has_city = 0;
        } else {
            if (is_array($citys) && !empty($citys['citys'][0])) {
                $has_city = 1;
            }

            if (!$citys['citys'][0] && !$citys['onlysent']) {
                //这里应该是商品没有设置配送方式
                $citys['citys'] = [];
                $citys['onlysent'] = '';
                $has_city = 0;
            }
        }

        $goods['on_dispatch_areas'] = $citys;
        $goods['has_city'] = $has_city;

        //运费
        $dispatch_price = ShopGoods::getDispatchPrice($goods);
        $goods['dispatchprice'] = $dispatch_price ? $dispatch_price : 0;

        //幻灯片
        $thumbs = iunserializer($goods['thumb_url']);

        if (empty($thumbs)) {
            $thumbs = array($goods['thumb']);
        }

        if (!empty($goods['thumb_first']) && !(empty($goods['thumb']))) {
            $thumbs = array_merge(array($goods['thumb']), $thumbs);
        }

        $goods['thumbs'] = set_medias2($thumbs, NULL, 720);
        $goods['video'] = tomedia($goods['video']);

        if (strexists($goods['video'], 'v.qq.com/txp/iframe/player.html')) {
            $videourl = \common\helpers\Video::getQVideo($goods['video']);

            if (!(is_error($videourl))) {
                $goods['video'] = $videourl;
            }

        }


        if (!(empty($goods['thumbs'])) && is_array($goods['thumbs'])) {
            $new_thumbs = array();

            foreach ($goods['thumbs'] as $i => $thumb) {
                $new_thumbs[] = $thumb;
            }

            $goods['thumbs'] = $new_thumbs;
        }

        //规格specs
        $specs = pdo_fetchall('select * from ' . ShopGoodsSpec::tableName() . " where goodsid=:goodsid and  uniacid=:uniacid order by displayorder asc", array(':goodsid' => $id, ':uniacid' => Request::getInstance()->uniacid));
        $spec_titles = array();
        foreach ($specs as $key => $spec) {
            if ($key >= 2) {
                break;
            }


            $spec_titles[] = $spec['title'];
        }

        if (0 < $goods['hasoption']) {
            $goods['spec_titles'] = implode('、', $spec_titles);
        } else {
            $goods['spec_titles'] = '';
        }

        //参数
        $goods['params'] = pdo_fetchall("SELECT * FROM " . ShopGoodsParam::tableName() . " WHERE uniacid=:uniacid and goodsid=:goodsid order by displayorder asc", array(':uniacid' => $uniacid, ":goodsid" => $goods['id']));

        $goods = set_medias($goods, 'thumb', 720);
        $goods['canbuy'] = ((!(empty($goods['status'])) && empty($goods['deleted']) ? 1 : 0));
        $goods['cannotbuy'] = '';

        if ($goods['total'] <= 0) {
            $goods['canbuy'] = 0;
            $goods['cannotbuy'] = '商品库存不足';
        }


        if ((0 < $goods['isendtime']) && (0 < $goods['endtime']) && ($goods['endtime'] < time())) {
            $goods['canbuy'] = 0;
            $goods['cannotbuy'] = '商品已过期';
        }

        $goods['timestate'] = '';

        //判断用户最大购买量
        $goods['userbuy'] = '1';

        if (0 < $goods['usermaxbuy']) {
            $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . ShopOrderGoods::tableName() . ' og ' . ' left join ' . tablename('new_shop_order') . ' o on og.orderid=o.id ' . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $goods['id'], ':uniacid' => $uniacid, ':openid' => $openid));

            if ($goods['usermaxbuy'] <= $order_goodscount) {
                $goods['userbuy'] = 0;
                $goods['canbuy'] = 0;
                $goods['cannotbuy'] = '超出最大购买数量';
            }
        }


        $levelid = $member['level'];
        $groupid = $member['groupid'];
        $goods['levelbuy'] = '1';

        if ($goods['buylevels'] != '') {
            $buylevels = explode(',', $goods['buylevels']);

            if (!(in_array($levelid, $buylevels))) {
                $goods['levelbuy'] = 0;
                $goods['canbuy'] = 0;
                $goods['cannotbuy'] = '购买级别不够';
            }

        }


        $goods['groupbuy'] = '1';

        if ($goods['buygroups'] != '') {
            $buygroups = explode(',', $goods['buygroups']);

            if (!(in_array($groupid, $buygroups))) {
                $goods['groupbuy'] = 0;
                $goods['canbuy'] = 0;
                $goods['cannotbuy'] = '所在会员组无法购买';
            }

        }

        $goods['timebuy'] = '0';

        if ($goods['istime'] == 1) {
            if (time() < $goods['timestart']) {
                $goods['timebuy'] = '-1';
                $goods['canbuy'] = 0;
                $goods['cannotbuy'] = '限时购未开始';
            } else if ($goods['timeend'] < time()) {
                $goods['timebuy'] = '1';
                $goods['canbuy'] = 0;
                $goods['cannotbuy'] = '限时购已结束';
            }

        }

        if ($goods['type'] == '4') {
            $intervalprice = iunserializer($goods['intervalprice']);

            if (0 < $goods['intervalfloor']) {
                $goods['intervalprice1'] = $intervalprice[0]['intervalprice'];
                if ($goods['intervalfloor'] < 2) {
                    $goods['intervalnum1'] = $intervalprice[0]['intervalnum'];
                } else {
                    $goods['intervalnum1'] = $intervalprice[0]['intervalnum'] . "-" . ($intervalprice[1]['intervalnum'] - "1");
                }
            }

            if (1 < $goods['intervalfloor']) {
                $goods['intervalprice2'] = $intervalprice[1]['intervalprice'];
                if (2 < $goods['intervalfloor']) {
                    $goods['intervalnum2'] = ($intervalprice[1]['intervalnum']) . "-" . ($intervalprice[2]['intervalnum'] - "1");
                } else {
                    $goods['intervalnum2'] = ">" . $intervalprice[1]['intervalnum'];
                }
            }

            if (2 < $goods['intervalfloor']) {
                $goods['intervalprice3'] = $intervalprice[2]['intervalprice'];
                $goods['intervalnum3'] = ">" . $intervalprice[2]['intervalnum'];
            }

            for ($i = 1; $i <= $goods['intervalfloor']; $i++) {
                $goods['wholesale'][] = [
                    "intervalprice" => $goods['intervalprice' . $i . ''],
                    "intervalnum" => $goods['intervalnum' . $i . '']
                ];
            }
            $intervalprice1 = $intervalprice[1]['intervalprice'];
            $price1 = $intervalprice[0]['intervalprice'];
            $num1 = $goods['intervalnum2'] - 1;
            $totleprice = sprintf("%01.2f", $price1);
            $code = 1;

            $goods['wholesale_init'] = [
                'intervalprice1' => $intervalprice1,
                'num1' => $num1,
                'totleprice' => $totleprice,
                'price1' => $price1,
                'code' => $code,
            ];

            $goods['get_wholesale_num_url'] = mobileUrl('app/goods/get_wholesale_num');
        }

        $isfullback = false;

        if ($goods['isfullback']) {
            $isfullback = true;
            $fullbackgoods = pdo_fetch('SELECT * FROM ' . \common\models\FullBackGoods::tableName() . ' WHERE uniacid = :uniacid and goodsid = :goodsid limit 1 ', array(':uniacid' => $uniacid, ':goodsid' => $id));

            if ($goods['hasoption'] == 1) {
                $fullprice = pdo_fetch('select min(allfullbackprice) as minfullprice,max(allfullbackprice) as maxfullprice,min(allfullbackratio) as minfullratio' . "\r\n" . '                            ,max(allfullbackratio) as maxfullratio,min(fullbackprice) as min_fullback_price,max(fullbackprice) as maxfullbackprice' . "\r\n" . '                            ,min(fullbackratio) as min_fullback_ratio,max(fullbackratio) as maxfullbackratio,min(`day`) as minday,max(`day`) as maxday' . "\r\n" . '                            from ' . \common\models\ShopGoodsOption::tableName() . ' where goodsid = :goodsid', array(':goodsid' => $id));
                $fullbackgoods['minallfullbackallprice'] = $fullprice['minfullprice'];
                $fullbackgoods['maxallfullbackallprice'] = $fullprice['maxfullprice'];
                $fullbackgoods['minallfullbackallratio'] = $fullprice['minfullratio'];
                $fullbackgoods['maxallfullbackallratio'] = $fullprice['maxfullratio'];
                $fullbackgoods['min_fullback_price'] = $fullprice['min_fullback_price'];
                $fullbackgoods['maxfullbackprice'] = $fullprice['maxfullbackprice'];
                $fullbackgoods['min_fullback_ratio'] = $fullprice['min_fullback_ratio'];
                $fullbackgoods['maxfullbackratio'] = $fullprice['maxfullbackratio'];
                $fullbackgoods['fullbackratio'] = $fullprice['min_fullback_ratio'];
                $fullbackgoods['fullbackprice'] = $fullprice['min_fullback_price'];
                $fullbackgoods['minday'] = $fullprice['minday'];
                $fullbackgoods['maxday'] = $fullprice['maxday'];
            } else {
                $fullbackgoods['maxallfullbackallprice'] = $fullbackgoods['minallfullbackallprice'];
                $fullbackgoods['maxallfullbackallratio'] = $fullbackgoods['minallfullbackallratio'];
                $fullbackgoods['minday'] = $fullbackgoods['day'];
            }
        }


        $goods['isfullback'] = $isfullback;
        $goods['fullbackgoods'] = $fullbackgoods;
        $isgift = 0;
        $gifts = array();
        $giftgoods = array();
        $grftarray = array();
        $i = 0;
        $time = time();
        $gifts = \common\models\ShopGift::fetchAll([
            'uniacid' => Request::getInstance()->uniacid,
            'activity' => 2,
            'status' => 1,
            ['<=', 'starttime', $time],
            ['>=', 'endtime', $time]
        ]);

        foreach ($gifts as $key => $value) {
            $gid = explode(',', $value['goodsid']);

            foreach ($gid as $ke => $val) {
                if ($val == $id) {
                    $giftgoods = explode(',', $value['giftgoodsid']);
                    foreach ($giftgoods as $k => $_val) {
                        $isgift = 1;
                        $gifts[$key]['gift'][$k] = pdo_fetch('select id,title,thumb,marketprice from ' . tablename('new_shop_goods') . ' where uniacid = :uniacid and deleted = 0 and total > 0 and status = 2 and id = :id ', array(':uniacid' => $uniacid, ':id' => $_val));
                        $gifttitle = !empty($gifts[$key]['gift'][$k]['title']) ? $gifts[$key]['gift'][$k]['title'] : '赠品';
                        $gifts[$key]['gift'][$k] = set_medias($gifts[$key]['gift'][$k], array('thumb'));
                    }
                }
            }

            if (empty($gifts[$key]['gift'])) {
                unset($gifts[$key]);
            } else {
                $grftarray[$i] = $gifts[$key];
                ++$i;
            }
        }

        $grftarray = set_medias($grftarray, array('thumb'));
        $goods['isgift'] = $isgift;
        $goods['gifts'] = $grftarray;
        $goods['canAddCart'] = 1;
        //秒杀商品不能加入购物车
        if (($goods['isverify'] == 2) || ($goods['type'] == 2) || ($goods['type'] == 3) || !(empty($grftarray)) || $goods['seckillinfo'] && $seckill) {
            $goods['canAddCart'] = 0;
        }


        $enoughs = com_run('sale::getEnoughs');
        $enoughfree = \common\modules\sale\Module::getEnoughFree();
        if (($is_openmerch == 1) && (0 < $goods['merchid'])) {
            $merch_set = $merch_plugin->getSet('sale', $goods['merchid']);

            if ($merch_set['enoughfree']) {
                $enoughfree = $merch_set['enoughorder'];

                if ($merch_set['enoughorder'] == 0) {
                    $enoughfree = -1;
                }

            }

        }


        if ($enoughfree && ($enoughfree < $goods['minprice'])) {
            $goods['dispatchprice'] = 0;
        }


        $goods['hasSales'] = 0;
        if ((0 < $goods['ednum']) || (0 < $goods['edmoney'])) {
            $goods['hasSales'] = 1;
        }


        if ($enoughfree || ($enoughs && (0 < count($enoughs)))) {
            $goods['hasSales'] = 1;
        }


        $goods['enoughfree'] = $enoughfree;
        $goods['enoughs'] = $enoughs;
        $minprice = $goods['minprice'];
        $maxprice = $goods['maxprice'];
        $level = \common\models\MemberLevel::getByOpenId($openid);
        $memberprice = m('goods')->getMemberPrice($goods, $level);
        if ($goods['isdiscount'] && (time() <= $goods['isdiscount_time'])) {
            $goods['oldmaxprice'] = $maxprice;
            $isdiscount_discounts = json_decode($goods['isdiscount_discounts'], true);
            $prices = array();
            if (!(isset($isdiscount_discounts['type'])) || empty($isdiscount_discounts['type'])) {
                $level = \common\models\MemberLevel::getByOpenId($openid);
                $prices_array = m('order')->getGoodsDiscountPrice($goods, $level, 1);
                $prices[] = $prices_array['price'];
            } else {
                $goods_discounts = m('order')->getGoodsDiscounts($goods, $isdiscount_discounts, $levelid);
                $prices = $goods_discounts['prices'];
            }

            $minprice = min($prices);
            $maxprice = max($prices);
        }


        $goods['minprice'] = (double)$minprice;
        $goods['maxprice'] = (double)$maxprice;
        $goods['getComments'] = empty($_W['shopset']['trade']['closecommentshow']);
        $goods['hasServices'] = $goods['cash'] || $goods['seven'] || $goods['repair'] || $goods['invoice'] || $goods['quality'];
        $goods['services'] = array();

        if ($goods['cash']) {
            $goods['services'][] = '货到付款';
        }


        if ($goods['quality']) {
            $goods['services'][] = '正品保证';
        }


        if ($goods['seven']) {
            $goods['services'][] = '7天无理由退换';
        }


        if ($goods['invoice']) {
            $goods['services'][] = '发票';
        }

        if ($goods['repair']) {
            $goods['services'][] = '保修';
        }

        $labelstyle = \common\models\ShopGoodsLabelStyle::fetchOne(['uniacid' => $uniacid]);

        if (json_decode($goods['labelname'], true)) {
            $labelname = json_decode($goods['labelname'], true);
        } else {
            $labelname = unserialize($goods['labelname']);
        }

        $goods['labelname'] = $labelname;
        $goods['labelstyle'] = (object)$labelstyle;
        $labellist = $goods['services'];

        if (is_array($labelname)) {
            $labellist = array_merge($labellist, $labelname);
        }


        $goods['labels'] = array('style' => (is_array($labelstyle) ? intval($labelstyle['style']) : 0), 'list' => $labellist);
        $goods['isfavorite'] = m('goods')->isFavorite($id);
        $goods['cartcount'] = GoodsShopModel::getCartCount();
        m('goods')->addHistory($id);
        $shop = set_medias(\common\models\ShopSysSet::getByKey('shop'), 'logo');
        $shop['url'] = mobileUrl('', NULL);
        $opencommission = false;

        if (p('commission')) {
            if (empty($member['agentblack'])) {
                $cset = p('commission')->getSet();
                $opencommission = 0 < intval($cset['level']);

                if ($opencommission) {
                    if (empty($mid)) {
                        if (($member['isagent'] == 1) && ($member['status'] == 1)) {
                            $mid = $member['id'];
                        }
                    }

                    if (!(empty($mid))) {
                        if (empty($cset['closemyshop'])) {
                            $shop = set_medias(p('commission')->getShop($mid), 'logo');
                            $shop['url'] = mobileUrl('commission/myshop', array('mid' => $mid), true);
                        }

                    }

                }

            }

        }

        if (empty($base_merch_user)) {
            $merch_flag = 0;
            if (($is_openmerch == 1) && (0 < $goods['merchid'])) {
                $merch_user = pdo_get('new_shop_merch_user', ['id' => intval($goods['merchid'])]);
                if (!empty($merch_user)) {
                    $shop = $merch_user;
                    $merch_flag = 1;
                }
            }

            if ($merch_flag == 1) {
                $shopdetail = [
                    'logo' => !empty($goods['detail_logo']) ? tomedia($goods['detail_logo']) : tomedia($shop['img']),
                    'shopname' => !(empty($goods['detail_shopname'])) ? $goods['detail_shopname'] : $shop['merchname'],
                    'description' => !empty($goods['detail_totaltitle']) ? $goods['detail_totaltitle'] : $shop['desc'],
                    'btntext1' => trim($goods['detail_btntext1']),
                    'btnurl1' => !empty($goods['detail_btnurl1']) ? $goods['detail_btnurl1'] : mobileUrl('goods'),
                    'btntext2' => trim($goods['detail_btntext2']),
                    'btnurl2' => !empty($goods['detail_btnurl2']) ? $goods['detail_btnurl2'] : mobileUrl('merch', ['merchid' => $goods['merchid']]),
                ];
            } else {
                $shopdetail = [
                    'logo' => !empty($goods['detail_logo']) ? tomedia($goods['detail_logo']) : $shop['img'],
                    'shopname' => !empty($goods['detail_shopname']) ? $goods['detail_shopname'] : $shop['name'],
                    'description' => !empty($goods['detail_totaltitle']) ? $goods['detail_totaltitle'] : $shop['description'],
                    'btntext1' => trim($goods['detail_btntext1']),
                    'btnurl1' => !empty($goods['detail_btnurl1']) ? $goods['detail_btnurl1'] : mobileUrl('goods'),
                    'btntext2' => trim($goods['detail_btntext2']),
                    'btnurl2' => !empty($goods['detail_btnurl2']) ? $goods['detail_btnurl2'] : $shop['url'],
                ];
            }

            $param = array(':uniacid' => Request::getInstance()->uniacid);

            if ($merch_flag == 1) {
                $sqlcon = ' and merchid=:merchid';
                $param[':merchid'] = $goods['merchid'];
            }


            if (empty($shop['selectgoods'])) {
                $statics = array('all' => pdo_fetchcolumn('select count(1) from ' . tablename('new_shop_goods') . ' where uniacid=:uniacid ' . $sqlcon . ' and status=1 and deleted=0', $param), 'new' => pdo_fetchcolumn('select count(1) from ' . tablename('new_shop_goods') . ' where uniacid=:uniacid ' . $sqlcon . ' and isnew=1 and status=1 and deleted=0', $param), 'discount' => pdo_fetchcolumn('select count(1) from ' . tablename('new_shop_goods') . ' where uniacid=:uniacid ' . $sqlcon . ' and isdiscount=1 and status=1 and deleted=0', $param));
            } else {
                $goodsids = explode(',', $shop['goodsids']);
                $statics = array('all' => count($goodsids), 'new' => pdo_fetchcolumn('select count(1) from ' . tablename('new_shop_goods') . ' where uniacid=:uniacid ' . $sqlcon . ' and id in( ' . $shop['goodsids'] . ' ) and isnew=1 and status=1 and deleted=0', $param), 'discount' => pdo_fetchcolumn('select count(1) from ' . tablename('new_shop_goods') . ' where uniacid=:uniacid ' . $sqlcon . ' and id in( ' . $shop['goodsids'] . ' ) and isdiscount=1 and status=1 and deleted=0', $param));
            }
        } else {
            if ($goods['checked'] == 1) {
                throw new ApiException(Response::GOODS_NOT_CHECKED);
            }

            $shop = $base_merch_user;
            $shopdetail = array('logo' => (!(empty($goods['detail_logo'])) ? tomedia($goods['detail_logo']) : tomedia($shop['logo'])), 'shopname' => (!(empty($goods['detail_shopname'])) ? $goods['detail_shopname'] : $shop['merchname']), 'description' => (!(empty($goods['detail_totaltitle'])) ? $goods['detail_totaltitle'] : $shop['desc']), 'btntext1' => trim($goods['detail_btntext1']), 'btnurl1' => (!(empty($goods['detail_btnurl1'])) ? $goods['detail_btnurl1'] : mobileUrl('goods')), 'btntext2' => trim($goods['detail_btntext2']), 'btnurl2' => (!(empty($goods['detail_btnurl2'])) ? $goods['detail_btnurl2'] : mobileUrl('merch', array('merchid' => $goods['merchid']))));

            if (empty($shop['selectgoods'])) {
                $statics = [
                    'all' => \common\models\ShopGoods::countAll([
                        'status' => 1,
                        'deleted' => 0,
                        'merchid' => $goods['merchid'],
                        'uniacid' => Request::getInstance()->uniacid,
                    ]),
                    'new' => pdo_fetchcolumn('select count(1) from ' . tablename('new_shop_goods') . ' where uniacid=:uniacid and merchid=:merchid and isnew=1 and status=1 and deleted=0', [':uniacid' => Request::getInstance()->uniacid, ':merchid' => $goods['merchid']]),
                    'discount' => pdo_fetchcolumn('select count(1) from ' . tablename('new_shop_goods') . ' where uniacid=:uniacid and merchid=:merchid and isdiscount=1 and status=1 and deleted=0', [':uniacid' => Request::getInstance()->uniacid, ':merchid' => $goods['merchid']]),
                ];
            } else {
                $goodsids = explode(',', $shop['goodsids']);
                $statics = [
                    'all' => count($goodsids),
                    'new' => pdo_fetchcolumn('select count(1) from ' . tablename('new_shop_goods') . ' where uniacid=:uniacid and merchid=:merchid and id in( ' . $shop['goodsids'] . ' ) and isnew=1 and status=1 and deleted=0', [':uniacid' => Request::getInstance()->uniacid, ':merchid' => $goods['merchid']]),
                    'discount' => pdo_fetchcolumn('select count(1) from ' . tablename('new_shop_goods') . ' where uniacid=:uniacid and merchid=:merchid and id in( ' . $shop['goodsids'] . ' ) and isdiscount=1 and status=1 and deleted=0', [':uniacid' => Request::getInstance()->uniacid, ':merchid' => $goods['merchid']]),
                ];
            }
        }

        $goodsdesc = ((!(empty($goods['description'])) ? $goods['description'] : $goods['subtitle']));
        $_W['shopshare'] = array('title' => (!(empty($goods['share_title'])) ? $goods['share_title'] : $goods['title']), 'imgUrl' => (!(empty($goods['share_icon'])) ? tomedia($goods['share_icon']) : tomedia($goods['thumb'])), 'desc' => (!(empty($goodsdesc)) ? $goodsdesc : $_W['shopset']['shop']['name']), 'link' => mobileUrl('app/share', array('type' => 'goods', 'id' => $goods['id']), true));
        $com = p('commission');

        if ($com) {
            $cset = $_W['shopset']['commission'];

            if (!(empty($cset))) {
                if (($member['isagent'] == 1) && ($member['status'] == 1)) {
                    $_W['shopshare']['link'] = mobileUrl('app/share', array('type' => 'goods', 'id' => $goods['id'], 'mid' => $member['id']), true);
                } else if (!(empty($mid))) {
                    $_W['shopshare']['link'] = mobileUrl('app/share', array('type' => 'goods', 'id' => $goods['id'], 'mid' => $mid), true);
                }

            }

        }

        //核销门店
        $stores = array();

        if ($goods['isverify'] == 2) {
            $storeids = array();

            if (!empty($goods['storeids'])) {
                $storeids = array_merge(explode(',', $goods['storeids']), $storeids);
            }

            $stores = \common\modules\store\Module::getSupportVerifyStores($storeids, $merchid);
        }

        unset($goods['pcate'], $goods['ccate'], $goods['tcate'], $goods['costprice'], $goods['original_price'], $goods['totalcnf'], $goods['salesreal'], $goods['score'], $goods['taobaoid'], $goods['taotaoid'], $goods['taobaourl'], $goods['updatetime'], $goods['notice_openid'], $goods['noticetype'], $goods['ccates'], $goods['pcates'], $goods['tcates'], $goods['cates'], $goods['artid'], $goods['allcates'], $goods['hascommission'], $goods['commission1_rate'], $goods['commission1_pay'], $goods['commission2_rate'], $goods['commission2_pay'], $goods['commission3_rate'], $goods['commission3_pay'], $goods['commission_thumb'], $goods['commission'], $goods['needfollow'], $goods['followurl'], $goods['followtip'], $goods['sharebtn'], $goods['keywords'], $goods['timestate'], $goods['nocommission'], $goods['hidecommission'], $goods['diysave'], $goods['diysaveid'], $goods['deduct2'], $goods['shopid'], $goods['shorttitle'], $goods['diyformtype'], $goods['diyformid'], $goods['diymode'], $goods['discounts'], $goods['verifytype'], $goods['diyfields'], $goods['groupstype'], $goods['merchsale'], $goods['manydeduct'], $goods['checked'], $goods['goodssn'], $goods['productsn'], $goods['isdiscount_discounts'], $goods['isrecommand'], $goods['dispatchtype'], $goods['dispatchid'], $goods['storeids'], $goods['thumb_url'], $goods['share_icon'], $goods['share_title']);

        if (!(empty($goods['thumb_url']))) {
            $goods['thumb_url'] = iunserializer($goods['thumb_url']);
        }


        $goods['stores'] = $stores;

        if (!empty($shopdetail)) {
            $shopdetail['btntext1'] = !empty($shopdetail['btntext1']) ? $shopdetail['btntext1'] : '全部商品';
            $shopdetail['btntext2'] = !empty($shopdetail['btntext2']) ? $shopdetail['btntext2'] : '进店逛逛';
            $shopdetail['btnurl1'] = \common\modules\wxapp\Module::getUrl($shopdetail['btnurl1']);
            $shopdetail['btnurl2'] = \common\modules\wxapp\Module::getUrl($shopdetail['btnurl2']);
            $shopdetail['static_all'] = $statics['all'];
            $shopdetail['static_new'] = $statics['new'];
            $shopdetail['static_discount'] = $statics['discount'];
        }

        $shopdetail = set_medias($shopdetail, 'logo');
        $goods['shopdetail'] = $shopdetail;
        $goods['share'] = $_W['shopshare'];
        $goods['memberprice'] = '';
        if (empty($goods['isdiscount']) || (!(empty($goods['isdiscount'])) && ($goods['isdiscount_time'] < time()))) {
            if (!(empty($memberprice)) && ($memberprice != $goods['minprice']) && !(empty($level))) {
                $goods['memberprice'] = array('levelname' => $level['levelname'], 'price' => $memberprice);
            }

        }

        if (com('coupon')) {
            $goods['coupons'] = \common\models\ShopCoupon::getCouponsByGoodId($goods['id']);
        }

        if ($goods['type'] == 3) {
        }

        $goods['pre_sale_send_start_time'] = date('m月d日', $goods['pre_sale_send_start_time']);
        $goods['endtime'] = date('Y-m-d H:i:s', $goods['endtime']);
        $goods['isdiscount_date'] = date('Y-m-d H:i:s', $goods['isdiscount_time']);
        $goods['productprice'] = (double)$goods['productprice'];
        $goods['credittext'] = $_W['shopset']['trade']['credittext'];
        $goods['moneytext'] = $_W['shopset']['trade']['moneytext'];
        $goods['content'] = m('common')->html_to_images2($goods['content'], 720);
        $goods['navbar'] = intval($_W['shopset']['app']['navbar']);
        $goods['customer'] = intval($_W['shopset']['app']['customer']);
        $goods['phone'] = intval($_W['shopset']['app']['phone']);
        $goods['customercolor'] = $goods['phonecolor'] = $goods['phonenumber'] = '';

        if (!(empty($goods['customer']))) {
            $goods['customercolor'] = ((empty($_W['shopset']['app']['customercolor']) ? '#ff5555' : $_W['shopset']['app']['customercolor']));
        }


        if (!(empty($goods['phone']))) {
            $goods['phonecolor'] = ((empty($_W['shopset']['app']['phonecolor']) ? '#ff5555' : $_W['shopset']['app']['phonecolor']));
            $goods['phonenumber'] = ((empty($_W['shopset']['app']['phonenumber']) ? '#ff5555' : $_W['shopset']['app']['phonenumber']));
        }


        if (!(empty($goods['ispresell']))) {
            $goods['preselldatestart'] = ((empty($goods['pre_sale_time_start']) ? 0 : date('Y-m-d H:i:s', $goods['pre_sale_time_start'])));
            $goods['preselldateend'] = empty($goods['pre_sale_time_end']) ? 0 : date('Y-m-d H:i:s', $goods['pre_sale_time_end']);
        }

        $sql = 'select pg.id,pg.pid,pg.goodsid,p.displayorder,p.title from ' . \common\models\ShopPackageGoods::tableName() . ' as pg ' .
            ' left join ' . \common\models\ShopPackage::tableName() . ' as p on pg.pid = p.id ' .
            ' where pg.uniacid = ' . $uniacid . ' and pg.goodsid = ' . $id . ' and  p.starttime <= ' . time() . ' and p.endtime >= ' . time() . ' and p.deleted = 0 and p.status = 1 ' .
            ' ORDER BY p.displayorder desc,pg.id desc ' .
            ' limit 1 ';
        $package_goods = pdo_fetch($sql);

        if ($package_goods['pid']) {
            $packages = \common\models\ShopPackageGoods::find()
                ->where([
                    'pid' => $package_goods['pid'],
                    'uniacid' => $uniacid,
                ])
                ->orderBy(['id' => SORT_DESC])
                ->asArray()
                ->all();
            $packages = set_medias($packages, array('thumb'));
        }

        $goods['packagegoods'] = $package_goods;
        $hasSales = false;
        if ((0 < $goods['ednum']) || (0 < $goods['edmoney'])) {
            $hasSales = true;
        }


        if ($enoughfree || ($enoughs && (0 < count($enoughs)))) {
            $hasSales = true;
        }


        $activity = array();
        if ($enoughs && (0 < count($enoughs)) && empty($seckillinfo)) {
            $activity['enough'] = $enoughs;
        } else {
//            $activity['enough'] = null;;
        }


        if (!(empty($merch_set['enoughdeduct'])) && empty($seckillinfo)) {
            $one = array(
                array('enough' => $merch_set['enoughmoney'], 'give' => $merch_set['enoughdeduct'])
            );
            $merch_set['enoughs'] = array_merge_recursive($one, $merch_set['enoughs']);
            $activity['merch_enough'] = $merch_set['enoughs'];
        }


        if ($hasSales && empty($seckillinfo)) {
            if ((!(is_array($goods['dispatchprice'])) && ($goods['type'] == 1) && ($goods['isverify'] != 2) && ($goods['dispatchprice'] == 0)) || ($enoughfree && ($enoughfree == -1)) || (0 < $enoughfree) || (0 < $goods['ednum']) || (0 < $goods['edmoney'])) {
                if (!(is_array($goods['dispatchprice']))) {
                    if (($goods['type'] == 1) && ($goods['isverify'] != 2)) {
                        if ($goods['dispatchprice'] == 0) {
                            $activity['postfree']['goods'] = true;
                        }

                    }

                }


                if ($enoughfree && ($enoughfree == -1)) {
                    if (!(empty($merch_set['enoughfree']))) {
                        $activity['postfree']['scope'] = '本店';
                    } else {
                        $activity['postfree']['scope'] = '全场';
                    }
                } else {
                    if (0 < $goods['ednum']) {
                        $activity['postfree']['num'] = $goods['ednum'];
                        $activity['postfree']['unit'] = ((empty($goods['unit']) ? '件' : $goods['unit']));
                    }


                    if (0 < $goods['edmoney']) {
                        $activity['postfree']['price'] = $goods['edmoney'];
                    }
                    if ($enoughfree) {
                        if (!(empty($merch_set['enoughfree']))) {
                            $activity['postfree']['scope'] = '本店';
                        } else {
                            $activity['postfree']['scope'] = '全场';
                        }
                    }


                    $activity['postfree']['enoughfree'] = $enoughfree;
                }
            }

        }


        if (!(empty($goods['deduct'])) && ($goods['deduct'] != '0.00')) {
            $activity['credit']['deduct'] = $goods['deduct'];
        } else {
//            $activity['credit']['deduct'] = null;
        }


        if (!(empty($goods['credit']))) {
            $activity['credit']['give'] = $goods['credit'];
        } else {
//            $activity['credit']['give'] = null;
        }


        if ((0 < floatval($goods['buyagain'])) && empty($seckillinfo)) {
            $activity['buyagain']['discount'] = $goods['buyagain'];
            $activity['buyagain']['buyagain_sale'] = $goods['buyagain_sale'];
        }


        if (!(empty($fullbackgoods)) && $isfullback) {
            if (0 < $fullbackgoods['type']) {
                if (0 < $goods['hasoption']) {
                    if ($fullbackgoods['minallfullbackallratio'] == $fullbackgoods['maxallfullbackallratio']) {
                        $activity['fullback']['all_enjoy'] = $fullbackgoods['minallfullbackallratio'] . '%';
                    } else {
                        $activity['fullback']['all_enjoy'] = $fullbackgoods['minallfullbackallratio'] . '% ~ ' . $fullbackgoods['maxallfullbackallratio'] . '%';
                    }

                    if ($fullbackgoods['min_fullback_ratio'] == $fullbackgoods['maxfullbackratio']) {
                        $activity['fullback']['enjoy'] = \common\Helper::formatPrice($fullbackgoods['min_fullback_ratio'], 2) . '%';
                    } else {
                        $activity['fullback']['enjoy'] = \common\Helper::formatPrice($fullbackgoods['min_fullback_ratio'], 2) . '% ~ ' . price_format($fullbackgoods['maxfullbackratio'], 2) . '%';
                    }
                } else {
                    $activity['fullback']['all_enjoy'] = $fullbackgoods['minallfullbackallratio'] . '%';
                    $activity['fullback']['enjoy'] = \common\Helper::formatPrice($fullbackgoods['fullbackratio'], 2) . '%';
                }
            } else if (0 < $goods['hasoption']) {
                if ($fullbackgoods['minallfullbackallprice'] == $fullbackgoods['maxallfullbackallprice']) {
                    $activity['fullback']['all_enjoy'] = '￥' . $fullbackgoods['minallfullbackallprice'];
                } else {
                    $activity['fullback']['all_enjoy'] = '￥' . $fullbackgoods['minallfullbackallprice'] . ' ~ ￥' . $fullbackgoods['maxallfullbackallprice'];
                }

                if ($fullbackgoods['min_fullback_price'] == $fullbackgoods['maxfullbackprice']) {
                    $activity['fullback']['enjoy'] = '￥' . \common\Helper::formatPrice($fullbackgoods['min_fullback_price'], 2);
                } else {
                    $activity['fullback']['enjoy'] = '￥' . \common\Helper::formatPrice($fullbackgoods['min_fullback_price'], 2) . ' ~ ￥' . price_format($fullbackgoods['maxfullbackprice'], 2);
                }
            } else {
                $activity['fullback']['all_enjoy'] = '￥' . $fullbackgoods['minallfullbackallprice'];
                $activity['fullback']['enjoy'] = '￥' . \common\Helper::formatPrice($fullbackgoods['fullbackprice'], 2);
            }

            if (0 < $goods['hasoption']) {
                if ($fullbackgoods['minday'] == $fullbackgoods['maxday']) {
                    $activity['fullback']['day'] = $fullbackgoods['minday'];
                } else {
                    $activity['fullback']['day'] = $fullbackgoods['minday'] . ' ~ ' . $fullbackgoods['maxday'];
                }
            } else {
                $activity['fullback']['day'] = $fullbackgoods['day'];
            }

            if (0 < $fullbackgoods['startday']) {
                $activity['fullback']['startday'] = $fullbackgoods['startday'];
            }

        }


        $goods['activity'] = (object)$activity;
        $goods['city_express_state'] = 1;
        $city_express = pdo_fetch('SELECT * FROM ' . tablename('new_shop_city_express') . ' WHERE uniacid=:uniacid and merchid=0 limit 1', array(':uniacid' => Request::getInstance()->uniacid));
        if (empty($city_express) || ($city_express['enabled'] == 0) || (0 < $goods['merchid']) || ($goods['type'] != 1)) {
            $goods['city_express_state'] = 0;
        }

        //o2o外卖
        $goods['o2o_sales'] = Yii::t('shop_o2o_page_string', '月销') . $goods['sales'];

        //补充预售发货类型

        //补充多规格数据
        $goods['picker'] = $this->apiGetGoodsPicker($goods['id'], false);

        return ['goods' => $goods];
    }

    public function get_goods_picker($id, $throwException = true, $token = '')
    {
        //兼容旧接口,以后删除
        return $this->apiGetGoodsPicker($id, $throwException, $token);
    }

    /**
     * 商品规格选择
     * @param int $id
     * @param boolean $throwException 是否直接抛出异常 //todo 是为了兼容有些列表页调该接口
     * @param string $token
     * @return array
     * @throws ApiException
     * @throws \yii\db\Exception
     * @throws \Exception
     */
    public function apiGetGoodsPicker($id, $throwException = true, $token = '')
    {
        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }
        $openid = AppUser::getInstance()->openid;

        //秒杀
        $seckillinfo = false;
        $seckill = p('seckill');
        $bargain = false;
        if ($seckill) {
            $time = time();
            $seckillinfo = $seckill->getSeckill($id);
            if (!(empty($seckillinfo))) {
                if (($seckillinfo['starttime'] <= $time) && ($time < $seckillinfo['endtime'])) {
                    $seckillinfo['status'] = 0; //抢购中
                } else if ($time < $seckillinfo['starttime']) {
                    $seckillinfo['status'] = 1; //即将开抢
                } else {
                    $seckillinfo['status'] = -1;//已过期
                }
            }
        }

        if (empty($id)) {
            if ($throwException) {
                throw new ApiException(Response::PARAMS_ERROR);
            } else {
                Yii::error('参数错误', __METHOD__);
            }
        }

        $goods = pdo_fetch('select id,thumb,title,bargain,marketprice,total,maxbuy,minbuy,unit,hasoption,showtotal,diyformid,diyformtype,diyfields, `type`, isverify, maxprice, minprice, merchsale from ' . ShopGoods::tableName() . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $id, ':uniacid' => Request::getInstance()->uniacid));

        if (empty($goods)) {
            if ($throwException) {
                throw new ApiException(Response::GOODS_NOT_FOUND);
            } else {
                Yii::error('商品未找到 ID:' . $id, __METHOD__);
            }
        }

        $goods = set_medias($goods, 'thumb');
        $goods['marketprice'] = $goods['marketprice'] ? $goods['marketprice'] : 0;

        $specs = array();
        $options = array();
        if (!(empty($goods)) && $goods['hasoption']) {
            $specs = pdo_fetchall('select id, goodsid, title from ' . ShopGoodsSpec::tableName() . ' where goodsid=:goodsid and uniacid=:uniacid order by displayorder asc', array(':goodsid' => $id, ':uniacid' => Request::getInstance()->uniacid));

            foreach ($specs as &$spec) {
                $spec['items'] = pdo_fetchall('select id, specid, title, thumb, `virtual` from ' . \common\models\ShopGoodsSpecItem::tableName() . ' where specid=:specid and `show`=1 order by displayorder asc', array(':specid' => $spec['id']));
            }

            unset($spec);
            $options = pdo_fetchall('select id, goodsid, title, thumb, productprice, marketprice, stock, weight, specs, goodssn, productsn, `virtual`  from ' . \common\models\ShopGoodsOption::tableName() . ' where goodsid=:goodsid and uniacid=:uniacid order by displayorder asc', array(':goodsid' => $id, ':uniacid' => Request::getInstance()->uniacid));
        }

        // 秒杀价格处理  即将开抢不处理价格
        if ($seckillinfo && $seckillinfo['status'] != 1) {
            Yii::info('--秒杀价格处理--');
            $minprice = $maxprice = $seckillinfo['price'];
            $goods['marketprice'] = $seckillinfo['price'];
            if ((0 < count($seckillinfo['options'])) && !(empty($options))) {
                foreach ($options as &$option) {
                    foreach ($seckillinfo['options'] as $so) {
                        if ($option['id'] == $so['optionid']) {
                            $option['marketprice'] = $so['price'];
                        }

                    }
                }
                unset($option);
            }
        } else {
            $minprice = $goods['minprice'];
            $maxprice = $goods['maxprice'];
        }

        //砍价价格处理  这里是显示底价
        /*        if ($goods && $goods['bargain']) {
                    //参与砍价
                    $bargain = true;
                    $bargainGoods = BargainGoods::findOne([
                        'id' => $goods['bargain'],
                        'account_id' => Request::getInstance()->uniacid
                    ]);
                    if ($bargainGoods && $bargain) {
                        Yii::info('--砍价价格处理--');
                        if (!$goods['hasoption']) {
                            //单规格
                            $goods['marketprice'] = number_format($bargainGoods['end_price'], 2);
                        }
                        if ($goods['hasoption'] && $bargainGoods['spec_price']) {
                            //多规格
                            $spec_prices = unserialize($bargainGoods['spec_price']);
                            foreach ($options as &$option) {
                                foreach ($spec_prices as $optionid => $spec_price) {
                                    if ($option['id'] == $optionid) {
                                        $option['marketprice'] = number_format($spec_price, 2);
                                    }
                                }
                            }
                            unset($option);
                        }

                    }

                }*/


        if ($goods['isdiscount'] && (time() <= $goods['isdiscount_time'])) {
            $goods['oldmaxprice'] = $maxprice;
            $isdiscount_discounts = json_decode($goods['isdiscount_discounts'], true);
            $prices = array();
            if (!(isset($isdiscount_discounts['type'])) || empty($isdiscount_discounts['type'])) {
                $level = \common\models\MemberLevel::getByOpenId($openid);
                $prices_array = m('order')->getGoodsDiscountPrice($goods, $level, 1);
                $prices[] = $prices_array['price'];
            } else {
                $goods_discounts = m('order')->getGoodsDiscounts($goods, $isdiscount_discounts, $levelid, $options);
                $prices = $goods_discounts['prices'];
                $options = $goods_discounts['options'];
            }

            $minprice = min($prices);
            $maxprice = max($prices);
        }

        $goods['minprice'] = number_format($minprice, 2);
        $goods['maxprice'] = number_format($maxprice, 2);
        $diyform_plugin = p('diyform');
        if ($diyform_plugin) {
            $fields = false;
            if ($goods['diyformtype'] == 1) {
                //模板
                if (!empty($goods['diyformid'])) {
                    $diyformid = $goods['diyformid'];
                    $formInfo = $diyform_plugin->getDiyformInfo($diyformid);
                    $fields = $formInfo['fields'];
                }

            } else if ($goods['diyformtype'] == 2) {
                $diyformid = 0;
                $fields = iunserializer($goods['diyfields']);

                if (empty($fields)) {
                    $fields = false;
                }

            }


            if (!(empty($fields))) {
                $openid = AppUser::getInstance()->openid;
                $member = m('member')->getMember($openid);
                $f_data = $diyform_plugin->getLastData(3, 0, $diyformid, $id, $fields, $member);
                $flag = 0;
                if (!(empty($f_data)) && is_array($f_data)) {
                    foreach ($f_data as $k => $v) {
                        while (!(empty($v))) {
                            $flag = 1;
                            break;
                        }
                    }
                }
                if (empty($flag)) {
                    $f_data = $diyform_plugin->getLastCartData($id);
                }

            }
        }


        if (!(empty($specs))) {
            foreach ($specs as $key => $value) {
                foreach ($specs[$key]['items'] as $k => &$v) {
                    $v['thumb'] = tomedia($v['thumb']);
                }

                unset($v);
            }
        }

        //是否可以加入购物车
        $goods['canAddCart'] = 1;
        if (($goods['isverify'] == 2) || ($goods['type'] == 2) || ($goods['type'] == 3)) {
            $goods['canAddCart'] = 0;
        }

        //检查商品组
        $goods['errormsg'] = '';

        $goods['isLimitBuy'] = 0;

        $check_group = \common\modules\goods\Module::checkGroupGoodsCanAddCart($goods['id'], $throwException);
        if ($check_group) {
            $goods['isgroup'] = $check_group['isgroup'];
            $goods['minbuy'] = $check_group['minbuy'];
            $goods['maxbuy'] = $check_group['maxbuy'];

            if ($check_group['errormsg'] && isset($check_group['canAddCart'])) {
                Yii::info('当前商品 ID:' . $goods['id'] . '不能加入购物车', __METHOD__);
                $goods['canAddCart'] = $check_group['canAddCart'];
                $goods['errormsg'] = $check_group['errormsg'];
            }
        }


        //商品组商品的限购
        $check_group_limt_buy = \common\modules\goods\Module::checkGroupsGoodsCanSubmitMemberCart($goods['id'], 0, true, $throwException);

        if ($check_group_limt_buy) {
            if ($check_group_limt_buy['errormsg'] && isset($check_group_limt_buy['canAddCart'])) {
                $goods['canAddCart'] = $check_group_limt_buy['canAddCart'];
                $goods['errormsg'] = $check_group_limt_buy['errormsg'];
            }

            if ($check_group_limt_buy['errormsg'] && isset($check_group_limt_buy['isLimitBuy'])) {
                $goods['isLimitBuy'] = $check_group_limt_buy['isLimitBuy'];
                $goods['errormsg'] = $check_group_limt_buy['errormsg'];
            }

        }

        unset($goods['diyformid'], $goods['diyformtype'], $goods['diyfields']);
        if (!(empty($options)) && is_array($options)) {
            foreach ($options as $index => &$option) {
                $option_specs = $option['specs'];
                if (!(empty($option_specs))) {
                    $option_specs_arr = explode('_', $option_specs);
                    array_multisort($option_specs_arr, SORT_ASC);
                    $option['specs'] = implode('_', $option_specs_arr);
                }
            }
            unset($option);
        }


        $appDatas = array(
            'fields' => array(),
            'f_data' => array()
        );

        if ($diyform_plugin) {
            $this_member = null;
            if (!AppUser::getInstance()->isGuest) {
                $this_member = AppUser::getInstance()->identity->toArray();
            }
            $appDatas = $diyform_plugin->wxApp($fields, $f_data, $this_member);
        }

        $data = [
            'goods' => $goods,
            'specs' => $specs,
            'options' => $options,
            'diyform' => [
                'fields' => $appDatas['fields'],
                'lastdata' => $appDatas['f_data']
            ]
        ];


        return $data;

    }

    public function add_goods_to_cart($id, $total = 1, $optionid = 0, $gpc_diyformdata = null, $token = '', $merch_id = 0)
    {
        //兼容旧接口,以后删除
        return $this->apiAddGoodsToCart($id, $total, $optionid, $gpc_diyformdata, $token, $merch_id);
    }

    /**
     * @param        $id
     * @param int $total
     * @param int $optionid
     * @param null $gpc_diyformdata
     * @param string $token
     * @param int $merch_id
     *
     * @return array
     * @throws \Throwable
     * @throws ApiException
     * @throws \yii\db\Exception
     */
    public function apiAddGoodsToCart($id, $total = 1, $optionid = 0, $gpc_diyformdata = null, $token = '', $merch_id = 0)
    {

        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }
        if (empty(AppUser::getInstance()->openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        ($total <= 0) && ($total = 1);

        if (empty($id)) {
            throw new ApiException(Response::PARAMS_ERROR);
        }

        $goods = pdo_fetch('select id,marketprice,total,diyformid,diyformtype,diyfields, isverify, type,merchid from ' . tablename('new_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $id, ':uniacid' => Request::getInstance()->uniacid));

        if (empty($goods)) {
            throw new ApiException(Response::GOODS_NOT_FOUND);
        }


        if ($goods['total'] < $total) {
            $total = $goods['total'];
        }

        //是否可以加入购物车
        if ($goods['isverify'] == 2 || $goods['type'] == 2 || $goods['type'] == 3) {
            throw new ApiException(Response::NOT_ADD_CART);
        }

//        // 不允许有商品组权限的商品在购物车里
//        $checkGroups = ShopGoods::checkGoodsInFreeGroup($goods['id']);
//        if ($checkGroups) {
//            throw new ApiException(Response::NOT_ADD_CART);
//        }

        $check_group = \common\modules\goods\Module::checkGroupGoodsCanAddCart($goods['id']);
        if ($check_group) {
            //todo
        }

        $check_group_limit_buy = \common\modules\goods\Module::checkGroupsGoodsCanSubmitMemberCart($goods['id'], 1, true);
        if ($check_group_limit_buy) {
            //
        }

        //自定义表单
        $diyform_plugin = p('diyform');
        $diyformid = 0;
        $diyformfields = iserializer(array());
        $diyformdata = iserializer(array());

        if ($diyform_plugin) {
            $diyformdata = $gpc_diyformdata;

            if (is_string($diyformdata)) {
                $diyformdatastring = htmlspecialchars_decode(str_replace('\\', '', $gpc_diyformdata));
                $diyformdata = @json_decode($diyformdatastring, true);
            }


            if (!(empty($diyformdata)) && is_array($diyformdata)) {
                $diyformfields = false;
                if ($goods['diyformtype'] == 1) {
                    //模板
                    $diyformid = intval($goods['diyformid']);
                    $formInfo = $diyform_plugin->getDiyformInfo($diyformid);
                    if (!empty($formInfo)) {
                        $diyformfields = $formInfo['fields'];
                    }

                } else if ($goods['diyformtype'] == 2) {
                    //自定义
                    $diyformfields = iunserializer($goods['diyfields']);
                }


                if (!(empty($diyformfields))) {
                    $insert_data = \common\modules\diyForm\Module::getInsertData($diyformfields, $diyformdata, true);
                    $diyformdata = $insert_data['data'];
                    $diyformfields = iserializer($diyformfields);
                }

            }

        }

        $data = MemberCart::find()
            ->select('id,total,diyformid')
            ->where([
                'goodsid' => $id,
                'uniacid' => Request::getInstance()->uniacid,
                'optionid' => $optionid,
                'deleted' => 0,
                'openid' => AppUser::getInstance()->openid
            ])
            ->asArray()
            ->one();

        if (empty($data)) {
            $seckill = p('seckill');
            $seckillinfo = $seckill->getSeckill($id);
            if ($seckillinfo) {
                Yii::info('--秒杀价格处理--');
                $goods['marketprice'] = $seckillinfo['price'];
                if ((0 < count($seckillinfo['options'])) && $optionid) {
                    //多规格
                    foreach ($seckillinfo['options'] as $sec) {
                        if ($sec['optionid'] == $optionid) {
                            $goods['marketprice'] = $sec['price'];
                        }
                    }
                }
            }
            $data = [
                'uniacid' => Request::getInstance()->uniacid,
                'merchid' => $goods['merchid'],
                'openid' => AppUser::getInstance()->openid,
                'goodsid' => $id,
                'optionid' => $optionid,
                'marketprice' => $goods['marketprice'],
                'total' => $total,
                'selected' => 1,
                'diyformid' => $diyformid,
                'diyformdata' => $diyformdata,
                'diyformfields' => $diyformfields,
                'createtime' => time()
            ];
            $cartid = MemberCart::insertOne($data);
            if (!$cartid) {
                throw new ApiException(Response::CREATE_DATA_ERROR);
            }
        } else {
            $data['diyformid'] = $diyformid;
            $data['diyformdata'] = $diyformdata;
            $data['diyformfields'] = $diyformfields;

            $data['total'] = intval($data['total']); // 原本 198
            $goods['total'] = intval($goods['total']); // 库存 200
            // 如果购物车商品数量 + 现在要买的数量 大于 该商品的库存
            if ($data['total'] + $total > $goods['total']) {
                $data['total'] = $goods['total'];
            } else {
                $data['total'] += $total;
            }
            MemberCart::updateAll($data, ['id' => $data['id']]);
        }

        // 购物车数量
        $cartcount = MemberCart::sumAll([
            'openid' => AppUser::getInstance()->openids,
            'deleted' => 0,
            'uniacid' => Request::getInstance()->uniacid,
        ], 'total');

        $res = [
            'isnew' => false, // ???
            'cartcount' => $cartcount
        ];
        if (!empty($merch_id)) {
            $cart_list = $this->get_cart_list($token, $merch_id);
        }
        if (!empty($cart_list)) {
            $res['cart_list'] = $cart_list;
        }

        return $res;
    }

    public function get_cart_list($token = '', $merch_id = 0)
    {
        //兼容旧接口,以后删除
        return $this->apiGetCartList($token, $merch_id);
    }

    /**
     * 获取购车车商品列表
     * @param string $token
     * @param int $merch_id
     * @return array
     * @throws ApiException
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function apiGetCartList($token = '', $merch_id = 0)
    {
        $uniacid = Request::getInstance()->uniacid;
        $merch_id = intval($merch_id);
        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }
        $openid = AppUser::getInstance()->openid;
        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        $condition = ' and f.uniacid= :uniacid and f.openid=:openid and f.deleted=0';
        $params = array(':uniacid' => $uniacid, ':openid' => $openid);

        if (!empty($merch_id)) {
            $condition = $condition . ' and f.merchid = ' . $merch_id;
        }

        $list = array();
        $total = 0;
        $totalprice = 0;
        $ischeckall = true;

        //会员级别
        $level = \common\models\MemberLevel::getByOpenId($openid);
        $sql = 'SELECT f.id,f.total,f.goodsid,g.total as stock, o.stock as optionstock, g.maxbuy,g.title,g.bargain,g.thumb,ifnull(o.marketprice, g.marketprice) as marketprice,' . ' g.productprice,o.title as optiontitle,f.optionid,o.specs,g.minbuy,g.maxbuy,g.unit,f.merchid,g.merchsale' . ' ,f.selected FROM ' . MemberCart::tableName() . ' f ' . ' left join ' . ShopGoods::tableName() . ' g on f.goodsid = g.id ' . ' left join ' . \common\models\ShopGoodsOption::tableName() . ' o on f.optionid = o.id ' . ' where 1 ' . $condition . ' ORDER BY `id` DESC ';
        $list = pdo_fetchall($sql, $params);

        foreach ($list as &$g) {
            if (!(empty($g['optionid']))) {
                $g['stock'] = $g['optionstock'];

                //读取规格的图片
                if (!empty($g['specs'])) {
                    $thumb = \common\models\ShopGoods::getSpecThumb($g['specs']);
                    if (!empty($thumb)) {
                        $g['thumb'] = $thumb;
                    }
                }
            }
            $seckill = p('seckill');
            $seckillinfo = $seckill->getSeckill($g['goodsid']);
            if ($seckillinfo) {
                Yii::debug('秒杀商品信息' . json_encode($seckillinfo, true), __METHOD__);
                $g['marketprice'] = $seckillinfo['price'];
                if ($g['optionid']) {
                    //多规格
                    foreach ($seckillinfo['options'] as $sec) {
                        if ($sec['optionid'] == $g['optionid']) {
                            $g['marketprice'] = (float)$sec['price'];
                        }
                    }
                }
            } else if ($g['bargain']) {
                //todo
                Yii::info('--砍价商品--');
            } else {
                $g['marketprice'] = (float)$g['marketprice'];
                if ($g['selected']) {
                    //促销或会员折扣
                    $prices = m('order')->getGoodsDiscountPrice($g, $level, 1);
                    $g['marketprice'] = $prices['price'];
                    $totalprice += $g['marketprice'] * $g['total'];
                    $total += $g['total'];
                }
            }

            //库存
            $totalmaxbuy = $g['stock'];

            //todo 这部分逻辑需要抽出来
            //检查商品组商品的限购
            $isgroup = ShopGoods::checkGoodsInFreeGroup($g['goodsid'], true);
            if ($isgroup) {
                $group = array_shift($isgroup);
                $group['free_minbuy'] = intval($group['free_minbuy']);
                $group['free_maxbuy'] = intval($group['free_maxbuy']);
                $group['free_usermaxbuy'] = intval($group['free_usermaxbuy']);
                $g['minbuy'] = $group['free_minbuy'];
                $g['maxbuy'] = $group['free_maxbuy'];

                //单次购买最大量
                if ($group['free_maxbuy'] > 0) {
                    if ($totalmaxbuy != -1) {
                        if ($totalmaxbuy > $group['free_maxbuy']) {
                            Yii::info('单次购买最大量' . $group['free_maxbuy'], __METHOD__);
                            $g['maxbuy'] = $group['free_maxbuy'];
                        }
                    } else {
                        $totalmaxbuy = $group['free_maxbuy'];
                    }
                }

                //总购买量
                if ($group['free_usermaxbuy'] > 0) {
                    $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . \common\models\ShopOrderGoods::tableName() . ' og '
                        . ' left join ' . tablename('new_shop_order') . ' o on og.orderid=o.id '
                        . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $g['goodsid'], ':uniacid' => $uniacid, ':openid' => $openid));
                    $last = $group['free_usermaxbuy'] - $order_goodscount;
                    if ($last <= 0) {
                        $last = 0;
                    }
                    if ($totalmaxbuy != -1) {
                        if ($totalmaxbuy > $last) {
                            $totalmaxbuy = $last;
                        }
                    } else {
                        $totalmaxbuy = $last;
                    }
                }
                //最小购买
                if ($group['free_minbuy'] > 0) {
                    if ($group['free_minbuy'] > $totalmaxbuy) {
                        $group['free_minbuy'] = $totalmaxbuy;
                    }
                }
            }
            //最大购买量
            if (!$isgroup && $g['maxbuy'] > 0) {
                if ($totalmaxbuy != -1) {
                    if ($g['maxbuy'] < $totalmaxbuy) {
                        $totalmaxbuy = $g['maxbuy'];
                    }

                } else {
                    $totalmaxbuy = $g['maxbuy'];
                }
            }


            if (!$isgroup && 0 < $g['usermaxbuy']) {
                $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . ShopOrderGoods::tableName() . ' og ' . ' left join ' . tablename('new_shop_order') . ' o on og.orderid=o.id ' . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $g['goodsid'], ':uniacid' => $uniacid, ':openid' => $openid));
                $last = $g['usermaxbuy'] - $order_goodscount;

                if ($last <= 0) {
                    $last = 0;
                }


                if ($totalmaxbuy != -1) {
                    if ($last < $totalmaxbuy) {
                        $totalmaxbuy = $last;
                    }

                } else {
                    $totalmaxbuy = $last;
                }
            }

            //最小购买
            if (!$isgroup && $g['minbuy'] > 0) {
                if ($g['minbuy'] > $totalmaxbuy) {
                    $g['minbuy'] = $totalmaxbuy;
                }

            }

            if (!$isgroup && $totalmaxbuy < $g['total']) {
                $g['total'] = $totalmaxbuy;
            }

            $g['totalmaxbuy'] = $totalmaxbuy;
            $g['productprice'] = price_format($g['productprice']);
            $g['unit'] = ((empty($data['unit']) ? '件' : $data['unit']));

            if (empty($g['selected'])) {
                $ischeckall = false;
            }

            unset($g['maxbuy']);
        }

        unset($g);
        $list = set_medias($list, 'thumb');
        $result = array(
            'ischeckall' => $ischeckall,
            'total' => (int)$total,
            'totalprice' => (double)$totalprice,
            'empty' => empty($list)
        );
        $merch_plugin = p('merch');
        $merch_data = m('common')->getPluginset('merch');
        if ($merch_plugin && $merch_data['is_openmerch']) {
            $getListUser = $merch_plugin->getListUser($list);
            $merch_user = $getListUser['merch_user'];
            $merch = $getListUser['merch'];
            if (is_array($list) && !(empty($list))) {
                $newlist = array();

                foreach ($merch as $merchid => $merchlist) {
                    $price = 0;
                    $total = 0;
                    foreach ($merchlist as $k => $v) {
                        $price = $price + $v['marketprice'] * $v['total'];
                        $total = $total + $v['total'];
                    }
                    $newlist[] = [
                        'merchname' => $merch_user[$merchid]['merchname'],
                        'merchid' => $merchid,
                        'totalprice' => $price,
                        'total' => $total,
                        'list' => $merchlist
                    ];
                }
            }


            $result['merch_list'] = $newlist;
        } else if (Request::getInstance()->isWxApp) {
            $result['list'] = $list;
        } else {
            $result['merch_list'] = array(
                array('merchname' => '', 'merchid' => 0, 'list' => $list)
            );
        }

        return $result;

    }

    public function get_cart_count($token = '')
    {
        //兼容旧接口,以后删除
        return $this->apiGetCartCount($token);
    }

    /**
     * @param string $token
     *
     * @return array
     * @throws ApiException
     * @throws \Exception
     */
    public function apiGetCartCount($token = '')
    {
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }
        $openid = AppUser::getInstance()->openid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        $cartcount = \common\models\MemberCart::sumAll([
            'openid' => $openid,
            'deleted' => 0,
            'selected' => 1,
            'uniacid' => Request::getInstance()->uniacid,
        ], 'total');
        if ($cartcount < 0) {
            $cartcount = 0;
        }
        return array('cartcount' => $cartcount);
    }

    public function remove_goods_to_cart($ids, $merch_id = 0, $token = '')
    {
        //兼容旧接口,以后删除
        return $this->apiRemoveGoodsToCart($ids, $merch_id, $token);
    }

    /**
     * 删除购物车数据
     *
     * @param array $ids
     * @param int $merch_id
     * @param string $token
     * @return array
     * @throws ApiException
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function apiRemoveGoodsToCart($ids, $merch_id = 0, $token = '')
    {
        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }
        $openids = AppUser::getInstance()->openids;

        if (empty($openids)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        if (empty($ids)) {
            throw new ApiException(Response::PARAMS_ERROR);
        }

        if (!is_array($ids)) {
            $ids = htmlspecialchars_decode(str_replace('\\', '', $ids));
            $ids = @json_decode($ids, true);
        }

        if (empty($ids)) {
            throw new ApiException(Response::PARAMS_ERROR);
        }

        $models = MemberCart::findAll([
            'id' => $ids,
            'openid' => $openids,
            'uniacid' => Request::getInstance()->uniacid,
        ]);
        foreach ($models as $model) {
            $model->deleted = 1;
            $model->save(false);
        }

        $res = [];
        if (!empty($merch_id)) {
            $cart_list = $this->get_cart_list($token, $merch_id);
        }
        if (!empty($cart_list)) {
            $res['cart_list'] = $cart_list;
        }
        return $res;
    }

    public function clean_my_card($token, $merch_id = 0)
    {
        //兼容旧接口,以后删除
        return $this->apiCleanMyCard($token, $merch_id);
    }

    /**
     * @param     $token
     * @param int $merch_id
     *
     * @return array
     * @throws \Throwable
     * @throws ApiException
     * @throws \yii\db\Exception
     */
    public function apiCleanMyCard($token, $merch_id = 0)
    {
        global $_W;

        AppUser::getInstance()->loginByJwt($token);

        $openid = AppUser::getInstance()->openid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        $where = [
            'openid' => $openid,
            'uniacid' => Request::getInstance()->uniacid,
        ];
        if (!empty($merch_id)) {
            $where['merchid'] = $merch_id;
        }
        \common\models\MemberCart::updateAll(['deleted' => 1], $where);

        $res = [
            'success' => 1,
            'success_string' => Yii::t('success_string', '操作成功'),
        ];

        if (!empty($merch_id)) {
            $cart_list = $this->get_cart_list($token, $merch_id);
        }
        if (!empty($cart_list)) {
            $res['cart_list'] = $cart_list;
        }

        return $res;
    }

    public function update_my_cart($id = 0, $goodstotal = 0, $optionid = 0, $gpc_diyformdata = null, $token = '', $merch_id = 0)
    {
        //兼容旧接口,以后删除
        return $this->apiUpdateMyCart($id, $goodstotal, $optionid, $gpc_diyformdata, $token, $merch_id);
    }

    /**
     * @param int $id
     * @param int $goodstotal
     * @param int $optionid
     * @param null $gpc_diyformdata
     * @param string $token
     * @param int $merch_id
     *
     * @return array
     * @throws \Throwable
     * @throws ApiException
     * @throws \yii\db\Exception
     */
    public function apiUpdateMyCart($id = 0, $goodstotal = 0, $optionid = 0, $gpc_diyformdata = null, $token = '', $merch_id = 0)
    {
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }
        $openid = AppUser::getInstance()->openid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }


        if (empty($id)) {
            throw new ApiException(Response::PARAMS_ERROR);
        }

        $data = \common\models\MemberCart::fetchOne([
            'id' => $id,
            'openid' => \common\components\AppUser::getInstance()->openids,
            'uniacid' => Request::getInstance()->uniacid,
        ]);
        if (empty($data)) {
            throw new ApiException(Response::NOT_IN_CART);
        }

        $goods = pdo_fetch('select id,maxbuy,minbuy,total,unit from ' . tablename('new_shop_goods') . ' where id=:id and uniacid=:uniacid and status=1 and deleted=0', array(':id' => $data['goodsid'], ':uniacid' => Request::getInstance()->uniacid));

        if (empty($goods)) {
            throw new ApiException(Response::GOODS_NOT_FOUND);
        }

        //自定义表单
        $diyform_plugin = p('diyform');
        $diyformid = 0;
        $diyformfields = iserializer(array());
        $diyformdata = iserializer(array());

        if ($diyform_plugin) {
            $diyformdata = $gpc_diyformdata;

            if (!(empty($diyformdata)) && is_string($diyformdata)) {
                $diyformdatastring = htmlspecialchars_decode(str_replace('\\', '', $gpc_diyformdata));
                $diyformdata = @json_decode($diyformdatastring, true);
            }


            if (!(empty($diyformdata)) && is_array($diyformdata)) {
                $diyformfields = false;

                if ($goods['diyformtype'] == 1) {
                    //模板
                    $diyformid = intval($goods['diyformid']);
                    $formInfo = $diyform_plugin->getDiyformInfo($diyformid);

                    if (!(empty($formInfo))) {
                        $diyformfields = $formInfo['fields'];
                    }

                } else if ($goods['diyformtype'] == 2) {
                    //自定义
                    $diyformfields = iunserializer($goods['diyfields']);
                }


                if (!(empty($diyformfields))) {
                    $insert_data = \common\modules\diyForm\Module::getInsertData($diyformfields, $diyformdata, true);
                    $diyformdata = $insert_data['data'];
                    $diyformfields = iserializer($diyformfields);
                }

            }

        }

        if ($goodstotal < 1) {
            \common\models\MemberCart::deleteAll([
                'id' => $id,
                'openid' => $openid,
                'uniacid' => Request::getInstance()->uniacid,
            ]);
        } else {
            $arr = array('total' => $goodstotal, 'optionid' => $optionid, 'diyformid' => $diyformid, 'diyformdata' => $diyformdata, 'diyformfields' => $diyformfields);
            \common\models\MemberCart::updateAll($arr, array('id' => $id, 'uniacid' => Request::getInstance()->uniacid, 'openid' => $_W['openid']));
        }

        $res = [];
        if (!empty($merch_id)) {
            $cart_list = $this->get_cart_list($token, $merch_id);
        }
        if (!empty($cart_list)) {
            $res['cart_list'] = $cart_list;
        }
        return $res;


    }


}