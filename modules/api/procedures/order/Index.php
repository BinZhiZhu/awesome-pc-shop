<?php

namespace common\modules\api\procedures\order;


use common\components\AppUser;
use common\models\CoreSetting;
use common\models\ShopGoods;
use common\models\ShopMemberAddress;
use common\models\ShopOrder;
use common\models\ShopOrderRefund;
use common\modules\api\procedures\ApiException;
use common\modules\api\procedures\BaseAppApi;
use Yii;

class Index extends BaseAppApi
{
    public function get_order_list($show_status = 0, $pindex = 1, $psize = 10, $token = '')
    {
        //兼容旧接口,以后删除
        return $this->apiGetOrderList($show_status, $pindex, $psize, $token);
    }

    public function apiGetOrderList($show_status = 0, $pindex = 1, $psize = 10, $token = '')
    {
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new ApiException(\common\components\Response::ERROR_PARAM_ERROR);
        }

        $r_type = [
            Yii::t('shop_o2o_page_string', '待退款'),
            Yii::t('shop_o2o_page_string', '待退货退款'),
            Yii::t('shop_o2o_page_string', '待换货')
        ];

        $condition = ' and openid=:openid and deleted=0 and uniacid=:uniacid ';
        $params = array(':uniacid' => $uniacid, ':openid' => $openid);

        $merch_plugin = p('merch');
        $merch_data = m('common')->getPluginset('merch');
        if ($merch_plugin && $merch_data['is_openmerch']) {
            $is_openmerch = 1;
        } else {
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

        } else {
            $condition .= ' and userdeleted=0 ';
        }

        $com_verify = com('verify');
        $list = pdo_fetchall('select id,ordersn,price,remark,userdeleted,isparent,refundstate,paytype,status,addressid,refundid,isverify,dispatchtype,verifytype,verifyinfo,verifycode,iscomment,merchid,expresssn,express from ' . ShopOrder::tableName() . ' where 1 ' . $condition . ' order by createtime desc LIMIT ' . (($pindex - 1) * $psize) . ',' . $psize, $params);
        $total = pdo_fetchcolumn('select count(*) from ' . tablename('new_shop_order') . ' where 1 ' . $condition, $params);
        $refunddays = intval($_W['shopset']['trade']['refunddays']);

        if ($is_openmerch == 1) {
            $merch_user = $merch_plugin->getListUser($list, 'merch_user');
        }

        foreach ($list as &$row) {
            $param = array();

            if ($row['isparent'] == 1) {
                $scondition = ' og.parentorderid=:parentorderid';
                $param[':parentorderid'] = $row['id'];
            } else {
                $scondition = ' og.orderid=:orderid';
                $param[':orderid'] = $row['id'];
            }

            $sql = 'SELECT og.goodsid,og.total,g.title,g.thumb,og.price,og.optionname as optiontitle,og.optionid,op.specs,g.merchid,g.status FROM ' . \common\models\ShopOrderGoods::tableName() . ' og ' . ' left join ' . ShopGoods::tableName() . ' g on og.goodsid = g.id ' . ' left join ' . \common\models\ShopGoodsOption::tableName() . ' op on og.optionid = op.id ' . ' where ' . $scondition . ' order by og.id asc';
            $goods = pdo_fetchall($sql, $param);
            $goods = ShopOrder::formatGoods($goods);
            $ismerch = 0;
            $merch_array = array();
            $g = 0;
            $nog = 0;

            foreach ($goods as &$r) {
                $merchid = $r['merchid'];
                $merch_array[$merchid] = $merchid;

                if ($r['status'] == 2) {
                    $row['gift'][$g] = $r;
                    ++$g;
                } else {
                    $row['nogift'][$nog] = $r;
                    ++$nog;
                }
            }
            unset($r);

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

            $row['goods'] = $goods_list;
            $statuscss = 'text-cancel';

            switch ($row['status']) {
                case '-1':
                    $status = Yii::t('shop_o2o_page_string', '已取消');
                    break;

                case '0':
                    if ($row['paytype'] == 3) {
                        $status = '待发货';
                    } else {
                        $status = Yii::t('shop_o2o_page_string', '待付款');
                    }

                    $statuscss = 'text-cancel';
                    break;

                case '1':
                    if ($row['isverify'] == 1) {
                        $status = '使用中';
                    } else if (empty($row['addressid'])) {
                        $status = Yii::t('shop_o2o_page_string', '待取货');
                    } else {
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
                        } else {
                            $status = ((empty($_W['shopset']['trade']['closecomment']) ? Yii::t('shop_o2o_page_string', '待评价') : Yii::t('shop_o2o_page_string', '已完成')));
                        }
                    } else {
                        $status = Yii::t('shop_o2o_page_string', '交易完成');
                    }

                    $statuscss = 'text-success';
                    break;
            }

            $row['statusstr'] = $status;
            $row['statuscss'] = $statuscss;

            if ((0 < $row['refundstate']) && !(empty($row['refundid']))) {
                $refund = ShopOrderRefund::fetchOne([
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


            $row['cancancel'] = !($row['userdeleted']) && !($row['status']);
            $row['canpay'] = ($row['paytype'] != 3) && !($row['userdeleted']) && ($row['status'] == 0);
            $row['canverify'] = $row['canverify'] && ($row['status'] != -1) && ($row['status'] != 0);
            $row['candelete'] = ($row['status'] == 3) || ($row['status'] == -1);
            $row['cancomment'] = ($row['status'] == 3) && ($row['iscomment'] == 0) && empty($_W['shopset']['trade']['closecomment']);
            $row['cancomment2'] = ($row['status'] == 3) && ($row['iscomment'] == 1) && empty($_W['shopset']['trade']['closecomment']);
            $row['cancomplete'] = $row['status'] == 2;
            $row['cancancelrefund'] = (0 < $row['refundstate']) && isset($refund) && ($refund['status'] != 5);
            $row['candelete2'] = $row['userdeleted'] == 1;
            $row['canrestore'] = $row['userdeleted'] == 1;
            $row['hasexpress'] = (1 < $row['status']) && (0 < $row['addressid']);

            //收货地址信息
            $isShowAddress = 0;//是否显示收货地址信息
            if (CoreSetting::getByKey('enable_order_list_show_member_address')) {
                $memberAddress = ShopOrder::getOrderMemberAddress($row['addressid']);
                if (!is_error($memberAddress)) {
                    $row['realname'] = $memberAddress['realname']; //收货人
                    $row['mobile'] = $memberAddress['mobile']; //电话
                    $isShowAddress = 1;
                }
            }
        }
        unset($row);

        $olist = [
            ['orderText' => Yii::t('shop_o2o_page_string', '全部'), 'dataType' => ''],
            ['orderText' => Yii::t('shop_o2o_page_string', '待付款'), 'dataType' => '0'],
            ['orderText' => Yii::t('shop_o2o_page_string', '待发货'), 'dataType' => '1'],
            ['orderText' => Yii::t('shop_o2o_page_string', '待收货'), 'dataType' => '2'],
            ['orderText' => Yii::t('shop_o2o_page_string', '已完成'), 'dataType' => '3'],
            ['orderText' => Yii::t('shop_o2o_page_string', '退换货'), 'dataType' => '4'],
            ['orderText' => Yii::t('shop_o2o_page_string', '回收站'), 'dataType' => '5']
        ];

        $olist_o2o = $olist;
        $olist_o2o[] = ['orderText' => Yii::t('shop_o2o_page_string', '待取货'), 'dataType' => '1'];

        if (\common\modules\system\Module::getInstance()->getUploadLogConfig()) {
            $type = $_W['route'];
            \common\modules\system\Module::getInstance()->backendLog($type);
        }
        return array(
            'list' => $list,
            'pagesize' => $psize,
            'total' => $total,
            'page' => $pindex,
            'olist' => $olist,
            'olist_o2o' => $olist_o2o,
            'isShowAddress' => $isShowAddress,
        );

    }

    public function get_order_detail($orderid = 0, $token = '')
    {
        //兼容旧接口,以后删除
        return $this->apiGetOrderDetail($orderid, $token);
    }

    public function apiGetOrderDetail($orderid = 0, $token = '')
    {
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($orderid) || empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ERROR_PARAM_ERROR);
        }

        $this_member = AppUser::getInstance()->identity->toArray();

        $verification_man = \common\models\Saler::fetchOne([
            'uniacid' => \common\components\Request::getInstance()->uniacid,
            'openid' => $openid,
            'status' => 1
        ]);

        if (!empty($verification_man) && !empty($verification_man['merchid'])) {
            $orderModel = \common\models\ShopOrder::findOne([
                'id' => $orderid,
                'uniacid' => $uniacid,
                'merchid' => $verification_man['merchid'],
            ]);
        } else {
            $orderModel = \common\models\ShopOrder::findOne([
                'id' => $orderid,
                'uniacid' => $uniacid,
                'openid' => \common\models\McMappingFan::getAllRelatedOpenIDs($openid),
            ]);
        }

        if (empty($orderModel)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ORDER_NOT_FOUND);
        }
        $order = $orderModel->toArray();

        if ($order['merchshow'] == 1) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ORDER_NOT_FOUND);
        }

        if ($order['userdeleted'] == 2) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ORDER_NOT_FOUND);
        }

        $merch_plugin = p('merch');
        $merch_data = m('common')->getPluginset('merch');
        if ($merch_plugin && $merch_data['is_openmerch']) {
            $is_openmerch = 1;
        } else {
            $is_openmerch = 0;
        }

        $merchid = $order['merchid'];
        $diyform_plugin = p('diyform');
        $diyformfields = '';

        if ($diyform_plugin) {
            $diyformfields = ',og.diyformfields,og.diyformdata';
        }


        $param = array();
        $param[':uniacid'] = \common\components\Request::getInstance()->uniacid;

        if ($order['isparent'] == 1) {
            $scondition = ' og.parentorderid=:parentorderid';
            $param[':parentorderid'] = $orderid;
        } else {
            $scondition = ' og.orderid=:orderid';
            $param[':orderid'] = $orderid;
        }

        $gift = array();
        $nogift = array();
        $gn = 0;
        $nog = 0;
        $goods = pdo_fetchall('select g.id,og.goodsid,og.price,g.title,g.thumb,g.status,og.total,g.credit,og.optionid,og.optionname as optiontitle,g.isverify,g.isfullback,g.storeids' . $diyformfields
            . ' from ' . \common\models\ShopOrderGoods::tableName() . ' og '
            . ' left join ' . tablename('new_shop_goods') . ' g on g.id=og.goodsid '
            . ' where ' . $scondition . ' and og.uniacid=:uniacid ', $param);

        $goods = \common\models\ShopOrder::formatGoods($goods);

        $diyform_flag = 0;

        if ($diyform_plugin) {
            //订单统一模板
            if (!(empty($order['diyformfields'])) && !(empty($order['diyformdata']))) {
                $order_fields = iunserializer($order['diyformfields']);
                $order_data = iunserializer($order['diyformdata']);
            }
        }

        // 收货地址
        $address = $orderModel->getAddressInfo();

        // 联系人
        $carrier = $orderModel->getCarrierInfo();

        // 门店
        $store = $orderModel->getStoreInfo();

        //核销门店
        $stores = false;
        $showverify = $orderModel->isShowVerify();  //是否显示消费码
        $canverify = false;  //是否可以核销
        $verifyinfo = false;

        if (com('verify')) {
            if ($order['isverify']) {
                //核销单
                $storeids = array();

                foreach ($goods as $g) {
                    if (!(empty($g['storeids']))) {
                        $storeids = array_merge(explode(',', $g['storeids']), $storeids);
                    }
                }

                $stores = \common\modules\store\Module::getSupportVerifyStores($storeids, $merchid);

                if (($order['verifytype'] == 0) || ($order['verifytype'] == 1)) {

                    $vs = iunserializer($order['verifyinfo']);
                    $verifyinfo = array(
                        array('verifycode' => $order['verifycode'], 'verified' => ($order['verifytype'] == 0 ? $order['verified'] : $goods[0]['total'] <= count($vs)))
                    );

                    if ($order['verifytype'] == 0) {
                        $canverify = empty($order['verified']) && $showverify;
                    } else if ($order['verifytype'] == 1) {
                        $canverify = (count($vs) < $goods[0]['total']) && $showverify;
                    }

                } else {
                    $verifyinfo = iunserializer($order['verifyinfo']);
                    $last = 0;

                    foreach ($verifyinfo as $v) {
                        if (!($v['verified'])) {
                            ++$last;
                        }

                    }

                    $canverify = (0 < $last) && $showverify;
                }
            } else if (!(empty($order['dispatchtype']))) {
                $verifyinfo = array(
                    array('verifycode' => $order['verifycode'], 'verified' => $order['status'] == 3)
                );
                $canverify = ($order['status'] == 1) && $showverify;
            }

        }


        $order['canverify'] = $canverify;
        $order['showverify'] = $showverify;
        $canrefund = $orderModel->isCanRefund();
        $order['canrefund'] = $canrefund;

        //如果发货，查找第一条物流
        $express = false;

        if ((2 <= $order['status']) && empty($order['isvirtual']) && empty($order['isverify'])) {

            $obj = new \common\modules\sysset\procedures\Recharge();
            $expresslist = $obj->apiInquireExpress($order['express'],$order['expresssn'],$order['ordersn']);


            if($expresslist['query_status'])
            {
                $query_data = $expresslist['query_data'];
                $express_array = ['time' => $query_data[0]['time'],'step' =>  $query_data[0]['context']];
                $express = $express_array;
            }
            /*
            $expresslist = m('util')->getExpressList($order['express'], $order['expresssn']);

            if (0 < count($expresslist)) {
                $express = $expresslist[0];
            }
            */

        }


        $shopname = $_W['shopset']['shop']['name'];

        if (!(empty($order['merchid'])) && ($is_openmerch == 1)) {
            $merch_user = $merch_plugin->getListUser($order['merchid']);
            $shopname = $merch_user['merchname'];
            $shoplogo = tomedia($merch_user['logo']);
        }

        $order['statusstr'] = \common\models\ShopOrder::getOrderStatusText($order);

        if (is_array($verifyinfo) && isset($verifyinfo)) {
            foreach ($verifyinfo as &$v) {
                if ($v['verified']) {
                    $status = '已使用';
                } else if ($order['dispatchtype']) {
                    $status = '未取货';
                } else if ($order['verifytype'] == 1) {
                    $status = '剩余' . ($goods[0]['total'] - count($vs)) . '次';
                } else {
                    $status = '未使用';
                }

                $v['status'] = $status;
            }

            unset($v);
        }


        $newFields = array();
        if (is_array($order_fields) && !(empty($order_fields))) {
            foreach ($order_fields as $k => $v) {
                $v['diy_type'] = $k;
                $newFields[] = $v;

                if (($v['data_type'] == 5) && !(empty($order_data[$k])) && is_array($order_data[$k])) {
                    $order_data[$k] = set_medias($order_data[$k]);
                }

            }
        }


        if (!(empty($verifyinfo)) && empty($order['status'])) {
            foreach ($verifyinfo as &$lala) {
                $lala['verifycode'] = '';
            }

            unset($lala);
        }


        $icon = '';

        if (empty($order['status'])) {
            if ($order['paytype'] == 3) {
                $icon = 'e623';
            } else {
                $icon = 'e711';
            }
        } else if ($order['status'] == 1) {
            $icon = 'e74c';
        } else if ($order['status'] == 2) {
            $icon = 'e623';
        } else if ($order['status'] == 3) {
            $icon = 'e601';
        } else if ($order['status'] == -1) {
            $icon = 'e60e';
        }

        if (!empty($order['verifycode'])) {
            $query = array('id' => $orderid, 'verifycode' => $order['verifycode']);
            $url = mobileUrl('verify/detail', $query, true);
            $qrcode = m('qrcode')->createQrcode($url);
        }

        $order = [
            'id' => $order['id'],
            'ordersn' => $order['ordersn'],
            'createtime' => $order['createtime'],
            'paytime' => !empty($order['paytime']) ? $order['paytime'] : '',
            'sendtime' => !empty($order['sendtime']) ? $order['sendtime'] : '',
            'finishtime' => !empty($order['finishtime']) ? $order['finishtime'] : '',
            'status' => $order['status'],
            'statusstr' => $order['statusstr'],
            'price' => $order['price'],
            'goodsprice' => $order['goodsprice'],
            'dispatchprice' => $order['dispatchprice'],
            'ispackage' => $order['ispackage'],
            'deductenough' => $order['deductenough'],
            'couponprice' => $order['couponprice'],
            'discountprice' => $order['discountprice'],
            'isdiscountprice' => $order['isdiscountprice'],
            'deductprice' => $order['deductprice'],
            'deductcredit2' => $order['deductcredit2'],
            'diyformfields' => (empty($newFields) ? [] : $newFields),
            'diyformdata' => (empty($order_data) ? [] : $order_data),
            'showverify' => $order['showverify'],
            'verifytitle' => ($order['dispatchtype'] ? '自提码' : '消费码'),
            'dispatchtype' => $order['dispatchtype'],
            'verifyinfo' => $verifyinfo,
            'merchid' => intval($order['merchid']),
            'virtual' => $order['virtual'],
            'virtual_str' => ($order['status'] == 3 ? $order['virtual_str'] : ''),
            'virtual_info' => ($order['status'] == 3 ? $order['virtual_info'] : ''),
            'isvirtualsend' => $order['isvirtualsend'],
            'virtualsend_info' => (empty($order['virtualsend_info']) ? '' : $order['virtualsend_info']),
            'canrefund' => $order['canrefund'],
            'refundtext' => (($order['status'] == 1 ? '申请退款' : '申请售后')) . ((!(empty($order['refundstate'])) ? '中' : '')),
            'refundtext_btn' => '',
            'cancancel' => !($order['userdeleted']) && !($order['status']),
            'canpay' => ($order['paytype'] != 3) && !($order['userdeleted']) && ($order['status'] == 0),
            'canverify' => $order['canverify'] && ($order['status'] != -1) && ($order['status'] != 0),
            'candelete' => ($order['status'] == 3) || ($order['status'] == -1),
            'cancomment' => ($order['status'] == 3) && ($order['iscomment'] == 0) && empty($_W['shopset']['trade']['closecomment']),
            'cancomment2' => ($order['status'] == 3) && ($order['iscomment'] == 1) && empty($_W['shopset']['trade']['closecomment']),
            'cancomplete' => $order['status'] == 2,
            'cancancelrefund' => 0 < $order['refundstate'],
            'candelete2' => $order['userdeleted'] == 1,
            'canrestore' => $order['userdeleted'] == 1,
            'verifytype' => $order['verifytype'],
            'refundstate' => $order['refundstate'],
            'icon' => $icon,
            'city_express_state' => $order['city_express_state']
        ];

        if ($order['canrefund']) {
            if (!(empty($order['refundstate']))) {
                $order['refundtext_btn'] = '查看';
            }


            if ($order['status'] == 1) {
                $order['refundtext_btn'] .= '申请退款';
            } else {
                $order['refundtext_btn'] .= '申请售后';
            }

            if (!(empty($order['refundstate']))) {
                $order['refundtext_btn'] .= '进度';
            }

        }


        $allgoods = array();

        foreach ($goods as $g) {
            $newFields = array();

            if (is_array($g['diyformfields'])) {
                foreach ($g['diyformfields'] as $k => $v) {
                    $v['diy_type'] = $k;
                    $newFields[] = $v;
                }
            }

            $allgoods[] = [
                'id' => $g['goodsid'],
                'title' => $g['title'],
                'price' => $g['price'],
                'thumb' => tomedia($g['thumb']),
                'total' => $g['total'],
                'isfullback' => $g['isfullback'],
                'fullbackgoods' => $g['fullbackgoods'],
                'status' => $g['status'],
                'optionname' => $g['optiontitle'],
                'diyformdata' => (empty($g['diyformdata']) ? [] : $g['diyformdata']),
                'diyformfields' => $newFields,
            ];
        }

        if (!empty($allgoods)) {
            foreach ($allgoods as $gk => $og) {
                if ($og['status'] == 2) {
                    $gift[$gn] = $og;
                    ++$gn;
                } else {
                    $nogift[$nog] = $og;
                    ++$nog;
                }
            }
        }


        $shop = array('name' => $shopname, 'logo' => $shoplogo);
        $map = new \common\modules\api\procedures\util\Map();
        $distance = $map->get_distance([$this_member['lng'], $this_member['lat']], [$merch_user['lng'], $merch_user['lat']]);
        $shop['distance'] = $distance;

        $result = array(
            'order' => $order,
            'goods' => $allgoods,
            'gift' => $gift,
            'nogift' => $nogift,
            'address' => $address,
            'express' => $express,
            'carrier' => $carrier,
            'store' => $store,
            'stores' => $stores,
            'shop' => $shop,
            'customer' => intval($_W['shopset']['app']['customer']),
            'phone' => intval($_W['shopset']['app']['phone'])
        );

        if (!(empty($result['customer']))) {
            $result['customercolor'] = ((empty($_W['shopset']['app']['customercolor']) ? '#ff5555' : $_W['shopset']['app']['customercolor']));
        }


        if (!(empty($result['phone']))) {
            $result['phonecolor'] = ((empty($_W['shopset']['app']['phonecolor']) ? '#ff5555' : $_W['shopset']['app']['phonecolor']));
            $result['phonenumber'] = ((empty($_W['shopset']['app']['phonenumber']) ? '#ff5555' : $_W['shopset']['app']['phonenumber']));
        }


        if (!(empty($order['virtual'])) && !(empty($order['virtual_str']))) {
            if ($order['status'] == 3) {
                $result['ordervirtual'] = $orderModel->getOrderVirtual();
            } else {
                $result['ordervirtual'] = '';
            }

            $result['virtualtemp'] = pdo_fetch('SELECT linktext, linkurl FROM ' . tablename('new_shop_virtual_type') . ' WHERE id=:id AND uniacid=:uniacid LIMIT 1', array(':id' => $order['virtual'], ':uniacid' => \common\components\Request::getInstance()->uniacid));
        }

        $result['refund'] = array(
            'url' => mobileUrl('order.refund', array('id' => $order['id']), true),
            'is_webview' => true,
            'open_type' => 'navigate'
        );

        $result['price_rows'] = \common\models\ShopOrder::getPriceRows($order);
        $result['desc_rows'] = \common\models\ShopOrder::getDescRows($order);
        $result['qrcode'] = $qrcode;


        return $result;

    }

    public function cancel_order($orderid = 0, $remark = '', $token = '')
    {
        //兼容旧接口,以后删除
        return $this->apiCancelOrder($orderid, $remark, $token);
    }

    /**
     * @param int $orderid
     * @param string $remark
     * @param string $token
     *
     * @return array
     * @throws \Throwable
     * @throws \common\modules\api\procedures\ApiException
     * @throws \yii\db\Exception
     */
    public function apiCancelOrder($orderid = 0, $remark = '', $token = '')
    {
        global $_W;

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($orderid) || empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ERROR_PARAM_ERROR);
        }
        $order = \common\models\ShopOrder::fetchOne([
            'id' => $orderid,
            'openid' => $openid,
            'uniacid' => $uniacid,
        ]);
        if (empty($order)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ORDER_NOT_FOUND);
        }

        if (0 < $order['status']) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ORDER_CANNOT_CANCEL);
        }

        if ($order['status'] < 0) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ORDER_CANNOT_CANCEL);
        }

        if (!(empty($order['virtual'])) && ($order['virtual'] != 0)) {
            $goodsid = pdo_fetch('SELECT goodsid FROM ' . \common\models\ShopOrderGoods::tableName() . ' WHERE uniacid = ' . $uniacid . ' AND orderid = ' . $order['id']);
            $typeid = $order['virtual'];
            $vkdata = ltrim($order['virtual_info'], '[');
            $vkdata = rtrim($vkdata, ']');
            $arr = explode('}', $vkdata);

            foreach ($arr as $k => $v) {
                if (!($v)) {
                    unset($arr[$k]);
                }
            }

            $vkeynum = count($arr);
            \common\models\VirtualData::updateAll([
                'openid' => '',
                'usetime' => 0,
                'orderid' => 0,
                'ordersn' => '',
                'price' => 0,
                'merchid' => 0,
            ], ['typeid' => intval($typeid), 'orderid' => $order['id']]);
            \common\models\VirtualType::updateAllCounters(['usedata' => -$vkeynum], ['id' => intval($typeid)]);
        }

        //处理订单库存及用户积分情况(赠送积分)
        m('order')->setStocksAndCredits($orderid, 2);
        //返还抵扣积分
        if (0 < $order['deductprice']) {
            m('member')->setCredit($order['openid'], 'credit1', $order['deductcredit'], array('0', $_W['shopset']['shop']['name'] . '购物返还抵扣积分 积分: ' . $order['deductcredit'] . ' 抵扣金额: ' . $order['deductprice'] . ' 订单号: ' . $order['ordersn']));
        }
        //返还抵扣余额
        m('order')->setDeductCredit2($order);
        //退还优惠券
        if (com('coupon') && !empty($order['couponid'])) {
            \common\modules\coupon\Module::returnConsumeCoupon($orderid); //手机关闭订单
        }

        \common\models\ShopOrder::updateAll(array('status' => -1, 'canceltime' => time(), 'closereason' => $remark), array('id' => $order['id'], 'uniacid' => \common\components\Request::getInstance()->uniacid));
        m('notice')->sendOrderMessage($orderid);
        return [
            'success' => 1,
            'success_string' => Yii::t('success_string', '操作成功'),
        ];
    }

    public function comment_order($orderid = 0, $comments = null, $token = '')
    {
        //兼容旧接口,以后删除
        return $this->apiCommentOrder($orderid, $comments, $token);
    }

    /**
     * @param int $orderid
     * @param null $comments
     * @param string $token
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     * @throws \yii\db\Exception
     * @throws \Exception
     */
    public function apiCommentOrder($orderid = 0, $comments = null, $token = '')
    {
        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($orderid) || empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ERROR_PARAM_ERROR);
        }

        $order = pdo_fetch('select id,status,iscomment from ' . tablename('new_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1', array(':id' => $orderid, ':uniacid' => $uniacid, ':openid' => $openid));

        if (empty($order)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::ORDER_NOT_FOUND);
        }

        $member = m('member')->getMember($openid);

        if (is_string($comments)) {
            $comments_string = htmlspecialchars_decode(str_replace('\\', '', $comments));
            $comments = @json_decode($comments_string, true);
        }

        if (!is_array($comments)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::SYSTEM_ERROR, '数据出错,请重试!');
        }

        $trade = \common\models\ShopSysSet::getByKey('trade');

        if (!empty($trade['commentchecked'])) {
            $checked = 0;
        } else {
            $checked = 1;
        }

        foreach ($comments as $c) {
            $old_c = \common\models\ShopOrderComment::countAll([
                'orderid' => $orderid,
                'goodsid' => $c['goodsid'],
                'uniacid' => \common\components\Request::getInstance()->uniacid,
            ]);
            if (empty($old_c)) {
                //第一次评论
                $comment = array(
                    'uniacid' => $uniacid,
                    'orderid' => $orderid,
                    'goodsid' => $c['goodsid'],
                    'level' => $c['level'],
                    'content' => trim($c['content']),
                    'images' => is_array($c['images']) ? iserializer($c['images']) : iserializer(array()),
                    'openid' => $openid,
                    'nickname' => $member['nickname'],
                    'headimgurl' => $member['avatar'],
                    'createtime' => time(),
                    'checked' => $checked
                );
                pdo_insert('new_shop_order_comment', $comment);
            } else {
                $comment = [
                    'append_content' => trim($c['content']),
                    'append_images' => is_array($c['images']) ? iserializer($c['images']) : iserializer([]),
                    'reply_checked' => $checked,
                ];
                \common\models\ShopOrderComment::updateAll($comment, array('uniacid' => \common\components\Request::getInstance()->uniacid, 'goodsid' => $c['goodsid'], 'orderid' => $orderid));
            }
        }

        if ($order['iscomment'] <= 0) {
            $d['iscomment'] = 1;
        } else {
            $d['iscomment'] = 2;
        }

        \common\models\ShopOrder::updateAll($d, ['id' => $orderid, 'uniacid' => $uniacid]);
        return [
            'success' => 1,
            'success_string' => Yii::t('success_string', '操作成功'),
        ];
    }


}