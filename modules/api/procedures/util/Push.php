<?php
namespace common\modules\api\procedures\util;


use common\components\AppUser;
use common\modules\api\procedures\ApiException;
use common\modules\api\procedures\BaseAppApi;
use Exception;
use Yii;

class Push extends BaseAppApi
{

    /**
     * @param     $token
     * @param int $type
     * @param int $size
     * @param int $page
     *
     * @return array
     * @throws ApiException
     * @throws Exception
     */
    public function apiGetAppPushRecord($token,$type = 0,$size = 20, $page = 1){

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }

        $openid = AppUser::getInstance()->openid;
        $uid = AppUser::getInstance()->id;
        $uniacid = \common\components\Request::getInstance()->uniacid;

        if (empty($openid) || empty($uid) || empty($uniacid)) {
            throw new ApiException(\common\components\Response::PARAMS_ERROR);
        }

        $where = [
            'uniacid'=>$uniacid,
            'uid'=>$uid,
            'message_type'=>'APP',
        ];

        $type = intval($type);
        if(!empty($type)){
            $where['type'] = $type;
        }

        $result = [];
        $type = \common\modules\message\models\Store::getMessageTypeList();
        foreach ($type as $k=>$v){
            $type_['type']  = $k;
            $type_['name']  = $v;
            $result['tabText'][] = $type_;
        }
        array_unshift($result['tabText'],['type'=>0,'name'=>Yii::t('shop_o2o_page_string','全部')]);

        $data = \common\modules\message\models\Store::fetchAll($where,'time DESC',$size, ($page-1)*$size);

        $result['list'] = [];
        foreach ($data as $k=>$v){
            $data_['id'] = $v['id'];
            $data_['type'] = $v['type'];
            $data_['type_string'] = $type[$v['type']];
            $data_['title'] = $v['title'];
            $data_['body'] = $v['body'];
            $data_['time'] = date('Y-m-d H:i:s',$v['time']);
            $data_['no'] = $v['no'];
            $result['list'][] = $data_;
        }

        //调了这个接口就是读取信息列表，全部更新成已读
        \common\modules\message\models\Store::updateAll(
            [
                'is_read'=>1,
            ],
            [
                'uniacid'=>$uniacid,
                'uid'=>$uid,
                'message_type'=>'APP',
                'is_read'=>0
            ]
        );

        return $result;

    }



}