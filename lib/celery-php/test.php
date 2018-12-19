<?php
require_once 'vendor/autoload.php';

$c = new \Celery\Celery(
    'localhost', /* Server */
    '', /* Login */
    '', /* Password */
    '0', /* vhost */
    'celery', /* exchange */
    'celery', /* binding */
    6379, /* port */
    'redis' /* connector */
);

$taskName = 'tasks.process';
// $result = $c->PostTask($taskName, array('/home/exploit/zig/Espagne.kml'));

// print('isready ? ' . ($result->isReady() ? 'true' : 'false') . "\n");
// sleep(1);
// print('isready ? ' . ($result->isReady() ? 'true' : 'false') . "\n");
// sleep(1);
// print('isready ? ' . ($result->isReady() ? 'true' : 'false'). "\n");
// sleep(1);
// print('isready ? ' . ($result->isReady() ? 'true' : 'false'). "\n");
// sleep(1);
// print('isready ? ' . ($result->isReady() ? 'true' : 'false'). "\n");

$taskId = 'php_5c1a50a0b90cc8.69916434'; //$result->getId();
print ($taskId . "\n");



// getAsyncResultMessage($taskName, $taskId, $args = null, $removeMessageFromQueue = true)

$removeMessageFromQueue = false;
$res = $c->getAsyncResultMessage($taskName, $taskId, null, $removeMessageFromQueue);
var_dump($res);
// print('isready ? ' . ($res->isReady() ? 'true' : 'false') . "\n");

// var_dump($result);


