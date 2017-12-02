<?php

namespace FFan\Std\Logger;

abstract class LoggerBase
{
    /**
     * LoggerBase constructor.
     */
    public function __construct()
    {
        $router = LogHelper::getLogRouter();
        $router->addLogger($this);
    }

    /**
     * 析构
     */
    public function __debugInfo()
    {
        $this->remove();
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
     * 收到日志
     * @param int $log_level
     * @param string $content
     */
    abstract public function onLog($log_level, $content);

}
