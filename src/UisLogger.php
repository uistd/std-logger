<?php

namespace UiStd\Logger;

use UiStd\Common\Env;

/**
 * Class UisLogger
 * @package UiStd\Logger
 */
class UisLogger extends LoggerBase
{
    /**
     * 最大只支持65535长度的字符串 暂时写死!
     */
    const MAX_LENGTH = 65535;

    /**
     * @var array
     */
    private $msg_buffer;

    /**
     * @var bool
     */
    private $is_first_log = true;

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
     * @var string 日志服务器
     */
    private $host;

    /**
     * UisLogClient constructor.
     * @param string $file_name 文件名
     * @param int $log_level
     * @param int $option
     * @param array $ext_conf
     */
    public function __construct($file_name = 'log', $log_level = 0, $option = 0, $ext_conf = [])
    {
        parent::__construct($file_name, $log_level, $option, $ext_conf);
        $this->file_name = $file_name;
        $this->parseConfig($ext_conf);
    }

    /**
     * 解析配置文件
     * @param array $ext_conf
     */
    private function parseConfig($ext_conf)
    {
        $host = isset($ext_conf['host']) ? $ext_conf['host'] : '127.0.0.1';
        $port = isset($ext_conf['port']) ? $ext_conf['port'] : 10666;
        $this->host = 'tcp://' . $host . ':' . $port;
    }

    /**
     * 保存缓存中的日志
     */
    public function flushLogBuffer()
    {
        if (empty($this->msg_buffer)) {
            return;
        }
        $fd_handler = $this->getLogHandler();
        if (is_resource($fd_handler)) {
            $content = join($this->break_flag, $this->msg_buffer) . PHP_EOL;
            //如果日志大于 最大单条日志, 并且不是生产环境, 切割日志
            //调试环境下, 经常有日志超过64K的情况
            if (strlen($content) > self::MAX_LENGTH && !Env::isProduct()) {
                $content = '';
                foreach ($this->msg_buffer as $each_log) {
                    $content .= $this->packLog($each_log . $this->break_flag);
                }
            } else {
                $content = $this->packLog($content);
            }
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
     * 解析设置
     */
    protected function parseOption()
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
     */
    private function getLogHandler()
    {
        if (null !== $this->fd_handler) {
            return $this->fd_handler;
        }

        $opt = STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT;
        $this->fd_handler = stream_socket_client($this->host, $err_no, $err_msg, 1, $opt);
        //如果无法连接服务器, 设置系统不可用
        if (!$this->fd_handler) {
            $this->setDisable();
            LogHelper::getLogRouter()->error($err_msg . '(' . $err_no . ')');
            return null;
        }
        stream_set_blocking($this->fd_handler, false);
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

        //第一条日志强制换行
        if ($this->is_first_log) {
            if (($this->current_opt & self::OPT_LOG_HEADER) > 0) {
                $prefix_str .= LogHelper::logHeader();
            }
            $time_str = '[' . strftime('%H:%M:%S') . ']';
            $this->is_first_log = false;
            $prefix_str = $time_str . $this->break_flag . $prefix_str;
        }

        if (!empty($prefix_str)) {
            $content = $prefix_str . $content;
        }

        //如果 每次请求内不换行 把换行符替换成
        if (($this->current_opt & self::OPT_BREAK_EACH_REQUEST) > 0) {
            $content = str_replace(PHP_EOL, '\\n', $content);
        }
        if ($this->is_write_buffer) {
            $this->msg_buffer[] = $content;
        } else {
            $content = $this->packLog($content . PHP_EOL);
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
        //如果超最大支持长度, 需要做内容切割
        if ($content_len > self::MAX_LENGTH) {
            $fix_str = '[too long]';
            $cut_buffer_len = 8;
            //后面的 8 是用于字符串 切割时, 不要出现乱码, utf_8 最大长度是8
            $max_length = self::MAX_LENGTH - strlen($fix_str) - $cut_buffer_len;
            $new_content = substr($content, 0, $max_length);
            $cut_buffer = substr($content, $max_length, $cut_buffer_len);
            //再从cut_buffer 中 切割 出一个完整的字符串
            $cut_buffer = mb_substr($cut_buffer, 0, 1);
            $content = $new_content . $cut_buffer . $fix_str;
            $content_len = strlen($content);
        }
        $bin_str = '';
        $file_len = strlen($this->file_name);
        $bin_str .= pack('Sa' . $file_len . 'Sa' . $content_len . '', $file_len, $this->file_name, $content_len, $content);
        $head_str = pack("LS", strlen($bin_str), 100);
        $bin_str = $head_str . $bin_str;
        return $bin_str;
    }
}
