<?php

namespace app\components\log\yiiLog;

use app\components\WebLog;

class FileTarget extends Target {
    public $logFile;

    public function setLogFile($value){
        $this->logFile = $value;
    }

    protected function export($messages){
        $text = implode("\n", array_map([$this, 'formatMessage'], $messages)) . "\n";
        file_put_contents($this->logFile, $text, FILE_APPEND | LOCK_EX);
    }


}
