<?php

namespace app\components\spiderV2\curlDriver;

class Curl extends CurlAbstract {
    private $curl;

    public function __construct($other_opts = []) {
        $this->curl = curl_init();
        $opts = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_HEADER => false,                     //返回头信息
            CURLOPT_NOSIGNAL => true,                   //设置为true可以解决毫秒级bug(超时时间在1000ms内)
        ];
        foreach ($other_opts as $k => $v){
            $opts[$k] = $v;
        }
        curl_setopt_array($this->curl, $opts);
    }

    public function run($opts){
        foreach ($opts as $k => $v){
            curl_setopt($this->curl, $k, $v);
        }
        $result = curl_exec($this->curl);
        return $result;
    }



}

