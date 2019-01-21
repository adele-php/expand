<?php

namespace app\components\log\yiiLog;

use app\components\log\LogHelper;

class SwooleFileTarget extends Target {
    public $logDir;
    public $workId = '';
    const DIVIDE_BY_PROCESS = false;    //是否根据进程ID区分文件名

    public function setLogFile($value){
        $this->logDir = $value;
    }

    public function setWorkId($value){
        $this->workId = $value;
    }

    protected function export($messages){
        foreach ($messages as $k => $message){
            $level_name = LogHelper::getLevelName($message[1]);
            $log_file_name = $this->formatLogfile($level_name);
            $message = $this->formatMessage($message) . PHP_EOL;
            file_put_contents($log_file_name, $message, FILE_APPEND | LOCK_EX);
        }
    }


    /*
     * 应用名_日志类型_日志名.log
     */
    protected function formatLogfile($level_name){
        $fileName = $this->logDir . $level_name;
        if(self::DIVIDE_BY_PROCESS){
            $fileName .= $this->workId;
        }
        $fileName .= '.log';
        return $fileName;
    }


}
