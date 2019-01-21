<?php

namespace app\components\spiderV2\cp;

use app\components\log\LogFacade;
use app\models\Book;
use app\models\TmpBook;
use app\models\TmpChapter;

class HongShu extends SpiderAbstract {
    const CORPID = '82';
    const SECRETKEY = 'zhangdu520fwefew534266!';
    const BASE_URL = 'http://api.hongshu.com/empower4/standard/?';

    public function init() {
        $this->source = Book::SOURCE_HONGSHU;
        $this->source_name = Book::getSourceTxt($this->source);
    }

    public function test() {
//        $data = $this->getBookList();
//        $data = $this->getBookDetail(11807);
        $data = $this->getChapterList(11807);
//        $data = $this->getChapterInfos([10050], [1840]);

        var_dump($data);
        die;
    }

    public function getBookList($time = 0) {
        $param = [
            'pageNo' => 1,
            'pageSize' => 1000,
            'time' => $time,
            'func' => 'getBookLists'
        ];
        $url = self::BASE_URL . $this->generateQueryParam($param);
        $data = $this->curl->run([
            CURLOPT_URL => $url
        ]);
        $data = $this->checkResult($data);

        if(isset($data['list'])){
            $list = [];
            foreach ($data['list'] as $k => $v){
                $v['book_update_time'] = strtotime($v['updateTime']);
                $list[$v['bookId']] = $v;
            }
            return $list;
        }
        return [];
    }

    /*
     * 红薯接口章节列表
     * @param $book_id 红薯的book_id
     */
    public function getChapterList($book_id) {
        $param = [
            'bookId' => $book_id,
            'func' => 'getBookChapterList'
        ];
        $url = self::BASE_URL . $this->generateQueryParam($param);
        $data = $this->curl->run([
            CURLOPT_URL => $url
        ]);
        $data = $this->checkResult($data);
        return $data['list'] ?? false;
    }

    /*
     * 红薯接口章节列表
     * @param $book_id 红薯的book_id
     */
    public function getChapterInfos($interface_bids, $interface_cids) {
        foreach ($interface_bids as $k => $interface_bid) {
            $param = [
                'bookId' => $interface_bid,
                'chapterId' => $interface_cids[$k],
                'func'=>'getBookChapterContent'
            ];
            $url = self::BASE_URL . $this->generateQueryParam($param);
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

    protected function chapterContentInDatabase($tmp_chapters) {
        $tmp_chapter_ids = [];
        $batch_data = [];
        $interface_book_ids = [];
        $interface_chapter_id = [];
        foreach ($tmp_chapters as $k => $v) {
            $interface_book_ids[$k] = $v['interface_book_id'];
            $interface_chapter_id[$k] = $v['interface_chapter_id'];
        }
        $data = $this->getChapterInfos($interface_book_ids, $interface_chapter_id);
        foreach ($data as $k => $v) {
            if ($v) {
                $batch_data[] = [
                    'chapter_id' => $tmp_chapters[$k]['chapter_id'],
                    'ctime' => time(),
                    'content' => self::changeLineHandle($v['content']),
                ];
                $tmp_chapter_ids[] = $tmp_chapters[$k]['id'];
            } else {
                //没采到
                $this->fail_tmp_chapters[] = $tmp_chapters[$k];
            }
        }
        unset($data);
        if ($batch_data) {
            if (!$this->batchContentInDatabase($batch_data, $tmp_chapter_ids)) {
                return [];
            }
        }

        return true;
    }


    protected function bookDataHandle($data) {
        $book_datas = [];
        if ($data) {
            $now_bids = [];
            //书籍入库
            foreach ($data as $k => $v) {
                if (in_array($v['bookId'], $now_bids)) {
                    continue;
                }
                $now_bids[] = $v['bookId'];
                $v = [
                    'book_id' => $v['bookId'],
                    'book_name' => $v['bookName'],
                    'ftitle' => $v['bookName'],
                    'pic' => $v['coverImg'],
                    'intro' => $v['description'],
                    'is_serial' => $v['bookStatus'] == 1 ? 0 : 1,
                    'author' => $v['authName'],
                    'source' => $this->source
                ];
                $book_datas[] = array_merge($this->baseBookData(), $v);
            }
        }
        return $book_datas;
    }

    /*
     * 红薯章节采集
     * 章节列表入库 临时书库中： 入库的 && 列表未处理的  =》 is_add=1 && chapter_list_handle=0
     * 采集章节
     * @param $book_id 指定书ID
     * @param $source
     */
    public function gatherChapter($book_ids) {
        $condition = 'id in (' . implode(',', $book_ids) . ')';
        $param = [];
        $this->tmp_books = TmpBook::getModels($condition, $param, 'book_id,source,id');
        if (!$this->tmp_books) {
            return false;
        }
        while (true) {
            $tmp_book = array_shift($this->tmp_books);
            if (!$tmp_book) {
                break;
            }
            $chapter_lists = $this->getChapterList($tmp_book['book_id']);
            if (!$chapter_lists) {
                if (isset($tmp_book['retry_num'])) {
                    if ($tmp_book['retry_num'] >= 5) {
                        LogFacade::error("{$tmp_book['id']} 尝试五次采集 失败", 'spider ' . $this->source_name, 0);
                        continue;
                    } else {
                        $tmp_book['retry_num']++;
                    }
                } else {
                    $tmp_book['retry_num'] = 1;
                }
                $this->tmp_books[] = $tmp_book;
                continue;
            }
            $chapter_infos = [];
            foreach ($chapter_lists as $chapter_list) {
                if (!TmpChapter::getModelByCondition('interface_chapter_id=:ici and source=:source and interface_book_id=:book_id', [':ici' => $chapter_list['chapterId'], ':source' => $this->source, ':book_id' => $tmp_book['book_id']], 'id')) {
                    $chapter_infos[] = [
                        'sort' => $chapter_list['sortId'],
                        'vid' => $chapter_list['volumeId'] ?? 0,
                        'vname' => $chapter_list['volumeName'] ?? '正文',
                        'cid' => $chapter_list['chapterId'],
                        'cname' => $chapter_list['chapterName'],
                    ];
                }
            }
            //章节列表入库
            if (!empty($chapter_infos)) {
                if ($this->chapterInDatabase($chapter_infos, $tmp_book['id'], $tmp_book['book_id'])) {
                    $this->saveUpBid($tmp_book['id']);
                }
            }
        }

    }


    /*
     * 生成查询参数
     * 1.合并公共参数
     * 2.生成sign
     * 3.生成query
     */
    private function generateQueryParam($param) {
        $common_param = [
            'timestamp' => time(),
            'corpId' => self::CORPID
        ];
        $param = array_merge($param, $common_param);
        $param['sign'] = $this->makeSign($param);
        return http_build_query($param);
    }

    private function makeSign($arr) {
        ksort($arr);
        $string = http_build_query($arr);
        $string = $string . '&' . self::SECRETKEY;
        $string = md5($string);
        return $string;
    }

    private function checkResult($data) {
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code'] == 0) {
            return $data['data'];
        }
        return false;
    }

}

