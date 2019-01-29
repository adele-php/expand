<?php

namespace app\components\spiderV2\cp;

use app\components\log\LogFacade;
use app\models\Book;
use app\models\TmpBook;
use app\models\TmpChapter;

class Ali extends SpiderAbstract {

    public function init() {
        $this->source = Book::SOURCE_ALI;
        $this->source_name = Book::getSourceTxt($this->source);
    }

    public function test() {
//        $data = $this->getBookList();
        $data = $this->getChapterList(10050);
//        $data = $this->getChapterInfos([10050], [1840]);

        var_dump($data);
        die;
    }

    public function getChapterInfos($interface_bids, $interface_cids) {
        foreach ($interface_bids as $k => $interface_bid) {
            $param = [
                'bookId' => $interface_bid,
                'chapterId' => $interface_cids[$k],
            ];
            $opt[] = [
                CURLOPT_URL => $url,
                'id' => $k
            ];
        }
        $data = $this->curl->multiRun($opt);
        $error_logs = $this->curl->getMultiError();
        foreach ($error_logs as $error_log) {
            LogFacade::error($error_log, 'spider ' . $this->source_name, 0);
        }
        foreach ($data as $k => $v) {
            $data[$k] = $this->checkResult($v);
        }
        return $data;
    }

    private function checkResult($data) {
        $data = json_decode($data, true);
        if (isset($data['status']) && $data['status'] == '200') {
            return $data['data'];
        }
        return false;
    }

}

