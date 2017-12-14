<?php

namespace FFan\Std\Logger;

use FFan\Std\Common\Config;
use FFan\Std\Common\InvalidConfigException;
use FFan\Std\Common\Utils;
use FFan\Std\Event\EventManager;

/**
 * Class UisLogger
 * @package FFan\Std\Logger
 */
class UisLogger extends LoggerBase
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
     * 每一条日志换行
     */
    const OPT_BREAK_EACH_LOG = 8;

    /**
     * 第一个请求换一次行
     */
    const OPT_BREAK_EACH_REQUEST = 16;

    /**
     * @var int 日志级别
     */
    private $log_level;

    /**
     * @var string 文件名
     */
    private $file_name;

    /**
     * @var array
     */
    private $msg_buffer;

    /**
     * @var bool
     */
    private $is_first_log = true;

    /**
     * @var int 选项
     */
    private $current_opt;

    /**
     * @var string
     */
    private $break_flag;

    /**
     * @var bool
     */
    private $is_write_buffer;

    /**
     * @var resource
     */
    private $fd_handler;

    /**
     * UisLogClient constructor.
     * @param string $path 目录
     * @param string $file_name 文件名
     * @param int $log_level
     * @param int $option
     */
    public function __construct($path, $file_name = 'log', $log_level = 0, $option = 0)
    {
        parent::__construct();
        if ($log_level <= 0) {
            $log_level = 0xffff;
        }
        $this->file_name = Utils::joinFilePath($path, $file_name);
        $this->log_level = $log_level;
        if (0 === $option) {
            //默认参数
            $option = self::OPT_LOG_HEADER | self::OPT_BREAK_EACH_LOG | self::OPT_WRITE_BUFFER | self::OPT_LOG_TYPE_STR;
        }
        $this->setOption($option);
        EventManager::instance()->attach(EventManager::SHUTDOWN_EVENT, [$this, 'saveLog']);
    }

    /**
     * 保存日志
     */
    public function saveLog()
    {
        if (empty($this->msg_buffer)) {
            return;
        }
        $fd_handler = $this->getLogHandler();
        if (is_resource($fd_handler)) {
            $content = $this->packLog(join($this->break_flag, $this->msg_buffer));
            $total_len = strlen($content);
            while ($total_len > 0) {
                $re = fwrite($fd_handler, $content, $total_len);
                if (false === $re || $re <= 0) {
                    $this->setDisable();
                    break;
                }
                $content = substr($content, $re);
                $total_len -= $re;
            }
        }
        //已经保存日志了, write_buffer 就不能再是true了
        $this->is_write_buffer = false;
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
        if (($opt & self::OPT_BREAK_EACH_REQUEST) > 0) {
            $this->break_flag = ' | ';
        } else {
            $this->break_flag = PHP_EOL;
        }
    }

    /**
     * 获取连接
     * @return resource
     * @throws InvalidConfigException
     */
    private function getLogHandler()
    {
        if (null !== $this->fd_handler) {
            return $this->fd_handler;
        }
        $config = Config::get('uis_log_server');
        if (!isset($config['host'])) {
            throw new InvalidConfigException('CONFIG `uis_log_server` required');
        }
        $host = $config['host'];
        $port = isset($config['port']) ? $config['port'] : 10666;
        $opt = STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT;
        $this->fd_handler = stream_socket_client('tcp://' . $host . ':' . $port, $err_no, $err_msg, 1, $opt);
        //如果无法连接服务器, 设置系统不可用
        if (!$this->fd_handler) {
            $this->setDisable();
            LogHelper::getLogRouter()->error($err_msg . '(' . $err_no . ')');
            return null;
        }
        stream_set_blocking($this->fd_handler, false);
        $this->is_write_buffer = false;
        return $this->fd_handler;
    }

    /**
     * 设置不可用
     */
    private function setDisable()
    {
        //将自己从LogRouter中移除
        $this->remove();
        if (is_resource($this->fd_handler)) {
            fclose($this->fd_handler);
        }
        $this->fd_handler = null;
        //重新开启文件日志
        $log_name = basename($this->file_name);
        $dir = dirname($this->file_name);
        new FileLogger($dir, $log_name);
    }


    /**
     * 收到日志
     * @param int $log_level
     * @param string $content
     */
    public function onLog($log_level, $content)
    {
        if (!($this->log_level & $log_level)) {
            return;
        }
        $fd_handler = $this->getLogHandler();
        if (null === $fd_handler) {
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
            $content = $this->packLog($content);
            $re = fwrite($fd_handler, $content, strlen($content));
            if (false === $re) {
                $this->setDisable();
            }
        }
    }

    /**
     * 打包日志
     * @param string $content
     * @return string
     */
    private function packLog($content)
    {
        $content_len = strlen($content);
        //最大只支持65535长度的字符串 暂时写死!
        $max_length = 0xffff;
        //如果超最大支持长度, 需要做内容切割
        if ($content_len >= $max_length) {
            $fix_str = '[too long]';
            $cut_buffer_len = 8;
            //后面的 8 是用于字符串 切割时, 不要出现乱码, utf_8 最大长度是8
            $max_length -= (strlen($fix_str) + $cut_buffer_len);
            $new_content = substr($content, 0, $max_length);
            $cut_buffer = substr($content, $max_length, $cut_buffer_len);
            //再从cut_buffer 中 切割 出一个完整的字符串
            $cut_buffer = mb_substr($cut_buffer, 0, 1);
            $content = $new_content . $cut_buffer . $fix_str;
            $content_len = strlen($content);
            echo "too long,", $content_len, PHP_EOL;
        }
        $bin_str = '';
        $file_len = strlen($this->file_name);
        $bin_str .= pack('Sa' . $file_len . 'Sa' . $content_len . '', $file_len, $this->file_name, $content_len, $content);
        $head_str = pack("LS", strlen($bin_str), 100);
        $bin_str = $head_str . $bin_str;
        return $bin_str;
    }
}
