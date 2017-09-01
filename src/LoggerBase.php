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
     * 收到日志
     * @param int $log_level
     * @param string $content
     */
    abstract public function onLog($log_level, $content);

}
