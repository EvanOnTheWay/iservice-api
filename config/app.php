<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-30 17:11:17
 */

use Kernel\Foundation\Config;

return [
    /**
     * Debug 模式
     */
    'debug' => (bool)Config::env('debug', 'false'),

    /**
     * 服务配置
     */
    'server' => [
        'host' => Config::env('server_host', '127.0.0.1'),
        'port' => (int)Config::env('server_port', '8000'),
        'swoole' => [
            'ractor_num' => 2,
            'worker_num' => (int)Config::env('server_worker_number', 10),
            'max_conn' => 5000,
            'max_request' => 1000,
            'dispatch_mode' => 2,
            'daemonize' => 1,
            'log_level' => 0,
            'task_ipc_mode' => 1,
            'task_worker_num' => (int)Config::env('server_task_work_number', 20),
            'task_max_request' => 1000,
            'log_file' => 'storage/logs/swoole.log'
        ],
    ],

    /**
     * 日志目录
     */
    'logs_dir' => ROOT_PATH . '/storage/logs/',

    /**
     * 系统调度
     */
    'scheduler' => [
        /**
         * 定时回调
         * 在系统启动完成激活的定时器
         *
         * index 触发的周期，单位: ms
         * value 回调方法组，Callable[]
         *
         * 请勿在 Callable 中执行耗时的同步逻辑，否则将会导致定时器延宕
         */
        '1000' => [
            // 消费微信会话监控队列
            \App\Scheduler\Wechat\WebRobot\SessionScheduler::class,
        ],
        '5000' => [
            // 消费微信群发消息队列
            \App\Scheduler\Wechat\WebRobot\MessageScheduler::class,
        ]
    ]
];
