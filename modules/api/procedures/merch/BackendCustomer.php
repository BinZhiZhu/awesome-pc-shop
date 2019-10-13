<?php

namespace common\modules\api\procedures\merch;

use common\components\MerchUser;
use common\components\WebUser;
use common\helpers\Perm;
use common\helpers\Url;
use Exception;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii;

class BackendCustomer extends BaseObject
{

    /**
     * 初始化web后台数据
     * 这个接口是供后台开发专用的，前台不需要理会
     *
     * @param string $token 用户TOKEN
     * @resultKey object userInfo 用户信息
     * @resultDemo {
     *      userInfo: {
     *          name: "admin",
     *          avatar: "http://127.0.0.1:16800/static/images/avatar.png"
     *      }
     * }
     * @throws Exception
     * @return array
     */
    public function backendCustomerInitAll($token = null)
    {
        WebUser::getInstance()->loginByOldCookie();
        if (WebUser::getInstance()->isGuest) {
            throw new Exception('用户未登录');
        }

        global $_W;

        $uid = WebUser::getInstance()->getId();
        $uid = intval($uid);
        $uniacid = uni_account_last_switch();
        if ($uniacid) {
            $_W['uniacid'] = $uniacid;
            $_W['account'] = uni_fetch($_W['uniacid']);
            $_W['acid'] = $_W['account']['acid'];
        }
//        var_dump($uniacid);exit;

        $allMenus = [];
        if ($uniacid) {
            $allMenus = Perm::getAllMenus();
            $allMenus = Perm::filterMenus($allMenus);
        }

        //后台显示用户信息兼容,已绑定手机号则显示手机号,否则为用户名
        $record = \common\models\User::find()
            ->select('a.*, b.mobile, b.avatar')
            ->from(\common\models\User::tableName().' a')
            ->leftJoin(\common\models\UsersProfile::tableName().' b', 'b.uid = a.uid')
            ->where(['a.uid' => WebUser::getInstance()->identity->uid])
            ->asArray()
            ->one();
        if ($record)
        {
            if ($record['mobile'])
            {
                $userInfo['name'] = $record['mobile'];
            }else{
                $userInfo['name'] = $record['username'];
            }
            $userInfo['avatar'] = $record['avatar'];
        }else{
            $identify = WebUser::getInstance()->identity;
            $userInfo = [
                'name' => $identify->username,
                'avatar' => $identify->avatar
            ];
        }

        $Menusarray = [
            'userInfo' => $userInfo,
            'userMenus' => [
                [
                    'title' => '我的账号',
                    'url' => wurl('user/profile'),
                ],
                [
                    'title' => '操作日志',
                    'url' => webUrl('perm/log/index'),
                ],
                [
                    'title' => '角色管理',
                    'url' => webUrl('perm/role/index'),
                ],
                [
                    'title' => '操作员管理',
                    'url' => webUrl('perm/user/index'),
                ],
                [
                    'title' => '更新缓存',
                    'url' => wurl('system/updatecache', [], true),
                ],
                [
                    'title' => '退出',
                    'url' => wurl('user/logout', [], true),
                    'external' => true,
                ],
            ],
            'uniacid' => $uniacid,
            'checkUniacid' => true,
            'account' => $_W['account'],
            'accountListLink' => wurl('account/display', [], true),
            'menus' => $allMenus,
        ];
//        if (\common\components\WebUser::getInstance()->isAdmin) {
//            Yii::info(' isAdmin ',__METHOD__);
//            $rbac = [
//                'title' => '权限分配',
//                'url' => Url::to('/app.php?r=rbac/assignment/assign&id=' . $uid),
//            ];
//            $Menusarray['userMenus'][] =  $rbac ;
//        }

        return $Menusarray;
    }

    /**
     * 多商户后台参数
     *
     * @return array
     * @throws Exception
     */
    public function merchCustomerInitAll()
    {
        MerchUser::getInstance()->loginByOldCookie();
        if (MerchUser::getInstance()->isGuest) {
            throw new Exception('用户未登录');
        }

        global $_W;

        $__language = m('cache')->get('__language_' . $_W['uniaccount']['merchid'] );
        if($__language === 'EN'){
            Yii::$app->language = 'en-US';
        }

        $allMenus = Perm::getMerchMenus();
        $allMenus = Perm::filterMenus($allMenus);

        //后台显示用户信息兼容,已绑定手机号则显示手机号,否则为用户名
        $record = \common\models\User::find()
            ->select('a.*, b.mobile, b.avatar')
            ->from(\common\models\User::tableName().' a')
            ->leftJoin(\common\models\UsersProfile::tableName().' b', 'b.uid = a.uid')
            ->where(['a.uid' => WebUser::getInstance()->identity->uid])
            ->asArray()
            ->one();
        if ($record)
        {
            if ($record['mobile'])
            {
                $userInfo['name'] = $record['mobile'];
            }else{
                $userInfo['name'] = $record['username'];
            }
            $userInfo['avatar'] = $record['avatar'];
        }else{
            $identify = WebUser::getInstance()->identity;
            $userInfo = [
                'name' => $identify->username,
                'avatar' => $identify->avatar
            ];
        }

        return [
            'userInfo' => $userInfo,
            'userMenus' => [
                [
                    'title' => '退出',
                    'url' => merchUrl('quit'),
                    'external' => true,
                ],
                [
                    'title' => 'Switch language',
                    'url' => merchUrl('switch_language'),
                    'external' => false,
                ],
            ],
            'checkUniacid' => false,
            'menus' => $allMenus,

        ];
    }
}
