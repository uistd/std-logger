<?php

require_once '../vendor/autoload.php';
require_once 'TestRouter.php';

\ffan\php\utils\Config::init(array(
    'env' => 'sit'
));

$logger = \FFan\Std\Logger\LogHelper::getLogger('log');

$router = new \FFan\Std\Logger\Router\ErrorRouter($logger, \FFan\Std\Logger\LogLevel::ALERT);

$logger->debug('debug test');

$logger->info('info test');

$logger->notice('notice test');

$logger->warning('warning test');
$logger->error('error test');
$logger->critical('critical test');
$logger->alert('alert test');
$logger->emergency('emergency test');