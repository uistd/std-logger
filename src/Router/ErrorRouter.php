<?php

namespace FFan\Std\Logger\Router;

use FFan\Std\Logger\Logger;
use FFan\Std\Logger\LogLevel;
use FFan\Std\Logger\LogRouter;

/**
 * Class ErrorRouter 错误日志处理
 * @package FFan\Std\Logger\Router
 */
class ErrorRouter extends LogRouter
{

    /**
     * @var int
     */
    private $listen_level;

    /**
     * @var string 日志路径
     */
    private $path;

    /**
     * @var string 日志文件
     */
    private $file;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * ErrorRouter constructor.
     * @param Logger $parent
     * @param int $listen_log_level 监听的日志等级
     */
    public function __construct(Logger $parent, $listen_log_level = LogLevel::ERROR)
    {
        parent::__construct($parent);
        $this->listen_level = $listen_log_level;
        $this->config($parent->getPath());
    }

    /**
     * @param $path
     * @param string $file
     */
    public function config($path, $file = 'error')
    {
        $this->path = $path;
        $this->file = $file;
    }

    /**
     * 路由
     * @param int $log_level
     * @param string $message
     * @return int
     */
    function route($log_level, $message)
    {
        if ($log_level > $this->listen_level) {
            return 0;
        }
        $logger = $this->getLogger();
        $logger->error('[' . LogLevel::levelName($log_level) . ']' . $message);
        return 0;
    }


    /**
     * 获取错误日志记录对象
     */
    private function getLogger()
    {
        if (null === $this->logger) {
            $this->logger = new Logger($this->parent->getPath(), 'error');
        }
        return $this->logger;
    }
}
