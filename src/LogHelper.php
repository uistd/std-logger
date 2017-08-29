<?php

namespace FFan\Std\Logger;

use ffan\php\utils\Ip;

/**
 * Class LogHelper
 * @package FFan\Std\Logger
 */
class LogHelper
{
    /**
     * 单条日志最大长度
     */
    const MAX_MESSAGE_SIZE = 2048;

    /**
     * @var string 日志头信息
     */
    private static $log_header;

    /**
     * @var Logger
     */
    private static $main_logger;

    /**
     * @var Logger[] 日志实例列表
     */
    private static $logger_arr;

    /**
     * 变量转字符
     * @param mixed $var
     */
    public static function toString(&$var)
    {
        if (is_string($var) || !method_exists($var, '__toString')) {
            return;
        }
        $type = gettype($var);
        switch ($type) {
            case 'bool':
                $var = $var ? 'true' : 'false';
                break;
            case 'NULL':
                $var = 'NULL';
                break;
            case 'integer':
            case 'double':
                $var = (string)$var;
                break;
            default:
                $var = json_encode($var, JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 日志头信息
     * @return string
     */
    public static function logHeader()
    {
        if (null !== self::$log_header) {
            return self::$log_header;
        }
        $time_str = ' [' . strftime('%y/%m/%d %H:%M:%S') . ']';
        if ('cli' === PHP_SAPI) {
            $log_msg = 'CLI ' . $time_str;
            if (is_array($_SERVER['argv'])) {
                $log_msg .= join(' ', $_SERVER['argv']);
            }
        } else {
            $ip = IP::get();
            $log_msg = $_SERVER['REQUEST_METHOD'] . ' ' . $ip . $time_str . '"';
            if (!empty($_SERVER['REQUEST_URI'])) {
                $log_msg .= urldecode(urldecode($_SERVER['REQUEST_URI']));
            }
            $log_msg .= '" COOKIE[' . $_SERVER['HTTP_COOKIE'] . ']';
            if (!empty($_POST)) {
                $log_msg .= ' POST[' . http_build_query($_POST) . ']';
            }
        }
        return $log_msg;
    }

    /**
     * 消息 长度 和 编码判断
     * @param string $message
     * @param int $max_length 数量最长长度
     */
    public static function fixMsg(&$message, $max_length = self::MAX_MESSAGE_SIZE)
    {
        $msg_len = strlen($message);
        //长度判断（注意 字符串剪切后新长度 会超过 MAX_MESSAGE_SIZE）
        if ($msg_len > $max_length) {
            $new_message = mb_substr($message, 0, $max_length);
            if (strlen($new_message) < $msg_len) {
                $new_message .= '...';
            }
            $message = $new_message;
        }
        //不是utf编码，直接base64 encode
        if ('UTF-8' !== mb_detect_encoding($message, 'UTF-8', true)) {
            $message = base64_encode($message);
        }
    }

    /**
     * 获取实例
     * @param string $path 路径
     * @param string $file_name 文件名
     * @return Logger
     */
    public static function getLogger($path, $file_name = 'log')
    {
        if (!is_string($path) || !is_string($file_name)) {
            throw new \InvalidArgumentException('Invalid path or file_name');
        }
        $key = $path . '/' . $file_name;
        if (isset(self::$logger_arr[$key])) {
            return self::$logger_arr[$key];
        }
        $logger = new Logger($path, $file_name);
        self::$logger_arr[$key] = $logger;
        return $logger;
    }

    /**
     * 获取主日志对象
     * @return Logger
     */
    public static function getMainLogger()
    {
        if (null === self::$main_logger) {
            self::$main_logger = self::getLogger('main');
        }
        return self::$main_logger;
    }

    /**
     * 设置主日志记录
     * @param Logger $logger
     */
    public static function setMainLogger(Logger $logger)
    {
        self::$main_logger = $logger;
    }
}
