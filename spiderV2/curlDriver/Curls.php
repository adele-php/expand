<?php

namespace app\components\spiderV2\curlDriver;

class Curls extends CurlAbstract {
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
        $opts = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_HEADER => false,                     //返回头信息
            CURLOPT_NOSIGNAL => false,                   //设置为true可以解决毫秒级bug(超时时间在1000ms内)
        ];
        foreach ($other_opts as $k => $v){
            $opts[$k] = $v;
        }
        $this->curl_num = $curl_num;

        for ($i = 1; $i <= $this->curl_num; $i++) {
            $curl_handle = curl_init();
            curl_setopt_array($curl_handle, $opts);
            $this->curl_handles[] = $curl_handle;
        }
    }

    /*
     * $opt => [
     *      'id' =>     //标识符       必须传
     * ]
     */
    public function multiRun($opts){
        $responses = [];
        $chunk_opts = array_chunk($opts, $this->curl_num);
        $opts = null;
        foreach ($chunk_opts as $opts){
            $key = 0;
            $key_ch_map = [];
            $key_id_map = [];
            $this->multi_curl_handle = curl_multi_init();
            foreach ($opts as $opt){
                $opt_array = $opt;
                unset($opt_array['id']);// 必须
                curl_setopt_array($this->curl_handles[$key], $opt_array);
                curl_multi_add_handle($this->multi_curl_handle, $this->curl_handles[$key]);
                $key_ch_map[$key] = $this->curl_handles[$key];
                $key_id_map[$key] = $opt['id'];
                $key++;
            }
            $responses += $this->execCurl($key_ch_map, $key_id_map);
            curl_multi_close($this->multi_curl_handle);
        }
        return $responses;
    }

    /*
     * @param $map = [
     *      $ch  => $opt
     * ]
     */
    private function execCurl($key_ch_map, $key_id_map){
        $responses = [];
        $active = null;
        do {
            while ( ($execrun = curl_multi_exec($this->multi_curl_handle, $active)) == CURLM_CALL_MULTI_PERFORM ){
                
            }
            if ($execrun != CURLM_OK) {
                //TODO LOG 说明有错误发生
                break;
            }
            while ($done = curl_multi_info_read($this->multi_curl_handle, $rel)) {
                $info = curl_getinfo($done['handle']);
                foreach ($key_ch_map as $key => $curl){
                    if($curl === $done['handle']){
                        if($info['http_code'] != 200){
                            $msg = curl_error($done['handle']);
                            $this->error_log[] = "({$info['http_code']})" . $msg . "({$info['url']})";
                        }else{

                        }
                        $this->log($done['handle']);
                        $responses[$key_id_map[$key]] = curl_multi_getcontent($done['handle']);
                        break;
                    }
                }
                curl_multi_remove_handle($this->multi_curl_handle, $done['handle']);
            }
            if($active <= 0){
                break;
            }

        } while (true);
        $this->flush();
        return $responses;
    }

    public static function test(){
        $opts = [];
        $url = 'https://s5016.zhangduyi.cn/article/view/1458679?t=wx';
        for ($i = 10; $i <= 20000; $i++){
            $opts[] = [
                CURLOPT_URL => $url . '?i=' . $i,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => ['phone' => 1540377922,'password'=>md5(time()),'device_token'=>'123','time'=>'thisweek'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'micromessenger',
            ];
        }
        $obj = new self(200);
        $start_time = microtime(true);
        $data = $obj->multiRun($opts);
        var_dump($data,  microtime(true) - $start_time);
    }

}

