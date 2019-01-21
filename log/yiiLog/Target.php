<?php

namespace app\components\log\yiiLog;

use app\components\log\LogHelper;
use yii\base\BaseObject;
use yii\helpers\VarDumper;

abstract class Target extends BaseObject {
    protected $trace_level = 5;
    public $messages = [];

    public function init() {
        parent::init();
        register_shutdown_function(function () {
            $this->flush();
        });
    }
    
    public function log($message, $level, $category, $trace_level) {
        $time = microtime(true);
        $traces = [];
        if ($trace_level > 0) {
            $ts = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $trace_level + 2);
            array_pop($ts);array_shift($ts);
            foreach ($ts as $trace) {
                if (isset($trace['file'], $trace['line']) && strpos($trace['file'], YII2_PATH) !== 0) {
                    unset($trace['object'], $trace['args']);
                    $traces[] = $trace;
                }
            }
        }
        $this->messages[] = [$message, $level, $category, $time, memory_get_usage(), $traces];
        if(count($this->messages) >= 5){
            $this->flush();
        }
    }

    protected function flush() {
        $messages = $this->messages;
        $this->messages = [];
        $this->export($messages);
    }
    
    protected abstract function export($message);

    protected function formatMessage($message) {
        list($text, $level, $category, $timestamp, $memory) = $message;
        $level = LogHelper::getLevelName($level);
        if (!is_string($text)) {
            if ($text instanceof \Throwable || $text instanceof \Exception) {
                $text = (string)$text;
            } else {
                $text = VarDumper::export($text);
            }
        }
        $traces = [];
        if (!empty($message[5])) {
            foreach ($message[5] as $trace) {
                $traces[] = "in {$trace['file']}:{$trace['line']}";
            }
        }
        $memory = LogHelper::getFormatMemory($memory);
        return LogHelper::getFormatTime($timestamp) . " [$level][$category][$memory] $text"
            . (empty($traces) ? '' : "\n    " . implode("\n    ", $traces));
    }


}
