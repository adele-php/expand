<?php

namespace app\components\spiderV2;

class SpiderContext {
    private $strategy = null;
    private $class_key = null;

    public function __construct($class_key) {
        $this->class_key = $class_key;
        $this->strategy = StrategyFactory::createStrategy($this->class_key);
    }

    /*
     * 全量更新
     */
    public function run($remove_lock = false) {
        if ($this->getExclusiveLock($remove_lock)) {
            $this->strategy->run();
            $this->removeExclusiveLock();
        }
    }

    /*
     * 连载更新
     */
    public function serial($remove_lock = false) {
        if ($this->getExclusiveLock($remove_lock)) {
            $time = time();
            $last_gather_time = \Yii::$app->redis->get('gather_time:' . $this->class_key);
            $last_gather_time = $last_gather_time ? $last_gather_time : 0;
            if($this->strategy->serial($last_gather_time)){
                \Yii::$app->redis->set('gather_time:' . $this->class_key, $time);
            }
            $this->removeExclusiveLock();
        }
    }

    /*
     * 全量更新
     */
    public function gatherChapter($book_ids, $remove_lock = false) {
        if ($this->getExclusiveLock($remove_lock)) {
            $book_ids = explode(',', $book_ids);
            foreach ($book_ids as $k => $book_id){
                $book_id = intval($book_id);
                if($book_id > 0){
                    $book_ids[$k] = $book_id;
                }else{
                    unset($book_ids[$k]);
                }
            }
            $this->strategy->gatherChapter($book_ids);
            $this->removeExclusiveLock();
        }
    }
    
    public function test(){
        $this->strategy->test();
    }

    /*
     * 阻止并发调用
     */
    private function getExclusiveLock($remove_mark) {
        if ($remove_mark) {
            $this->removeExclusiveLock();
        }
        return \Yii::$app->redis->set("gather_status:{$this->class_key}", 1, ['nx', 'ex' => 43200]);
    }

    private function removeExclusiveLock() {
        $key = "gather_status:{$this->class_key}";
        \Yii::$app->redis->del($key);
    }



}

