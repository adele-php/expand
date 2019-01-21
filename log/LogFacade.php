<?php

namespace app\components\log;

use app\components\log\yiiLog\RedisTarget;
use app\components\log\yiiLog\Target;

class LogFacade {
    private static $logger;

    public static function trace($message, $category = 'app', $trace_level = 0) {
        self::getLogger()->log($message, LogHelper::LEVEL_TRACE, $category, $trace_level);
    }

    public static function info($message, $category = 'app', $trace_level = 0) {
        self::getLogger()->log($message, LogHelper::LEVEL_INFO, $category, $trace_level);
    }

    public static function warning($message, $category = 'app', $trace_level = 0) {
        self::getLogger()->log($message, LogHelper::LEVEL_WARNING, $category, $trace_level);
    }

    public static function error($message, $category = 'app', $trace_level = 0) {
        self::getLogger()->log($message, LogHelper::LEVEL_ERROR, $category, $trace_level);
    }

    public static function setLogger(Target $logger){
        self::$logger = $logger;
    }

    private static function getLogger() :Target{
        if (self::$logger !== null) {
            return self::$logger;
        }
        return self::$logger = new RedisTarget();
    }

}
