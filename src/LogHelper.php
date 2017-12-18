<?php

namespace UiStd\Logger;

use UiStd\Common\Ip;
use UiStd\Common\Utils;

/**
 * Class LogHelper
 * @package UiStd\Logger
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
     * @var LogRouter 主路由器
     */
    private static $log_router;

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
     * @param bool $log_post_data 是否记录POST的数据
     * @return string
     */
    public static function logHeader($log_post_data = false)
    {
        if (null !== self::$log_header) {
            return self::$log_header;
        }
        if ('cli' === PHP_SAPI) {
            $log_msg = 'CLI ';
            if (is_array($_SERVER['argv'])) {
                $log_msg .= join(' ', $_SERVER['argv']);
            }
        } else {
            $ip = IP::get();
            $log_msg = $_SERVER['REQUEST_METHOD'] . ' ' . $ip . ' "';
            if (!empty($_SERVER['REQUEST_URI'])) {
                $log_msg .= urldecode(urldecode($_SERVER['REQUEST_URI']));
            }
            //如果 是post请求, 打印一部分数据
            if ($log_post_data && 'POST' === Utils::getHttpMethod()) {
                //防止日志量太大, 记录一部分 post数据
                $post_data = mb_substr(file_get_contents('php://input'), 0, 20480);
                $log_msg .= ' POST[' . urldecode(urldecode($post_data)) . ']';
            }
        }
        $log_msg .= ' ';
        return $log_msg;
    }

    /**
     * 获取日志路由器
     * @return LogRouter
     */
    public static function getLogRouter()
    {
        if (null === self::$log_router) {
            self::$log_router = new LogRouter();
        }
        return self::$log_router;
    }
}
