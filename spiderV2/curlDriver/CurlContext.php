<?php

namespace app\components\spiderV2\curlDriver;

class CurlContext {
    private $curl = null;
    private $curls = null;

    public function __construct($config) {
        $this->curl = new Curl($config['curl_opt']);
        $this->curls = new Curls($config['curl_num'], $config['curl_opt']);
    }

    public function run($opt){
        return $this->curl->run($opt);
    }

    public function multiRun($opt){
        return $this->curls->multiRun($opt);
    }

    public function getMultiError(){
        $error_log = $this->curls->error_log;
        $this->curls->error_log = [];
        return $error_log;
    }



}

