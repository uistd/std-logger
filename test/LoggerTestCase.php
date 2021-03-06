<?php

use UiStd\Logger\FileLogger;

require_once '../vendor/autoload.php';

\UiStd\Common\Config::init(array(
    'env' => 'dev'
));

$logger = new \UiStd\Logger\FileLogger();
$logger->setOption(FileLogger::OPT_LOG_TYPE_STR|FileLogger::OPT_BREAK_EACH_REQUEST);
$log_router = \UiStd\Logger\LogHelper::getLogRouter();
$log_router->debug('debug test');

$log_router->info('info test');
$log_router->info(print_r($_SERVER, true));

$log_router->notice('notice test');

$log_router->warning('warning test');

$log_router->error('error test');

$log_router->critical('critical test');

$log_router->alert('alert test');

$log_router->emergency('emergency test');

$log_router->notice("This \n is \n break \n log");

$logger->remove();
