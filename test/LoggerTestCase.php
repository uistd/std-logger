<?php

use FFan\Std\Logger\FileLogger;

require_once '../vendor/autoload.php';

\FFan\Std\Common\Config::init(array(
    'env' => 'dev'
));

$logger = new \FFan\Std\Logger\FileLogger();
$logger->setOption(FileLogger::OPT_LOG_TYPE_STR|FileLogger::OPT_BREAK_EACH_REQUEST);
$log_router = \FFan\Std\Logger\LogHelper::getLogRouter();
$log_router->debug('debug test');

$log_router->info('info test');
$log_router->info(print_r($_SERVER, true));

$log_router->notice('notice test');

$log_router->warning('warning test');

$log_router->error('error test');

$log_router->critical('critical test');

$log_router->alert('alert test');

$log_router->emergency('emergency test');

$logger->remove();
