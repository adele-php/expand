<?php

namespace app\components\spiderV2\cp;

use app\components\log\LogFacade;
use app\models\Book;
use app\models\TmpBook;
use app\models\TmpChapter;
use yii\helpers\ArrayHelper;

class Youle extends SpiderAbstract {
    const BASE_URL = 'http://openapi.iyoule.com/std/i5GdemVefVv165Nz/';
    const DEBUG = false;

    public function init() {
        $this->source = Book::SOURCE_YOULE;
        $this->source_name = Book::getSourceTxt($this->source);
    }

    /*
     * 书本列表
     * @param $interface_bid 接口的书本ID
     */
    public function getBookList() {
        $param = [
        ];
        $url = self::BASE_URL . 'booklist';
        $url .= $this->buildQuery($param);
        $data = $this->curl->run([
            CURLOPT_URL => $url
        ]);
        if ($data = $this->dataHandle($data)) {
            $book_details = [];
            $book_ids = ArrayHelper::getColumn($data, 'bookid');
            foreach ($book_ids as $interface_bid) {
                if ($book_detail = $this->getBookDetail($interface_bid)) {
                    $book_detail['book_update_time'] = strtotime($book_detail['update_time']);
                    $book_details[$book_detail['bookid']] = $book_detail;
                }
            }
            return $book_details;
        }
        return [];
    }

    /*
     * 书本详情
     * @param $interface_bid 接口的书本ID
     */
    public function getBookDetail($interface_bid) {
        $param = [
            'bookid' => $interface_bid,
        ];
        $url = self::BASE_URL . 'bookinfo';
        $url .= $this->buildQuery($param);
        $data = $this->curl->run([
            CURLOPT_URL => $url
        ]);
        $data = $this->dataHandle($data);
        return $data;
    }

    /*
     * 书本章节列表
     * @param $interface_bid 接口的书本ID
     */
    public function getChapterList($interface_bid) {
        $param = [
            'bookid' => $interface_bid,
        ];
        $url = self::BASE_URL . 'chapterlist';
        $url .= $this->buildQuery($param);
        $data = $this->curl->run([
            CURLOPT_URL => $url
        ]);
        $data = $this->dataHandle($data);
        return $data['volumes'] ?? [];
    }

    /*
     * 书本章节内容
     * @param $interface_bid 接口的书本ID
     * @param $interface_cid 接口的章节ID
     */
    public function getChapterInfo($interface_bid, $interface_cid) {
        $param = [
            'bookid' => $interface_bid,
            'chapterid' => $interface_cid,
        ];
        $url = self::BASE_URL . 'chapterinfo';
        $url .= $this->buildQuery($param);
        $data = $this->curl->run([
            CURLOPT_URL => $url
        ]);
        $data = $this->dataHandle($data);
        return $data;
    }

    public function getChapterInfos($interface_bids, $interface_cids) {
        $base_url = self::BASE_URL . 'chapterinfo';
        foreach ($interface_bids as $k => $interface_bid) {
            $param = [
                'bookid' => $interface_bid,
                'chapterid' => $interface_cids[$k],
            ];
            $url = $base_url . $this->buildQuery($param);
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
            $data[$k] = $this->dataHandle($v);
        }
        return $data;
    }

    protected function dataHandle($data) {
        if ($data) {
            $data = json_decode($data, 1);
            if (isset($data['code']) && $data['code'] == 200) {
                return $data['data'];
            }
        }
        if (self::DEBUG) {
            var_dump($data);
            throw new \Exception(json_encode($data), -1);
        }
        return false;
    }

    public function test() {
        $data = $this->getBookList();
//        $data = $this->getBookDetail(2410);
//        $data = $this->getChapterList(2410);
//        $data = $this->getChapterInfo(1,35);

//        $interface_data = $this->getBookList();
//        $data = $this->bookDataHandle($interface_data,$source);
//        $this->tmpBookInDb($data,$source);
//        $this->tmpBookToSocial($source);

//        $this->gatherChapter(false,$source);die;
//        $this->gatherContent(false, $source);
//        die;
        var_dump($data);
        die;
        $data = $this->getBookList();
        $i = 1;
        foreach ($data as $v) {
            $this->getChapterList($v['bookid']);
            if ($i++ >= 50) {
                die;
            }
        }

        var_dump($data);
    }


    //TODO
    public function run() {
    }


    protected function bookDataHandle($data) {
        $book_datas = [];
        if ($data) {
            //书籍入库
            $now_bids = [];
            foreach ($data as $k => $v) {
                if (in_array($v['bookid'], $now_bids)) {
                    continue;
                }
                $now_bids[] = $v['bookid'];
                $v = [
                    'book_id' => $v['bookid'],
                    'book_name' => $v['bookname'],
                    'ftitle' => $v['bookname'],
                    'pic' => $v['cover'],
                    'intro' => $v['intro'],
                    'is_serial' => $v['status'] == 1 ? 1 : 0,
                    'author' => $v['pen_name'],
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
            $volume_infos = $this->getChapterList($tmp_book['book_id']);
            if (!$volume_infos) {
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
            ArrayHelper::multisort($volume_infos, 'volume_order', SORT_ASC, SORT_NUMERIC);
            $sort = 0;
            $chapter_infos = [];
            foreach ($volume_infos as $volume_info) {
                if ($volume_info['volume_type'] != 1) {
                    continue;   //非正文卷跳过
                }
                $vid = $volume_info['volume_id'];
                $vname = $volume_info['volume_name'];
                foreach ($volume_info['chapters'] as $chapter_info) {
                    $sort++;
                    if (!TmpChapter::getModelByCondition('interface_chapter_id=:ici and source=:source and interface_book_id=:book_id', [':ici' => $chapter_info['chapter_id'], ':source' => $this->source, ':book_id' => $tmp_book['book_id']], 'id')) {
                        $chapter_infos[] = [
                            'sort' => $sort,
                            'vid' => $vid,
                            'vname' => $vname,
                            'cid' => $chapter_info['chapter_id'],
                            'cname' => preg_replace('/[\s]{2,}/', ' ', $chapter_info['chapter_name']),
                        ];
                    }
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

}

