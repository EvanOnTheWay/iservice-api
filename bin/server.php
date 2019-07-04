<?php
require dirname(__DIR__) . '/bootstrap.php';

try {
    $swoole = new \Kernel\Swoole();
    $action = isset($argv[1]) ? $argv[1] : '';
    switch ($action) {
        case 'start':
            if ($swoole->status()) {
                echo '服务已在运行' . PHP_EOL;
            } else {
                // 启动服务进程
                if (!$swoole->start()) {
                    echo '服务启动失败' . PHP_EOL;
                }
            }
            break;
        case 'stop':
            if ($swoole->status()) {
                if ($swoole->shutdown()) {
                    echo '服务停止成功' . PHP_EOL;
                } else {
                    echo '服务停止失败' . PHP_EOL;
                }
            } else {
                echo '服务没有运行' . PHP_EOL;
            }
            break;
        case 'status':
            if ($swoole->status()) {
                echo '服务正在运行' . PHP_EOL;
            } else {
                echo '服务没有运行' . PHP_EOL;
            }
            break;
        default:
            echo '可用命令: ' . PHP_EOL;
            echo '    (启动) php server.php start' . PHP_EOL;
            echo '    (停止) php server.php stop' . PHP_EOL;
            echo '    (重载) php server.php reload' . PHP_EOL;
            break;
    }
} catch (Throwable $e) {
    echo $e->getFile() . PHP_EOL . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
}


