<?php

mb_internal_encoding('UTF-8');
date_default_timezone_set('PRC');

global $config;
$config = include __DIR__ . '/config.php';

/**
 * 获取日志实例
 *
 * @return \Monolog\Logger
 */
function logger()
{
    static $log = null;
    if (!$log) {
        $log = new \Monolog\Logger('http_push');
        $log->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . '/app.log', \Monolog\Logger::DEBUG));
        $log->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . '/app_error.log', \Monolog\Logger::ERROR));
    }
    return $log;
}

/**
 * 获取redis实例
 *
 * @return \Predis\Client
 */
function redis()
{
    static $redis;
    if (!$redis) {
        global $config;
        $redis = new \Predis\Client($config['redis']['parameters'], $config['redis']['options']);
    }
    return $redis;
}

/**
 * 异步请求数据 post方式
 *
 * @param String $url
 * @param Array $data
 * @param callable $success
 * @param callable $error
 * @return void
 */
function async_post($url, string $data, callable $success = null, callable $error = null)
{
    $res = '';
    $loop    = \Workerman\Worker::getEventLoop();
    $client  = new \React\HttpClient\Client($loop);
    $request = $client->request('POST', trim($url), [
        'Content-Type' => 'text/plain',
        'Content-Length' => strlen($data)
    ]);
    $request->on('error', function (\Exception $e) use ($error) {
        if ($error) {
            $error($e);
        }
    });
    $request->on('response', function ($response) use (&$res, $success) {
        $response->on('data', function ($data) use (&$res) {
            $res .= $data;
        });
        $response->on('end', function () use (&$res, $success) {
            if ($success) {
                $success($res);
            }
        });
    });
    $request->end($data);
}

/**
 * 执行任务
 *
 * @param [任务队列] $queue_key
 * @return void
 */
function do_task($queue_key)
{
    if (!$task = redis()->executeRaw(['RPOP', $queue_key])) {
        return false;
    }
    $task = json_decode($task, true);
    async_post($task['url'], $task['payload'], function ($response) use ($task) {
        if ($response == 'success') {
            logger()->debug('success', [
                'task' => $task,
                'response' => $response,
            ]);
        } else {
            redo($task);
            logger()->debug('fail', [
                'task' => $task,
                'response' => $response
            ]);
        }
    }, function ($e) use ($task) {
        redo($task);
        logger()->debug('error', [
            'task' => $task,
            'error' => $e->getMessage()
        ]);
    });
    return true;
}

/**
 * 任务重新入队
 *
 * @param 任务 $task
 * @return void
 */
function redo(array $task)
{
    $task['times'] += 1;
    global $config;
    if (!isset($config['tactics'][$task['tactic']])) {
        logger()->debug('tactic not found!', $task);
        return;
    }
    $cfg = $config['tactics'][$task['tactic']];
    if ($task['times'] > $cfg['max_times']) {
        logger()->debug('maximum number of times!', $task);
        return;
    }
    if ($task['times'] > count($cfg['fail_interval'])) {
        $next_time = time() + $cfg['fail_interval'][count($cfg['fail_interval']) - 1];
    } else {
        $next_time = time() + $cfg['fail_interval'][$task['times'] - 1];
    }
    redis()->executeRaw(['LPUSH', 'http_push_' . $next_time, json_encode($task)]);
}
