<?php

namespace app\components\log;


class LogHelper {
    /**
     * Error message level. An error message is one that indicates the abnormal termination of the
     * application and may require developer's handling.
     */
    const LEVEL_ERROR = 0x01;
    /**
     * Warning message level. A warning message is one that indicates some abnormal happens but
     * the application is able to continue to run. Developers should pay attention to this message.
     */
    const LEVEL_WARNING = 0x02;
    /**
     * Informational message level. An informational message is one that includes certain information
     * for developers to review.
     */
    const LEVEL_INFO = 0x04;
    /**
     * Tracing message level. An tracing message is one that reveals the code execution flow.
     */
    const LEVEL_TRACE = 0x08;


    public static function getLevelName($level) {
        static $levels = [
            self::LEVEL_ERROR => 'error',
            self::LEVEL_WARNING => 'warning',
            self::LEVEL_INFO => 'info',
            self::LEVEL_TRACE => 'trace',
        ];

        return isset($levels[$level]) ? $levels[$level] : 'unknown';
    }


    public static function getFormatTime($timestamp) {
        $timestamp_arr = explode('.', $timestamp);
        $timestamp = $timestamp_arr[0];
        $usec = sprintf('%04d', $timestamp_arr[1] ?? 0);

        return date('Y-m-d H:i:s', $timestamp) . '.' . $usec;
    }

    public static function getFormatMemory($bytes) {
        $unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        if ($bytes == 0) return '0 ' . $unit[0];
        return round($bytes / pow(1000, ($i = floor(log($bytes, 1000)))), 2) . ' ' . (isset($unit[$i]) ? $unit[$i] : 'B');
    }

}
