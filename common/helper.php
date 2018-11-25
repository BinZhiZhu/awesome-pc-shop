<?php

namespace common;


class Helper
{
    public static function requestApi($url)
    {
        if(empty($url)){
            return false;
        }
        $url = trim($url);

        $routes = explode('/',$url);
        $url = 'index.php?r='.$routes[0] .'/'.$routes[1];

        return $url;

    }
}