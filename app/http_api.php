<?php
require_once __DIR__ . '/../vendor/autoload.php';

$http_worker = new \Workerman\Worker("http://0.0.0.0:2345");

$http_worker->count = 4;

$http_worker->onWorkerStart = function () {
    include __DIR__ . '/common.php';
    function check_url($url)
    {
        if (!preg_match('/http:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is', $url)) {
            return false;
        }
        return true;
    }
};

$http_worker->onMessage = function ($connection, $data) {
    global $config;
    if (!isset($_POST['url']) || !check_url($_POST['url'])) {
        return $connection->send('Invalid url!');
    }
    if (isset($_POST['payload']) && !is_string($_POST['payload'])) {
        return $connection->send('Invalid payload!');
    }
    if (!isset($_POST['tactic']) || in_array($_POST['tactic'], $config['tactics'])) {
        return $connection->send('Invalid tactic!');
    }
    if (!isset($_POST['time']) || ($_POST['time'] <= time())) {
        $_POST['time'] = time();
    }

    redis()->executeRaw(['LPUSH', 'http_push_' . intval($_POST['time']), json_encode([
        'uuid' => \Ramsey\Uuid\Uuid::uuid4(),
        'url' => $_POST['url'],
        'payload' => isset($_POST['payload']) ? $_POST['payload'] : '',
        'tactic' => $_POST['tactic'],
        'times' => 0
    ])]);

    $connection->send('success');
};

\Workerman\Worker::runAll();
