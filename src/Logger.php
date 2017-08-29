<?php

namespace FFan\Std\Logger;

use ffan\php\utils\Env;
use ffan\php\utils\Utils;

/**
 * Class Logger 日志类
 * @package FFan\Std\Logger
 */
class Logger
{
    /**
     * @var string 文件名
     */
    private $file_name;

    /**
     * @var string 分隔格式
     */
    private $split_format = '.YmdH';

    /**
     * @var string 消息间分割
     */
    private $split_flag = PHP_EOL;

    /**
     * @var Resource 打开文件的句柄
     */
    private $file_handle;

    /**
     * @var string 日志分割名称
     */
    private $split_name;

    /**
     * @var int 上一次日间戳
     */
    private $last_timestamp;

    /**
     * @var bool 是否无法使用
     */
    private $is_disable;

    /**
     * @var string 日志目录
     */
    private $log_path;

    /**
     * @var bool 是否记录请求头信息
     */
    private $log_request_header = true;

    /**
     * @var bool 日志级别
     */
    private $log_level;

    /**
     * @var array 未写入的消息
     */
    private $msg_buffer;

    /**
     * @var bool 是否将日志写入buffer
     */
    private $is_write_buffer = false;

    /**
     * @var LogRouter[] 路由器
     */
    private $router_list;

    /**
     * @var string 日志后缀名
     */
    private $log_file_suffix = 'log';

    /**
     * @var int 运行环境标志
     */
    private $env_flag;

    /**
     * constructor.
     * @param string $path 目录
     * @param string $file_name 文件名
     * @param int $env
     */
    public function __construct($path, $file_name, $env = null)
    {
        $this->file_name = $file_name;
        $this->log_path = Utils::fixWithRuntimePath($path);
        //目录不存在，无法创建
        if (!is_dir($this->log_path) && !mkdir($this->log_path, 0755, true)) {
            $this->is_disable = true;
        }
        //目录无写入权限
        if (!$this->is_disable && !is_writeable($this->log_path)) {
            $this->is_disable = true;
        }
        //默认日志级别
        $this->log_level = LogLevel::EMERGENCY | LogLevel::ALERT | LogLevel::CRITICAL | LogLevel::ERROR | LogLevel::WARNING | LogLevel::NOTICE;
        if (null === $env) {
            $env = Env::DEV;
        }
        $this->env_flag = $env;
        //不是生产环境，把info 打开
        if ($env !== Env::PRODUCT) {
            $this->log_level |= LogLevel::INFO;
        }
        //开发环境 把debug打开
        if ($env === Env::DEV) {
            $this->log_level |= LogLevel::DEBUG;
            //开发环境 不分割日志
            $this->split_format = null;
        } else {
            $this->is_write_buffer = true;
        }
    }

    /**
     * 设置日志参数
     * @param array $conf
     */
    public function config(array $conf)
    {
        //日志级别，只允许增加级别
        if (isset($conf['log_level'])) {
            $this->log_level |= (int)$conf['log_level'];
        }
        //日志后缀名
        if (isset($conf['log_file_suffix'])) {
            $this->log_file_suffix = $conf['log_file_suffix'];
        }
    }

    /**
     * 析构
     */
    public function __destruct()
    {
        if (!empty($this->msg_buffer)) {
            $file_handle = $this->getFileHandle();
            if (null !== $file_handle) {
                fwrite($file_handle, join($this->split_flag, $this->msg_buffer) . $this->split_flag);
            }
        }
        if ($this->file_handle) {
            fwrite($this->file_handle, PHP_EOL);
        }
        $this->close();
    }

    /**
     * 关闭文件打开句柄
     */
    private function close()
    {
        if (null === $this->file_handle) {
            return;
        }
        fclose($this->file_handle);
        $this->file_handle = null;
    }

    /**
     * 添加路由器，用于将一些特定的日志路由到其它地方
     * @param LogRouter $router
     */
    public function addRouter(LogRouter $router)
    {
        $this->router_list[] = $router;
    }

    /**
     * 记录日志
     * @param int $level 日志级别
     * @param string $message 日志消息主体
     * @param array $context 消息体变量数据
     */
    private function log($level, $message, $context)
    {
        if ($this->is_disable || !($this->log_level & $level)) {
            return;
        }

        //字符串内变量替换
        if (!empty($context)) {
            $message = self::contextReplace($message, $context);
        }

        LogHelper::fixMsg($message);

        //路由器处理
        if (!empty($this->router_list)) {
            $route_result = 0;
            /** @var LogRouter $router */
            foreach ($this->router_list as $router) {
                $route_result |= $router->route($level, $message);
            }
            //如果 路由指定不再将日志写入文件
            if (($route_result & LogRouter::STOP_WRITE_FILE)) {
                return;
            }
        }

        $file_handle = $this->getFileHandle();
        if (null === $file_handle) {
            $this->is_disable = true;
            return;
        }
        //日志开始
        if ($this->log_request_header) {
            $eol_str = PHP_EOL;
            //是否生产环境
            if ($this->env_flag !== Env::PRODUCT) {
                $eol_str .= str_repeat('-', 100) . PHP_EOL;
            }
            $message = $eol_str . LogHelper::logHeader() . $this->split_flag . $message;
            $this->log_request_header = false;
        }
        if ($this->is_write_buffer) {
            $this->msg_buffer[] = $message;
        } else {
            $write_len = fwrite($file_handle, $message . $this->split_flag);
            if (false === $write_len) {
                $this->is_disable = true;
            }
        }
    }


    /**
     * 获取日志文件文件描述符
     * @return Resource|null
     */
    private function getFileHandle()
    {
        $now_time = time();
        $split_name = $this->split_name;
        //每3秒检查一次日志是否需要分割，暂时写死
        if (null === $this->last_timestamp || $now_time - $this->last_timestamp > 3) {
            if (null === $this->split_format) {
                $split_name = '';
            } else {
                $split_name = date($this->split_format, $now_time);
            }
            $this->last_timestamp = $now_time;
        }
        if ($split_name !== $this->split_name) {
            //文件分割，先关闭之前打开的文件
            if ($this->file_handle) {
                $this->close();
            }
            $this->split_name = $split_name;
        }
        if (null === $this->file_handle) {
            $this->file_handle = $this->fileOpen();
        }
        return $this->file_handle;
    }

    /**
     * 打开文件
     * @return Resource|null
     */
    private function fileOpen()
    {
        $file_name = $this->file_name . $this->split_name . '.' . $this->log_file_suffix;
        $file_path = Utils::joinFilePath($this->log_path, $file_name);
        $file_handle = fopen($file_path, 'a+');
        return $file_handle ?: null;
    }

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

    /**
     * 获取日志路径
     * @return string
     */
    public function getPath()
    {
        return $this->log_path;
    }

    /**
     * 获取日志文件
     * @return string
     */
    public function getFileName()
    {
        return $this->file_name;
    }
}
