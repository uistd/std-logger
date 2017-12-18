<?php

use UiStd\Common\Config;
use UiStd\Logger\LogHelper;
use UiStd\Logger\UisLogger;

require_once '../vendor/autoload.php';

Config::init(array(
    'env' => 'dev'
));

$all_logger = array();
for ($i = 0; $i < 1; ++$i) {
    $logger = new UisLogger('test_' . $i . '/log', null, 0);
    $log_router = LogHelper::getLogRouter();
    $log_router->debug('debug test' . str_repeat("this is debug string " . $i, 100) . " end");

    $log_router->info('info test');
    $log_router->info(print_r($_SERVER, true));

    $log_router->notice('notice test' . str_repeat("this is notice string " . $i, 1000) . " end");

    $log_router->warning('warning test' . str_repeat("this is warning string " . $i, 1000) . " end");

    $log_router->error('error test' . str_repeat("this is error string " . $i, 1000) . " end");

    $log_router->critical('critical test' . str_repeat("this is critical string " . $i, 1000) . " end");

    $log_router->alert('alert test' . str_repeat("this is alert string " . $i, 10000) . " end");

    $log_router->emergency('emergency test' . str_repeat("this is emergency string " . $i, 10000) . " end");

    $logger->remove();

    $all_logger[] = $logger;
}

sleep(2);
