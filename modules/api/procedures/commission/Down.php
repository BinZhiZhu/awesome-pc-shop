<?php
/**
 * Created by PhpStorm.
 * User: yibin
 * Date: 2018/9/11
 * Time: 10:47
 */

namespace common\modules\api\procedures\commission;

use common\components\AppUser;
use common\components\Request;
use common\components\Response;
use common\modules\api\procedures\ApiException;
use common\modules\api\procedures\BaseAppApi;
use Yii;

class Down extends BaseAppApi
{
    /**
     * @param string $set_texts_c1
     * @param string $set_texts_c2
     * @param string $set_texts_c3
     * @param int $set_level
     * @param string $token
     * @return array
     * @throws ApiException
     * @throws \Exception
     */

    public function commissionGetDownSetting($set_texts_c1 = '',$set_texts_c2 = '',$set_texts_c3 = '',$set_level = 0,$token = ''){
        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        $member = p('commission')->getInfo($openid);

        $levelcount1 = $member['level1'];
        $levelcount2 = $member['level2'];
        $levelcount3 = $member['level3'];
        $level1 = $level2 = $level3 = 0;
        $levels = array();

        // 一级
        $level1 = \common\models\ShopMember::countAll([
            'agentid' => $member['id'],
            'uniacid' => $uniacid,
        ]);
        $levels[0] = array('level'=>1, 'name'=>$set_texts_c1, 'total'=>$level1);
        // 二级
        if ($set_level >= 2) {
            $levels[1] = array('level'=>2, 'name'=>$set_texts_c2, 'total'=>0);
            if($levelcount1 > 0){
                $levels[1]['total'] = \common\models\ShopMember::countAll([
                    'agentid' => array_keys($member['level1_agentids']),
                    'uniacid' => $uniacid,
                ]);
            }
        }
        // 三级
        if ($set_level >= 3) {
            $levels[2] = array('level'=>3, 'name'=>$set_texts_c3, 'total'=>0);
            if($levelcount2 > 0){
                $levels[2]['total'] = \common\models\ShopMember::countAll([
                    'agentid' => array_keys($member['level2_agentids']),
                    'uniacid' => $uniacid,
                ]);
            }
        }

        $total = $level1 + $level2 + $level3;

        $result = [
           'total' => $total,
           'levels' => $levels
        ];
        return $result;
    }

    /**
     * @param string $token
     * @return array
     * @throws ApiException
     * @throws \yii\db\Exception
     * @throws \Exception
     */
    public function commissionGetDownList($token = '')
    {
        global $_GPC;
        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        $member = p('commission')->getInfo($openid);

        $total_level = 0;
        $level = intval($_GPC['level']);
        ($level > 3 || $level <= 0) && $level = 1;
        $condition = '';
        $levelcount1 = $member['level1'];
        $levelcount2 = $member['level2'];
        $levelcount3 = $member['level3'];
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;

        if ($level == 1) {
            $condition = ' and agentid=' . $member['id'];
            $total_level = \common\models\ShopMember::countAll([
                'agentid' => $member['id'],
                'uniacid' => $uniacid,
            ]);
        } else if ($level == 2) {
            if (empty($levelcount1)) {
                app_json(array('list' => array(), 'total' => 0, 'pagesize' => $psize));
            }

            $condition = ' and agentid in( ' . implode(',', array_keys($member['level1_agentids'])) . ')';
            $total_level = \common\models\ShopMember::countAll([
                'agentid' => array_keys($member['level1_agentids']),
                'uniacid' => $uniacid,
            ]);
        } else {
            if ($level == 3) {
                if (empty($levelcount2)) {
                    app_json(array(
                        'list' => array(),
                        'total' => 0,
                        'pagesize' => $psize
                    ));
                }

                $condition = ' and agentid in( ' . implode(',', array_keys($member['level2_agentids'])) . ')';
                $total_level = \common\models\ShopMember::countAll([
                    'agentid' => array_keys($member['level2_agentids']),
                    'uniacid' => $uniacid,
                ]);
            }
        }

        $list = pdo_fetchall('select * from ' . tablename('new_shop_member') . ' where uniacid = ' . $uniacid . ' ' . $condition . '  ORDER BY isagent desc,id desc limit ' . (($pindex - 1) * $psize) . ',' . $psize);
        if (!is_array($list) || empty($list)) {
            $list = array();
        }
        foreach ($list as &$row) {
            if ($member['isagent'] && $member['status']) {
                $info = p('commission')->getInfo($row['openid'], array('total'));
                $row['commission_total'] = $info['commission_total'];
                $row['agentcount'] = $info['agentcount'];
                $row['agenttime'] = date('Y-m-d H:i', $row['agenttime']);
            }

            $ordercount = \common\models\ShopOrder::countAll([
                'openid' => \common\models\McMappingFan::getAllRelatedOpenIDs($row['openid']),
                'uniacid' => $uniacid,
            ]);
            $row['ordercount'] = number_format(intval($ordercount), 0);
            $moneycount = pdo_fetchcolumn('select sum(og.realprice) from ' . \common\models\ShopOrderGoods::tableName() . ' og ' . ' left join ' . tablename('new_shop_order') . ' o on og.orderid=o.id where o.openid=:openid  and o.status>=1 and o.uniacid=:uniacid limit 1', array(':uniacid' => $uniacid, ':openid' => $row['openid']));
            $row['moneycount'] = number_format(floatval($moneycount), 2);
            $row['createtime'] = date('Y-m-d H:i', $row['createtime']);
        }

        unset($row);
        $result = [
            'list' => $list,
            'total' => $total_level,
            'pagesize' => $psize
        ];
        return $result;
    }
}
