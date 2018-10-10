<?php

namespace app\components\spider\curls;

class Curls {
    private $curl_num;                   //并发数 设置并发最大值
    private $multi_curl_handle;
    private $curl_handles = [];
    public $error_log = [];

    /*
     * @param $curl_num 线程数
     * @param $other_opts curl选项
     *
     */
    public function __construct($curl_num = 3, $other_opts = []) {
        $this->curl_num = $curl_num;
        for ($i = 1; $i <= $this->curl_num; $i++) {
            $curl_handle = curl_init();
            $opts = [
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_HEADER => false,                     //返回头信息
                CURLOPT_NOSIGNAL => false,                   //设置为true可以解决毫秒级bug(超时时间在1000ms内)
            ];
            $opts = $other_opts + $opts;
            curl_setopt_array($curl_handle, $opts);
            $this->curl_handles[] = $curl_handle;
        }
    }

    public function run($opts){
        $responses = [];
        $chunk_opts = array_chunk($opts, $this->curl_num);
        $opts = null;
        foreach ($chunk_opts as $opts){
            $this->multi_curl_handle = curl_multi_init();
            $key = 0;
            foreach ($opts as $opt){
                curl_setopt_array($this->curl_handles[$key], $opt);
                curl_multi_add_handle($this->multi_curl_handle, $this->curl_handles[$key]);
                $key++;
            }
            $responses += $this->execCurl();
            curl_multi_close($this->multi_curl_handle);
        }
        return $responses;
    }

    private function execCurl(){
        $responses = [];
        $active = null;
        do {
            while ( ($execrun = curl_multi_exec($this->multi_curl_handle, $active)) == CURLM_CALL_MULTI_PERFORM ) ;
            if ($execrun != CURLM_OK) {
                break;
            }
            while ($done = curl_multi_info_read($this->multi_curl_handle, $rel)) {
                $info = curl_getinfo($done['handle']);
                $error = curl_error($done['handle']);
                if (isset($info['http_code']) && $info['http_code'] == 200) {
                    //成功
                    $responses[$info['url']] = curl_multi_getcontent($done['handle']);
                }else{
                    //失败
                    $this->error_log[] = "({$info['http_code']})" . $error . "({$info['url']})";
                }
                curl_multi_remove_handle($this->multi_curl_handle, $done['handle']);
            }
            if($active <= 0){
                break;
            }

        } while (true);
        return $responses;
    }

    public static function test(){
        $opts = [];
        $url = 'http://testapp.zhangdu.com/test1/tt';
        for ($i = 10; $i <= 1010; $i++){
            $opts[] = [
                CURLOPT_URL => $url . '?i=' . $i,
//                CURLOPT_POST => 0,
//                CURLOPT_POSTFIELDS => [],
//                CURLOPT_SSL_VERIFYPEER => false,
//                CURLOPT_SSL_VERIFYHOST => false,
            ];
        }
        $obj = new self(100);
        $start_time = microtime(true);
        $data = $obj->run($opts);
        var_dump($data, $obj->error_log, microtime(true) - $start_time);
    }

}

