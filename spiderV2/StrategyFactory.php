<?php

namespace app\components\spiderV2;

use app\components\spiderV2\cp\SpiderAbstract;

class StrategyFactory {
    private static $CLASS_MAP = [
        'youle' => [
            'class' => 'app\components\spiderV2\cp\Youle',
            'config' => [
                'curl_num' => 50,
                'curl_opt' => [
                    CURLOPT_TIMEOUT_MS => 20000,
                    CURLOPT_NOSIGNAL      =>true,                   //设置为true可以解决毫秒级bug(超时时间在1000ms内)
                ]
            ],
        ],
        'hongshu' => [
            'class' => 'app\components\spiderV2\cp\HongShu',
            'config' => [
                'curl_num' => 20,
                'curl_opt' => [
                    CURLOPT_TIMEOUT_MS => 20000,
                    CURLOPT_NOSIGNAL      =>true,                   //设置为true可以解决毫秒级bug(超时时间在1000ms内)
                ]
            ],
        ],
        'yueming' => [
            'class' => 'app\components\spiderV2\cp\Yueming',
            'config' => [
                'curl_num' => 20,
                'curl_opt' => [
                    CURLOPT_TIMEOUT_MS => 20000,
                    CURLOPT_NOSIGNAL      =>true,                   //设置为true可以解决毫秒级bug(超时时间在1000ms内)
                ]
            ],
        ],
        'ali' => [
            'class' => 'app\components\spiderV2\cp\Ali',
            'config' => [
                'curl_num' => 20,
                'curl_opt' => [
                    CURLOPT_TIMEOUT_MS => 20000,
                    CURLOPT_NOSIGNAL      =>true,                   //设置为true可以解决毫秒级bug(超时时间在1000ms内)
                ]
            ],
        ],
        'changdu' => [
            'class' => 'app\components\spiderV2\cp\Changdu',
            'config' => [
                'curl_num' => 20,
                'curl_opt' => [
                    CURLOPT_TIMEOUT_MS => 20000,
                    CURLOPT_NOSIGNAL      =>true,                   //设置为true可以解决毫秒级bug(超时时间在1000ms内)
                ]
            ],
        ],
        'zhixin' => [
            'class' => 'app\components\spiderV2\cp\Zhixin',
            'config' => [
                'curl_num' => 20,
                'curl_opt' => [
                    CURLOPT_TIMEOUT_MS => 20000,
                    CURLOPT_NOSIGNAL      =>true,                   //设置为true可以解决毫秒级bug(超时时间在1000ms内)
                ]
            ],
        ],
    ];
    
    public static function createStrategy($class_key, $config = []) :SpiderAbstract{
        if(isset(self::$CLASS_MAP[$class_key])){
            $class_info = self::$CLASS_MAP[$class_key];
            $default_config = $class_info['config'] ?? [];
            $config = array_merge($default_config, $config);
            return new $class_info['class']($config);
        }
        throw new \Exception('找不到类名');
    }


}

