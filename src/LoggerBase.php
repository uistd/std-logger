<?php

namespace FFan\Std\Logger;

use FFan\Std\Common\Env;
use FFan\Std\Event\EventManager;

/**
 * Class LoggerBase
 * @package FFan\Std\Logger
 */
abstract class LoggerBase
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
     * @var mixed 额外的配置
     */
    protected $ext_config;

    /**
     * @var string 日志名件名
     */
    protected $file_name;

    /**
     * @var int 日志级别
     */
    protected $log_level;

    /**
     * @var int 内容参数
     */
    protected $current_opt = 0;

    /**
     * LoggerBase constructor.
     * @param string $file_name 日志名
     * @param int $log_level 日志级别
     * @param int $option 内容参数
     * @param mixed $ext_config
     */
    public function __construct($file_name = '', $log_level = null, $option = 0, $ext_config = null)
    {
        $this->ext_config = $ext_config;
        $this->file_name = $file_name;
        $router = LogHelper::getLogRouter();
        $router->addLogger($this);
        //如果没有设置日志级别,全开
        if (null === $log_level) {
            $log_level = 0xffff;
        }
        $this->log_level = $log_level;
        if (0 === $option) {
            //默认参数
            $option = self::OPT_LOG_HEADER | self::OPT_BREAK_EACH_LOG | self::OPT_LOG_TYPE_STR;
            $env = Env::getEnv();
            //生产环境，日志按小时分割
            if (Env::PRODUCT === $env) {
                $option |= self::OPT_SPLIT_BY_HOUR;
                $option |= self::OPT_WRITE_BUFFER;
            } elseif (Env::SIT === $env || Env::UAT === $env) {
                $option |= self::OPT_SPLIT_BY_DAY;
                $option |= self::OPT_WRITE_BUFFER;
            }
        }
        $this->setOption($option);
        EventManager::instance()->attach(EventManager::SHUTDOWN_EVENT, [$this, 'flushLogBuffer']);
    }

    /**
     * 析构
     */
    public function __debugInfo()
    {
        $this->remove();
    }

    /**
     * 保存在缓存中日志
     */
    public function flushLogBuffer()
    {

    }

    /**
     * 移除
     */
    public function remove()
    {
        $router = LogHelper::getLogRouter();
        $router->removeLogger($this);
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
    protected function parseOption()
    {
    }

    /**
     * 收到日志
     * @param int $log_level
     * @param string $content
     */
    abstract public function onLog($log_level, $content);

}
