<?php

namespace app\components\spiderV2\cp;

use app\components\log\LogFacade;
use app\models\Book;
use app\models\TmpBook;
use app\models\TmpChapter;

class Ali extends SpiderAbstract {
    const CPID = '10043';
    const CPKEY = '5b630bf09799de7169581e040178bcaa';
    const SECRETKEY = 'zhangdu520fwefew534266!';
    const BASE_URL = 'http://ognv1.shuqireader.com/cpapi/cp/';

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

    public function getBookList() {
        $param = [
            'cpId' => self::CPID,
        ];
        $url = self::BASE_URL . 'booklist/?' . self::makeSign($param);
        $data = $this->curl->run([
            CURLOPT_URL => $url
        ]);
        $data = $this->checkResult($data);
        if ($data) {
            $list = [];
            foreach ($data as $k => $v) {
                if ($book_detail = $this->getBookDetail($v['bookId'])) {
                    $book_detail['book_update_time'] = strtotime($book_detail['upTime']);
                    $list[$book_detail['bookId']] = $book_detail;
                }
            }
            return $list;
        }
        return [];
    }

    public function getBookDetail($interface_bid) {
        $param = [
            'cpId' => self::CPID,
            'bookId' => $interface_bid,
        ];
        $url = self::BASE_URL . 'bookinfo/?' . self::makeSign($param);
        $data = $this->curl->run([
            CURLOPT_URL => $url
        ]);
        return $this->checkResult($data);
    }

    /*
     * 红薯接口章节列表
     * @param $book_id 红薯的book_id
     */
    public function getChapterList($interface_bid) {
        $param = [
            'cpId' => self::CPID,
            'bookId' => $interface_bid,
        ];
        $url = self::BASE_URL . 'chapterlist/?' . self::makeSign($param) . '&volumeInfo=N';
        $data = $this->curl->run([
            CURLOPT_URL => $url
        ]);
        $data = $this->checkResult($data);
        return $data['chapterList'] ?? [];
    }

    /*
     * 红薯接口章节列表
     * @param $book_id 红薯的book_id
     */
    public function getChapterInfos($interface_bids, $interface_cids) {
        foreach ($interface_bids as $k => $interface_bid) {
            $param = [
                'cpId' => self::CPID,
                'bookId' => $interface_bid,
                'chapterId' => $interface_cids[$k],
            ];
            $url = self::BASE_URL . 'content/?' . self::makeSign($param);
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
                    'pic' => $v['cover'],
                    'intro' => $v['intro'],
                    'is_serial' => $v['bookStatus'] == 1 ? 0 : 1,
                    'author' => $v['authorName'],
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
                        'sort' => $chapter_list['chapterOrd'],
                        'vid' => 0,
                        'vname' => '正文',
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
     * 加密算法
     */
    private static function makeSign($param) {
        $param['timestamp'] = time();
        $data = [];
        ksort($param);
        foreach ($param as $k => $v) {
            $data[] = $k . '=' . $v;
        }
        $str = implode('', $data);
        $str .= self::CPKEY;
        $param['sign'] = md5($str);

        return http_build_query($param);
    }

    private function checkResult($data) {
        $data = json_decode($data, true);
        if (isset($data['status']) && $data['status'] == '200') {
            return $data['data'];
        }
        return false;
    }

}

