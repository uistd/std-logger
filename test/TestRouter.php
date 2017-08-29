<?php
use FFan\Std\Logger\Logger;
use FFan\Std\Logger\LogLevel;
use FFan\Std\Logger\LogRouter;

/**
 * Class TestRouter
 */
class TestRouter extends LogRouter
{
    /**
     * @var Logger
     */
    private $err_logger;

    /**
     * 路由
     * @param int $log_level
     * @param string $message
     * @return int
     */
    function route($log_level, $message)
    {
        if ($log_level > LogLevel::ERROR) {
            return 0;
        }
        $logger = $this->getLogger();
        $logger->error($log_level, '[' . LogLevel::levelName($log_level) . ']' . $message);
        return 0;
    }

    /**
     * 获取错误日志记录对象
     */
    private function getLogger()
    {
        if (null === $this->err_logger) {
            $this->err_logger = new Logger($this->parent->getPath(), 'error');
        }
        return $this->err_logger;
    }
}