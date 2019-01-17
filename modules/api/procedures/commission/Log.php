<?php
/**
 * Created by PhpStorm.
 * User: yibin
 * Date: 2018/9/11
 * Time: 12:17
 */

namespace common\modules\api\procedures\commission;

use common\components\AppUser;
use common\components\Request;
use common\components\Response;
use common\models\CommissionApply;
use common\modules\api\procedures\ApiException;
use common\modules\api\procedures\BaseAppApi;
use Yii;

class Log extends BaseAppApi
{
    /**
     * @param string $token
     * @return array
     * @throws ApiException
     * @throws \yii\db\Exception
     * @throws \Exception
     */
    public function commissionGetLogList($token = ''){
        global $_W;
        global $_GPC;
        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new ApiException(Response::USER_NOT_LOGIN);
        }

        $member = \common\models\ShopMember::getInfo($openid);
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;

        $condition = " and `mid`=:mid and uniacid=:uniacid";
        $params = array(
            ':mid' => $member['id'],
            ':uniacid' => $uniacid
        );
        $status = intval($_GPC['status']);
        if (!empty($status)) {
            $condition.= ' and status=' . $status;
        }

        $commissioncount = CommissionApply::sumAll([
            'mid' => $member['id'],
            ['>', 'status', -1],
            'uniacid' => $uniacid,
        ], 'commission');

        $list = pdo_fetchall("select * from " . CommissionApply::tableName() . " where 1 {$condition} order by id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, $params);
        $total = pdo_fetchcolumn('select count(*) from ' . CommissionApply::tableName() . " where 1 {$condition}", $params);
        if(!is_array($list) || empty($list)){
            $list = array();
        }
        $list = CommissionApply::formatList($list);
        return [
            'total' => $total,
            'list' => $list,
            'pagesize' => $psize,
            'commissioncount' => number_format($commissioncount, 2)
        ];
    }
}