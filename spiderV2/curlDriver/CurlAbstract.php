<?php

namespace app\components\spiderV2\curlDriver;

use app\components\log\LogHelper;

class CurlAbstract {
    const DEBUG = true;
    private $debug_info = [];

    protected function log($curl){
        if(self::DEBUG){
            $info = curl_getinfo($curl);
            $time = microtime(true);
            $message = "[http_code:{$info['http_code']}][平均下载速度:{$info['speed_download']}][总耗时:{$info['total_time']}] ";
            if(curl_errno($curl)){
                $message .= curl_error($curl);
            }
            $this->debug_info[] = [$message, $time, memory_get_usage()];
        }
    }

    protected function flush() {
        $debug_infos = $this->debug_info;
        $this->debug_info = [];
        foreach ($debug_infos as $debug_info){
            list($text, $timestamp, $memory) = $debug_info;
            $memory = LogHelper::getFormatMemory($memory);
            echo LogHelper::getFormatTime($timestamp) . " [$memory] $text" . PHP_EOL;
        }
    }


}

