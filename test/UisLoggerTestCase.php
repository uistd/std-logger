<?php

use FFan\Std\Common\Config;
use FFan\Std\Logger\LogHelper;
use FFan\Std\Logger\UisLogger;

require_once '../vendor/autoload.php';

Config::init(array(
    'env' => 'dev',
    'uis_log_server' => array(
        'host' => '127.0.0.1'
    )
));

$all_logger = array();
for($i = 0; $i < 100; ++$i) {
    $logger = new UisLogger('test_'. $i, 'log');
    $log_router = LogHelper::getLogRouter();
    $log_router->debug('debug test');

    $log_router->info('info test');
    $log_router->info(print_r($_SERVER, true));

    $log_router->notice('notice test');

    $log_router->warning('warning test');

    $log_router->error('error test');

    $log_router->critical('critical test');

    $log_router->alert('alert test');

    $log_router->emergency('emergency test'. str_repeat("this is test string ", 100) . " end");

    $logger->remove();

    $all_logger[] = $logger;
}

