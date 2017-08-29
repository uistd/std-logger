<?php

require_once '../vendor/autoload.php';
require_once 'TestRouter.php';
$logger = \FFan\Std\Logger\LogHelper::getLogger('log');

$router = new TestRouter($logger);

$logger->debug('debug test');

$logger->info('info test');

$logger->notice('notice test');

$logger->warning('warning test');
$logger->error('error test');
$logger->critical('critical test');
$logger->alert('alert test');
$logger->emergency('emergency test');