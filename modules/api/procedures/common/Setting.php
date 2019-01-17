<?php

namespace common\modules\api\procedures\common;

use common\components\AppUser;
use common\Config;
use common\models\McMappingFan;
use common\models\ShopSysSet;
use common\modules\system\Module;
use yii\helpers\VarDumper;

class Setting
{

    /**
     * @param $version
     * @return array
     * @throws \Throwable
     */
    public function apiGetShopConfig($version)
    {
        global $_W;
        $localversion = 1;
        $version = intval($version);
        if (empty($version) || ($version < $localversion)) {
            $arr = [
                'update' => 1,
                'data' => [
                    'version' => $localversion,
                    'areas' => Module::getInstance()->getAreaData(),
                ]
            ];
        } else {
            $arr = ['update' => 0];
        }

        $_W['shopset'] = \common\models\ShopSysSet::getByKey();
        $commissionSetting = m('common')->getPluginset('commission');
        $rawTabbar = iunserializer(\common\models\ShopSysSet::getByKey('app')['tabbar']);

        if ($rawTabbar && $rawTabbar['enable'] == 1)
        {
            $tabbar = [];
//            $tabbarPath = [];
            $tabbar['backgroundColor'] = $rawTabbar['backgroundColor'];
            $tabbar['selectedColor'] = $rawTabbar['selectedColor'];
            $tabbar['borderStyle'] = $rawTabbar['borderStyle'];
            $tabbar['color'] = $rawTabbar['color'];
            $tabbar['styletype'] = $rawTabbar['styletype'];
            if ($rawTabbar['styletype'] == 1)
            {
                $tabbar['list'] = $rawTabbar['imagelist'];
            }else{
                $tabbar['list'] = $rawTabbar['list'];
            }
            foreach ($tabbar['list'] as $key => &$iitem)
            {
                $iitem['iconPath'] = tomedia($iitem['iconPath']);
                $iitem['selectedIconPath'] = tomedia($iitem['selectedIconPath']);
                $iitem['selected'] = $key == 0;
//                $tabbarPath[] = $iitem['pagePath'];
            }
        }else{
            //虽然不启用页面配置，但是还是返回底部页面路径的参数吧
            $tabbar = new \ArrayObject();
//            $tabbarPath = [];
//            foreach ($rawTabbar['list'] as $ii)
//            {
//                $tabbarPath[] = $ii['pagePath'];
//            }
        }

//        \Yii::error($_W['shopset']);
        $arr['sysset'] = [
            'shopname' => $_W['shopset']['shop']['name'],
            'shoplogo' => tomedia($_W['shopset']['shop']['logo']),
            'description' => $_W['shopset']['shop']['description'],
            'share' => $_W['shopset']['share'],
            'texts' => ['credit' => $_W['shopset']['trade']['credittext'], 'money' => $_W['shopset']['trade']['moneytext']],
            'isclose' => $_W['shopset']['app']['isclose'],
            'openbind' => Config::forceBindPhone(),
            'bindurl' => mobileUrl('member/bind'),
            'copyright'=> $_W['shopset']['copyright'] == '1' ? tomedia('images/global/copyright.jpg'. '?time=' . time(),true) : '',
            'plugins'=> \common\models\ShopSysSet::getPluginSet('diypage'),
            'tabbar'=>$tabbar,
//            'tabbarPath'=>$tabbarPath,
            'primaryColor'=>$_W['shopset']['wap']['wxprimarycolor'],
            'secondaryColor'=>$_W['shopset']['wap']['wxsecondarycolor'],
            'isFollower' => McMappingFan::isFollowed(AppUser::getInstance()->openid),
            'iscloseMyShop' => $commissionSetting['closemyshop'] == 1
        ];
        $arr['sysset']['share']['logo'] = tomedia($arr['sysset']['share']['logo']);
        $arr['sysset']['share']['icon'] = tomedia($arr['sysset']['share']['icon']);
        $arr['sysset']['share']['followqrcode'] = tomedia($arr['sysset']['share']['followqrcode']);
        if (!empty($_W['shopset']['app']['isclose'])) {
            $arr['sysset']['closetext'] = $_W['shopset']['app']['closetext'];
        }
        return $arr;
    }
}
