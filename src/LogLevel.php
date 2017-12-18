<?php

namespace UiStd\Logger;

/**
 * Class LogLevel 日志级别
 * @package UiStd\Logger
 */
class LogLevel
{
    /**
     * 非常紧急日志
     */
    const EMERGENCY = 0x1;

    /**
     * 报警日志
     */
    const ALERT = 0x2;

    /**
     * 重要事件日志
     */
    const CRITICAL = 0x4;

    /**
     * 运行错误日志
     */
    const ERROR = 0x8;

    /**
     * 警告日志
     */
    const WARNING = 0x10;

    /**
     * 需注意日志
     */
    const NOTICE = 0x20;

    /**
     * 信息日志
     */
    const INFO = 0x40;

    /**
     * 调试信息
     */
    const DEBUG = 0x80;

    /**
     * 日志级别对应的名称
     * @param int $level
     * @return string
     */
    public static function levelName($level)
    {
        switch ($level) {
            case self::EMERGENCY:
                return 'EMERGENCY';
            case self::ALERT:
                return 'ALERT';
            case self::CRITICAL:
                return 'CRITICAL';
            case self::ERROR:
                return 'ERROR';
            case self::WARNING:
                return 'WARNING';
            case self::NOTICE:
                return 'NOTICE';
            case self::INFO:
                return 'INFO';
            case self::DEBUG:
                return 'DEBUG';
            default:
                return 'UNKNOWN';

        }
    }
}
