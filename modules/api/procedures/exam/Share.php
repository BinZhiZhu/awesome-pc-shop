<?php
/**
 * Created by PhpStorm.
 * User: vsion
 * Date: 2018/8/14
 * Time: 15:34
 */

namespace common\modules\api\procedures\exam;

use common\modules\api\procedures\BaseAppApi;
use common\models\ShopMember;
use common\modules\course\models\ExUserRelationship;
use common\modules\sns\controllers\web\ManageController;

class Share extends BaseAppApi
{
    /**
     * 分享邀请好友接口
     *
     * @category 课程模块
     * @param $openid1
     * @param $openid2
     * @return array
     * @throws \Exception
     */
    public function do_invite_friend($openid1, $openid2) {

        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (!$openid1 || !is_string($openid1)) {
            \Yii::warning('openid1参数错误，openid1：'.$openid1,__METHOD__);
            throw new \Exception('参数错误！');
        }

        if (!$openid2 || !is_string($openid2)) {
            \Yii::warning('openid2参数错误，openid2：'.$openid2,__METHOD__);
            throw new \Exception('参数错误！');
        }

        if($openid1 == $openid2)
        {
            return ['status' => 0];
        }
        $uid1 = ShopMember::getModel($openid1);
        $uid1 = $uid1 ? $uid1->id : 0;

        $uid2 = ShopMember::getModel($openid2);
        $uid2 = $uid2 ? $uid2->id : 0;

        if (!$uid1 || !$uid2) {
            throw new \Exception('openid参数错误!');
        }

        $data = [
            'invite_uid' => $uid1,
            'invited_uid' => $uid2,
            'create_time' => time(),
            'uniacid' => $uniacid,
        ];

        $result1 = ExUserRelationship::findOne(
            array('invite_uid' =>$uid1,'invited_uid' =>$uid2)
        );
        $result2 = ExUserRelationship::findOne(
            array('invite_uid' =>$uid2,'invited_uid' =>$uid1)
        );

        if($result1||$result2)
        {
            //他们是好友关系
            return ['status' => 0];
        }
        else
        {
            $result = \common\modules\course\models\ExUserRelationship::insertOne($data);
            $status = $result ? 1 : 0;

            //答对题目增加用户威望
            if ($status) {
                $inc_prestige = 1; //先默认每对一题加1分

                $ex_config = \common\modules\course\models\ExPaperConfig::findOne(['uniacid' => $uniacid]);
                if ($ex_config) {
                    $config = json_decode($ex_config->config, true);
                    if ($config && is_array($config)) {
                        $inc_prestige = $config['inc_prestige2'];
                    }
                }

                \Yii::info('会员1' . $openid1 . '成功邀请会员2' . $openid2 . '道题，增加会员1' . $inc_prestige . '个威望值');
                m('member')->setCredit($openid1, 'credit3', $inc_prestige, array(0, '增加会员' . $inc_prestige . '威望值'));
            }
            return ['status' => $status];
        }
    }
}