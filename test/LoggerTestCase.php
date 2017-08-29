<?php

use FFan\Std\Logger\LogRouter;

require_once '../vendor/autoload.php';

$logger = \FFan\Std\Logger\LogHelper::getLogger('log');

$router = new TestRouter($logger);

$logger->error('Test');