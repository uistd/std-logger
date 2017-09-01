<?php

namespace FFan\Std\Logger;

use FFan\Std\Common\Env;
use FFan\Std\Common\Utils;

/**
 * Class FileLogger 文件日志类
 * @package FFan\Std\Logger
 */
class FileLogger extends LoggerBase
{
    /**
     * 是否记录请求头
     */
    const OPT_LOG_HEADER = 1;

    /**
     * 是否将日志写入buffer，然后一次性写入文件
     */
    const OPT_WRITE_BUFFER = 2;

    /**
     * 是否记录日志类型
     */
    const OPT_LOG_TYPE_STR = 4;
    /**
     * 按天分隔
     */
    const OPT_SPLIT_BY_DAY = 8;

    /**
     * 按小时分隔
     */
    const OPT_SPLIT_BY_HOUR = 16;

    /**
     * 每一条日志换行
     */
    const OPT_BREAK_EACH_LOG = 32;

    /**
     * 第一个请求换一次行
     */
    const OPT_BREAK_EACH_REQUEST = 64;

    /**
     * @var string 文件名
     */
    private $file_name;

    /**
     * @var string 分隔格式
     */
    private $split_format;

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
     * @var string 日志后缀名
     */
    private $log_file_suffix = 'log';

    /**
     * @var int 当前生效的option
     */
    private $current_opt = 0;

    /**
     * @var bool 是否是第一条日志
     */
    private $is_first_log = true;

    /**
     * @var string 日志分割符
     */
    private $break_flag;

    /**
     * constructor.
     * @param string $path 目录（默认在 runtime目录的logs下）
     * @param string $file_name 文件名
     * @param int $log_level
     */
    public function __construct($path = 'logs', $file_name = 'log', $log_level = null)
    {
        parent::__construct();
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
        //如果没有设置日志级别,全开
        if (null === $log_level) {
            $log_level = 0xffff;
        }
        $this->log_level = $log_level;
        //默认参数
        $init_opt = self::OPT_LOG_HEADER | self::OPT_BREAK_EACH_LOG;
        $env = Env::getEnv();
        //生产环境，日志按小时分割
        if (Env::PRODUCT === $env) {
            $init_opt |= self::OPT_SPLIT_BY_HOUR;
            $init_opt |= self::OPT_WRITE_BUFFER;
        } elseif (Env::SIT === $env || Env::UAT === $env) {
            $init_opt |= self::OPT_SPLIT_BY_DAY;
            $init_opt |= self::OPT_WRITE_BUFFER;
        }
        $this->setOption($init_opt);
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
     * 设置option
     * @param int $append_option
     * @param int $remove_option
     * @internal param int $option
     */
    public function setOption($append_option = 0, $remove_option = 0)
    {
        if ($append_option > 0) {
            $this->current_opt |= $append_option;
        }
        if ($remove_option > 0) {
            $this->current_opt ^= $remove_option;
        }
        $this->parseOption();
    }

    /**
     * 解析设置
     */
    private function parseOption()
    {
        $opt = $this->current_opt;
        $this->is_write_buffer = ($opt & self::OPT_WRITE_BUFFER) > 0;
        $this->split_name = null;
        if (($opt & self::OPT_SPLIT_BY_DAY) > 0) {
            $this->split_format = '.Ymd';
        } elseif (($opt & self::OPT_SPLIT_BY_HOUR) > 0) {
            $this->split_format = '.YmdH';
        } else {
            $this->split_format = null;
        }
        if (($opt & self::OPT_BREAK_EACH_REQUEST) > 0) {
            $this->break_flag = ' | ';
        } else {
            $this->break_flag = PHP_EOL;
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
                fwrite($file_handle, join($this->break_flag, $this->msg_buffer) . $this->break_flag);
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
     * 收到日志
     * @param int $log_level
     * @param string $content
     */
    public function onLog($log_level, $content)
    {
        if ($this->is_disable || !($this->log_level & $log_level)) {
            return;
        }

        $file_handle = $this->getFileHandle();
        if (null === $file_handle) {
            $this->is_disable = true;
            return;
        }
        //前面增加的内容
        $prefix_str = '';
        //增加类型
        if (($this->current_opt & self::OPT_LOG_TYPE_STR) > 0) {
            $prefix_str = '[' . LogLevel::levelName($log_level) . ']';
        }

        //如果 每次请求内不换行 把换行符替换成
        if (($this->current_opt & self::OPT_BREAK_EACH_REQUEST) > 0) {
            $content = str_replace(PHP_EOL, '\\n', $content);
        }

        //第一条日志强制换行
        if ($this->is_first_log) {
            if (($this->current_opt & self::OPT_LOG_HEADER) > 0) {
                $prefix_str .= LogHelper::logHeader();
            }
            $time_str = '[' . strftime('%H:%M:%S') . ']';
            $this->is_first_log = false;
            $prefix_str = PHP_EOL . $time_str . $this->break_flag . $prefix_str;
        }

        if (!empty($prefix_str)) {
            $content = $prefix_str . $content;
        }
        if ($this->is_write_buffer) {
            $this->msg_buffer[] = $content;
        } else {
            $write_len = fwrite($file_handle, $content . $this->break_flag);
            if (false === $write_len) {
                $this->is_disable = true;
            }
        }
    }
}
