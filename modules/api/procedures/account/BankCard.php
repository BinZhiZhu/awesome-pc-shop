<?php

namespace common\modules\api\procedures\account;

use common\components\AppUser;
use common\modules\api\procedures\BaseAppApi;
use Yii;

class BankCard extends BaseAppApi
{

    /**
     * @param $number
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     */
    public function apiGetBankInfo($number){
        $number = intval($number);
        $info = \Sco\Bankcard\BankList::getBankInfo($number);
        if($info['code'] === -1){
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::SYSTEM_ERROR,$info['message']);
        }
        return ['info'=>$info,'number'=>$number];
    }

    /**
     * @param $token
     * @param $number
     * @param $bank_name
     * @param $realname
     * @param $expiry_year
     * @param $expiry_month
     * @param $cvv2
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     */
    public function apiAddOrUpdateBankcard($token,$number,$bank_name,$realname,$expiry_year,$expiry_month,$cvv2,$id = 0,$second_pwd = ''){
        global $_W;

        $number = intval($number);
        $expiry_year = intval($expiry_year);
        $expiry_month = intval($expiry_month);
        $cvv2 = intval($cvv2);
        $id = intval($id);

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::USER_NOT_LOGIN);
        }

        $set = \common\models\ShopSysSet::getByKey('trade');
        $this_member = AppUser::getInstance()->identity->toArray();

        if($set['second_pwd'] == 1){
            $second_pwd = intval($second_pwd);
            if($second_pwd != $this_member['second_pwd']){
                throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR,Yii::t('shop_o2o_page_string','支付密码错误'));
            }
        }

        if(!empty($number) && !empty($bank_name) && !empty($realname) && !empty($expiry_year) && !empty($expiry_month) && !empty($cvv2)){
            $data = [
                'uniacid'=>$uniacid,
                'user_id'=>$this_member['id'],
                'bank_name'=>$bank_name,
                'realname'=>$realname,
                'number'=>$number,
                'expiry_year'=>$expiry_year,
                'expiry_month'=>$expiry_month,
                'cvv2'=>$cvv2,
                'created_at'=>time(),
            ];
            if(!empty($id)){
                unset($data['created_at']);
                $data['updated_at'] = time();
                \common\models\BankCard::updateAll($data,['id'=>$id,'user_id'=>$this_member['id'],'uniacid'=>$uniacid]);
                $bank_id = $id;
            }else{
                $bank_id = \common\models\BankCard::insertOne($data);
            }
            return [
                'success'=>1,
                'bank_id'=>$bank_id,
                'success_string' => Yii::t('success_string','操作成功'),
            ];

        }else{
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR);
        }

    }

    /**
     * @param $token
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     * @throws \Exception
     */
    public function apiGetBankcardList($token){
        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::USER_NOT_LOGIN);
        }
        $this_member = AppUser::getInstance()->identity->toArray();

        $list = [];
        $data = \common\models\BankCard::fetchAll(['user_id'=>$this_member['id'],'uniacid'=>$uniacid]);
        if(!empty($data)){
            foreach ($data as $k=>$v){
                $icon = \Sco\Bankcard\BankList::getBankIcon($v['bank_name']);
                $number = substr($v['number'],0,4).'*****'.substr($v['number'],15,strlen($v['number']));//保留前三位和后三位
                $list[$k]['id'] = $v['id'];
                $list[$k]['bank_name'] = $v['bank_name'];
                $list[$k]['number'] = $number;
                $list[$k]['icon'] = $icon;
            }

        }
        return ['list'=>$list];

    }

    /**
     * @param $token
     * @param $id
     *
     * @return array
     * @throws \common\modules\api\procedures\ApiException
     * @throws \Exception
     */
    public function apiDeleteBankcard($token,$id,$second_pwd = ''){

        $id = intval($id);

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid)) {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::USER_NOT_LOGIN);
        }

        $set = \common\models\ShopSysSet::getByKey('trade');
        $this_member = AppUser::getInstance()->identity->toArray();

        if($set['second_pwd'] == 1){
            $second_pwd = intval($second_pwd);
            if($second_pwd != $this_member['second_pwd']){
                throw new \common\modules\api\procedures\ApiException(\common\components\Response::PARAMS_ERROR,Yii::t('shop_o2o_page_string','支付密码错误'));
            }
        }

        \common\models\BankCard::deleteAll(['id'=>$id,'uniacid'=>$uniacid,'user_id'=>$this_member['id']]);

        return [
            'success'=>1,
            'success_string' => Yii::t('success_string','删除成功'),
        ];

    }









    }