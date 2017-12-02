<?php

namespace FFan\Std\Logger;

/**
 * Class LogRouter 日志路由器
 * @package FFan\Std\Logger
 */
class LogRouter
{
    /**
     * @var LoggerBase[] 所有的
     */
    private $logger_list = array();

    /**
     * 字符串变量替换
     * @param string $message
     * @param array $context
     * @return string
     */
    private static function contextReplace($message, $context)
    {
        $replace = array();
        $re = preg_match_all('/\\{([a-zA-Z_][a-zA-Z_0-9]*)\\}/', $message, $match_arr);
        if (!$re) {
            return $message;
        }
        $tmp_var_arr = $match_arr[0];
        //循环所有匹配的变量
        foreach ($match_arr[1] as $index => $name) {
            if (!isset($context[$name])) {
                continue;
            }
            $val = $context[$name];
            LogHelper::toString($val);
            $replace[$tmp_var_arr[$index]] = $val;
        }
        return strtr($message, $replace);
    }

    /**
     * 添加一个日志跟踪器
     * @param LoggerBase $logger
     */
    public function addLogger(LoggerBase $logger)
    {
        $this->logger_list[] = $logger;
    }

    /**
     * 移除日志对象
     * @param LoggerBase $logger
     */
    public function removeLogger(LoggerBase $logger)
    {
        foreach ($this->logger_list as $i => $each_logger) {
            if ($logger === $each_logger) {
                unset($this->logger_list[$i]);
            }
        }
    }

    /**
     * 记录日志
     * @param int $level 日志级别
     * @param string $message 日志消息主体
     * @param array $context 消息体变量数据
     */
    private function log($level, $message, $context)
    {
        if (empty($this->logger_list)) {
            return;
        }
        //字符串内变量替换
        if (!empty($context)) {
            $message = self::contextReplace($message, $context);
        }

        //不是utf编码，直接base64 encode
        if ('UTF-8' !== mb_detect_encoding($message, 'UTF-8', true)) {
            $message = base64_encode($message);
        }

        /** @var LoggerBase $logger */
        foreach ($this->logger_list as $logger) {
            $logger->onLog($level, $message);
        }
    }

    /**
     * 非常紧急日志
     * @param string $message 日志消息主体
     * @param array $context 消息体变量数据
     */
    public function emergency($message, array $context = array())
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * 报警日志
     * 例如：网站瘫痪，数据库不可用等，应该发短信提醒
     * @param string $message 日志消息主体
     * @param array $context 消息体变量数据
     */
    public function alert($message, array $context = array())
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * 重要事件日志.
     * 例如：应用组件不可用，或者突发异常.
     * @param string $message 日志消息主体
     * @param array $context 消息体变量数据
     */
    public function critical($message, array $context = array())
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * 运行错误日志
     * 不需要立即处理，但需要监控和记录
     * @param string $message 日志消息主体
     * @param array $context 消息体变量数据
     */
    public function error($message, array $context = array())
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * 警告日志
     * 但不是错误.
     * @param string $message 日志消息主体
     * @param array $context 消息体变量数据
     */
    public function warning($message, array $context = array())
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * 需注意日志
     * @param string $message 日志消息主体
     * @param array $context 消息体变量数据
     */
    public function notice($message, array $context = array())
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * 普通信息打印日志
     * 例如：用户登录，SQL日志
     * @param string $message 日志消息主体
     * @param array $context 消息体变量数据
     */
    public function info($message, array $context = array())
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * 调试信息.
     * @param string $message 日志消息主体
     * @param array $context 消息体变量数据
     */
    public function debug($message, array $context = array())
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
}
