<?php

namespace app\components\log\yiiLog;


use app\components\WebLog;

class RedisTarget extends Target {
    const LOG_KEY = WebLog::KEYNAME;

    //TODO 压缩
    protected function export($messages){
        $text = implode("\n", array_map([$this, 'formatMessage'], $messages)) . "\n";
        \Yii::$app->redis->lpush(self::LOG_KEY, $text);
    }


}
