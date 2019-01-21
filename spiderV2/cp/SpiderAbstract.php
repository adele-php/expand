<?php

namespace app\components\spiderV2\cp;

use app\components\aliyun\OtsChapter;
use app\components\encrypt\Blowfish;
use app\components\log\LogFacade;
use app\components\spiderV2\curlDriver\CurlContext;
use app\components\spiderV2\Volume;
use app\components\Util;
use app\models\ApiComm;
use app\models\Book;
use app\models\BookChapter;
use app\models\BookChapterContent;
use app\models\BookChapterVolume;
use app\models\BookReadvol;
use app\models\TmpBook;
use app\models\TmpChapter;
use app\models\TmpVolume;
use app\modules\mobile\models\FrontUploadImg;
use yii\helpers\ArrayHelper;

abstract class SpiderAbstract {
    protected $curl;
    protected $source;
    protected $source_name;
    protected $tmp_books;
    protected $curl_num;
    protected $fail_tmp_chapters = [];  //批量采内容，采集失败待重试章节

    public function __construct($config) {
        $this->curl = new CurlContext($config);
        $this->init();
        $this->curl_num = $config['curl_num'];
    }

    public function init() {
    }

    public function __call($name, $params) {
        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $params);
        }
        throw new \Exception('Calling unknown method: ' . get_class($this) . "::$name()");
    }

    /*
     * [interface_book_id => [interface_data], ..]
     */
    protected abstract function getBookList();

    protected abstract function bookDataHandle($interface_data);

    public abstract function gatherChapter($book_ids);

    protected abstract function chapterContentInDatabase($tmp_chapters);

    public function run() {
        try {
            $book_ids = [];
            LogFacade::info('run开始', 'spider_process ' . $this->source_name, 0);
            $interface_lists = $this->getBookList();
            $data = $this->bookDataHandle($interface_lists);
            unset($interface_lists);
            if (!empty($data)) {
                $exist_book_ids = $this->tmpBookInDb($data);
                $add_book_ids = $this->tmpBookToSocial();
                $book_ids = array_merge($exist_book_ids, $add_book_ids);
                unset($data, $exist_book_ids, $add_book_ids);
            }
            LogFacade::info('gatherChapter开始', 'spider_process' . $this->source_name, 0);
            if(!empty($book_ids)){
                $this->gatherChapter($book_ids);
            }
            unset($book_ids);

            $this->gatherContent(false);
            $this->failContentRetry();
            $this->gatherContentUpOss(false);
            $this->gatherVolume();
            $this->serailSet();
            $this->autoUpChapter();
        } catch (\Throwable $e) {
            LogFacade::error($e, 'spider ' . $this->source_name, 0);
        }
    }

    /*
     * 过滤旧书中更新时间小于上次的采集时间的书
     */
    public function serial($last_gather_time) {
        try {
            $serial_book_ids = [];
            LogFacade::info('serial开始', 'spider_process ' . $this->source_name, 0);
            $interface_lists = $this->getBookList();
            if (!empty($interface_lists)) {
                $exist_book_ids = $this->tmpBookInDb($this->bookDataHandle($interface_lists));        //[interface_book_id => book_id,...]
                $new_book_ids = $this->tmpBookToSocial();                                             //[interface_book_id => book_id,...]
                $serial_book_ids = $new_book_ids;   //新增的书肯定要才章节
                foreach ($exist_book_ids as $interface_book_id => $book_id) {
                    if ($interface_lists[$interface_book_id]['book_update_time'] >= $last_gather_time) {
                        //书籍有更新也去采章节
                        $serial_book_ids[] = $book_id;
                    }
                }
                unset($interface_lists, $exist_book_ids, $new_book_ids);
            }
            LogFacade::info('gatherChapter开始', 'spider_process ' . $this->source_name, 0);
            if(!empty($serial_book_ids)){
                $this->gatherChapter(array_unique($serial_book_ids));
            }
            unset($serial_book_ids);

            $this->gatherContent(false);
            $this->failContentRetry();
            $this->gatherContentUpOss(false);
            $this->gatherVolume();
            $this->serailSet();
            $this->autoUpChapter();
            return true;
        } catch (\Throwable $e) {
            LogFacade::error($e, 'spider ' . $this->source_name, 0);
            return false;
        }
    }

    /*
     * 失败的章节内容重试
     */
    protected function failContentRetry() {
        for ($i = 1; $i <= 5; $i++) {
            //最多重试5次
            if ($this->fail_tmp_chapters) {
                $fail_tmp_chapterss = array_chunk($this->fail_tmp_chapters, 10);
                $this->fail_tmp_chapters = [];
                foreach ($fail_tmp_chapterss as $fail_tmp_chapters) {
                    $this->chapterContentInDatabase($fail_tmp_chapters);
                }
            } else {
                break;
            }
        }

    }


    /*
     *  章节内容入库  内容未处理的  =》 chapter_content_handle=0
     */
    public function gatherContent($mod = false) {
        if ($mod !== false) {
            $condition = 'id>:id and content_handle=0 and MOD(id,8)=:mod and source=' . $this->source;
            $param = [':mod' => $mod, ':id' => 0];
        } else {
            $condition = 'id>:id and content_handle=0 and source=' . $this->source;
            $param = [':id' => 0];
        }
        while (true) {
            $tmp_chapters = TmpChapter::getModels($condition, $param,
                '*'
                , 'order by id asc limit ' . $this->curl_num);
            if ($tmp_chapters) {
                $this->chapterContentInDatabase($tmp_chapters);
            } else {
                break;
            }
            $tmp_chapter = array_pop($tmp_chapters);
            $param[':id'] = $tmp_chapter['id'];
        }
    }

    /*
     * tmp_book表入库
     * @return  [interface_book_id => book_id]
     */
    public function tmpBookInDb($data) {
        $exist_book_ids = [];
        if (empty($data)) {
            return [];
        }
        $batch_data = [];
        foreach ($data as $k => $v) {
            //检测是否已经存在了
            $tmp_info = TmpBook::getModelByCondition('book_id=:book_id and source=:source',
                [':book_id' => $v['book_id'], 'source' => $this->source], 'id');
            if ($tmp_info) {
                //已存在
                $exist_book_ids[$v['book_id']] = $tmp_info['id'];
            } else {
                //不存在
                $batch_data[] = $v;
            }
        }

        if (count($batch_data) > 0) {
            $field = array_keys($batch_data[0]);
            \Yii::$app->db->createCommand()->batchInsert('tmp_book', $field, $batch_data)->execute();
        }

        sleep(1);
        return $exist_book_ids;
    }

    /*
     * tmp_book转到book
     * @return [book_id,..]
     */
    public function tmpBookToSocial() {
        $condition = 'is_add=0 and is_repeat=0 and id>:id and source=:s';
        $param = [':id' => 0, ':s' => $this->source];
        $insert_book_ids = [];

        while (true) {
            //判断是否已经存在了
            $tmp_books = TmpBook::getModels($condition, $param,
                'id,book_id,book_name,ftitle,pic,intro,checked,ptype,type_id,chapter_num,is_serial,is_vip,charge_type,charge_chapter,money,author,stime,ctime,utime,charnum,source'
                , 'order by id asc limit 10');
            if ($tmp_books) {
                foreach ($tmp_books as $k => $tmp_book) {
                    $interface_book_id = $tmp_book['book_id'];
                    $tmp_book['book_id'] = $tmp_book['id'];
                    unset($tmp_book['id']);
                    $tmp_books[$k] = $tmp_book;
                    //检测是否已经存在了
                    if ($tmp = Book::getModelByCondition('book_id=:book_id',
                        [':book_id' => $tmp_book['book_id']], 'book_id')) {
                        //更新临时表状态
                        TmpBook::updateStatus(['is_repeat' => 1], 'id=' . $tmp_book['book_id']);
                        continue;
                    }
                    //将图片保存OSS
                    if (YII_ENV !== 'dev') {
                        try {
                            if (!($tmp_book['pic'] = FrontUploadImg::uploadBookImg($tmp_book['book_id'], $tmp_book['pic']))) {
                                $tmp_book['pic'] = 'app/book_img/default_book.png';
                            }
                        } catch (\Exception $e) {
                            LogFacade::error(['msg' => (string)$e, 'other' => 'book_id:' . $tmp_book['book_id'] . '图片上传失败'], 'spider ' . $this->source_name, 0);
                            $tmp_book['pic'] = 'app/book_img/default_book.png';
                        }
                    }

                    $transaction = \Yii::$app->db->beginTransaction();
                    try {
                        //入库
                        Book::insertData($tmp_book);
                        Book::addBookRely($tmp_book);
                        //改变临时库小说状态
                        TmpBook::updateStatus(['is_add' => 1], 'id=' . $tmp_book['book_id']);
                        $transaction->commit();
                        $insert_book_ids[$interface_book_id] = $tmp_book['book_id'];
                    } catch (\Throwable $e) {
                        $transaction->rollBack();
                        LogFacade::error(['msg' => (string)$e], 'spider ' . $this->source_name, 0);
                        break;
                    }
                }
            } else {
                break;
            }
            $tmp_book = array_pop($tmp_books);
            $param[':id'] = $tmp_book['book_id'];
        }
        sleep(1);
        return $insert_book_ids;
    }

    /*
     * 保存章节ID，用于去重和内容页
     * $data => [
     *      '0' => [
     *          'vid'
     *          'vname'
     *          'cname'
     *          'sort'
     *          'cid'
     *      ]
     * ]
     */
    protected function chapterInDatabase($data, $local_book_id, $interface_bid) {
        $time = time();
        $volume_obj = new Volume();
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            foreach ($data as $v) {
                //得到卷ID
                $volume_id = $volume_obj->getVolumeId($interface_bid, $this->source, $v['vid'], $v['vname'], $local_book_id);
                $insert_data = [
                    'book_id' => $local_book_id,
                    'chapter_name' => $v['cname'],
                    'sort' => $v['sort'],
                    'volume_id' => $volume_id,
                    'volume_name' => $v['vname'],
                    'skey' => Blowfish::generateKey(),
                    'checked' => 1,
                    'ctime' => $time,
                    'utime' => 0,
                    'charnum' => 0,
                ];

                $chapter_id = BookChapter::insertData($insert_data);
                $tmp_data = [
                    'chapter_id' => $chapter_id,
                    'interface_book_id' => $interface_bid,
                    'interface_chapter_id' => $v['cid'],
                    'content_handle' => 0,
                    'source' => $this->source,
                ];
                TmpChapter::insertData($tmp_data);
            }
            $transaction->commit();
            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw new \Exception($e->getTraceAsString());
        }
    }

    /*
     * 数据源换行处理
     */
    public static function changeLineHandle($str, $is_debug = false) {
        $search = ['<br>', '<br/>', "\r\n", '<p>', '</p>', '<br />'];
        $replace = "\n";
        $str = str_replace('&nbsp;', '', $str);
        $str = str_replace($search, $replace, $str);
        //去除连续多个\n
        $str = preg_replace('|(\n)\1{1,}|i', "\n", $str);
        $str = trim($str);
        if ($is_debug) {
            $str = str_replace("\n", '\1', $str);
        }
        return $str;
    }

    //更新字数
    public static function countWord($chapter_id, $num) {
        BookChapter::updateData(['charnum' => $num], 'chapter_id=' . $chapter_id);
    }

    /*
     * 记录异常的章节,本地有接口没 TODO
     */
    protected function recordExceptionChapter($diff_cids, $book_id) {
        $book_chapters = BookChapter::getModels('chapter_id in (' . implode(',', $diff_cids) . ')', [], 'chapter_id');
        if ($book_chapters) {
            $chapter_ids = ArrayHelper::getColumn($book_chapters, 'chapter_id');
            $msg = "书本ID：{$book_id},本地有但不存在接口中的章节ID:" . implode(',', $chapter_ids);
            LogFacade::info($msg, 'spider ' . $this->source_name, 0);
        }
    }

    /*
     * 保存此次更新的书本ID
     */
    public function saveUpBid($book_id) {
        \Yii::$app->redis->sadd('spider:update_book:s' . $this->source, $book_id);
    }

    /*
     * 章节内容批量入库
     */
    protected function batchContentInDatabase($batch_data, $tmp_chapter_ids) {
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            BookChapterContent::batchInsert(['chapter_id', 'ctime', 'content'], $batch_data);
            foreach ($batch_data as $v) {
                self::countWord($v['chapter_id'], Util::getUtf8_StrLeng($v['content']));
            }
            TmpChapter::updateStatus(['content_handle' => 1], 'id in (' . implode(',', $tmp_chapter_ids) . ')');
            $transaction->commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            LogFacade::error(['msg' => (string)$e], 'spider ' . $this->source_name, 0);
            return false;
        }
    }

    /*
     *  章节内容入库  内容未处理的  =》 chapter_content_handle=0
     */
    public function gatherContentUpOss($mod = false) {
        LogFacade::info('gatherContentUpOss开始', 'spider_process ' . $this->source_name, 0);
        if (YII_ENV === 'dev') {
            return;
        }
        if ($mod !== false) {
            $condition = 'id>:id and content_handle=1 and is_up_oss=0 and MOD(id,9)=:mod and source=:s';
            $param = [':mod' => $mod, ':id' => 0, ':s' => $this->source];
        } else {
            $condition = 'id>:id and content_handle=1 and is_up_oss=0 and source=:s';
            $param = [':id' => 0, ':s' => $this->source];
        }

        $ots = new OtsChapter();
        while (true) {
            $tmp_chapters = TmpChapter::getModels($condition, $param,
                '*'
                , 'order by id asc limit 10');
            if ($tmp_chapters) {
                foreach ($tmp_chapters as $tmp_chapter) {
                    $oss_data['type'] = 'gather_content';
                    $chapter_info = BookChapter::getInfo($tmp_chapter['chapter_id'], 'book_id,skey');
                    $book_info = Book::getInfo($chapter_info['book_id'], 'ctime');
                    $oss_data = [
                        'book_id' => $chapter_info['book_id'],
                        'chapter_id' => $tmp_chapter['chapter_id'],
                        'ctime' => $book_info['ctime'],
                        'tid' => $tmp_chapter['id'],
                        'skey' => $chapter_info['skey'],
                    ];
                    $content_info = BookChapterContent::getModelByCondition('chapter_id=:id', [':id' => $oss_data['chapter_id']], 'content');
                    if (empty($content_info['content']) || !$oss_data['skey']) {
                        continue;
                    }
                    $content = Blowfish::encrypt($content_info['content'], $oss_data['skey']);
                    $content = base64_encode($content);
                    try {
                        $ots->saveContent($oss_data['chapter_id'], $content);
//                        $oss->updateChapterContent($content, $oss_data['book_id'], $oss_data['chapter_id'], $oss_data['ctime']);
                        TmpChapter::updateStatus(['is_up_oss' => 1], 'id=' . $oss_data['tid']);
                    } catch (\Exception $e) {
                        echo $e->getMessage();
                        continue;
                    }
                }
                if (count($tmp_chapters) < 10) {
                    break;
                }
            } else {
                break;
            }
            $tmp_chapter = array_pop($tmp_chapters);
            $param[':id'] = $tmp_chapter['id'];
        }
        LogFacade::info('gatherContentUpOss结束', 'spider_process ' . $this->source_name, 0);
    }

    /*
     * 卷处理
     */
    public function gatherVolume() {
        $param = [':id' => 0, ':s' => $this->source];
        while (true) {
            $tmp_books = TmpBook::getModels('id>:id and is_add=1 and volume_handle=0 and source=:s', $param,
                'book_id,source,id'
                , 'order by id asc limit 5');
            if ($tmp_books) {
                foreach ($tmp_books as $tmp_book) {
                    if (!self::volumeInDatabase($tmp_book['id'])) {
                        continue;
                    }
                    //tmp_book更新状态
                    TmpBook::updateStatus(['volume_handle' => 1], 'id=' . $tmp_book['id']);
                }
                if (count($tmp_books) < 5) {
                    break;
                }
            } else {
                break;
            }
            $tmp_book = array_pop($tmp_books);
            $param[':id'] = $tmp_book['id'];
        }
    }

    /*
     * 批量卷入库
     * fixme 采集接口有新增得卷会采不到， 目前没用到卷 所有不做处理。
     */
    public function volumeInDatabase($book_id) {
        try {
            $field = [];
            $insert_datas = [];
            $time = time();
            $tmp_volumes = TmpVolume::getModels('book_id=:book_id', [':book_id' => $book_id]);
            foreach ($tmp_volumes as $v) {
                //字数
                $book_chapter = BookChapter::getModelByCondition('book_id=:book_id and volume_id=:volume_id',
                    [':book_id' => $v['book_id'], ':volume_id' => $v['id']], 'sum(charnum) as charnums');
                $charnum = $book_chapter['charnums'];
                $insert_data = [
                    'volume_id' => $v['id'],
                    'volume_name' => $v['volume_name'],
                    'book_id' => $v['book_id'],
                    'checked' => 1,
                    'ctime' => $time,
                    'charnum' => $charnum
                ];
                if (BookChapterVolume::getModels('volume_id=:id', [':id' => $v['id']], 'volume_id')) {
                    continue;
                }
                if (empty($field)) {
                    $field = array_keys($insert_data);
                }
                $insert_datas[] = $insert_data;
                if (count($insert_datas) >= 100) {
                    BookChapterVolume::batchInsert($field, $insert_datas);
                    $insert_datas = [];
                }
            }
            if (count($insert_datas) > 0) {
                BookChapterVolume::batchInsert($field, $insert_datas);
            }
            return true;
        } catch (\Throwable $e) {
            LogFacade::error(['msg' => (string)$e], 'spider ' . $this->source_name, 0);
            return false;
        }

    }

    /*
     * 更新书本状态 连载和完结
     */
    public function serailSet() {
        $interface_data = $this->getBookList();
        $data = $this->bookDataHandle($interface_data);
        foreach ($data as $k => $v) {
            $tmp_book = TmpBook::getModelByCondition('book_id=:bid and source=:s', [':bid' => $v['book_id'], ':s' => $this->source], 'id');
            if (!$tmp_book) {
                continue;
            }
            $con = 'book_id=' . $tmp_book['id'];
            $book_info = Book::getModelByCondition($con, [], 'is_serial');
            if ($book_info['is_serial'] != $v['is_serial']) {
                $u_data = ['is_serial' => $v['is_serial']];
                \Yii::$app->db->createCommand()->update('book', $u_data, $con)->execute();
                BookReadvol::updateData($u_data, $con);
            }
        }
    }

    /*
     * 新采集章节自动更新
     */
    public function autoUpChapter() {
        $keys[] = 'spider:update_book:s' . $this->source;
        foreach ($keys as $key) {
            while ($book_id = \Yii::$app->redis->spop($key)) {
                ApiComm::ossUpChapterListData($book_id, false);
                //待推送书籍
                continue;   //停止推送
                \Yii::$app->redis->sadd('spider:wait_um_push', $book_id);
            }
        }
    }

    protected function buildQuery($param) {
        $data = [];
        foreach ($param as $k => $v) {
            $data[] = "$k=$v";
        }
        return '?' . implode('&', $data);
    }

    protected function baseBookData() {
        return [
            'ptype' => 1,
            'type_id' => 0,
            'charnum' => 0,
            'chapter_num' => 0,
            'is_vip' => 0,
            'charge_type' => 1,
            'charge_chapter' => 31,
            'checked' => 0,
            'money' => 50,
            'stime' => 0,
            'ctime' => time(),
            'utime' => 0,
        ];
    }


}

