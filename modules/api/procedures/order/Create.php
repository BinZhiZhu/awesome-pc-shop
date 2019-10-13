<?php

namespace common\modules\api\procedures\order;

use AmsClient;
use common\components\AppUser;
use common\components\Request;
use common\components\Response;
use common\models\BargainGoods;
use common\models\CorePayLog;
use common\models\BargainActor;
use common\models\McMappingFan;
use common\models\MemberAddress;
use common\models\ShopGift;
use common\models\ShopGoods;
use common\models\ShopOrder;
use common\models\ShopOrderGoods;
use common\modules\api\procedures\ApiException;
use common\modules\api\procedures\BaseAppApi;
use Exception;
use Yii;
use yii\data\Sort;
use yii\helpers\ArrayHelper;

class Create extends BaseAppApi
{

    public function create_order($giftid = 0, $id = 0,
                                 $packageid = 0, $iswholesale = 0, $bargain_id = 0, $optionid = 0,
                                 $total = 0, $gdid = 0, $g = null, $token = '', $merch_id = 0
    )
    {
        //兼容旧接口,以后删除
        return $this->apiGetCreateOrderData($giftid, $id, $packageid, $iswholesale, $bargain_id, $optionid, $total, $gdid, $g, $token, $merch_id);
    }

    /**
     * @param int $giftid 赠品id
     * @param int $id 商品id，不从购物车进入必填
     * @param int $packageid 套餐id
     * @param int $iswholesale
     * @param int $bargain_id 砍价id
     * @param int $optionid 多规格id
     * @param int $total 数量，不从购物车进入必填
     * @param int $gdid 自定义表单id
     * @param null $g
     * @param string $token
     * @param int $merch_id
     *
     * @return array
     * @throws \Throwable
     * @throws ApiException
     * @throws \yii\db\Exception
     */
    public function apiGetCreateOrderData($giftid = 0, $id = 0,
                                          $packageid = 0, $iswholesale = 0, $bargain_id = 0, $optionid = 0,
                                          $total = 0, $gdid = 0, $g = null, $token = '', $merch_id = 0
    )
    {
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = Request::getInstance()->uniacid;
        $create_merch_order = intval($merch_id);

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        //秒杀部分
        $open_redis = function_exists('redis') && !(is_error(redis()));
        $seckillinfo = false;

        //允许参加优惠
        $allow_sale = true;

        //获取前台用户数据
        $member = \common\models\ShopMember::getInfo($openid);

        //找不到用户信息就抛出未登陆异常
        if (empty($member)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        // 多商户
        $merch_plugin = p('merch');

        //获取多商户配置
        $merch_set = m('common')->getPluginset('merch');

        //标示是否开启多商户
        if ($merch_plugin && $merch_set['is_openmerch']) {
            $is_openmerch = 1;
        } else {
            $is_openmerch = 0;
        }

        // 无packageID也就是不参与套餐
        if (empty($packageid) ) {
            //非套餐订单
            $merch_array = array();
            $merchs = array();
            $merch_id = 0;

            //查找快递手机号码，没有就用用户手机号码
            $member['carrier_mobile'] = empty($member['carrier_mobile']) ? $member['mobile'] : $member['carrier_mobile'];

            //会员级别
            $level = \common\models\MemberLevel::getByOpenId($openid);

            //获取自定义表单模型
            $diyform_plugin = p('diyform');
            $order_formInfo = false;
            $diyform_set = false;
            $orderdiyformid = 0;
            $fields = array();
            $f_data = array();

            //如果有自定义表单的话就拿自定义表单数据
            if ($diyform_plugin) {
                $diyform_set = $_W['shopset']['diyform'];

                if (!(empty($diyform_set['order_diyform_open']))) {
                    $orderdiyformid = intval($diyform_set['order_diyform']);

                    if (!(empty($orderdiyformid))) {
                        $order_formInfo = $diyform_plugin->getDiyformInfo($orderdiyformid);
                        $fields = $order_formInfo['fields'];
                        $f_data = $diyform_plugin->getLastOrderData($orderdiyformid, $member);
                    }
                }
            }

            //这段代码是干嘛的
            $appDatas = array();
            if ($diyform_plugin) {
                $this_member = null;
                if (!AppUser::getInstance()->isGuest) {
                    $this_member = AppUser::getInstance()->identity->toArray();
                }
                $appDatas = $diyform_plugin->wxApp($fields, $f_data, $this_member);
            }

            //处理砍价优惠
            Yii::$app->session->remove('bargain_id');
            if (p('bargain') && $bargain_id) {
                Yii::$app->session->set('bargain_id', $bargain_id);
                //获取砍价
                $bargain_act = BargainActor::fetchOne([
                    'id' => $bargain_id,
                    'openid' => AppUser::getInstance()->openid
                ]);
                if (empty($bargain_act)) {
                    throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
                }

                $bargain_act_id = BargainGoods::fetchOne([
                    'id' => $bargain_act['goods_id']
                ]);
                if (empty($bargain_act_id)) {
                    throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
                }

                $if_bargain = ShopGoods::fetchOne([
                    'id' => $bargain_act_id['goods_id'],
                    'uniacid' => Request::getInstance()->uniacid
                ]);

                if (empty($if_bargain['bargain'])) {
                    throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
                }

                $id = $bargain_act_id['goods_id'];
            }


            if ($total < 1) {
                $total = 1;
            }

            $buytotal = $total; //备份数量
            //错误代码 0 正常 1 未找到商品
            $errcode = 0;

            //是否为核销单
            $isverify = false;

            //是否为虚拟物品(虚拟或卡密)
            $isvirtual = false;

            //是否是虚拟物品自动发货
            $isvirtualsend = false;
            $isonlyverifygoods = true;

            //是否可调整商品数量
            $changenum = false;

            //是否从购物车购买
            $fromcart = 0;

            //是否提供提供发票
            $hasinvoice = false;

            //最后一个发票名称
            $invoicename = '';

            //是否支持优惠
            $buyagain_sale = true;
            $buyagainprice = 0;
            $isonlyverifygoods = true;

            //所有商品
            $goods = array();
            $giftGood = array();
            $gifts = array();

            // 购物车购买 id=商品id
            if (empty($id)) {
                $sql = 'SELECT c.goodsid,c.total,g.maxbuy,g.type,g.is_send_free,g.isnodiscount,g.unit' . ',g.weight,o.weight as optionweight,g.title,g.thumb,ifnull(o.marketprice, g.marketprice) as marketprice,o.title as optiontitle,c.optionid,g.isfullback,' . ' g.storeids,g.isverify,g.deduct,g.manydeduct,g.virtual,o.virtual as optionvirtual,discounts,' . ' g.deduct2,g.ednum,g.edmoney,g.edareas,g.diyformtype,g.diyformid,diymode,g.dispatchtype,g.dispatchid,g.dispatchprice,g.minbuy ' . ' ,g.isdiscount,g.isdiscount_time,g.isdiscount_discounts,g.cates, ' . ' g.virtualsend,invoice,o.specs,g.merchid,g.checked,g.merchsale,' . ' g.buyagain,g.buyagain_islong,g.buyagain_condition, g.buyagain_sale' . ' FROM ' . tablename('new_shop_member_cart') . ' c ' . ' left join ' . tablename('new_shop_goods') . ' g on c.goodsid = g.id ' . ' left join ' . \common\models\ShopGoodsOption::tableName() . ' o on c.optionid = o.id ' . ' where c.openid=:openid and c.selected=1 and  c.deleted=0 and c.uniacid=:uniacid';
                if (!empty($create_merch_order)) {
                    $sql = $sql . ' and c.merchid=' . $create_merch_order;
                }
                $sql = $sql . ' order by c.id desc';

                $goods = pdo_fetchall($sql, array(':uniacid' => $uniacid, ':openid' => $openid));

                if (empty($goods)) {
                    throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
                }


                foreach ($goods as $k => $v) {
                    if ($is_openmerch == 0) {
                        //未开启多商户的情况下,购物车中是否有多商户的商品
                        if ($v['merchid'] > 0) {
                            throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
                        }
                    } //判断多商户商品是否通过审核
                    else if ((0 < $v['merchid']) && ($v['checked'] == 1)) {
                        throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
                    }

                    if ($k == 0) {
                        $merch_id = $v['merchid'];
                    }

                    if ($merch_id == $v['merchid']) {
                        $merch_id = $v['merchid'];
                    } else {
                        $merch_id = 0;
                    }

                    // 读取规格的图片
                    if (!empty($v['specs'])) {
                        $thumb = \common\models\ShopGoods::getSpecThumb($v['specs']);
                        if (!empty($thumb)) {
                            $goods[$k]['thumb'] = $thumb;
                        }
                    }

                    if (!(empty($v['optionvirtual']))) {
                        $goods[$k]['virtual'] = $v['optionvirtual'];
                    }


                    if (!(empty($v['optionweight']))) {
                        $goods[$k]['weight'] = $v['optionweight'];
                    }

                    //秒杀信息
                    $goods[$k]['seckillinfo'] = p('seckill')->getSeckill($v['goodsid'], $v['optionid'], true, $openid);

                    if (!(empty($goods[$k]['seckillinfo']['maxbuy'])) && (($goods[$k]['seckillinfo']['maxbuy'] - $goods[$k]['seckillinfo']['selfcount']) < $goods[$k]['total'])) {
                        throw new Exception('您已购买了' . $goods[$k]['seckillinfo']['selfcount'] . '最多购买' . $goods[$k]['seckillinfo']['maxbuy'] . '件');
                    }

                    $check_group = \common\modules\goods\Module::checkGroupsGoodsCanSubmitMemberCart($v['goodsid'], 1,true);
                    if($check_group){
                        //
                    }
                }

                $fromcart = 1;
            } else {
                // id不为空, 说明是单个购买(立即购买进来的)
                //直接购买（查那一个商品）
                $sql = 'SELECT id as goodsid,type,title,weight,is_send_free,isnodiscount,isfullback, ' . ' thumb,marketprice,storeids,isverify,deduct,' . ' manydeduct,`virtual`,maxbuy,usermaxbuy,discounts,total as stock,deduct2,showlevels,' . ' ednum,edmoney,edareas,' . ' diyformtype,diyformid,diymode,dispatchtype,dispatchid,dispatchprice,cates,minbuy, ' . ' isdiscount,isdiscount_time,isdiscount_discounts, ' . ' virtualsend,invoice,needfollow,followtip,followurl,merchid,checked,merchsale, ' . ' buyagain,buyagain_islong,buyagain_condition, buyagain_sale' . ' FROM ' . tablename('new_shop_goods') . ' where id=:id and uniacid=:uniacid  limit 1';
                //这个商品的数据
                $data = pdo_fetch($sql, array(':uniacid' => $uniacid, ':id' => $id));

                //处理砍价优惠
                if (!(empty($bargain_act))) {
                    $data['marketprice'] = $bargain_act['now_price'];
                    Yii::debug('--砍价订单金额--' . $data['marketprice'], __METHOD__);
                }

                //秒杀信息
                $data['seckillinfo'] = p('seckill')->getSeckill($data['goodsid'], $optionid, true, $openid);
                if ($data['seckillinfo'] && ($data['seckillinfo']['status'] == 0)) {
                    //秒杀不管赠品
                    $changenum = false; //秒杀不能修改数量
                }
                //多商户id
                $merch_id = $data['merchid'];
                //全返
                $fullbackgoods = array();
                //是否全返
                if ($data['isfullback']) {
                    $fullbackgoods = pdo_fetch('SELECT * FROM ' . \common\models\FullBackGoods::tableName() . ' WHERE goodsid = :goodsid and uniacid = :uniacid and status = 1 limit 1 ', array(':goodsid' => $data['goodsid'], ':uniacid' => $uniacid));
                }

                if (empty($data) || (!(empty($data['showlevels'])) && !(strexists($data['showlevels'], $member['level']))) || ((0 < $data['merchid']) && ($data['checked'] == 1)) || (($is_openmerch == 0) && (0 < $data['merchid']))) {
                    throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
                }

//                $follow = McMappingFan::isFollowed($openid);

//                if (!(empty($data['needfollow'])) && !($follow) && is_weixin()) {
//                    $followtip = ((empty($goods['followtip']) ? '如果您想要购买此商品，需要您关注我们的公众号，点击【确定】关注后再来购买吧~' : $goods['followtip']));
//                    $followurl = ((empty($goods['followurl']) ? $_W['shopset']['share']['followurl'] : $goods['followurl']));
//                    \common\helpers\Message::info($followtip, $followurl);
//                }

                //最少购买个数
                if ((0 < $data['minbuy']) && ($total < $data['minbuy'])) {
                    $total = $data['minbuy'];
                }

                //如果是秒杀的话就把商品total设置为1
                if ($data['seckillinfo'] && ($data['seckillinfo']['status'] == 0)) {
                    $is_seckill = true;
                    $total = 1;
                }

                $data['total'] = $total;
                $data['optionid'] = $optionid;

                //非砍价多规格
                if (!empty($optionid) && !$bargain_id) {
                    //拿到制定规格
                    $option = \common\models\ShopGoodsOption::fetchOne([
                        'id' => $optionid,
                        'goodsid' => $id,
                        'uniacid' => $uniacid,
                    ]);
                    if (!(empty($option))) {
                        $data['optionid'] = $optionid;
                        $data['optiontitle'] = $option['title'];
                        $data['marketprice'] = $option['marketprice'];
                        $data['virtual'] = $option['virtual'];
                        $data['stock'] = $option['stock'];

                        if (!(empty($option['weight']))) {
                            $data['weight'] = $option['weight'];
                        }

                        if (!empty($option['specs'])) {
                            $thumb = \common\models\ShopGoods::getSpecThumb($option['specs']);
                            if (!empty($thumb)) {
                                $data['thumb'] = $thumb;
                            }
                        }

                        if ($option['isfullback'] && !(empty($fullbackgoods))) {
                            $fullbackgoods['minallfullbackallprice'] = $option['allfullbackprice'];
                            $fullbackgoods['fullbackprice'] = $option['fullbackprice'];
                            $fullbackgoods['minallfullbackallratio'] = $option['allfullbackratio'];
                            $fullbackgoods['fullbackratio'] = $option['fullbackratio'];
                            $fullbackgoods['day'] = $option['day'];
                        }
                    }
                }
                //可以调整数量
                if ($giftid) {
                    $changenum = false;
                } else {
                    $changenum = true;
                }


                $goods[] = $data;
            }
            //设置商品图片路径
            $goods = set_medias($goods, 'thumb');
            //遍历商品数组
            foreach ($goods as &$g) {
                $isSeckill = ($g['seckillinfo'] && ($g['seckillinfo']['status'] == 0));

                if ($isSeckill) {
                    $g['is_task_goods'] = 0;
                } else {
                    if (p('task')) {
                        $task_id = intval(Yii::$app->session->get($id . '_task_id'));

                        if (!empty($task_id)) {
                            $rewarded = \common\models\TaskExtensionJoin::fetchField([
                                'id' => $task_id,
                                'openid' => \common\components\AppUser::getInstance()->openids,
                                'uniacid' => \common\components\Request::getInstance()->uniacid,
                            ], 'rewarded');
                            $taskGoodsInfo = unserialize($rewarded);
                            $taskGoodsInfo = $taskGoodsInfo['goods'][$id];

                            if (!(empty($optionid)) && !(empty($taskGoodsInfo['option'])) && ($optionid == $taskGoodsInfo['option'])) {
                                $taskgoodsprice = $taskGoodsInfo['price'];
                            } else if (empty($optionid)) {
                                $taskgoodsprice = $taskGoodsInfo['price'];
                            }

                        }

                    }

                    //任务活动购买商品
                    $rank = intval(Yii::$app->session->get($id . '_rank'));
                    $join_id = intval(Yii::$app->session->get($id . '_join_id'));
                    $task_goods_data = \common\modules\task\Module::getGoodsInfo($openid, $id, $rank, $join_id, $optionid);

                    if (empty($task_goods_data['is_task_goods'])) {
                        $g['is_task_goods'] = 0;
                    } else {
                        $allow_sale = false;
                        $g['is_task_goods'] = $task_goods_data['is_task_goods'];
                        $g['is_task_goods_option'] = $task_goods_data['is_task_goods_option'];
                        $g['task_goods'] = $task_goods_data['task_goods'];
                    }
                }

                if ($is_openmerch == 1) {
                    if (!$bargain_id) {
                        $merchid = $g['merchid'];
                        $merch_array[$merchid]['goods'][] = $g['goodsid'];
                    }
                }

                if ($g['isverify'] == 2) {
                    //核销商品
                    $isverify = true;
                }

                if (!(empty($g['virtual'])) || ($g['type'] == 2) || ($g['type'] == 3) || ($g['type'] == 20)) {
                    //虚拟商品
                    $isvirtual = true;

                    //是否虚拟物品自动发货
                    if ($g['virtualsend']) {
                        $isvirtualsend = true;
                    }
                }

                if ($g['invoice']) {
                    $hasinvoice = $g['invoice'];
                }

                if ($g['type'] != 5) {
                    $isonlyverifygoods = false;
                }

                //最大购买量
                //库存
                $totalmaxbuy = $g['stock'];

                //最大购买量 秒杀只读取自己的总购买数限制 无二次购买

                if ($isSeckill) {
                    $seckilllast = 0;

                    if (0 < $g['seckillinfo']['maxbuy']) {
                        $seckilllast = $g['seckillinfo']['maxbuy'] - $g['seckillinfo']['selfcount'];
                    }


                    $g['totalmaxbuy'] = $g['total'];
                } else {
                    if ($g['maxbuy'] > 0) {
                        if ($totalmaxbuy != -1) {
                            if ($g['maxbuy'] < $totalmaxbuy) {
                                $totalmaxbuy = $g['maxbuy'];
                            }

                        } else {
                            $totalmaxbuy = $g['maxbuy'];
                        }
                    }
                }

                //总购买量
                if (0 < $g['usermaxbuy']) {
                    $sql = 'select ifnull(sum(og.total),0)  from ' . ShopOrderGoods::tableName() . ' og '
                        . ' left join ' . ShopOrder::tableName() . ' o on og.orderid=o.id '
                        . ' where og.goodsid=:goodsid and  o.status>=0 and o.openid=:openid  and og.uniacid=:uniacid ';
                    $order_goodscount = pdo_fetchcolumn($sql, array(':goodsid' => $g['goodsid'], ':uniacid' => $uniacid, ':openid' => $openid));
                    $last = $data['usermaxbuy'] - $order_goodscount;

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


                if (!(empty($g['is_task_goods']))) {
                    if ($g['task_goods']['total'] < $totalmaxbuy) {
                        $totalmaxbuy = $g['task_goods']['total'];
                    }

                }


                $g['totalmaxbuy'] = $totalmaxbuy;

                if (($g['totalmaxbuy'] < $g['total']) && !(empty($g['totalmaxbuy']))) {
                    $g['total'] = $g['totalmaxbuy'];
                }

                if ((0 < floatval($g['buyagain'])) && empty($g['buyagain_sale'])) {
                    //第一次后买东西享受优惠
                    if (m('goods')->canBuyAgain($g)) {
                        $buyagain_sale = false;
                    }

                }
            }

            unset($g);

            if ($hasinvoice) {
                $invoicename = pdo_fetchcolumn('select invoicename from ' . tablename('new_shop_order') . ' where openid=:openid and uniacid=:uniacid and ifnull(invoicename,\'\')<>\'\'', array(':openid' => $openid, ':uniacid' => $uniacid));

                if (empty($invoicename)) {
                    $invoicename = $member['realname'];
                }

            }

            if ($is_openmerch == 1) {
                //读取多商户营销设置
                foreach ($merch_array as $key => $value) {
                    if ($key > 0) {
                        $merch_id = $key;
                        $merch_array[$key]['set'] = $merch_plugin->getSet('sale', $key);
                        $merch_array[$key]['enoughs'] = $merch_plugin->getEnoughs($merch_array[$key]['set']);
                    }

                }
            }

            //商品总重量
            $weight = 0;

            //计算初始价格
            $total = 0; //商品数量
            $goodsprice = 0; //商品价格
            $realprice = 0; //需支付
            $deductprice = 0; //积分抵扣的
            $taskdiscountprice = 0; //任务活动优惠
            $lotterydiscountprice = 0; //游戏活动优惠
            $discountprice = 0; //会员优惠
            $isdiscountprice = 0; //促销优惠
            $deductprice2 = 0; //余额抵扣限额
            $stores = array(); //核销门店
            $address = false; //默认地址
            $carrier = false; //自提地点
            $carrier_list = array(); //自提点
            $dispatch_list = false;
            $dispatch_price = 0; //邮费

            //秒杀部分
            $seckill_dispatchprice = 0; //秒杀商品的运费
            $seckill_price = 0;//秒杀减少的金额
            $seckill_payprice = 0;//秒杀的消费金额

            $ismerch = 0;

            if ($is_openmerch == 1) {
                if (!(empty($merch_array))) {
                    if (1 < count($merch_array)) {
                        $ismerch = 1;
                    }
                }
            }

            if (!$isverify && !$isvirtual && !$ismerch) { //虚拟 或 卡密 或 不同多商户的商品 不读取自提点
                $carrier_list = \common\models\Store::find()
                    ->where([
                        'merchid' => intval($merch_id),
                        'status' => 1,
                        'type' => [1, 3],
                        'uniacid' => Request::getInstance()->uniacid,
                    ])
                    ->orderBy(['displayorder' => SORT_DESC, 'id' => SORT_DESC])
                    ->asArray()
                    ->all();
            }

            //营销插件
            $sale_plugin = com('sale');
            $saleset = false;
            if ($sale_plugin && $buyagain_sale && $allow_sale) {
                $saleset = $_W['shopset']['sale'];
                $saleset['enoughs'] = $sale_plugin->getEnoughs();
            }


            //计算产品成交价格及是否包邮
            foreach ($goods as &$g) {
                if (empty($g['total']) || (intval($g['total']) < 1)) {
                    $g['total'] = 1;
                }
                $isSeckill = ($g['seckillinfo'] && ($g['seckillinfo']['status'] == 0));

                if ($isSeckill) {
                    //秒杀无优惠
                    $gprice = $g['ggprice'] = $g['seckillinfo']['price'] * $g['total'];
                    $seckill_payprice += $g['seckillinfo']['price'] * $g['total'];
                    $seckill_price += ($g['marketprice'] * $g['total']) - $gprice;//秒杀优惠
                    $goodsprice = $gprice;
                    Yii::debug('--秒杀优惠--' . $seckill_price, __METHOD__);
                    Yii::debug('--秒杀价格--' . $goodsprice, __METHOD__);
                } else {
                    //商品原价
                    $gprice = $g['marketprice'] * $g['total'];

                    //促销或会员折扣
                    $prices = m('order')->getGoodsDiscountPrice($g, $level);

                    $g['ggprice'] = $prices['price'];
                    $g['unitprice'] = $prices['unitprice'];

                    if ($is_openmerch == 1) {
                        $merchid = $g['merchid'];
                        $merch_array[$merchid]['ggprice'] += $g['ggprice'];
                        $merchs[$merchid] += $g['ggprice'];
                    }

                    $g['dflag'] = intval($g['ggprice'] < $gprice);
                    if (!$bargain_id) {//如果不是砍价订单,执行下面语句
                        //任务活动优惠
                        $taskdiscountprice += $prices['taskdiscountprice'];

                        //折扣价格
                        $g['taskdiscountprice'] = $prices['taskdiscountprice'];
                        $g['discountprice'] = $prices['discountprice'];
                        $g['isdiscountprice'] = $prices['isdiscountprice'];
                        $g['discounttype'] = $prices['discounttype'];
                        $g['isdiscountunitprice'] = $prices['isdiscountunitprice'];
                        $g['discountunitprice'] = $prices['discountunitprice'];

                        $buyagainprice += $prices['buyagainprice'];
                        if ($prices['discounttype'] == 1) {
                            //促销优惠
                            $isdiscountprice += $prices['isdiscountprice'];
                        } else if ($prices['discounttype'] == 2) {
                            //会员优惠
                            $discountprice += $prices['discountprice'];
                        }
                    }

                    //需要支付
                    $realprice += $g['ggprice'];

                    //商品原价
                    //$goodsprice += $gprice;
                    if ($gprice > $g['ggprice']) {
                        $goodsprice += $gprice;
                    } else {
                        $goodsprice += $g['ggprice'];
                    }

                    //商品数据
                    $total += $g['total'];
                    if (!$bargain_id) {
                        //如果不是砍价订单,执行下面语句
                        if (floatval($g['buyagain']) > 0 && empty($g['buyagain_sale'])) {
                            //第一次后买东西享受优惠
                            if (m('goods')->canBuyAgain($g)) {
                                $g['deduct'] = 0;
                            }
                        } elseif ($isSeckill) {
                            //秒杀不参与二次购买
                            $g['deduct'] = 0;
                        }


                        if ($isSeckill) {
                            //秒杀不参与抵扣
                        } elseif ($open_redis) {
                            //积分抵扣
                            if (intval($g['manydeduct'])) {
                                $deductprice += $g['deduct'] * $g['total'];
                            } else {
                                $deductprice += $g['deduct'];
                            }

                            //余额抵扣限额
                            if ($g['deduct2'] == 0) {
                                //全额抵扣
                                $deductprice2 += $g['ggprice'];
                            } else if (0 < $g['deduct2']) {
                                //最多抵扣
                                if ($g['ggprice'] < $g['deduct2']) {
                                    $deductprice2 += $g['ggprice'];
                                } else {
                                    $deductprice2 += $g['deduct2'];
                                }
                            }
                        }
                    }
                }


            }

            unset($g);

            if ($isverify) {
                //核销单 所有核销门店
                $storeids = array();
                $merchid = 0;

                foreach ($goods as $g) {
                    if (!(empty($g['storeids']))) {
                        $merchid = $g['merchid'];
                        $storeids = array_merge(explode(',', $g['storeids']), $storeids);
                    }

                }

                //门店加入支持核销的判断
                $stores = \common\modules\store\Module::getSupportVerifyStores($storeids, $merchid);
            } else {
                Yii::debug($member, __METHOD__);
                Yii::debug('读取地址信息，当前队长ID：' . $member['team_captain_id'], __METHOD__);
                $canSelectAddress = true;
                if ($member['team_captain_id']) {
                    $teamCaptain = \common\models\ShopMember::findOne($member['team_captain_id']);
                    if ($teamCaptain && $teamCaptain->agentlevel) {
                        $level = \common\models\CommissionLevel::findOne($teamCaptain->agentlevel);
                        // 强制使用上级等级
                        if ($level && $level->force_use_team_captain_address) {
                            $address = \common\models\MemberAddress::find()
                                ->where([
                                    'deleted' => 0,
                                    'openid' => \common\models\McMappingFan::getAllRelatedOpenIDs($teamCaptain->openid),
                                    'uniacid' => $uniacid,
                                ])
                                ->orderBy(['isdefault' => SORT_DESC])
                                ->asArray()
                                ->one();
                            $canSelectAddress = false;
                        }
                    }
                }

                //默认地址
                if(!$address){
                    $address_list = MemberAddress::find()->select('*')->where([
                        'deleted' => 0,
                        'openid' => McMappingFan::getAllRelatedOpenIDs($openid),
                        'uniacid' => $uniacid,
                    ])->orderBy([
                        'isdefault'=>SORT_DESC,
                        'id'=>SORT_DESC
                    ])->asArray()->all();
                    Yii::debug('地址信息');
                    Yii::debug($address_list);
                    foreach ($address_list as $address_item) {
                        if ($address_item['isdefault'] == 1)
                        {
                            $address = $address_item;
                            break;
                        }
                    }
                }

                if (!empty($carrier_list)) {
                    $carrier = $carrier_list[0];
                }

                //实体物品计算运费
                if (!($isvirtual) && !($isonlyverifygoods)) {
                    $dispatch_array = m('order')->getOrderDispatchPrice($goods, $member, $address, $saleset, $merch_array, 0);
                    $dispatch_price = $dispatch_array['dispatch_price'] - $dispatch_array['seckill_dispatch_price'];
                    $seckill_dispatchprice = $dispatch_array['seckill_dispatch_price'];
                }
            }

            //多商户满减
            if ($is_openmerch == 1) {
                $merch_enough = \common\modules\order\Module::getMerchEnough($merch_array);
                $merch_array = $merch_enough['merch_array'];
                $merch_enough_total = $merch_enough['merch_enough_total'];
                $merch_saleset = $merch_enough['merch_saleset'];

                if (0 < $merch_enough_total) {
                    $realprice -= $merch_enough_total;
                }
            }
            if ($saleset) {
                if (!$bargain_id) {
                    foreach ($saleset['enoughs'] as $e) {
                        if ((floatval($e['enough']) <= $realprice - $seckill_payprice) && (0 < floatval($e['money']))) {
                            $saleset['showenough'] = true;
                            $saleset['enoughmoney'] = $e['enough'];
                            $saleset['enoughdeduct'] = $e['money'];
                            $realprice -= floatval($e['money']);
                            break;
                        }

                    }
                }
            }

            $realprice += $dispatch_price + $seckill_dispatchprice;
            $deductcredit = 0;//抵扣需要扣除的积分
            $deductmoney = 0; //抵扣的钱
            $deductcredit2 = 0; //余额抵扣的钱


            if (!empty($saleset)) {
                // 积分抵扣
                if (!empty($saleset['creditdeduct'])) {
                    $credit = floatval($member['credit1']);
                    Yii::debug("用户积分{$credit}", __METHOD__);
                    $pcredit = intval($saleset['credit']); //积分比例
                    $pmoney = round(floatval($saleset['money']), 2); //抵扣比例

                    if ((0 < $pcredit) && (0 < $pmoney)) {
                        if (($credit % $pcredit) == 0) {
                            $deductmoney = round(intval($credit / $pcredit) * $pmoney, 2);
                        } else {
                            $deductmoney = round((intval($credit / $pcredit) + 1) * $pmoney, 2);
                        }
                    }

                    if ($deductprice < $deductmoney) {
                        $deductmoney = $deductprice;
                    }

                    //减掉秒杀的金额再抵扣
                    if ($deductmoney > $realprice - $seckill_payprice) {
                        $deductmoney = $realprice - $seckill_payprice;
                    }


                    if (($pmoney * $pcredit) != 0) {
                        $deductcredit = ($deductmoney / $pmoney) * $pcredit;
                    }


                }
                // TODO: 余额抵扣
                //补充余额抵扣
                if (!empty($saleset['moneydeduct'])) {
                    $deductcredit2 = $member['credit2'];

                    //减掉秒杀的金额再抵扣
                    if ($deductcredit2 > $realprice - $seckill_payprice) {
                        $deductcredit2 = $realprice - $seckill_payprice;
                    }
                    if ($deductcredit2 > $realprice) {
                        $deductcredit2 = $realprice;
                    }
                }
            }

            $goodsdata = array();
            $goodsdata_temp = array();

            foreach ($goods as $g) {
                if (0 < floatval($g['buyagain'])) {
                    if (!(m('goods')->canBuyAgain($g)) || !(empty($g['buyagain_sale']))) {
                        $goodsdata_temp[] = array('goodsid' => $g['goodsid'], 'total' => $g['total'], 'optionid' => $g['optionid'], 'marketprice' => $g['marketprice'], 'merchid' => $g['merchid'], 'cates' => $g['cates'], 'discounttype' => $g['discounttype'], 'isdiscountprice' => $g['isdiscountprice'], 'discountprice' => $g['discountprice'], 'isdiscountunitprice' => $g['isdiscountunitprice'], 'discountunitprice' => $g['discountunitprice']);
                    }

                } else {
                    $goodsdata_temp[] = array('goodsid' => $g['goodsid'], 'total' => $g['total'], 'optionid' => $g['optionid'], 'marketprice' => $g['marketprice'], 'merchid' => $g['merchid'], 'cates' => $g['cates'], 'discounttype' => $g['discounttype'], 'isdiscountprice' => $g['isdiscountprice'], 'discountprice' => $g['discountprice'], 'isdiscountunitprice' => $g['isdiscountunitprice'], 'discountunitprice' => $g['discountunitprice']);
                }

                $goodsdata[] = array('goodsid' => $g['goodsid'], 'total' => $g['total'], 'optionid' => $g['optionid'], 'marketprice' => $g['marketprice'], 'merchid' => $g['merchid'], 'cates' => $g['cates'], 'discounttype' => $g['discounttype'], 'isdiscountprice' => $g['isdiscountprice'], 'discountprice' => $g['discountprice'], 'isdiscountunitprice' => $g['isdiscountunitprice'], 'discountunitprice' => $g['discountunitprice']);
            }



            //可用优惠券(减掉秒杀的商品及总价)

            $couponcount = \common\modules\coupon\Module::consumeCouponCount($openid, $realprice, $merch_array, $goodsdata_temp);
            if (empty($goodsdata_temp) || !($allow_sale)) {
                $couponcount = 0;
            }

            // 强制绑定手机号
            $mustbind = 0;

            if (!(empty($_W['shopset']['wap']['open'])) && !(empty($_W['shopset']['wap']['mustbind'])) && empty($member['mobileverify'])) {
                $mustbind = 1;
            }


            if ($is_openmerch == 1) {
                $merchs = $merch_plugin->getMerchs($merch_array);
            }

            $goods_list = array();
            $i = 0;

            if ($ismerch) {
                $getListUser = $merch_plugin->getListUser($goods);
                $merch_user = $getListUser['merch_user'];

                foreach ($getListUser['merch'] as $k => $v) {
                    if (empty($merch_user[$k]['merchname'])) {
                        $goods_list[$i]['shopname'] = $_W['shopset']['shop']['name'];
                    } else {
                        $goods_list[$i]['shopname'] = $merch_user[$k]['merchname'];
                    }

                    $goods_list[$i]['goods'] = $v;
                    ++$i;
                }
            } else {
                if ($merchid == 0) {
                    $goods_list[$i]['shopname'] = $_W['shopset']['shop']['name'];
                } else {
                    $merch_data = \common\models\MerchUser::getOneByMerchId($merchid);
                    $goods_list[$i]['shopname'] = $merch_data['merchname'];
                }

                $goods_list[$i]['goods'] = $goods;
            }

            $realprice = $goodsprice + $dispatch_price;

            $createInfo = [
                'id' => $id,
                'gdid' => $gdid,
                'fromcart' => $fromcart,
                'addressid' => !empty($address) && !$isverify && !$isvirtual ? $address['id'] : 0,
                'storeid' => !empty($carrier_list) && !$isverify && !$isvirtual ? $carrier_list[0]['id'] : 0,
                'couponcount' => $couponcount,
                'isvirtual' => $isvirtual,
                'isverify' => $isverify,
                'goods' => $goodsdata,
                'merchs' => $merchs,
                'orderdiyformid' => $orderdiyformid,
                'mustbind' => $mustbind,
            ];
            $buyagain = $buyagainprice;
        } else {
            $merch_array = array();
            $merchs = array();
            $package = \common\models\ShopPackage::fetchOne(['id' => $packageid, 'uniacid' => $uniacid]);
            $package = set_medias($package, array('thumb'));

            if (time() < $package['starttime']) {
                throw new ApiException(Response::ORDER_CREATE_PACKAGE_TIME_NOT_START);
            }


            if ($package['endtime'] < time()) {
                throw new ApiException(Response::ORDER_CREATE_PACKAGE_TIME_END);
            }

            $goods = array();
            $goodsprice = 0;
            $marketprice = 0;
            $goods_list = array();

            foreach ($g as $key => $value) {
                $goods[$key] = pdo_fetch('select id,title,thumb,marketprice,merchid,dispatchtype,dispatchid,dispatchprice from ' . tablename('new_shop_goods') . "\r\n" . '                            where id = ' . $value['goodsid'] . ' and uniacid = ' . $uniacid . ' ');

                if ($is_openmerch == 1) {
                    $merchid = $goods[$key]['merchid'];
                    $merch_array[$merchid]['goods'][] = $goods[$key]['id'];
                }


                $option = array();
                $packagegoods = array();

                if (0 < $value['optionid']) {
                    $option = \common\models\ShopPackageGoodsOption::fetchOne([
                        'goodsid' => $value['goodsid'],
                        'optionid' => $value['optionid'],
                        'pid' => $packageid,
                        'uniacid' => $uniacid,
                    ]);
                    $goods[$key]['packageprice'] = $option['packageprice'];
                } else {
                    $packagegoods = \common\models\ShopPackageGoods::fetchOne([
                        'pid' => $packageid,
                        'goodsid' => $value['goodsid'],
                        'uniacid' => $uniacid,
                    ]);
                    $goods[$key]['packageprice'] = $packagegoods['packageprice'];
                }

                $goods[$key]['optiontitle'] = ((!(empty($option['title'])) ? $option['title'] : ''));
                $goods[$key]['optionid'] = ((!(empty($value['optionid'])) ? $value['optionid'] : 0));
                $goods[$key]['goodsid'] = $value['goodsid'];
                $goods[$key]['total'] = 1;

                if ($option) {
                    $goods[$key]['packageprice'] = $option['packageprice'];
                } else {
                    $goods[$key]['packageprice'] = $goods[$key]['packageprice'];
                }

                if ($is_openmerch == 1) {
                    $merch_array[$merchid]['ggprice'] += $goods[$key]['packageprice'];
                }


                $goodsprice += price_format($goods[$key]['packageprice']);
                $marketprice += price_format($goods[$key]['marketprice']);
            }
            // 默认地址
            $address = [];
            $address_list = MemberAddress::find()->select('*')->where([
                'deleted' => 0,
                'openid' => McMappingFan::getAllRelatedOpenIDs($openid),
                'uniacid' => $uniacid,
            ])->orderBy([
                'isdefault'=>SORT_DESC,
                'id'=>SORT_DESC
            ])->asArray()->all();
            Yii::debug('地址信息');
            Yii::debug($address_list);
            foreach ($address_list as $address_item) {
                if ($address_item['isdefault'] == 1)
                {
                    $address = $address_item;
                    break;
                }
            }

            $total = count($goods);
            $dispatch_price = $package['freight'];
            $realprice = $goodsprice + $package['freight'];

            if (0 < $package['dispatchtype']) {
                $dispatch_array = m('order')->getOrderDispatchPrice($goods, $member, $address, false, $merch_array, 0);
                $dispatch_price = $dispatch_array['dispatch_price'] - $dispatch_array['seckill_dispatch_price'];
            } else {
                $dispatch_price = $package['freight'];
            }

            $realprice = $goodsprice + $dispatch_price;
            $packprice = $goodsprice;
            $token = md5(microtime());

            //订单创建数据
            $createInfo = [
                'id' => 0,
                'gdid' => $gdid,
                'fromcart' => 0,
                'packageid' => $packageid,
                'addressid' => $address['id'],
                'storeid' => 0,
                'couponcount' => 0,
                'isvirtual' => 0,
                'isverify' => 0,
                'isonlyverifygoods' => 0,
                'goods' => $goods,
                'merchs' => $merchs,
                'orderdiyformid' => 0,
                'token' => $token,
                'mustbind' => 0
            ];
            $goods_list = array();
            $goods_list[0]['shopname'] = $_W['shopset']['shop']['name'];
            $goods_list[0]['goods'] = $goods;
        }

        $_W['shopshare']['hideMenus'] = array(
            'menuItem:share:qq',
            'menuItem:share:QZone',
            'menuItem:share:email',
            'menuItem:copyUrl',
            'menuItem:openWithSafari',
            'menuItem:openWithQQBrowser',
            'menuItem:share:timeline',
            'menuItem:share:appMessage'
        );
        $allgoods = array();

        foreach ($goods_list as $k => $v) {
            $allgoods[$k]['shopname'] = $v['shopname'];

            foreach ($v['goods'] as $g) {
                $goodsPrice = ($g['unitprice'] < $g['marketprice'] ? (double)$g['marketprice'] : (double)$g['unitprice']);
                $g_marketprice = (double)$g['marketprice'];
                if ($is_seckill) {
                    //秒杀
                    Yii::info('--秒杀-- 商品--');
                    $goodsPrice = $goodsprice ? $goodsprice : 0;
                    $g_marketprice = $goodsPrice;
                }
                $goodsAr = [
                    'id' => $g['goodsid'],
                    'goodsid' => $g['goodsid'],
                    'title' => $g['title'],
                    'thumb' => tomedia($g['thumb']),
                    'optionid' => (int)$g['optionid'],
                    'optiontitle' => $g['optiontitle'],
                    'hasdiscount' => empty($g['isnodiscount']) && !(empty($g['dflag'])),
                    'total' => $g['total'],
                    'price' => $goodsPrice,
                    'marketprice' => $g_marketprice,
                    'merchid' => $g['merchid'],
                    'cates' => $g['cates'],
                    'unit' => $g['unit'],
                    'totalmaxbuy' => $g['totalmaxbuy'],
                    'minbuy' => $g['minbuy'],
                    'isSeckill' => $is_seckill ? 1 : 0,//秒杀
                    'discountprice' => $g['discountprice'],
                    'isdiscountprice' => $g['isdiscountprice'],
                    'discounttype' => $g['discounttype'],
                    'isdiscountunitprice' => $g['isdiscountunitprice'],
                    'discountunitprice' => $g['discountunitprice'],
                ];

                if ($is_seckill) {
                    $goodsAr['seckill_price'] = $goodsPrice + $dispatch_price;//秒杀优惠
                    $goodsAr['dispatch_price'] = $dispatch_price;//运费
                }
                $allgoods[$k]['goods'][] = $goodsAr;
            }
        }

        //报单功能
        $declaration = array();
        //报单开关
        if (!AppUser::getInstance()->checkModules('enable_declaration')) {
            $declaration['show'] = 0;
        } else {
            //报单文案
            $declaration['text'] = \common\Config::getPayTypeName(4) == '' ? '报单支付' : \common\Config::getPayTypeName(4);
            //报单功能
            if (empty($member['level'])) {
                $declaration['show'] = 0;
            } else {
                $ret = \common\models\MemberLevel::fetchOne([
                    'id' => $member['level'],
                    'uniacid' => $uniacid,
                ], 'is_declaration');

                if ($ret['is_declaration'] == '1') {
                    $declaration['show'] = 1;
                } else {
                    $declaration['show'] = 0;
                }
            }
        }

        if (!empty($carrier_list[0]['lat']) && !empty($carrier_list[0]['lng'])) {
            $map = new \common\modules\api\procedures\util\Map();
            $carrier_list[0]['distance'] = $map->get_distance([$member['lng'], $member['lat']], [$carrier_list[0]['lng'], $carrier_list[0]['lat']]);
        }

        if (\common\models\CoreSetting::getByKey('enable_merch_service_fee')) {
            //服务价格。
            $service_fee = 0;
            $service_fee_proportion = 0;
            $service_fee_set = !empty($merch_set['service_fee']) && is_array($merch_set['service_fee']) ? $merch_set['service_fee'] : false;
            if ($service_fee_set) {
                foreach ($service_fee_set as $k => $v) {
                    if (round($realprice, 2) >= $v['min'] && round($realprice, 2) <= $v['max']) {
                        $service_fee = round($realprice, 2) * ($v['fee'] / 100);
                        $realprice = round($realprice, 2) + $service_fee;
                        $service_fee_proportion = $v['fee'];
                        break;
                    }
                }
            }
        }

        $gifttitle = '';
        if ($giftid) {
            Yii::debug('拿到了giftId');
            $time = time();
            $gift = ShopGift::fetchOne([
                'uniacid' => Request::getInstance()->uniacid,
                'id' => $giftid,
                'status' => 1,
                ['<=', 'starttime', $time],
                ['>=', 'endtime', $time]
            ]);
            $gifttitle = $gift['title'];
            $giftGoods = array();
            //遍历赠品，查询赠品商品的数据
            if (!empty($gift['giftgoodsid'])) {
                $giftGoodsid = explode(',', $gift['giftgoodsid']);
                if ($giftGoodsid) {
                    $giftGoods = ShopGoods::find()
                        ->select('id,title,thumb,marketprice')
                        ->where(['id' => $giftGoodsid,
                            'uniacid' => $uniacid,
                            'status' => 2,
                            'deleted' => 0])
                        ->asArray()
                        ->all();
                }
                Yii::debug('查询的赠品数据');
                Yii::debug(array_column($giftGoods,'title'));
                foreach ($giftGoods as &$giftGood)
                {
//                        $giftGood['total'] = $total;
                    $giftGood['isgift'] = true;
                }
                Yii::debug('合并前');
                Yii::debug($goodsdata);
                $giftGoods = set_medias($giftGoods, array('thumb'));
                $goodsdata = array_merge($goodsdata, $giftGoods);
                Yii::debug('合并后是goodsdata');
                Yii::debug(array_column($goodsdata, 'title'));
            }
        } else {
            Yii::debug('没拿giftId');
            //如果没有传赠品id
            $isgift = 0;
            $gifts = array();
            $giftgoods = array();
            $time = time();
            //拿活动类型是购买指定商品的赠品组
            $gifts = ShopGift::fetchAll([
                'uniacid' => Request::getInstance()->uniacid,
                'status' => 1,
                ['<=', 'starttime', $time],
                ['>=', 'endtime', $time],
                ['<=', 'orderprice', $goodsprice],
                'activity' => 2,
            ]);

            foreach ($gifts as $key => $value) {
                $isgift = 1;
                $giftgoods = explode(',', $value['giftgoodsid']);

                foreach ($giftgoods as $k => $val) {
                    $gifts[$key]['gift'][$k] = pdo_fetch('select id,title,thumb,marketprice from ' . tablename('new_shop_goods') . ' where uniacid = ' . $uniacid . ' and status = 2 and id = ' . $val . ' ');
                }

                $gifts[$key]['gift'] = set_medias($gifts[$key]['gift'], array('thumb'));
                $gifttitle = $gifts[$key]['gift'][0]['title'];
            }

            $gifts = set_medias($gifts, array('thumb'));
        }

        $orderInfoData = [
            //收货人
            'receiver' => !empty($address_list) ?  $address_list : [],
            //门店自提地址
            'carrierInfo' => (!(empty($carrier_list)) ? $carrier_list[0] : []),
            //全部商品数据
            'goods' => $allgoods,
            //自定义表单
            'diyForm' => [
                'fields' => (!(empty($order_formInfo)) ? $fields : false),
                'f_data' => (!(empty($order_formInfo)) ? $f_data : false),
            ],
            //订单状态变量
            'vars' => [
                //是否显示地址
                'showAddress' => !($isverify) && !($isvirtual),
                //是否上门自提
                'showTab' => (0 < count($carrier_list)) && !($isverify) && !($isvirtual),
                // 积分抵扣的积分
                'deductcredit' => $deductcredit,
                // 积分抵扣的订单金额
                'deductmoney' => $deductmoney,
                // 余额抵扣的余额
                'deductcredit2' => $deductcredit2,
            ],
            //价格信息
            'priceInfo'=>[
                //实际支付价格（一定保证这个是对的）
                'payPrice' => round($realprice, 2),
                //商品小记价格
                'goodsPrice' => $goodsprice,
                //运费价格
                'dispatch_price' => $dispatch_price,
            ],
        ];

        $result = array(
            'member' => array('realname' => $member['realname'], 'mobile' => $member['carrier_mobile']),
            'showTab' => (0 < count($carrier_list)) && !($isverify) && !($isvirtual),
            'showAddress' => !($isverify) && !($isvirtual),
            'isverify' => $isverify,
            'isvirtual' => $isvirtual,
            'carrierInfo' => (!(empty($carrier_list)) ? $carrier_list[0] : false),
            'address' => $address,
            'canSelectAddress' => $canSelectAddress, //队员使用队长地址
            'goods' => $allgoods,
            'merchid' => $merch_id,
            'packageid' => $packageid,
            'fullbackgoods' => $fullbackgoods,
            'giftid' => $giftid,
            'gift' => $gift,
            'gifts' => $gifts,
            'gifttitle' => $gifttitle,
            'changenum' => $changenum,
            'hasinvoice' => (bool)$hasinvoice,
            'invoicename' => $invoicename,
            'couponcount' => (int)$couponcount,
            'deductcredit' => $deductcredit, // 积分抵扣的积分
            'deductmoney' => $deductmoney, // 积分抵扣的订单金额
            'deductcredit2' => $deductcredit2, // 余额抵扣的余额
            'stores' => $stores,
            'fields' => (!(empty($order_formInfo)) ? $fields : false),
            'f_data' => (!(empty($order_formInfo)) ? $f_data : false),
            'dispatch_price' => $dispatch_price,
            'goodsprice' => $goodsprice,
            'taskdiscountprice' => $taskdiscountprice,
            'discountprice' => $discountprice,
            'isdiscountprice' => $isdiscountprice,
            'showenough' => (empty($saleset['showenough']) ? false : true),
            'enoughmoney' => $saleset['enoughmoney'],
            'enoughdeduct' => $saleset['enoughdeduct'],
            'merch_showenough' => (empty($merch_saleset['merch_showenough']) ? false : true),
            'merch_enoughmoney' => (double)$merch_saleset['merch_enoughmoney'],
            'merch_enoughdeduct' => (double)$merch_saleset['merch_enoughdeduct'],
            'merchs' => (array)$merchs,
            'realprice' => round($realprice, 2),
            'service_fee' => round($service_fee, 2),
            'service_fee_proportion' => $service_fee_proportion . '%',
            'total' => $total,
            'buyagain' => round($buyagain, 2),
            'fromcart' => (int)$fromcart,
            'isonlyverifygoods' => $isonlyverifygoods,
            'city_express_state' => (empty($dispatch_array['city_express_state']) ? 0 : $dispatch_array['city_express_state']),
            'declaration' => $declaration,
            'isSeckill' => $is_seckill ? 1 : 0, //是否秒杀
            'seckill_price' => $seckill_price,//秒杀优惠
            //新接口返回数据
            'orderInfo'=>$orderInfoData
        );

        return $result;
    }


    public function submit_order($declaration = null, $packageid = 0,
                                 $dispatchid = 0, $dispatchtype = 0, $carrierid = 0, $goods = null,
                                 $giftid = null, $fromcart = 0, $gdid = 0, $couponid = 0, $addressid = 0,
                                 $deduct = null, $carrier = null, $remark = '', $invoicename = '',
                                 $diydata = null, $token = '', $merch_id = 0
    )
    {
        //兼容旧接口,以后删除
        return $this->apiCreateOrder(
            $declaration, $packageid, $dispatchid, $dispatchtype, $carrierid,
            $goods, $giftid, $fromcart, $gdid, $couponid, $addressid, $deduct,
            $carrier, $remark, $invoicename, $diydata, $token, $merch_id
        );
    }

    /**
     *  提交订单
     */
    public function apiCreateOrder($declaration = null, $packageid = 0,
                                   $dispatchid = 0, $dispatchtype = 0, $carrierid = 0, $goods = null,
                                   $giftid = null, $fromcart = 0, $gdid = 0, $couponid = 0, $addressid = 0,
                                   $deduct = false, $deduct2 = false, $carrier = null, $remark = '', $invoicename = '',
                                   $diydata = null, $token = '', $merch_id = 0, $bargain_id = 0
    )
    {
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }


        $openid = AppUser::getInstance()->openid;
        $uniacid = intval(Request::getInstance()->uniacid);

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }
        //会员
        $member = \common\models\ShopMember::getInfo($openid);
        //是黑名单
        if ($member['isblack'] == 1) {
            throw new ApiException(Response::USER_IS_BLACK);
        }

        //如果是报单
        if ($declaration == 4) {
            if (!Appuser::getInstance()->checkModules('enable_declaration')) {
                throw new ApiException(Response::DECLARATION_CLOSE_ERROR);
            } else {
                //是否有权限报单支付
                $levels = \common\models\MemberLevel::fetchOne(array('id' => $member['level'], 'uniacid' => $uniacid));
                if ($levels['is_declaration'] == 0) {
                    throw new ApiException(Response::DECLARATION_NO_PERMISSION);
                }
            }
        }

        // 验证是否必须绑定手机
//        if(!empty($_W['shopset']['wap']['open']) && !empty($_W['shopset']['wap']['mustbind']) && empty($member['mobileverify'])){
//            show_json(0, array('message'=>"请先绑定手机", 'url'=>mobileUrl('member/bind', null, true)));
//        }

        //允许参加优惠
        $allow_sale = true;

        if ($bargain_id) {
            $allow_sale = false;
        }

        $package = array();         //套餐详情
        $packgoods = array();       //套餐商品详情
        $packageprice = 0;
        if (!empty($packageid) && $packageid > 0) {
            Yii::debug('套餐id'.$packageid);
            // 套餐详情
            $package = \common\models\ShopPackage::fetchOne([
                'id' => $packageid,
                'deleted' => 0,
                'status' => 1,
                'uniacid' => $uniacid,
            ]);

            if (empty($package)) {
                throw new ApiException(Response::ORDER_CREATE_NO_PACKAGE);
            }
            if ($package['starttime'] > time()) {
                throw new ApiException(Response::ORDER_CREATE_PACKAGE_TIME_NOT_START);
            }
            if ($package['endtime'] < time()) {
                throw new ApiException(Response::ORDER_CREATE_PACKAGE_TIME_END);
            }

            //套餐商品
            $packgoods = \common\models\ShopPackageGoods::find()
                ->where([
                    'pid' => $packageid,
                    'uniacid' => $uniacid,
                ])
                ->orderBy(['id' => SORT_DESC])
                ->asArray()
                ->all();
            if (empty($packgoods)) {
                throw new ApiException(Response::ORDER_CREATE_NO_PACKAGE);
            }
        }

        $diyform_plugin = p('diyform');
        $order_formInfo = false;
        $diyform_set = false;
        $orderdiyformid = 0;
        $fields = array();
        $f_data = array();

        if ($diyform_plugin) {
            $diyform_set = $_W['shopset']['diyform'];

            if (!(empty($diyform_set['order_diyform_open']))) {
                $orderdiyformid = intval($diyform_set['order_diyform']);

                if (!(empty($orderdiyformid))) {
                    $order_formInfo = $diyform_plugin->getDiyformInfo($orderdiyformid);
                    $fields = $order_formInfo['fields'];
                    $f_data = $diyform_plugin->getLastOrderData($orderdiyformid, $member);
                }
            }
        }

        $appDatas = array();

        if ($diyform_plugin) {
            $this_member = null;
            if (!AppUser::getInstance()->isGuest) {
                $this_member = AppUser::getInstance()->identity->toArray();
            }
            $appDatas = $diyform_plugin->wxApp($fields, $f_data, $this_member);
        }

        //多商户
        $merch_plugin = p('merch');
        $merch_set = m('common')->getPluginset('merch');
        if ($merch_plugin && $merch_set['is_openmerch']) {
            $is_openmerch = 1;
        } else {
            $is_openmerch = 0;
        }

        $merch_array = array();

        $ismerch = 0;
        $discountprice_array = array();

        //会员等级
        $level = \common\models\MemberLevel::getByOpenId($openid);


        if (is_string($goods)) {
            $goodsstring = htmlspecialchars_decode(str_replace('\\', '', $goods));
            $goods = @json_decode($goodsstring, true);
        }

        $goods_tmp = array();

        foreach ($goods as $val) {
            $goods_tmp[] = $val;
        }
        $goods = $goods_tmp;
        $goods[0]['bargain_id'] = Yii::$app->session->get('bargain_id'); // 砍价订单的价格传递
        Yii::$app->session->remove('bargain_id');

        if (!(empty($goods[0]['bargain_id']))) {
            $allow_sale = false;
        }


        if (empty($goods) || !(is_array($goods))) {
            throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
        }

        //所有商品
        $allgoods = array();
        $tgoods = array();
        $totalprice = 0; //总价
        $goodsprice = 0; //商品总价
        $grprice = 0; //商品实际总价
        $weight = 0; //总重量
        $taskdiscountprice = 0; //任务活动优惠
        $discountprice = 0; //折扣的钱
        $isdiscountprice = 0; //促销优惠的钱
        $merchisdiscountprice = 0; //多商户促销优惠的钱
        $cash = 1; //是否支持货到付款


        $deductprice = 0; //抵扣的钱

        $deductprice2 = 0; // 余额最多可抵扣
        $virtualsales = 0; //虚拟卡密的虚拟销量

        $dispatch_price = 0;

        //是否支持重购优惠
        $buyagain_sale = true;

        $buyagainprice = 0;

        $seckill_price = 0;//秒杀的商品价格
        $seckill_payprice = 0; //秒杀商品的总价格，此部分钱不参加活动
        $seckill_dispatchprice = 0; //秒杀商品的运费，不参与余额抵扣抵扣

        $sale_plugin = com('sale'); //营销插件
        $saleset = false;
        if ($sale_plugin && $allow_sale) {
            $saleset = $_W['shopset']['sale'];

            if ($packageid) {
                $saleset = false;
            } else {
                $saleset['enoughs'] = $sale_plugin->getEnoughs();
            }
        }
        $isvirtual = false;
        $isverify = false;
        $verifytype = 0; //核销类型
        $isvirtualsend = false;

        $merchCouponId = 0; //使用的优惠券merchid


        if ($giftid) {
            $gift = array();
            $time = time();
            $giftdata = ShopGift::fetchOne([
                'id' => $giftid,
                'status' => 1,
                ['<=', 'starttime', $time],
                ['>=', 'endtime', $time],
                'uniacid' => $uniacid,
            ]);
            if ($giftdata['giftgoodsid']) {
                $giftgoodsid = explode(',', $giftdata['giftgoodsid']);

                foreach ($giftgoodsid as $key => $value) {
                    $gift[$key] = pdo_fetch('select id as goodsid,title,thumb from ' . tablename('new_shop_goods') . ' where uniacid = ' . $uniacid . ' and status = 2 and id = ' . $value . ' and deleted = 0 ');
                }

                $goods = array_merge($goods, $gift);
            }

        }


        foreach ($goods as $g) {
            if (empty($g)) {
                continue;
            }


            $goodsid = intval($g['goodsid']);
            $optionid = intval($g['optionid']);
            $goodstotal = intval($g['total']);

            if ($goodstotal < 1) {
                $goodstotal = 1;
            }

            if (empty($goodsid)) {
                throw new ApiException(Response::ERROR_PARAM_ERROR);
            }

            $sql = 'SELECT id as goodsid,title,type,hascommission, weight,total,is_send_free, isnodiscount, thumb,marketprice,cash,isverify,verifytype,'
                . ' goodssn,productsn,sales,istime,timestart,timeend,'
                . ' usermaxbuy,minbuy,maxbuy,unit,buylevels,buygroups,deleted,'
                . ' status,deduct,manydeduct,`virtual`,discounts,deduct2,ednum,edmoney,edareas,diyformtype,diyformid,diymode,'
                . ' dispatchtype,dispatchid,dispatchprice,merchid,merchsale,cates,'
                . ' isdiscount,isdiscount_time,isdiscount_discounts, virtualsend,'
                . ' buyagain,buyagain_islong,buyagain_condition, buyagain_sale,verify_goods_days,verify_goods_limit_type,verify_goods_limit_date'
                . ' FROM ' . tablename('new_shop_goods') . ' where id=:id and uniacid=:uniacid  limit 1';
            $data = pdo_fetch($sql, array(':uniacid' => $uniacid, ':id' => $goodsid));
            $data['seckillinfo'] = p('seckill')->getSeckill($goodsid, $optionid, true, $openid);
            if ($data['type'] == 5) {
                if ($data['verify_goods_limit_type'] == 1) {
                    if ($data['verify_goods_limit_date'] <= time()) {
                        throw new ApiException(Response::GOODS_NOT_FOUND, '"' . $data['title'] . '"商品使用时间已失效,无法购买 !');
                    }

                    if (($data['verify_goods_limit_date'] - (3600 * 3)) <= time()) {
                        throw new ApiException(Response::GOODS_NOT_FOUND, '"' . $data['title'] . '"商品的使用时间即将失效,无法购买 !');
                    } else if ($data['verify_goods_limit_type'] == 0) {
                        if (($data['verify_goods_days'] * 3600 * 24) <= time()) {
                            throw new ApiException(Response::GOODS_NOT_FOUND, '"' . $data['title'] . '"商品使用时间已失效,无法购买 !');
                        }


                        if ((($data['verify_goods_days'] * 3600 * 24) - (3600 * 3)) <= time()) {
                            throw new ApiException(Response::GOODS_NOT_FOUND, '"' . $data['title'] . '"商品的使用时间即将失效,无法购买 !');
                        }

                    }

                }

            } else {
                $isonlyverifygoods = false;
            }

            if (empty($data['status']) || !(empty($data['deleted']))) {
                throw new ApiException(Response::GOODS_NOT_FOUND, $data['title'] . ' 已下架!');
            }


            $rank = intval(Yii::$app->session->get($goodsid . '_rank'));
            $join_id = intval(Yii::$app->session->get($goodsid . '_join_id'));
            $task_goods_data = \common\modules\task\Module::getGoodsInfo($openid, $goodsid, $rank, $join_id, $optionid);

            if ($data['seckillinfo'] && $data['seckillinfo']['status'] == 0) {
                //秒杀不管任务
                $data['is_task_goods'] = 0;
                $tgoods = false;
            } else {
                if (empty($task_goods_data['is_task_goods'])) {
                    $data['is_task_goods'] = 0;
                } else {
                    $allow_sale = false;
                    $tgoods['title'] = $data['title'];
                    $tgoods['openid'] = $openid;
                    $tgoods['goodsid'] = $goodsid;
                    $tgoods['optionid'] = $optionid;
                    $tgoods['total'] = $goodstotal;
                    $data['is_task_goods'] = $task_goods_data['is_task_goods'];
                    $data['is_task_goods_option'] = $task_goods_data['is_task_goods_option'];
                    $data['task_goods'] = $task_goods_data['task_goods'];
                }
            }


            $merchid = $data['merchid'];
            $merch_array[$merchid]['goods'][] = $data['goodsid'];

            if (0 < $merchid) {
                $ismerch = 1;
            }


            $virtualid = $data['virtual'];
            $data['stock'] = $data['total'];
            $data['total'] = $goodstotal;

            if ($data['cash'] != 2) {
                $cash = 0;
            }

            //套餐配送方式
            if (!(empty($packageid))) {
                $cash = $package['cash'];
            }

            $unit = empty($data['unit']) ? '件' : $data['unit'];
            //一次购买量，总购买量，限时购，会员级别，会员组判断
            //最低购买
            //秒杀限购
            if ($data['seckillinfo'] && $data['seckillinfo']['status'] == 0) {
                Yii::info('--检查秒杀限购--');
                $check_buy = \common\modules\seckill\Module::checkBuy($data['seckillinfo'], $data['title'], $data['unit']);
                if (is_error($check_buy)) {
                    throw new ApiException(Response::SECKILL_BUY_LIMIT, $check_buy['message']);
                }
            } else {
                // 检查商品是否是免费领取商品, 用户是否能领取该商品
                $check_result = ShopGoods::checkFreeGoodsAuth($g['goodsid'], $member['openid'], $goodstotal);
                $isfreeGoods = false;
                if (is_error($check_result)) {
                    throw new ApiException(Response::ORDER_CREATE_FALSE, $check_result['message']);
//                throw new \common\modules\api\procedures\ApiException(\common\AppError::$OrderCreateFreeGoodsError, $check_result['message']);
                } else {
                    $isfreeGoods = $check_result;
                }

                if (!$isfreeGoods) {
                    if ($data['minbuy'] > 0) {
                        if ($goodstotal < $data['minbuy']) {
                            throw new ApiException(Response::ORDER_CREATE_MIN_BUY_LIMIT, $data['title'] . ' ' . $data['minbuy'] . $unit . '起售!');
                        }

                    }

                    //一次购买
                    if (0 < $data['maxbuy']) {
                        if ($data['maxbuy'] < $goodstotal) {
                            throw new ApiException(Response::ORDER_CREATE_ONE_BUY_LIMIT, $data['title'] . '一次限购 ' . $data['maxbuy'] . $unit . '!');
                        }

                    }

                    //总购买量
                    if (0 < $data['usermaxbuy']) {
                        $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . ShopOrderGoods::tableName() . ' og ' . ' left join ' . tablename('new_shop_order') . ' o on og.orderid=o.id ' . ' where og.goodsid=:goodsid and  o.status>=0 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $data['goodsid'], ':uniacid' => $uniacid, ':openid' => $openid));

                        if ($data['usermaxbuy'] <= $order_goodscount) {
                            throw new ApiException(Response::ORDER_CREATE_MAX_BUY_LIMIT, $data['title'] . ' 最多限购 ' . $data['usermaxbuy'] . $unit . '，或请前往会员中心确认是否有待付款订单！');
                        }

                    }

                    $levelid = intval($member['level']);
                    $groupid = intval($member['groupid']);
//					 if (empty($member['groupid'])) {
//						 $groupid = array();
//					 }
//					 else {
//						 $groupid = explode(',', $member['groupid']);
//					 }
                    //判断会员权限
                    if ($data['buylevels'] != '') {
                        $buylevels = explode(',', $data['buylevels']);

                        if (!(in_array($levelid, $buylevels))) {
                            throw new ApiException(Response::ORDER_CREATE_MEMBER_LEVEL_LIMIT, '抱歉，您当前会员等级暂无权限购买此商品!');
                        }

                    }

                    //会员组权限
                    if ($data['buygroups'] != '') {
                        $buygroups = explode(',', $data['buygroups']);

                        if (!(in_array($groupid, $buygroups))) {
                            throw new ApiException(Response::ORDER_CREATE_MEMBER_GROUP_LIMIT, '抱歉，您当前会员等级暂无权限购买此商品!');
                        }

                    }
                }


                if (!(empty($data['is_task_goods']))) {
                    if ($data['task_goods']['total'] < $goodstotal) {
                        throw new ApiException(Response::ORDER_CREATE_MAX_BUY_LIMIT, $data['title'] . ', 任务活动优惠限购 ' . $data['task_goods']['total'] . $unit . '!');
                    }

                }

                //判断限时购
                if ($data['istime'] == 1) {
                    if (time() < $data['timestart']) {
                        throw new ApiException(Response::ORDER_CREATE_TIME_NOT_START, $data['title'] . '<br/> 限购时间未到!');
                    }


                    if ($data['timeend'] < time()) {
                        throw new ApiException(Response::ORDER_CREATE_TIME_END, $data['title'] . '<br/> 限购时间已过!');
                    }

                }
            }


            if (!empty($optionid)) {
                $option = \common\models\ShopGoodsOption::fetchOne([
                    'id' => $optionid,
                    'goodsid' => $goodsid,
                    'uniacid' => $uniacid,
                ]);

                if (!empty($option)) {
                    if ($option['stock'] != -1) {
                        if (empty($option['stock'])) {
                            throw new ApiException(Response::ORDER_CREATE_STOCK_ERROR, $data['title'] . '<br/>' . $option['title'] . ' 库存不足!');
                        }

                    }


                    $data['optionid'] = $optionid;
                    $data['optiontitle'] = $option['title'];
                    $data['marketprice'] = $option['marketprice'];

                    //套餐规格
                    $packageoption = array();

                    if ($packageid) {
                        $packageoption = \common\models\ShopPackageGoodsOption::fetchOne([
                            'goodsid' => $goodsid,
                            'optionid' => $optionid,
                            'pid' => $packageid,
                            'uniacid' => $uniacid,
                        ]);
                        $data['marketprice'] = $packageoption['packageprice'];
                        $packageprice += $packageoption['packageprice'];
                    }

                    $virtualid = $option['virtual'];

                    if (!(empty($option['goodssn']))) {
                        $data['goodssn'] = $option['goodssn'];
                    }


                    if (!(empty($option['productsn']))) {
                        $data['productsn'] = $option['productsn'];
                    }


                    if (!(empty($option['weight']))) {
                        $data['weight'] = $option['weight'];
                    }

                }

            }

            //套餐无规格
            if ($packageid) {
                $pg = \common\models\ShopPackageGoods::fetchOne([
                    'pid' => $packageid,
                    'goodsid' => $goodsid,
                    'uniacid' => $uniacid,
                ]);
                $data['marketprice'] = $pg['packageprice'];
                $packageprice += $pg['packageprice'];
            } else if ($data['stock'] != -1) {
                if (empty($data['stock'])) {
                    throw new ApiException(Response::ORDER_CREATE_STOCK_ERROR, $data['title'] . '<br/> 库存不足!');
                }

            }


            $data['diyformdataid'] = 0;
            $data['diyformdata'] = iserializer(array());
            $data['diyformfields'] = iserializer(array());

            if ($fromcart == 1) {
                if ($diyform_plugin) {
                    $cartdata = pdo_fetch('select id,diyformdataid,diyformfields,diyformdata from ' . tablename('new_shop_member_cart') . ' ' . ' where goodsid=:goodsid and optionid=:optionid and openid=:openid and deleted=0 order by id desc limit 1', array(':goodsid' => $data['goodsid'], ':optionid' => intval($data['optionid']), ':openid' => $openid));

                    if (!(empty($cartdata))) {
                        $data['diyformdataid'] = $cartdata['diyformdataid'];
                        $data['diyformdata'] = $cartdata['diyformdata'];
                        $data['diyformfields'] = $cartdata['diyformfields'];
                    }

                }

            } else if (!(empty($data['diyformtype'])) && $diyform_plugin) {
                $temp_data = $diyform_plugin->getOneDiyformTemp($gdid, 0);
                $data['diyformfields'] = $temp_data['diyformfields'];
                $data['diyformdata'] = $temp_data['diyformdata'];

                if ($data['diyformtype'] == 2) {
                    $data['diyformid'] = 0;
                } else {
                    $data['diyformid'] = $data['diyformid'];
                }
            }


            if ($data['status'] == 2) {
                $data['marketprice'] = 0;
            }

            if ($data['seckillinfo'] && ($data['seckillinfo']['status'] == 0)) {
                //秒杀价格
                $data['ggprice'] = $gprice = $data['seckillinfo']['price'] * $goodstotal;
                $seckill_payprice += $gprice;
                $seckill_price += ($data['marketprice'] * $goodstotal) - $gprice;
                $goodsprice += $data['marketprice'] * $goodstotal;
                $data['taskdiscountprice'] = 0;
                $data['lotterydiscountprice'] = 0;
                $data['discountprice'] = 0;
                $data['discountprice'] = 0;
                $data['discounttype'] = 0;
                $data['isdiscountunitprice'] = 0;
                $data['discountunitprice'] = 0;
                $data['price0'] = 0;
                $data['price1'] = 0;
                $data['price2'] = 0;
                $data['buyagainprice'] = 0;
                // todo 秒杀商品规格
            } else {
                $gprice = $data['marketprice'] * $goodstotal;
                $goodsprice += $gprice;
                $prices = m('order')->getGoodsDiscountPrice($data, $level);

                Yii::debug('计算折扣价' . var_export($prices, true), __METHOD__);
                if (empty($packageid)) {
                    $data['ggprice'] = $prices['price'];
                } else {
                    $data['ggprice'] = $data['marketprice'];
                    $prices = array();
                }

                $data['taskdiscountprice'] = $prices['taskdiscountprice'];
                $data['discountprice'] = $prices['discountprice'];
                $data['discountprice'] = $prices['discountprice'];
                $data['discounttype'] = $prices['discounttype'];
                $data['isdiscountunitprice'] = $prices['isdiscountunitprice'];
                $data['discountunitprice'] = $prices['discountunitprice'];
                $data['price0'] = $prices['price0'];
                $data['price1'] = $prices['price1'];
                $data['price2'] = $prices['price2'];
                $data['buyagainprice'] = $prices['buyagainprice'];
                $buyagainprice += $prices['buyagainprice'];
                $taskdiscountprice += $prices['taskdiscountprice'];

                if ($prices['discounttype'] == 1) {
                    $isdiscountprice += $prices['isdiscountprice'];
                    $discountprice += $prices['discountprice'];

                    if (!(empty($data['merchsale']))) {
                        $merchisdiscountprice += $prices['isdiscountprice'];
                        $discountprice_array[$merchid]['merchisdiscountprice'] += $prices['isdiscountprice'];
                    }


                    $discountprice_array[$merchid]['isdiscountprice'] += $prices['isdiscountprice'];
                } else if ($prices['discounttype'] == 2) {
                    $discountprice += $prices['discountprice'];
                    $discountprice_array[$merchid]['discountprice'] += $prices['discountprice'];
                }

                $discountprice_array[$merchid]['ggprice'] += $prices['ggprice'];
            }

            $merch_array[$merchid]['ggprice'] += $data['ggprice'];
            $totalprice += $data['ggprice'];

            if ($data['isverify'] == 2) {
                $isverify = true;
                $verifytype = $data['verifytype'];
            }


            if (!(empty($data['virtual'])) || ($data['type'] == 2)) {
                $isvirtual = true;

                if ($data['virtualsend']) {
                    $isvirtualsend = true;
                }

            }

            if ($data['seckillinfo'] && ($data['seckillinfo']['status'] == 0)) {
            } else {
                if (floatval($data['buyagain']) > 0 && empty($data['buyagain_sale'])) {
                    //第一次后买东西享受优惠
                    if (m('goods')->canBuyAgain($data)) {
                        $data['deduct'] = 0;
                        $saleset = false;
                    }
                }
            }


            //积分抵扣
            if ($data['manydeduct']) {
                $deductprice += $data['deduct'] * $data['total'];
            } else {
                $deductprice += $data['deduct'];
            }

            $virtualsales += $data['sales'];

            // 余额抵扣限额
            if ($data['deduct2'] == 0) {
                //全额抵扣
                $deductprice2 += $data['ggprice'];
            } else if ($data['deduct2'] > 0) {
                //最多抵扣
                if ($data['deduct2'] > $data['ggprice']) {
                    $deductprice2 += $data['ggprice'];
                } else {
                    $deductprice2 += $data['deduct2'];
                }
            }
            $allgoods[] = $data;
        }

        $grprice = $totalprice;

        if ((1 < count($goods)) && !(empty($tgoods))) {
            throw new ApiException(Response::ORDER_CREATE_TASK_GOODSCART, $tgoods['title'] . '不能放入购物车下单,请单独购买!');
        }


        if (empty($allgoods)) {
            throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
        }


        if ($is_openmerch == 1) {
            //读取多商户营销设置
            foreach ($merch_array as $key => $value) {
                if (0 < $key) {
                    if (!($packageid)) {
                        $merch_array[$key]['set'] = $merch_plugin->getSet('sale', $key);
                        $merch_array[$key]['enoughs'] = $merch_plugin->getEnoughs($merch_array[$key]['set']);
                    }

                }

            }

            if ($allow_sale) {
                //多商户满额减
                $merch_enough = \common\modules\order\Module::getMerchEnough($merch_array);
                $merch_array = $merch_enough['merch_array'];
                $merch_enough_total = $merch_enough['merch_enough_total'];
                $merch_saleset = $merch_enough['merch_saleset'];

                if (0 < $merch_enough_total) {
                    $totalprice -= $merch_enough_total;
                }

            }

        }

        //满额减
        $deductenough = 0;

        if ($saleset) {
            if ($allow_sale) {
                foreach ($saleset['enoughs'] as $e) {
                    if ((floatval($e['enough']) <= $totalprice) && (0 < floatval($e['money']))) {
                        $deductenough = floatval($e['money']);

                        if ($totalprice < $deductenough) {
                            $deductenough = $totalprice;
                        }

                        $totalprice -= $deductenough;
                        break;
                    }

                }
            }

        }


        $goodsdata_coupon = array();
        $goodsdata_coupon_temp = array();
        foreach ($allgoods as $g) {
            if ($g['seckillinfo'] && ($g['seckillinfo']['status'] == 0)) {
                $goodsdata_coupon_temp[] = $g;
            } else if (floatval($g['buyagain']) > 0) {
                //第一次后买东西享受优惠
                if (!m('goods')->canBuyAgain($g) || !empty($g['buyagain_sale'])) {
                    $goodsdata_coupon[] = $g;
                } else {
                    $goodsdata_coupon_temp[] = $g;
                }
            } else {
                $goodsdata_coupon[] = $g;
            }
        }

        $return_array = \common\modules\coupon\Module::calculateCoupon(2, $couponid, null, null, null, $goodsdata_coupon, $totalprice, $discountprice, $isdiscountprice, 1, $discountprice_array, $merchisdiscountprice);
        $couponprice = 0;
        $coupongoodprice = 0;

        if (!empty($return_array)) {
            $isdiscountprice = $return_array['isdiscountprice'];
            $discountprice = $return_array['discountprice'];
            $couponprice = $return_array['deductprice'];
            $totalprice = $return_array['totalprice'];
            $discountprice_array = $return_array['discountprice_array'];
            $merchisdiscountprice = $return_array['merchisdiscountprice'];
            $coupongoodprice = $return_array['coupongoodprice'];
            $merchCouponId = $return_array['coupon_merch_id'];
            $allgoods = $return_array['goodsarr'];
            $allgoods = array_merge($allgoods, $goodsdata_coupon_temp);
        }

        $address = false;

        if (!(empty($addressid)) && ($dispatchtype == 0) && !($isonlyverifygoods) && $data['type'] != \common\models\ShopGoods::TYPE_VIRTUAL) {
            // 因为传入的地址，不一定是本人地址，所以去除下面的检测
            $address = \common\models\MemberAddress::fetchOne([
                'uniacid' => $uniacid,
//                'openid' => McMappingFan::getAllRelatedOpenIDs($openid),
                'id' => $addressid,
            ]);

            if (empty($address)) {
                throw new ApiException(Response::ADDRESS_NOT_FOUND);
            }

        }

        //$isvirtual 实体物品计算运费
        //$isverify  非核销计算运费
        //$dispatchtype 选择了快递(非自提)计算运费
        if (!($isvirtual) && !($isverify) && ($dispatchtype == 0) && !($isonlyverifygoods)) {
            $dispatch_array = m('order')->getOrderDispatchPrice($allgoods, $member, $address, $saleset, $merch_array, 2);
            $dispatch_price = $dispatch_array['dispatch_price'];
            Yii::debug('订单运费' . $dispatch_price, __METHOD__);
            $nodispatch_array = $dispatch_array['nodispatch_array'];

            if (!(empty($nodispatch_array['isnodispatch']))) {
                throw new ApiException(Response::ORDER_CREATE_NO_DISPATCH, $nodispatch_array['nodispatch']);
            }
        }
        if ($isonlyverifygoods) {
            $addressid = 0;
        }


        //运费
        $totalprice += $dispatch_price;
        Yii::debug('金额：' . $totalprice . ' 运费' . $dispatch_price, __METHOD__);
        //余额最多抵扣+运费
        if ($saleset && empty($saleset['dispatchnodeduct'])) {
            $deductprice2 += $dispatch_price;
        }


        // 常规订单流
        if (!$bargain_id) {
            Yii::info('常规订单流');
            //积分抵扣
            $deductcredit = 0; //抵扣需要扣除的积分
            $deductmoney = 0; //抵扣的钱
            $deductcredit2 = 0; //可抵扣的余额

            if ($sale_plugin) {
                //积分抵扣
                if (!empty($deduct)) {
                    Yii::info('into 积分抵扣', __METHOD__);
                    //会员积分
                    $credit = floatval($member['credit1']);
                    Yii::debug("用户积分{$credit}", __METHOD__);
                    //积分抵扣
                    if (!empty($saleset['creditdeduct'])) {
                        $pcredit = intval($saleset['credit']); //积分比例
                        $pmoney = round(floatval($saleset['money']), 2); //抵扣比例

                        if ((0 < $pcredit) && (0 < $pmoney)) {
                            if (($credit % $pcredit) == 0) {
                                $deductmoney = round(intval($credit / $pcredit) * $pmoney, 2);
                            } else {
                                $deductmoney = round((intval($credit / $pcredit) + 1) * $pmoney, 2);
                            }
                        }


                        if ($deductprice < $deductmoney) {
                            $deductmoney = $deductprice;
                        }


                        if ($totalprice < $deductmoney) {
                            $deductmoney = $totalprice;
                        }


                        $deductcredit = round(($deductmoney / $pmoney) * $pcredit, 2);
                    }

                }


                $totalprice -= $deductmoney;
            }
            //补充余额抵扣
            if (!empty($saleset['moneydeduct'])) {
                if (!empty($deduct2)) {
                    Yii::info('into 余额抵扣', __METHOD__);
                    $deductcredit2 = $member['credit2'];
                    if ($deductprice2 < $deductcredit2) {
                        $deductcredit2 = $deductprice2;
                    }
                }
                $totalprice -= $deductcredit2;

            }

        }


        //生成核销消费码
        $verifyinfo = array();
        $verifycode = '';
        $verifycodes = array();
        if ($isverify || $dispatchtype) {

            if ($isverify) {
                if ($verifytype == 0 || $verifytype == 1) {
                    //一次核销+ 按次核销（一个码 )
                    $verifycode = random(8, true);
                    while (1) {
                        $count = ShopOrder::countAll([
                            'verifycode' => $verifycode,
                            'uniacid' => Request::getInstance()->uniacid,
                        ]);
                        if ($count <= 0) {
                            break;
                        }
                        $verifycode = random(8, true);
                    }
                } else if ($verifytype == 2) {
                    //按码核销
                    $totaltimes = intval($allgoods[0]['total']);
                    if ($totaltimes <= 0) {
                        $totaltimes = 1;
                    }


                    $i = 1;

                    while ($i <= $totaltimes) {
                        $verifycode = random(8, true);

                        while (1) {
                            $count = pdo_fetchcolumn('select count(*) from ' . tablename('new_shop_order') . ' where concat(verifycodes,\'|\' + verifycode +\'|\' ) like :verifycodes and uniacid=:uniacid limit 1', array(':verifycodes' => '%' . $verifycode . '%', ':uniacid' => $_W['uniacid']));

                            if ($count <= 0) {
                                break;
                            }


                            $verifycode = random(8, true);
                        }

                        $verifycodes[] = '|' . $verifycode . '|';
                        $verifyinfo[] = array('verifycode' => $verifycode, 'verifyopenid' => '', 'verifytime' => 0, 'verifystoreid' => 0);
                        ++$i;
                    }
                }

            } else if ($dispatchtype) {
                //自提码
                $verifycode = random(8, true);

                while (1) {
                    $count = pdo_fetchcolumn('select count(*) from ' . tablename('new_shop_order') . ' where verifycode=:verifycode and uniacid=:uniacid limit 1', array(':verifycode' => $verifycode, ':uniacid' => $_W['uniacid']));

                    if ($count <= 0) {
                        break;
                    }


                    $verifycode = random(8, true);
                }
            }

        }


        if (is_string($carrier)) {
            $carrierstring = htmlspecialchars_decode(str_replace('\\', '', $carrier));
            $carrier = @json_decode($carrierstring, true);
        }


        $carriers = ((is_array($carrier) ? iserializer($carrier) : iserializer(array())));

        if ($totalprice <= 0) {
            $totalprice = 0;
        }

        if ($ismerch == 0 || ($ismerch == 1 && count($merch_array) == 1)) {
            //需要创建一个订单
            $multiple_order = 0;
        } else {
            //需要创建多个订单
            $multiple_order = 1;
        }

        //生成订单号
        if ($ismerch > 0) {
            $ordersn = \common\Helper::createNO('order', 'ordersn', 'ME');
        } else {
            $ordersn = \common\Helper::createNO('order', 'ordersn', 'SH');
        }

        //砍价订单流
        if ($bargain_id) {
            Yii::info('砍价订单流');
            $bargain_act = BargainActor::fetchOne([
                'id' => $bargain_id,
                'openid' => $openid
            ]);

            if (!$bargain_act) {
                throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
            }

            $totalprice = $bargain_act['now_price'] + $dispatch_price; //砍价价+运费
            $goodsprice = $bargain_act['now_price']; //砍价后的商品价
            $updateBargain = BargainActor::updateAll(['status' => 1], ['id' => $bargain_id, 'openid' => $openid]);
            if (!$updateBargain) {
                throw new ApiException(Response::ORDER_CREATE_FALSE);
            }
            $ordersn = substr_replace($ordersn, 'KJ', 0, 2);
        }

        //秒杀订单
        if ($goods[0]['isSeckill']) {
            $totalprice = $goods[0]['seckill_price'] - $discountprice + $dispatch_price;
            $goodsprice = $goods[0]['seckill_price'];
            Yii::debug('秒杀商品价格:'.$goodsprice.' 秒杀最终价格'.$totalprice,__METHOD__);
        }

        //套餐订单价格
        $is_package = 0;

        if (!(empty($packageid))) {
            Yii::info('--套餐订单价格--');
            $goodsprice = price_format($packageprice);

            if ($package['dispatchtype'] == 1) {
                $dispatch_array = m('order')->getOrderDispatchPrice($allgoods, $member, $address, false, $merch_array, 0);
                $dispatch_price = $dispatch_array['dispatch_price'] - $dispatch_array['seckill_dispatch_price'];
            } else {
                $dispatch_price = $package['freight'];
            }

            $totalprice = $packageprice + $dispatch_price;
            $is_package = 1;
            $discountprice = 0;
        }

        //订单数据
        $order = array();

        if (\common\models\CoreSetting::getByKey('enable_merch_service_fee')) {
            //服务价格。
            $order['service_fee'] = 0;
            $service_fee_set = !empty($merch_set['service_fee']) && is_array($merch_set['service_fee']) ? $merch_set['service_fee'] : false;
            if ($service_fee_set) {
                foreach ($service_fee_set as $k => $v) {
                    if ($totalprice >= $v['min'] && $totalprice <= $v['max']) {

                        $order['service_fee'] = round($totalprice, 2) * ($v['fee'] / 100);
                        $totalprice = $totalprice + round($totalprice, 2) * ($v['fee'] / 100);
                        break;
                    }
                }
            }

        }

        $order['ismerch'] = $ismerch;
        $order['parentid'] = 0;
        $order['uniacid'] = $uniacid;
        $order['openid'] = $openid;
        $order['ordersn'] = $ordersn;
        $order['price'] = $totalprice;
        $order['oldprice'] = $totalprice;
        $order['grprice'] = $grprice;
        $order['taskdiscountprice'] = $taskdiscountprice;
        $order['discountprice'] = $discountprice;
        $order['isdiscountprice'] = $isdiscountprice;
        $order['merchisdiscountprice'] = $merchisdiscountprice;
        $order['cash'] = $cash;
        $order['status'] = 0;
        $order['create_from_wxapp'] = 1;
        $order['remark'] = $remark;
        $order['addressid'] = empty($dispatchtype) ? $addressid : 0;
        $order['goodsprice'] = $goodsprice;
        $order['dispatchprice'] = $dispatch_price;
        $order['dispatchtype'] = $dispatchtype;
        $order['dispatchid'] = $dispatchid;
        $order['storeid'] = $carrierid;
        $order['carrier'] = $carriers;
        $order['createtime'] = time();
        $order['old_dispatch_price'] = $dispatch_price;
        $order['couponid'] = $couponid;
        $order['coupon_merch_id'] = $merchCouponId;
        $order['paytype'] = 0; //如果是上门取货，支付方式为3
        //        报单功能
        if ($declaration == 4) {
            $order['paytime'] = time();
            $order['status'] = 1;
            $order['paytype'] = 4;  //报单支付方式为4
        }
        $order['deductprice'] = $deductmoney;
        $order['deductcredit'] = $deductcredit;
        $order['deductcredit2'] = $deductcredit2;
        $order['deductenough'] = $deductenough;
        $order['merchdeductenough'] = $merch_enough_total;
        $order['couponprice'] = $couponprice;
        $order['merchshow'] = 0;
        $order['buyagainprice'] = $buyagainprice;
        $order['ispackage'] = $is_package;
        $order['packageid'] = $packageid;
        $order['coupongoodprice'] = $coupongoodprice;

        $author = p('author');

        if ($author) {
            $author_set = $author->getSet();

            if (!(empty($member['agentid'])) && !(empty($member['authorid']))) {
                $order['authorid'] = $member['authorid'];
            }


            if (!(empty($author_set['selfbuy'])) && !(empty($member['isauthor'])) && !(empty($member['authorstatus']))) {
                $order['authorid'] = $member['id'];
            }

        }


        if ($multiple_order == 0) {
            $order_merchid = current(array_keys($merch_array));
            $order['merchid'] = intval($order_merchid);
            $order['isparent'] = 0;
            $order['transid'] = '';
            $order['isverify'] = $isverify ? 1 : 0;
            $order['verifytype'] = $verifytype;
            $order['verifycode'] = $verifycode;
            $order['verifycodes'] = implode('', $verifycodes);
            $order['verifyinfo'] = iserializer($verifyinfo);
            $order['virtual'] = $virtualid;
            $order['isvirtual'] = $isvirtual ? 1 : 0;
            $order['isvirtualsend'] = $isvirtualsend ? 1 : 0;

            $order['invoicename'] = $invoicename;
            $order['city_express_state'] = ((empty($dispatch_array['city_express_state']) == true ? 0 : $dispatch_array['city_express_state']));

        } else {
            //创建多个订单的字段
            $order['isparent'] = 1;
            $order['merchid'] = 0;
        }

        if ($diyform_plugin) {

            if (is_string($diydata)) {
                $diyformdatastring = htmlspecialchars_decode(str_replace('\\', '', $diydata));
                $diydata = @json_decode($diyformdatastring, true);
            }


            if (is_array($diydata) && !(empty($order_formInfo))) {
                $diyform_data = \common\modules\diyForm\Module::getInsertData($fields, $diydata, true);
                $idata = $diyform_data['data'];
                // 小程序、APP保存时需要把fields 转换
                $order['diyformfields'] = $diyform_plugin->getInsertFields($fields);
                $order['diyformdata'] = $idata;
                $order['diyformid'] = $order_formInfo['id'];
            }
        }

        if (!empty($address)) {
            $order['address'] = iserializer($address);
        }

        //生成订单
        $orderid = ShopOrder::insertOne($order);

        if ($bargain_id && p('bargain')) {
            BargainActor::updateAll(array('order' => $orderid), array('id' => $goods[0]['bargain_id'], 'openid' => $_W['openid']));
        }
        if ($multiple_order == 0) {
            //开始创建一个订单

            //保存订单商品
            foreach ($allgoods as $goods) {
                $order_goods = array();

                if (!(empty($bargain_act)) && p('bargain')) {
                    $goods['total'] = 1;
                }


                $order_goods['merchid'] = $goods['merchid'];
                $order_goods['merchsale'] = $goods['merchsale'];
                $order_goods['uniacid'] = $uniacid;
                $order_goods['orderid'] = $orderid;
                $order_goods['goodsid'] = $goods['goodsid'];
                $order_goods['price'] = $goods['marketprice'] * $goods['total'];
                $order_goods['total'] = $goods['total'];
                $order_goods['optionid'] = $goods['optionid'];
                $order_goods['createtime'] = time();
                $order_goods['optionname'] = $goods['optiontitle'];
                $order_goods['goodssn'] = $goods['goodssn'];
                $order_goods['productsn'] = $goods['productsn'];
                $order_goods['realprice'] = $goods['ggprice'];
                $order_goods['oldprice'] = $goods['ggprice'];
                $order_goods['hascommission'] = $goods['hascommission'];

                if ($goods['discounttype'] == 1) {
                    $order_goods['isdiscountprice'] = $goods['isdiscountprice'];
                } else {
                    $order_goods['isdiscountprice'] = 0;
                }

                $order_goods['openid'] = $openid;

                if ($diyform_plugin) {
                    if ($goods['diyformtype'] == 2) {
                        //商品使用了独立自定义的表单
                        $order_goods['diyformid'] = 0;
                    } else {
                        //商品使用了表单模板
                        $order_goods['diyformid'] = $goods['diyformid'];
                    }
                    $order_goods['diyformdata'] = $goods['diyformdata'];
                    $order_goods['diyformfields'] = $goods['diyformfields'];
                }


                if (0 < floatval($goods['buyagain'])) {
                    if (!(m('goods')->canBuyAgain($goods))) {
                        $order_goods['canbuyagain'] = 1;
                    }

                }
                //补充秒杀订单记录
                if ($goods['seckillinfo'] && $goods['seckillinfo']['status'] == 0) {
                    $order_goods['seckill'] = 1;
                    $order_goods['seckill_taskid'] = $goods['seckillinfo']['taskid'];
                    $order_goods['seckill_roomid'] = $goods['seckillinfo']['roomid'];
                    $order_goods['seckill_timeid'] = $goods['seckillinfo']['timeid'];
                }
                Yii::info('订单商品的数据为' . json_encode($order_goods), __METHOD__);
                ShopOrderGoods::insertOne($order_goods);
                if ($goods['seckillinfo'] && ($goods['seckillinfo']['status'] == 0)) {
                    p('seckill')->setSeckill($goods['seckillinfo'], $goods, $openid, $orderid, 0, $order['createtime']);
                }
            }
        } else {
            //开始创建多个子订单

            //记录订单商品中的订单id

            $og_array = array();
            $ch_order_data = m('order')->getChildOrderPrice($order, $allgoods, $dispatch_array, $merch_array, $sale_plugin, $discountprice_array);

            foreach ($merch_array as $key => $value) {
                //生成子订单号
                $order['ordersn'] = \common\Helper::createNO('order', 'ordersn', 'ME');
                $merchid = $key;
                $order['merchid'] = $merchid;
                $order['parentid'] = $orderid;
                $order['isparent'] = 0;
                $order['merchshow'] = 1;
                $order['dispatchprice'] = $dispatch_array['dispatch_merch'][$merchid];
                $order['old_dispatch_price'] = $dispatch_array['dispatch_merch'][$merchid];

                if (empty($packageid)) {
                    $order['merchisdiscountprice'] = $discountprice_array[$merchid]['merchisdiscountprice'];
                    $order['isdiscountprice'] = $discountprice_array[$merchid]['isdiscountprice'];
                    $order['discountprice'] = $discountprice_array[$merchid]['discountprice'];
                }


                $order['price'] = $ch_order_data[$merchid]['price'];
                $order['grprice'] = $ch_order_data[$merchid]['grprice'];
                $order['goodsprice'] = $ch_order_data[$merchid]['goodsprice'];
                $order['deductprice'] = $ch_order_data[$merchid]['deductprice'];
                $order['deductcredit'] = $ch_order_data[$merchid]['deductcredit'];
                $order['deductcredit2'] = $ch_order_data[$merchid]['deductcredit2'];
                $order['merchdeductenough'] = $ch_order_data[$merchid]['merchdeductenough'];
                $order['deductenough'] = $ch_order_data[$merchid]['deductenough'];

                //多商户参与优惠券计算的商品价格(参与活动之后的价格)
                $order['coupongoodprice'] = $discountprice_array[$merchid]['coupongoodprice'];

                $order['couponprice'] = $discountprice_array[$merchid]['deduct'];

                if (empty($order['couponprice'])) {
                    $order['couponid'] = 0;
                    $order['coupon_merch_id'] = 0;
                } else if (0 < $merchCouponId) {
                    if ($merchid == $merchCouponId) {
                        $order['couponid'] = $couponid;
                        $order['coupon_merch_id'] = $merchCouponId;
                    } else {
                        $order['couponid'] = 0;
                        $order['coupon_merch_id'] = 0;
                    }
                }

                // 子订单id
                $ch_orderid = ShopOrder::insertOne($order);
                $merch_array[$merchid]['orderid'] = $ch_orderid;

                if (0 < $merchCouponId) {
                    if ($merchid == $merchCouponId) {
                        $couponorderid = $ch_orderid;
                    }

                }
                foreach ($value['goods'] as $k => $v) {
                    //$v 商品id
                    $og_array[$v] = $ch_orderid;
                }
            }

            //子订单保存订单商品
            foreach ($allgoods as $goods) {
                $goodsid = $goods['goodsid'];
                $order_goods = array();
                $order_goods['parentorderid'] = $orderid;
                $order_goods['merchid'] = $goods['merchid'];
                $order_goods['merchsale'] = $goods['merchsale'];
                $order_goods['orderid'] = $og_array[$goodsid];
                $order_goods['uniacid'] = $uniacid;
                $order_goods['goodsid'] = $goodsid;
                $order_goods['price'] = $goods['marketprice'] * $goods['total'];
                $order_goods['total'] = $goods['total'];
                $order_goods['optionid'] = $goods['optionid'];
                $order_goods['createtime'] = time();
                $order_goods['optionname'] = $goods['optiontitle'];
                $order_goods['goodssn'] = $goods['goodssn'];
                $order_goods['productsn'] = $goods['productsn'];
                $order_goods['realprice'] = $goods['ggprice'];
                $order_goods['oldprice'] = $goods['ggprice'];
                $order_goods['hascommission'] = $goods['hascommission'];
                $order_goods['isdiscountprice'] = $goods['isdiscountprice'];
                $order_goods['openid'] = $openid;

                if ($diyform_plugin) {
                    if ($goods['diyformtype'] == 2) {
                        //商品使用了独立自定义的表单
                        $order_goods['diyformid'] = 0;
                    } else {
                        //商品使用了表单模板
                        $order_goods['diyformid'] = $goods['diyformid'];
                    }
                    $order_goods['diyformdata'] = $goods['diyformdata'];
                    $order_goods['diyformfields'] = $goods['diyformfields'];
                }


                if (0 < floatval($goods['buyagain'])) {
                    if (!(m('goods')->canBuyAgain($goods))) {
                        $order_goods['canbuyagain'] = 1;
                    }
                }
                //报单功能
                if ($declaration == 4) {
                    $order_goods['paytype'] = $declaration;
                }
                ShopOrderGoods::insertOne($order_goods);
            }
        }

        if ($data['type'] == 3) {
            $order_v = ShopOrder::fetchOne([
                'id' => $orderid,
                'uniacid' => Request::getInstance()->uniacid,
            ]);
            com('virtual')->pay_befo($order_v);
        }

        if (0 < $deductcredit) {
            //扣除抵扣积分
            m('member')->setCredit($openid, 'credit1', -$deductcredit, array('0', $_W['shopset']['shop']['name'] . '购物积分抵扣 消费积分: ' . $deductcredit . ' 抵扣金额: ' . $deductmoney . ' 订单号: ' . $ordersn));
        }

        if (0 < $buyagainprice) {
            m('goods')->useBuyAgain($orderid);
        }

        // 余额抵扣
        if ($deductcredit2 > 0) {
            //扣除抵扣余额
            m('member')->setCredit($openid, 'credit2', -$deductcredit2, array('0', $_W['shopset']['shop']['name'] . "购物余额抵扣: {$deductcredit2} 订单号: {$ordersn}"));
        }

        //正常创建订单之后 触发以下事件
        \common\helpers\Event::emit(EVENT_SHOP_ORDER_SUBMIT_SUCCESS, [
            'carrier' => $carrier,
            'member' => $member,
            'orderID' => $orderid,
            'order' => $order,
            'multipleOrder' => $multiple_order,
            'merchArray' => $merch_array,
            'allGoods' => $allgoods,
            'taskGoods' => $tgoods,
            'couponOrderId' => $couponorderid,
            'merchCouponId' => $merchCouponId,
            'virtualId' => $virtualid,
            'exchangeTitle' => '',
            'fromCart' => $fromcart,
            'merchid' => !empty($merch_id) ? $merch_id : 0,
        ]);

        if ($declaration == 4) {
            $res = [
                'orderid' => $orderid,
                'declaration' => $declaration
            ];
        } else {
            $res = [
                'orderid' => $orderid,
            ];
        }

        return $res;
    }


    public function prepare_pay_order($orderid = 0, $token = '')
    {
        //兼容旧接口,以后删除
        return $this->apiPreparePayOrder($orderid, $token);
    }

    /**
     * @param int $orderid
     * @param string $token
     *
     * @return array
     * @throws ApiException
     * @throws \yii\db\Exception
     * @throws \Exception
     */
    public function apiPreparePayOrder($orderid = 0, $token = '', $bargainid = 0)
    {
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }
        $member = \common\models\ShopMember::getInfo($openid);

        if (empty($orderid)) {
            throw new ApiException(Response::ERROR_PARAM_ERROR);
        }


        $order = ShopOrder::fetchOne([
            'id' => $orderid,
            'uniacid' => $uniacid,
            'openid' => McMappingFan::getAllRelatedOpenIDs($openid),
        ]);


        if (empty($order)) {
            throw new ApiException(Response::ORDER_NOT_FOUND);
            Response::stop();
        }

        //砍价部分
//        if (p('bargain') && $bargainid) {
//            Yii::info('--砍价支付价格处理--');
////            Yii::$app->session->remove('bargain_id');
////            Yii::$app->session->set('bargain_id', $bargain_id);
//            $bargain_act = BargainActor::fetchOne([
//                'id' => $bargainid,
//                'openid' => AppUser::getInstance()->openid
//            ]);
//            if (empty($bargain_act)) {
//                throw new ApiException(Response::ORDER_CREATE_NO_GOODS);
//            }
//            $order['price'] = $bargain_act['now_price'];
//        }


        if ($order['status'] == -1) {
            throw new ApiException(Response::ORDER_CANNOT_PAY);
        } else if (1 <= $order['status']) {
            throw new ApiException(Response::ORDER_ALREADY_PAY);
        }


        $log = pdo_fetch('SELECT * FROM ' . tablename('core_paylog') . ' WHERE `uniacid`=:uniacid AND `module`=:module AND `tid`=:tid limit 1', array(':uniacid' => $uniacid, ':module' => 'ewei_shopv2', ':tid' => $order['ordersn']));


        if (!(empty($log)) && ($log['status'] != '0')) {
            throw new ApiException(Response::ORDER_ALREADY_PAY);
        }

        if (!(empty($log)) && ($log['status'] == '0')) {
            \common\models\CorePayLog::deleteAll(array('plid' => $log['plid']));
            $log = NULL;
        }

        if (empty($log)) {
            $log = [
                'uniacid' => $uniacid,
                'openid' => $member['uid'],
                'module' => 'ewei_shopv2',
                'tid' => $order['ordersn'],
                'fee' => $order['price'],
                'status' => 0,
            ];
            $plid = CorePayLog::insertOne($log);
        }

        $set = \common\models\ShopSysSet::getByKey(array('shop', 'pay'));
        $credit = array('success' => false);

        if (isset($set['pay']) && ($set['pay']['credit'] == 1)) {
            $credit = array('success' => true, 'current' => $member['credit2']);
        }


        $wechat = array('success' => false);

        if (!empty($set['pay']['joinpay_wxapp']) && (0 < $order['price']) && Request::getInstance()->isWxApp) {
            $tid = $order['ordersn'];

            if (!(empty($order['ordersn2']))) {
                $var = sprintf('%02d', $order['ordersn2']);
                $tid .= 'GJ' . $var;
            }

            $payinfo = [
                'openid' => $_W['openid_wa'],
                'title' => $set['shop']['name'] . '订单',
                'tid' => $tid,
                'fee' => $order['price'],
            ];
            $res = \common\modules\wxapp\Module::getJoinPayWxappPayData($payinfo, 14);

            if (!(is_error($res))) {
                $wechat = array('success' => true, 'payinfo' => $res);
                $prepay_id = $res['prepay_id'];
                ShopOrder::updateAll(array('wxapp_prepay_id' => $prepay_id), array('id' => $orderid, 'uniacid' => Request::getInstance()->uniacid));
            } else {
                $wechat['payinfo'] = $res;
            }
        }
        elseif (!empty($set['pay']['wxapp']) && (0 < $order['price']) && Request::getInstance()->isWxApp) {
            $tid = $order['ordersn'];

            if (!(empty($order['ordersn2']))) {
                $var = sprintf('%02d', $order['ordersn2']);
                $tid .= 'GJ' . $var;
            }

            $payinfo = [
                'openid' => $_W['openid_wa'],
                'title' => $set['shop']['name'] . '订单',
                'tid' => $tid,
                'fee' => $order['price'],
            ];
            $res = \common\modules\wxapp\Module::getWxappPayData($payinfo, 14);

            if (!(is_error($res))) {
                $wechat = array('success' => true, 'payinfo' => $res);

                if (!(empty($res['package'])) && strexists($res['package'], 'prepay_id=')) {
                    $prepay_id = str_replace('prepay_id=', '', $res['package']);
                    ShopOrder::updateAll(array('wxapp_prepay_id' => $prepay_id), array('id' => $orderid, 'uniacid' => Request::getInstance()->uniacid));
                }

            } else {
                $wechat['payinfo'] = $res;
            }
        }

        //货到付款
        $cash = array('success' => ($order['cash'] == 1) && isset($set['pay']) && ($set['pay']['cash'] == 1) && ($order['isverify'] == 0) && ($order['isvirtual'] == 0));
        $alipay = array('success' => false);

        if (!empty($set['pay']['nativeapp_alipay']) && (0 < $order['price']) && !Request::getInstance()->isWxApp) {
            $params = array('out_trade_no' => $log['tid'], 'total_amount' => $order['price'], 'subject' => $set['shop']['name'] . '订单', 'body' => Request::getInstance()->uniacid . ':0:NATIVEAPP');
            $sec = m('common')->getSec();
            $sec = iunserializer($sec['sec']);
            $alipay_config = $sec['nativeapp']['alipay'];

            if (!(empty($alipay_config))) {
                $res = p('app')->alipay_build($params, $alipay_config);
                $alipay = array('success' => true, 'payinfo' => $res);
            }

        }

        $payfirma = array('success' => false);
        if (!empty($set['pay']['app_payfirma'])) {
            $payfirma['success'] = true;
        }

        $orderArr = [
            'id' => $order['id'],
            'ordersn' => $order['ordersn'],
            'price' => $order['price'],
            'title' => $set['shop']['name'] . '订单'
        ];
        return array(
            'order' => $orderArr,
            'credit' => $credit,
            'wechat' => $wechat,
            'alipay' => $alipay,
            'cash' => $cash,
            'payfirma' => $payfirma
        );
    }

    public function complete_pay($orderid = 0, $type = '', $gpc_alidata = null, $deduct = 0, $token = '', $bank_id = 0, $second_pwd = '')
    {
        //兼容旧接口,以后删除
        return $this->apiCompletePay($orderid, $type, $gpc_alidata, $deduct, $token, $bank_id, $second_pwd);
    }

    /**
     * 支付订单
     * @param int $orderid
     * @param string $type
     * @param null $gpc_alidata
     * @param int $deduct
     * @param string $token
     * @param int $bank_id
     * @param string $second_pwd
     * @return array
     * @throws ApiException
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function apiCompletePay($orderid = 0, $type = '', $gpc_alidata = null, $deduct = 0, $token = '', $bank_id = 0, $second_pwd = '')
    {
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }
        if (empty($orderid)) {
            throw new ApiException(Response::ERROR_PARAM_ERROR);
        }

        $this_member = AppUser::getInstance()->identity->toArray();
        $trade_set = \common\models\ShopSysSet::getByKey('trade');

        if ($trade_set['second_pwd'] == 1) {
            $second_pwd = intval($second_pwd);
            if ($second_pwd != $this_member['second_pwd']) {
                throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR, Yii::t('shop_o2o_page_string', '支付密码错误'));
            }
        }


        if (!(in_array($type, array('wechat', 'alipay', 'credit', 'cash', 'declaration', 'payfirma')))) {
            throw new ApiException(Response::ORDER_PAY_NO_PAY_TYPE);
        }

        if (($type == 'alipay') && empty($gpc_alidata)) {
            throw new ApiException(Response::ERROR_PARAM_ERROR, '支付宝返回数据错误');
        }


        $set = \common\models\ShopSysSet::getByKey(array('shop', 'pay'));
        $set['pay']['weixin'] = ((!(empty($set['pay']['weixin_sub'])) ? 1 : $set['pay']['weixin']));
        $set['pay']['weixin_jie'] = ((!(empty($set['pay']['weixin_jie_sub'])) ? 1 : $set['pay']['weixin_jie']));
        $member = m('member')->getMember($openid);
        $order = ShopOrder::fetchOne([
            'id' => $orderid,
            'uniacid' => $uniacid,
            'openid' => McMappingFan::getAllRelatedOpenIDs($openid),
        ]);

        if (empty($order)) {
            throw new ApiException(Response::ORDER_NOT_FOUND);
        }


        if (1 <= $order['status']) {
            return $this->success($orderid);
        }


        $log = pdo_fetch('SELECT * FROM ' . tablename('core_paylog') . ' WHERE `uniacid`=:uniacid AND `module`=:module AND `tid`=:tid limit 1', array(':uniacid' => $uniacid, ':module' => 'ewei_shopv2', ':tid' => $order['ordersn']));

        if (empty($log)) {
            throw new ApiException(Response::ORDER_PAY_FAIL);
        }


        $order_goods = pdo_fetchall('select og.id,g.title, og.goodsid,og.optionid,g.total as stock,og.total as buycount,g.status,g.deleted,g.maxbuy,g.usermaxbuy,g.istime,g.timestart,g.timeend,g.buylevels,g.buygroups,g.totalcnf from  ' . ShopOrderGoods::tableName() . ' og ' . ' left join ' . tablename('new_shop_goods') . ' g on og.goodsid = g.id ' . ' where og.orderid=:orderid and og.uniacid=:uniacid ', array(':uniacid' => Request::getInstance()->uniacid, ':orderid' => $orderid));

        foreach ($order_goods as $data) {
            if (empty($data['status']) || !(empty($data['deleted']))) {
                throw new ApiException(Response::ORDER_PAY_FAIL, $data['title'] . ' 已下架!');
            }


            $unit = ((empty($data['unit']) ? '件' : $data['unit']));

            //最低购买
            if (0 < $data['minbuy']) {
                if ($data['buycount'] < $data['minbuy']) {
                    throw new ApiException(Response::ORDER_CREATE_MIN_BUY_LIMIT, $data['title'] . '<br/> ' . $data['min'] . $unit . '起售!');
                }

            }

            //一次购买
            if (0 < $data['maxbuy']) {
                if ($data['maxbuy'] < $data['buycount']) {
                    throw new ApiException(Response::ORDER_CREATE_MIN_BUY_LIMIT, $data['title'] . '<br/> 一次限购 ' . $data['maxbuy'] . $unit . '!');
                }

            }

            //总购买量
            if (0 < $data['usermaxbuy']) {
                $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . ShopOrderGoods::tableName() . ' og ' . ' left join ' . tablename('new_shop_order') . ' o on og.orderid=o.id ' . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $data['goodsid'], ':uniacid' => $uniacid, ':openid' => $openid));

                if ($data['usermaxbuy'] <= $order_goodscount) {
                    throw new ApiException(Response::ORDER_CREATE_MAX_BUY_LIMIT, $data['title'] . '<br/> 最多限购 ' . $data['usermaxbuy'] . $unit);
                }

            }

            //判断限时购
            if ($data['istime'] == 1) {
                if (time() < $data['timestart']) {
                    throw new ApiException(Response::ORDER_CREATE_TIME_NOT_START, $data['title'] . '<br/> 限购时间未到!');
                }


                if ($data['timeend'] < time()) {
                    throw new ApiException(Response::ORDER_CREATE_TIME_END, $data['title'] . '<br/> 限购时间已过!');
                }

            }

            //判断会员权限
            if ($data['buylevels'] != '') {
                $buylevels = explode(',', $data['buylevels']);

                if (!(in_array($member['level'], $buylevels))) {
                    throw new ApiException(Response::ORDER_CREATE_MEMBER_LEVEL_LIMIT, '抱歉，您当前会员等级暂无权限购买此商品!');
                }

            }

            //会员组权限
            if ($data['buygroups'] != '') {
                $buygroups = explode(',', $data['buygroups']);

                if (!(in_array($member['groupid'], $buygroups))) {
                    throw new ApiException(Response::ORDER_CREATE_MEMBER_GROUP_LIMIT, '抱歉，您当前会员等级暂无权限购买此商品!');
                }

            }


            if ($data['totalcnf'] == 1) {
                if (!empty($data['optionid'])) {
                    $option = \common\models\ShopGoodsOption::fetchOne([
                        'id' => $data['optionid'],
                        'goodsid' => $data['goodsid'],
                        'uniacid' => $uniacid,
                    ]);
                    if (!empty($option)) {
                        if ($option['stock'] != -1) {
                            if (empty($option['stock'])) {
                                throw new ApiException(Response::ORDER_CREATE_STOCK_ERROR, $data['title'] . '<br/>' . $option['title'] . ' 库存不足!');
                            }

                        }

                    }
                } else if ($data['stock'] != -1) {
                    if (empty($data['stock'])) {
                        throw new ApiException(Response::ORDER_CREATE_STOCK_ERROR, $data['title'] . '<br/>' . $option['title'] . ' 库存不足!');
                    }

                }

            }

        }
        //货到付款
        if ($type == 'cash') {
            if (empty($set['pay']['cash'])) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '未开启货到付款');
            }

            ShopOrder::setOrderPayType($order['id'], 3);
            $ret = array();
            $ret['result'] = 'success';
            $ret['type'] = 'cash';
            $ret['from'] = 'return';
            $ret['tid'] = $log['tid'];
            $ret['user'] = $order['openid'];
            $ret['fee'] = $order['price'];
            $ret['weid'] = Request::getInstance()->uniacid;
            $ret['uniacid'] = Request::getInstance()->uniacid;
            $pay_result = m('order')->payResult($ret);
            return $this->success($orderid);
        }
        $ps = array();
        $ps['tid'] = $log['tid'];
        $ps['user'] = $openid;
        $ps['fee'] = $log['fee'];
        $ps['title'] = $log['title'];

        //余额支付
        if ($type == 'credit') {
            if (empty($set['pay']['credit']) && (0 < $ps['fee'])) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '未开启余额支付');
            }


            if ($ps['fee'] < 0) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '金额错误');
            }

            $credits = $this_member['credit2'];

            if ($credits < $ps['fee']) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '余额不足,请充值');
            }


            $fee = floatval($ps['fee']);
            $shopset = \common\models\ShopSysSet::getByKey('shop');
            $result = m('member')->setCredit($openid, 'credit2', -$fee, array($_W['member']['uid'], $shopset['name'] . 'APP 消费' . $fee));

            if (is_error($result)) {
                throw new ApiException(Response::ORDER_PAY_FAIL, $result['message']);
            }


            $record = array();
            $record['status'] = '1';
            $record['type'] = 'cash';
            CorePayLog::updateAll($record, array('plid' => $log['plid']));
            $ret = array();
            $ret['result'] = 'success';
            $ret['type'] = $log['type'];
            $ret['from'] = 'return';
            $ret['tid'] = $log['tid'];
            $ret['user'] = $log['openid'];
            $ret['fee'] = $log['fee'];
            $ret['weid'] = $log['weid'];
            $ret['uniacid'] = $log['uniacid'];
            $pay_result = m('order')->payResult($ret);
            ShopOrder::setOrderPayType($order['id'], 1);

            $logno = \common\Helper::createNO('member_log', 'logno', 'XF');
            $member_log = [
                'uniacid' => $uniacid,
                'logno' => $logno,
                'title' => '余额支付',
                'openid' => $openid,
                'money' => '-' . $log['fee'],
                'type' => 2,
                'createtime' => time(),
                'status' => 1,
                'couponid' => 0,
                'rechargetype' => 'credit',
                'apppay' => 1
            ];
            \common\models\ShopMemberLog::insertOne($member_log);

            return $this->success($orderid);
        } else if ($type == 'wechat') {
            if (empty($set['pay']['wxapp']) && Request::getInstance()->isWxApp) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '未开启微信支付');
            }


            $ordersn = $order['ordersn'];

            if (!(empty($order['ordersn2']))) {
                $ordersn .= 'GJ' . sprintf('%02d', $order['ordersn2']);
            }

            if(Request::getInstance()->isWxApp)
            {
                if (!empty($set['pay']['joinpay_wxapp']))
                {
                    $payquery =  \common\modules\wxapp\Module::getJoinPayOrderQuery($ordersn,$order['price']);
                }
                elseif(!empty($set['pay']['wxapp']))
                {
                    $payquery = p('app')->isWeixinPay($ordersn, $order['price']);
                }
            }
            else
            {
                $payquery = p('app')->isWeixinPay($ordersn, $order['price']);
            }



            if (!(is_error($payquery))) {
                $record = array();
                $record['status'] = '1';
                $record['type'] = 'wechat';
                CorePayLog::updateAll($record, array('plid' => $log['plid']));
                $ret = array();
                $ret['result'] = 'success';
                $ret['type'] = 'wechat';
                $ret['from'] = 'return';
                $ret['tid'] = $log['tid'];
                $ret['user'] = $log['openid'];
                $ret['fee'] = $log['fee'];
                $ret['weid'] = $log['weid'];
                $ret['uniacid'] = $log['uniacid'];
                $ret['deduct'] = $deduct == 1;
                $pay_result = m('order')->payResult($ret);
                ShopOrder::setOrderPayType($order['id'], 21);
                ShopOrder::updateAll(array('apppay' => 2), array('id' => $order['id']));
                return $this->success($orderid);
            }


            throw new ApiException(Response::ORDER_PAY_FAIL);
        } else if ($type == 'alipay') {
            if (empty($set['pay']['nativeapp_alipay'])) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '未开启支付宝支付');
            }


            $sec = m('common')->getSec();
            $sec = iunserializer($sec['sec']);
            $public_key = $sec['nativeapp']['alipay']['public_key'];

            if (empty($public_key)) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '支付宝公钥为空');
            }


            $alidata = htmlspecialchars_decode($gpc_alidata);
            $alidata = json_decode($alidata, true);
            $newalidata = $alidata['alipay_trade_app_pay_response'];
            $newalidata['sign_type'] = $alidata['sign_type'];
            $newalidata['sign'] = $alidata['sign'];
            $alisign = m('finance')->RSAVerify($newalidata, $public_key, false, true);

            if ($alisign) {
                $record = array();
                $record['status'] = '1';
                $record['type'] = 'wechat';
                CorePayLog::updateAll($record, ['plid' => $log['plid']]);
                $ret = array();
                $ret['result'] = 'success';
                $ret['type'] = 'alipay';
                $ret['from'] = 'return';
                $ret['tid'] = $log['tid'];
                $ret['user'] = $log['openid'];
                $ret['fee'] = $log['fee'];
                $ret['weid'] = $log['weid'];
                $ret['uniacid'] = $log['uniacid'];
                $ret['deduct'] = $deduct == 1;
                $pay_result = m('order')->payResult($ret);
                ShopOrder::setOrderPayType($order['id'], 22);
                ShopOrder::updateAll(array('apppay' => 2), array('id' => $order['id']));
            }

        } else if ($type == 'payfirma') {
            if (empty($set['pay']['app_payfirma'])) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '未开启payfirma支付');
            }


            if ($ps['fee'] < 0) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '金额错误');
            }

            $this_member = AppUser::getInstance()->identity->toArray();
            $bank = \common\models\BankCard::fetchOne(['user_id' => $this_member['id'], 'id' => $bank_id, 'uniacid' => Request::getInstance()->uniacid]);

            if (empty($bank)) {
                throw new ApiException(Response::ORDER_PAY_FAIL, '找不到该银行卡');
            }

            $ams_param = [
                'amount' => floatval($ps['fee']),
                'out_trade_no' => $order['ordersn'],
                'fee_type' => 'CAD',//加拿大
                'card_number' => $bank['number'],
                'cvv2' => $bank['cvv2'],
                'card_expiry_year' => substr($bank['expiry_year'], 2) . '',
                'card_expiry_month' => str_pad($bank['expiry_month'], 2, "0", STR_PAD_LEFT) . '',
            ];

            try {
                $ams_result = p('app')->tradeCreditPay($ams_param['amount'], $ams_param['fee_type'], $ams_param['card_expiry_month'], $ams_param['card_expiry_year'], $ams_param['card_number'], $ams_param['cvv2'], $ams_param['out_trade_no']);
//                $ams_result =  AmsClient::call('tradeCreditPay',$ams_param);
            } catch (Exception $e) {
                throw new ApiException(Response::ORDER_PAY_FAIL);
            }


            if ($ams_result['status'] == 1) {
                $record = array();
                $record['status'] = '1';
                $record['type'] = 'cash';
                CorePayLog::updateAll($record, array('plid' => $log['plid']));
                $ret = array();
                $ret['result'] = 'success';
                $ret['type'] = $log['type'];
                $ret['from'] = 'return';
                $ret['tid'] = $log['tid'];
                $ret['user'] = $log['openid'];
                $ret['fee'] = $log['fee'];
                $ret['weid'] = $log['weid'];
                $ret['uniacid'] = $log['uniacid'];
                $pay_result = m('order')->payResult($ret);
                ShopOrder::setOrderPayType($order['id'], 5);

                $logno = \common\Helper::createNO('member_log', 'logno', 'XF');
                $member_log = [
                    'uniacid' => $uniacid,
                    'logno' => $logno,
                    'title' => 'payfirma支付',
                    'openid' => $openid,
                    'money' => '-' . $log['fee'],
                    'type' => 2,
                    'createtime' => time(),
                    'status' => 1,
                    'couponid' => 0,
                    'rechargetype' => 'payfirma',
                    'apppay' => 1
                ];
                \common\models\ShopMemberLog::insertOne($member_log);

                return $this->success($orderid);
            } else {
                throw new ApiException(Response::ORDER_PAY_FAIL, $ams_result['msg']);
            }


        }

    }

    /**
     * @param $orderid
     *
     * @return array
     * @throws ApiException
     * @throws \yii\db\Exception
     */
    protected function success($orderid)
    {
        global $_W;
        global $_GPC;
        $openid = AppUser::getInstance()->openid;
        $uniacid = Request::getInstance()->uniacid;
        $member = m('member')->getMember($openid);

        if (empty($orderid)) {
            throw new ApiException(Response::ERROR_PARAM_ERROR);
        }

        /** @var ShopOrder $orderModel */
        $orderModel = ShopOrder::findOne([
            'id' => $orderid,
            'uniacid' => $uniacid,
            'openid' => McMappingFan::getAllRelatedOpenIDs($openid),
        ]);
        if (!$orderModel) {
            throw new ApiException(Response::ERROR_PARAM_ERROR);
        }
        $order = $orderModel->toArray();

        $merchid = $order['merchid'];
        $goods = pdo_fetchall('select og.goodsid,og.price,g.title,g.thumb,og.total,g.credit,og.optionid,og.optionname as optiontitle,g.isverify,g.storeids from ' . ShopOrderGoods::tableName() . ' og ' . ' left join ' . tablename('new_shop_goods') . ' g on g.id=og.goodsid ' . ' where og.orderid=:orderid and og.uniacid=:uniacid ', array(':uniacid' => $uniacid, ':orderid' => $orderid));
        $address = $orderModel->getAddressInfo();

        // 联系人
        $carrier = $orderModel->getCarrierInfo();

        //自提点
        $store = $orderModel->getStoreInfo();

        //核销门店
        $stores = false;
        if ($order['isverify']) {
            $storeids = array();

            foreach ($goods as $g) {
                if (!(empty($g['storeids']))) {
                    $storeids = array_merge(explode(',', $g['storeids']), $storeids);
                }

            }

            if (empty($storeids)) {
                $stores = \common\models\Store::fetchAll([
                    'merchid' => intval($merchid),
                    'status' => 1,
                    'uniacid' => Request::getInstance()->uniacid,
                ]);
            } else {
                $stores = \common\models\Store::fetchAll([
                    'id' => \common\Helper::ensureArray($storeids),
                    'merchid' => intval($merchid),
                    'status' => 1,
                    'uniacid' => Request::getInstance()->uniacid,
                ]);
            }
        }


        $text = '';

        if (!empty($address)) {
            $text = '您的包裹整装待发';
        }


        if (!(empty($order['dispatchtype'])) && empty($order['isverify'])) {
            $text = '您可以到您选择的自提点取货了';
        }


        if (!(empty($order['isverify']))) {
            $text = '您可以到适用门店去使用了';
        }


        if (!(empty($order['virtual']))) {
            $text = '您购买的商品已自动发货';
        }


        if (!(empty($order['isvirtual'])) && empty($order['virtual'])) {
            if (!(empty($order['isvirtualsend']))) {
                $text = '您购买的商品已自动发货';
            } else {
                $text = '您已经支付成功';
            }
        }


        if ($_GPC['result'] == 'seckill_refund') {
            $icon = 'e75a';
        } else {
            if (!(empty($address))) {
                $icon = 'e623';
            }


            if (!(empty($order['dispatchtype'])) && empty($order['isverify'])) {
                $icon = 'e7b9';
            }


            if (!(empty($order['isverify']))) {
                $icon = 'e7b9';
            }


            if (!(empty($order['virtual']))) {
                $icon = 'e7a1';
            }


            if (!(empty($order['isvirtual'])) && empty($order['virtual'])) {
                if (!(empty($order['isvirtualsend']))) {
                    $icon = 'e7a1';
                } else {
                    $icon = 'e601';
                }
            }

        }

        $result = array(
            'order' => array('id' => $orderid, 'isverify' => $order['isverify'], 'virtual' => $order['virtual'], 'isvirtual' => $order['isvirtual'], 'isvirtualsend' => $order['isvirtualsend'], 'virtualsend_info' => $order['virtualsend_info'], 'virtual_str' => $order['virtual_str'], 'status' => ($order['paytype'] == 3 ? '订单提交支付' : Yii::t('shop_o2o_page_string', '订单支付成功')), 'text' => $text, 'price' => $order['price']),
            'paytype' => ($order['paytype'] == 3 ? '需到付' : '实付金额'),
            'carrier' => $carrier,
            'address' => $address,
            'stores' => $stores,
            'store' => $store,
            'icon' => $icon
        );

        if (!(empty($order['virtual'])) && !(empty($order['virtual_str']))) {
            $result['ordervirtual'] = $orderModel->getOrderVirtual();
            $result['virtualtemp'] = pdo_fetch('SELECT linktext, linkurl FROM ' . tablename('new_shop_virtual_type') . ' WHERE id=:id AND uniacid=:uniacid LIMIT 1', array(':id' => $order['virtual'], ':uniacid' => Request::getInstance()->uniacid));
        }

        //查询是否存在支付领优惠券活动

        $activity = com('coupon')->activity($order['price']);
        if ($activity) {
            $result['share'] = 1;
        } else {
            $result['share'] = 0;
        }

        $result['success'] = 1;
        return $result;
    }
}
