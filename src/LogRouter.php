<?php

namespace FFan\Std\Logger;

/**
 * Class LogRouter 日志路由器，对一些
 * @package FFan\Std\Logger
 */
abstract class LogRouter
{
    /**
     * 不将日志写入文件
     */
    const STOP_WRITE_FILE = 0xffff;

    /**
     * @var Logger
     */
    protected $parent;

    /**
     * LogRouter constructor.
     * @param Logger $parent 附着对象
     */
    public function __construct(Logger $parent)
    {
        $this->parent = $parent;
        $parent->addRouter($this);
    }

    /**
     * 路由
     * @param int $log_level
     * @param string $message
     * @return int
     */
    abstract function route($log_level, $message);
}
