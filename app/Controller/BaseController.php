<?php
/**
 * Created by PhpStorm.
 * User: wenpeng
 * Date: 2017/9/4
 * Time: 下午10:34
 */

namespace App\Controller;

use Kernel\Worker;

class BaseController
{
    /**
     * 服务器状态信息
     */
    public static function status()
    {
        $worker = Worker::getInstance();

        return [
            'status' => $worker->getServerInstance()->stats(),
            'workerId' => $worker->getWorkerId(),
            'workerType' => $worker->getServerInstance()->taskworker ? 'TaskWorker' : 'Worker',
            'memoryUsage' => memory_get_peak_usage(true)
        ];
    }
}
