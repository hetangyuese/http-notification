<?php
require_once __DIR__ . '/../vendor/autoload.php';

$task = new \Workerman\Worker();
$task->onWorkerStart = function () {
    include __DIR__ . '/common.php';

    foreach ($config['notify_rates'] as $key => $rate) {
        \Workerman\Lib\Timer::add($rate, function () use ($key) {
            do_task('http_push_' . (time() - $key));
        });
    }

    $clear_start = $config['clear_start'];
    \Workerman\Lib\Timer::add($config['clear_rate'], function () use (&$clear_start, $config) {
        if (!do_task('http_push_' . $clear_start)) {
            if ($clear_start < time() - count($config['notify_rates'])) {
                $clear_start += 1;
            }
        }
    });
};

// 运行worker
\Workerman\Worker::runAll();
