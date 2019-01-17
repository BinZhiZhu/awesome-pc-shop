<?php
namespace common\modules\api\procedures\util;


use common\components\AppUser;
use common\modules\api\procedures\ApiException;
use common\modules\api\procedures\BaseAppApi;
use common\components\Request;
use Exception;
use Yii;

class Map extends BaseAppApi
{
    /**
     * @param $lat
     * @param $lng
     *
     * @return bool|mixed
     * @throws ApiException
     * @throws Exception
     */
    public function apiGetLocationInfo($lat,$lng,$token = ''){

        if (!empty($token)) {
            AppUser::getInstance()->loginByJwt($token);
        }
        $openid = AppUser::getInstance()->openid;
        $uniacid = Request::getInstance()->uniacid;

        if(!empty($openid) && !empty($uniacid)){
            \common\models\ShopMember::updateAll(['lat'=>$lat,'lng'=>$lng],['openid'=>$openid,'uniacid'=>$uniacid]);
        }
//        $lat = '35.658651,'; //纬度
//        $lng = '139.745415'; //经度
        $key = 'AIzaSyBNy0O34SJbC01qzwNEHgjoszq4N6FSg_M';
        $result = $this->get_location($key,$lat,$lng,'google');
        if($result && is_array($result['results'])){
            $location_name = $result['results'][0]['address_components'][0]['long_name'];
        }
        $near_result = $this->get_near_location($key,$lat,$lng,'google');
        if($near_result && is_array($near_result['results'])){
            $data = array();
            foreach ($near_result['results'] as $k => $v){
                $data[$k]['location']['lat'] = $v['geometry']['location']['lat'];
                $data[$k]['location']['lng'] = $v['geometry']['location']['lng'];
                $data[$k]['name'] = $v['name'];
                $data[$k]['address'] = $v['vicinity'];
            }
            return ['location_name'=>$location_name,'near'=>$data];
        }
        throw new ApiException(\common\AppError::$SystemError, '定位失败');
    }


    private function get_location($key,$lat,$lng,$type){

        if($type === 'google'){
            $google_location =  $lat . ',' . $lng;
            $google_url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$google_location}&key={$key}";

            $request = ihttp_get($google_url);
            $content = $request['content'];
            $content = json_decode($content, true);
            Yii::info('谷歌地图地理位置api返回结果：' . json_encode($request));
            if(empty($content['error_message'])){
                return $content;
            }
        }
        return false;
    }

    private function get_near_location($key,$lat,$lng,$type){

        if($type === 'google'){
            $google_location =  $lat . ',' . $lng;
            $radius = 1500;
            $google_url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?radius={$radius}&location={$google_location}&key={$key}";

            $request = ihttp_get($google_url);
            $content = $request['content'];
            $content = json_decode($content, true);
            Yii::info('谷歌地图附近地点api返回结果：' . json_encode($request));
            if(empty($content['error_message'])){
                return $content;
            }
        }
        return false;
    }


    /**
     * 根据起点坐标和终点坐标测距离
     * @param  [array]   $from 	[起点坐标(经纬度),例如:array(118.012951,36.810024)]
     * @param  [array]   $to 	[终点坐标(经纬度)]
     * @param  [bool]    $km        是否以公里为单位 false:米 true:公里(千米)
     * @param  [int]     $decimal   精度 保留小数位数
     * @return [string]  距离数值
     */
    public function get_distance($from,$to,$decimal=2){
        sort($from);
        sort($to);
        $EARTH_RADIUS = 6370.996; // 地球半径系数

        $distance = $EARTH_RADIUS*2*asin(sqrt(pow(sin( ($from[0]*pi()/180-$to[0]*pi()/180)/2),2)+cos($from[0]*pi()/180)*cos($to[0]*pi()/180)* pow(sin( ($from[1]*pi()/180-$to[1]*pi()/180)/2),2)))*1000;

        if($distance > 1000){
            $distance = $distance / 1000;
            return round($distance, $decimal) . 'km';
        }else{
            return round($distance, 0) . 'm';
        }

    }
}